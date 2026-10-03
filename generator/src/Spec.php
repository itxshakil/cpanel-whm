<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Generator;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * A loaded OpenAPI document with $ref resolution.
 */
final readonly class Spec
{
    /**
     * @param  array<string, mixed>  $document
     */
    public function __construct(public array $document) {}

    public static function load(string $path): self
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Cannot read the OpenAPI spec at {$path}.");
        }

        $document = str_ends_with($path, '.json')
            ? json_decode($contents, true, 512, JSON_THROW_ON_ERROR)
            : Yaml::parse($contents);

        if (! is_array($document) || ! is_array($document['paths'] ?? null)) {
            throw new RuntimeException("{$path} is not an OpenAPI document with paths.");
        }

        /** @var array<string, mixed> $document */
        return new self($document);
    }

    public function version(): string
    {
        $info = $this->document['info'] ?? [];

        return is_array($info) && is_scalar($info['version'] ?? null) ? (string) $info['version'] : 'unknown';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function paths(): array
    {
        /** @var array<string, array<string, mixed>> $paths */
        $paths = $this->document['paths'];

        return $paths;
    }

    /**
     * Tag name => tag group name, from x-tagGroups.
     *
     * @return array<string, string>
     */
    public function tagGroups(): array
    {
        $map = [];

        foreach ((array) ($this->document['x-tagGroups'] ?? []) as $group) {
            if (! is_array($group) || ! is_string($group['name'] ?? null)) {
                continue;
            }

            $map[$group['name']] ??= $group['name'];

            foreach ((array) ($group['tags'] ?? []) as $tag) {
                if (is_string($tag)) {
                    $map[$tag] ??= $group['name'];
                }
            }
        }

        return $map;
    }

    /**
     * Follow a local $ref (and nested ones) until a schema without $ref is reached.
     *
     * @param  array<array-key, mixed>  $schema
     * @return array<array-key, mixed>
     */
    public function resolve(array $schema, int $depth = 0): array
    {
        $ref = $schema['$ref'] ?? null;

        if (! is_string($ref) || $depth > 10) {
            return $schema;
        }

        $node = $this->document;

        foreach (explode('/', ltrim(substr($ref, 1), '/')) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);

            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return [];
            }

            $node = $node[$segment];
        }

        return is_array($node) ? $this->resolve($node, $depth + 1) : [];
    }
}
