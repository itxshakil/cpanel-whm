<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Tests\Feature;

use Itxshakil\CpanelWhm\Api\UapiApi;
use Itxshakil\CpanelWhm\Api\WhmApi;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Exceptions\UapiCallFailed;
use Itxshakil\CpanelWhm\Facades\Whm;
use Itxshakil\CpanelWhm\Support\CuratedFunctions;
use Itxshakil\CpanelWhm\Support\FunctionCatalog;
use Itxshakil\CpanelWhm\Tests\TestCase;
use Itxshakil\CpanelWhm\WhmRequest;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;

final class GeneratedApiTest extends TestCase
{
    #[Test]
    public function read_only_functions_are_sent_as_get_with_only_given_arguments(): void
    {
        $fake = Whm::fake(['parse_dns_zone' => Whm::response(['payload' => []])]);

        Whm::api()->dns()->parseDnsZone(zone: 'example.com');

        $fake->assertCalled('parse_dns_zone', static fn (array $p, WhmRequest $r): bool => $p === ['zone' => 'example.com'] && $r->method === HttpMethod::Get);
    }

    #[Test]
    public function changes_are_sent_as_post_with_flags_lists_and_extras(): void
    {
        $fake = Whm::fake(['*' => Whm::response()]);

        Whm::api()->dns()->addZoneKey(algoNum: 13, domain: 'example.com', keyType: 'simple', active: true);
        Whm::api()->dns()->exportZoneFiles(zone: ['a.test', 'b.test']);
        Whm::api()->accounts()->createacct(username: 'acme', domain: 'acme.test', extra: ['customip' => '203.0.113.5']);

        $fake->assertCalled('add_zone_key', static fn (array $p, WhmRequest $r): bool => $p === ['algo_num' => 13, 'domain' => 'example.com', 'key_type' => 'simple', 'active' => 1]
            && $r->method === HttpMethod::Post)
            ->assertCalled('export_zone_files', static fn (array $p): bool => $p === ['zone' => 'a.test', 'zone-1' => 'b.test'])
            ->assertCalled('createacct', static fn (array $p): bool => $p == ['username' => 'acme', 'domain' => 'acme.test', 'customip' => '203.0.113.5']);
    }

    #[Test]
    public function uapi_functions_run_as_the_account(): void
    {
        $fake = Whm::fake(['uapi_cpanel' => Whm::sequence(Whm::uapi([['email' => 'info@acme.test']]), Whm::uapiFailure('The account does not exist.'))]);

        $result = Whm::asUser('acme')->api()->email()->listPops(regex: 'info');

        self::assertSame('info@acme.test', $result->get('0.email'));
        $fake->assertCalled('uapi_cpanel', static fn (array $p): bool => $p === [
            'cpanel.user' => 'acme', 'cpanel.module' => 'Email', 'cpanel.function' => 'list_pops', 'regex' => 'info',
        ]);

        $this->expectException(UapiCallFailed::class);
        Whm::asUser('acme')->api()->subDomain()->addsubdomain(domain: 'blog', rootdomain: 'acme.test');
    }

    #[Test]
    public function every_catalog_entry_points_at_a_real_method(): void
    {
        foreach (['WHM' => [FunctionCatalog::WHM, WhmApi::class], 'UAPI' => [FunctionCatalog::UAPI, UapiApi::class]] as $kind => [$catalog, $entry]) {
            $accessors = [];

            foreach ((new ReflectionClass($entry))->getMethods() as $method) {
                $type = $method->getReturnType();

                if ($method->isPublic() && ! $method->isConstructor() && $type instanceof ReflectionNamedType) {
                    $accessors[$method->getName()] = $type->getName();
                }
            }

            foreach ($catalog as $function => [$generated]) {
                if ($generated === null) {
                    continue;
                }

                self::assertMatchesRegularExpression('/^(\w+)\(\)->(\w+)\(\)$/', $generated);
                preg_match('/^(\w+)\(\)->(\w+)\(\)$/', $generated, $match);
                self::assertArrayHasKey($match[1], $accessors, "{$kind} {$function}: no accessor {$match[1]}()");
                self::assertTrue(method_exists($accessors[$match[1]], $match[2]), "{$kind} {$function}: no method {$match[2]}()");
            }
        }
    }

    #[Test]
    public function the_catalog_covers_the_spec_and_every_curated_function(): void
    {
        self::assertGreaterThan(600, count(FunctionCatalog::WHM));
        self::assertGreaterThan(650, count(FunctionCatalog::UAPI));
        self::assertTrue(FunctionCatalog::isReadOnly('listaccts'));
        self::assertFalse(FunctionCatalog::isReadOnly('createacct'));
        self::assertFalse(FunctionCatalog::isReadOnly('no_such_function'));
        self::assertSame('POST', FunctionCatalog::whm('removeacct')['httpMethod'] ?? null);
        self::assertNull(FunctionCatalog::whm('no_such_function'));
        self::assertSame('email()->listPops()', FunctionCatalog::uapi('Email', 'list_pops')['method'] ?? null);
        self::assertNull(FunctionCatalog::uapi('Email', 'nope'));

        foreach (array_keys(CuratedFunctions::MAP) as $function) {
            self::assertTrue(FunctionCatalog::has($function) || in_array($function, ['verify_new_username'], true), "{$function} is not in cPanel's spec");
        }
    }

    /**
     * Calls every generated method with dummy values for its required
     * arguments and checks the request it builds.
     */
    #[Test]
    public function every_generated_method_builds_its_request(): void
    {
        $fake = Whm::fake(['*' => Whm::uapi()]);
        $calls = 0;

        foreach ([[FunctionCatalog::WHM, Whm::api(), false], [FunctionCatalog::UAPI, Whm::asUser('acme')->api(), true]] as [$catalog, $api, $isUapi]) {
            foreach ($catalog as $key => [$generated, , $readOnly]) {
                if ($generated === null) {
                    continue;
                }

                preg_match('/^(\w+)\(\)->(\w+)\(\)$/', $generated, $match);
                $group = $api->{$match[1]}();
                $method = new ReflectionMethod($group, $match[2]);
                $arguments = [];

                foreach ($method->getParameters() as $parameter) {
                    if (! $parameter->isOptional()) {
                        $arguments[$parameter->getName()] = self::dummy($parameter->getType());
                    }
                }

                $method->invokeArgs($group, $arguments);
                $calls++;

                $request = $fake->recorded()[array_key_last($fake->recorded())];
                $function = $isUapi ? 'uapi_cpanel' : (string) $key;

                self::assertSame($function, $request->function, "{$key} sent the wrong function");

                if ($isUapi) {
                    [$module, $uapiFunction] = explode('::', (string) $key);
                    self::assertSame($module, $request->params['cpanel.module']);
                    self::assertSame($uapiFunction, $request->params['cpanel.function']);
                } else {
                    self::assertSame($readOnly ? HttpMethod::Get : HttpMethod::Post, $request->method, "{$key} used the wrong HTTP method");
                }
            }
        }

        self::assertGreaterThan(1250, $calls);
    }

    private static function dummy(?ReflectionType $type): mixed
    {
        $names = match (true) {
            $type instanceof ReflectionNamedType => [$type->getName()],
            $type instanceof ReflectionUnionType => array_map(static fn ($t): string => $t instanceof ReflectionNamedType ? $t->getName() : 'mixed', $type->getTypes()),
            default => ['mixed'],
        };

        return match ($names[0]) {
            'array' => ['one', 'two'],
            'bool' => true,
            'float', 'int' => 1,
            default => 'value',
        };
    }
}
