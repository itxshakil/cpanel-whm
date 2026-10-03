<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Unit;

use Itxshakil\CpanelWhm\Generator\Naming;
use Itxshakil\CpanelWhm\Generator\Operation;
use Itxshakil\CpanelWhm\Generator\Reader;
use Itxshakil\CpanelWhm\Generator\Spec;
use Itxshakil\CpanelWhm\Generator\Text;
use Itxshakil\CpanelWhm\Generator\Writer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GeneratorTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function names(): array
    {
        return [
            'plain' => ['createacct', 'createacct'],
            'snake' => ['api_token_create', 'apiTokenCreate'],
            'dashes' => ['acl-add-pkg', 'aclAddPkg'],
            'upper snake' => ['MAX_EMAIL_PER_HOUR', 'maxEmailPerHour'],
            'cP brand' => ['Login Security (cPHulk)', 'loginSecurityCpHulk'],
            'acronym' => ['CSVImport', 'csvImport'],
            'trailing acronym' => ['BlockIP', 'blockIp'],
            'dotted' => ['services.email.enabled', 'servicesEmailEnabled'],
            'leading digit' => ['360Monitoring', 'v360Monitoring'],
            'camel stays' => ['colorScheme', 'colorScheme'],
        ];
    }

    #[Test]
    #[DataProvider('names')]
    public function names_become_identifiers(string $name, string $expected): void
    {
        self::assertSame($expected, Naming::camel($name));
    }

    #[Test]
    public function text_is_cleaned_for_docblocks(): void
    {
        self::assertSame('See the docs * / now.', Text::clean("See [the docs](https://x.test)\n */ now."));
        self::assertSame('First sentence.', Text::firstSentence('First sentence. Second one.'));
        self::assertSame("'it\\'s'", Text::quote("it's"));
    }

    #[Test]
    public function operations_are_read_with_typed_parameters(): void
    {
        $operations = $this->operations();
        $create = $operations['createacct'];

        self::assertSame('Accounts', $create->group);
        self::assertSame('This function creates a cPanel account. See the docs.', $create->description);
        self::assertFalse($create->readOnly);
        self::assertSame('https://api.docs.cpanel.net/specifications/whm.openapi/account-creation/accounts-createacct', $create->docsUrl);

        $params = [];

        foreach ($create->parameters as $parameter) {
            $params[$parameter->name] = $parameter->signature();
        }

        self::assertSame([
            'username' => 'string $username',
            'bwlimit' => 'int|string|null $bwlimit = null',
            'hasshell' => 'bool|int|null $hasshell = null',
            'MAX_EMAIL_PER_HOUR' => '?int $maxEmailPerHour = null',
            'extra' => '?string $extraValue = null',
        ], $params);

        self::assertTrue($operations['listaccts']->readOnly);
        self::assertTrue($operations['listaccts']->deprecated);
        self::assertSame('LoginSecurityCpHulk', $operations['flush_cphulk_login_history_for_ips']->group);
        self::assertSame('array<array-key, mixed>', $operations['flush_cphulk_login_history_for_ips']->parameters[0]->docType());
        self::assertSame('Other', $operations['addzonerecord']->group);
        self::assertSame(['zone', 'ttl'], array_map(static fn ($p): string => $p->name, $operations['addzonerecord']->parameters));
        self::assertSame('float|int|null', $operations['addzonerecord']->parameters[1]->docType());
        self::assertSame('takes a JSON request body', $operations['personalization_set']->skipReason);
    }

    #[Test]
    public function the_writer_generates_loadable_classes_a_catalog_and_coverage(): void
    {
        $root = sys_get_temp_dir().'/cpanel-whm-gen-'.bin2hex(random_bytes(4));
        mkdir($root.'/src/Api/Whm', 0o777, true);
        file_put_contents($root.'/src/Api/Whm/Stale.php', '<?php // '.Writer::HEADER);
        file_put_contents($root.'/src/Api/Whm/HandWritten.php', '<?php // mine');

        $whm = array_values($this->operations());
        $written = (new Writer($root))->write($whm, '11.200.0.1', [], '11.200.0.1', ['createacct' => 'accounts()->create()']);

        self::assertContains('src/Api/Whm/Accounts.php', $written);
        self::assertContains('docs/coverage.md', $written);
        self::assertFileDoesNotExist($root.'/src/Api/Whm/Stale.php');
        self::assertFileExists($root.'/src/Api/Whm/HandWritten.php');

        $accounts = (string) file_get_contents($root.'/src/Api/Whm/Accounts.php');
        self::assertStringContainsString('public function createacct(', $accounts);
        self::assertStringContainsString('], $extra, HttpMethod::Post);', $accounts);
        self::assertStringContainsString('@deprecated', $accounts);
        self::assertStringContainsString("return \$this->invoke('listaccts', [\n            'search' => \$search,\n        ], \$extra);", $accounts);

        foreach ($written as $file) {
            if (str_ends_with($file, '.php')) {
                exec('php -l '.escapeshellarg($root.'/'.$file).' 2>&1', $output, $status);
                self::assertSame(0, $status, implode("\n", $output));
            }
        }

        $coverage = (string) file_get_contents($root.'/docs/coverage.md');
        self::assertStringContainsString('| WHM API 1 | 5 | 4 | 80.0% | 1 |', $coverage);
        self::assertStringContainsString('`personalization_set` | takes a JSON request body', $coverage);
    }

    #[Test]
    public function a_missing_spec_is_reported(): void
    {
        $this->expectException(RuntimeException::class);

        Spec::load('/nonexistent/spec.yaml');
    }

    /**
     * @return array<string, Operation>
     */
    private function operations(): array
    {
        $spec = Spec::load(__DIR__.'/../Fixtures/openapi/whm-mini.yaml');
        self::assertSame('11.200.0.1', $spec->version());

        $operations = [];

        foreach ((new Reader($spec, Reader::WHM))->operations() as $operation) {
            $operations[$operation->function] = $operation;
        }

        return $operations;
    }
}
