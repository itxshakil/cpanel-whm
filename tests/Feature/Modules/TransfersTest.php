<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature\Modules;

use InvalidArgumentException;
use Itxshakil\CpanelWhm\Data\RemoteServer;
use Itxshakil\CpanelWhm\Data\TransferOptions;
use Itxshakil\CpanelWhm\Enums\TransferState;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Tests\TestCase;
use Itxshakil\CpanelWhm\WhmRequest;
use PHPUnit\Framework\Attributes\Test;

final class TransfersTest extends TestCase
{
    #[Test]
    public function a_session_is_created_with_every_required_flag_and_the_credentials(): void
    {
        $fake = Whm::fake(['create_remote_root_transfer_session' => Whm::response([
            'transfer_session_id' => 'oldservercopy20261008abcd',
            'create_rawout' => 'Connected.',
            'analyze_rawout' => 'Analyzed.',
        ])]);

        $session = Whm::transfers()->createSession(
            new RemoteServer('old.example.com', password: 'r00t-Pass', port: 2222),
            new TransferOptions(lowPriority: true, transferThreads: 2),
        );

        self::assertSame('oldservercopy20261008abcd', $session->id);
        self::assertSame('Connected.', $session->createOutput);
        self::assertSame('Analyzed.', $session->analyzeOutput);
        self::assertStringNotContainsString('r00t-Pass', print_r(new RemoteServer('old.example.com', password: 'r00t-Pass'), true));

        $fake->assertCalled('create_remote_root_transfer_session', static fn (array $p, WhmRequest $r): bool => self::sorted($p) === self::sorted([
            'comm_transport' => 'ssh',
            'compressed' => 1,
            'copy_reseller_privs' => 1,
            'enable_custom_pkgacct' => 0,
            'host' => 'old.example.com',
            'low_priority' => 1,
            'restore_threads' => 1,
            'transfer_threads' => 2,
            'unencrypted' => 0,
            'unrestricted_restore' => 0,
            'use_backups' => 0,
            'user' => 'root',
            'port' => 2222,
            'password' => 'r00t-Pass',
        ]) && $r->timeout === 300);
    }

    #[Test]
    public function an_ssh_key_and_root_escalation_can_be_used_instead(): void
    {
        $fake = Whm::fake(['create_remote_root_transfer_session' => Whm::response(['transfer_session_id' => 'x'])]);

        Whm::transfers()->createSession(new RemoteServer(
            'old.example.com',
            user: 'admin',
            sshKeyName: 'migration',
            sshKeyPassphrase: 'key-pass',
            rootPassword: 'r00t',
            rootEscalation: 'sudo',
        ));

        $fake->assertCalled('create_remote_root_transfer_session', static fn (array $p): bool => $p['user'] === 'admin'
            && $p['sshkey_name'] === 'migration'
            && $p['sshkey_passphrase'] === 'key-pass'
            && $p['root_password'] === 'r00t'
            && $p['root_escalation_method'] === 'sudo'
            && ! isset($p['password']));
    }

    #[Test]
    public function a_remote_server_needs_exactly_one_way_to_log_in(): void
    {
        foreach ([
            static fn () => new RemoteServer('old.example.com'),
            static fn () => new RemoteServer('old.example.com', password: 'a', sshKeyName: 'b'),
            static fn () => new RemoteServer('old.example.com', password: 'a', rootEscalation: 'doas'),
        ] as $build) {
            try {
                $build();
                self::fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException) {
            }
        }

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function accounts_are_enqueued_started_polled_paused_and_aborted(): void
    {
        $fake = Whm::fake([
            'enqueue_transfer_item' => Whm::response(),
            'start_transfer_session' => Whm::response(['pid' => 4242]),
            'get_transfer_session_state' => Whm::sequence(
                Whm::response(['state_name' => 'TRANSFER_INPROGRESS']),
                Whm::response(['state_name' => 'COMPLETED']),
                Whm::response(['state_name' => 'SOMETHING_NEW']),
            ),
            'pause_transfer_session' => Whm::response(),
            'abort_transfer_session' => Whm::response(),
        ]);
        $transfers = Whm::transfers();

        $transfers->enqueueAccount('sess1', 'Acme', localUser: 'acme2', options: ['skipbwdata' => true]);
        self::assertSame(4242, $transfers->start('sess1'));

        $running = $transfers->state('sess1');
        self::assertSame(TransferState::TransferInProgress, $running);
        self::assertFalse($running->isFinished());

        $done = $transfers->state('sess1');
        self::assertTrue($done->isFinished());
        self::assertTrue($done->isSuccessful());

        self::assertSame(TransferState::Unknown, $transfers->state('sess1'));

        $transfers->pause('sess1');
        $transfers->abort('sess1');

        $fake->assertCalled('enqueue_transfer_item', static fn (array $p): bool => $p === [
            'skipbwdata' => 1,
            'transfer_session_id' => 'sess1',
            'module' => 'AccountRemoteRoot',
            'user' => 'acme',
            'localuser' => 'acme2',
        ])
            ->assertCalled('pause_transfer_session', static fn (array $p): bool => $p === ['transfer_session_id' => 'sess1'])
            ->assertCalled('abort_transfer_session', static fn (array $p): bool => $p === ['transfer_session_id' => 'sess1']);
    }

    #[Test]
    public function migrate_creates_a_session_enqueues_every_account_and_starts_it(): void
    {
        $fake = Whm::fake([
            'create_remote_root_transfer_session' => Whm::response(['transfer_session_id' => 'sess9']),
            'enqueue_transfer_item' => Whm::response(),
            'start_transfer_session' => Whm::response(['pid' => 1]),
        ]);

        $session = Whm::transfers()->migrate(new RemoteServer('old.example.com', password: 'p'), ['acme', 'shop']);

        self::assertSame('sess9', $session->id);
        $fake->assertCalledTimes('enqueue_transfer_item', 2)
            ->assertCalled('enqueue_transfer_item', static fn (array $p): bool => $p['user'] === 'shop' && $p['localuser'] === 'shop')
            ->assertCalled('start_transfer_session', static fn (array $p): bool => $p === ['transfer_session_id' => 'sess9']);

        $order = array_map(static fn (WhmRequest $r): string => $r->function, $fake->recorded());
        self::assertSame(['create_remote_root_transfer_session', 'enqueue_transfer_item', 'enqueue_transfer_item', 'start_transfer_session'], $order);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private static function sorted(array $params): array
    {
        ksort($params);

        return $params;
    }
}
