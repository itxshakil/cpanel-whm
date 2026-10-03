<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Generator;

/**
 * Reads WHM API 1 or UAPI operations out of cPanel's OpenAPI documents.
 */
final readonly class Reader
{
    public const string WHM = 'whm';

    public const string UAPI = 'uapi';

    /**
     * Method names the generated classes cannot use (they belong to the base class).
     */
    private const array RESERVED_METHODS = ['invoke', '__construct', 'client', 'user'];

    /**
     * Variable names the generated methods cannot use.
     */
    private const array RESERVED_VARIABLES = ['extra', 'this'];

    private TypeMapper $types;

    public function __construct(private Spec $spec, private string $kind)
    {
        $this->types = new TypeMapper($spec);
    }

    /**
     * @return list<Operation>
     */
    public function operations(): array
    {
        $tagGroups = $this->spec->tagGroups();
        $operations = [];
        $taken = [];

        foreach ($this->spec->paths() as $path => $item) {
            foreach (['get', 'post'] as $verb) {
                $operation = $item[$verb] ?? null;

                if (! is_array($operation)) {
                    continue;
                }

                $built = $this->build($path, $operation, $tagGroups);

                // Keep method names unique within each class.
                $method = $built->method;
                $suffix = 2;

                while (isset($taken[$built->group][strtolower($method)])) {
                    $method = $built->method.$suffix++;
                }

                $taken[$built->group][strtolower($method)] = true;

                $operations[] = $method === $built->method ? $built : new Operation(
                    $built->function, $built->group, $method, $built->summary, $built->description,
                    $built->readOnly, $built->deprecated, $built->parameters, $built->docsUrl, $built->skipReason, $built->module, $built->title,
                );

                break;   // one operation per path
            }
        }

        usort($operations, static fn (Operation $a, Operation $b): int => [$a->group, $a->function] <=> [$b->group, $b->function]);

        return $operations;
    }

    /**
     * @param  array<array-key, mixed>  $operation
     * @param  array<string, string>  $tagGroups
     */
    private function build(string $path, array $operation, array $tagGroups): Operation
    {
        $segments = array_values(array_filter(explode('/', $path), static fn (string $s): bool => $s !== ''));
        $tags = array_values(array_filter((array) ($operation['tags'] ?? []), is_string(...)));

        if ($this->kind === self::UAPI) {
            $module = $segments[0];
            $function = $segments[1] ?? $segments[0];
            $group = Naming::studly($module);
            $title = $module;
        } else {
            $module = null;
            $function = implode('/', $segments);
            $groupName = 'Other';

            foreach ($tags as $tag) {
                if (isset($tagGroups[$tag])) {
                    $groupName = $tagGroups[$tag];

                    break;
                }
            }

            $group = Naming::studly($groupName);
            $title = $groupName;
        }

        $method = Naming::camel((string) end($segments));

        if (in_array($method, self::RESERVED_METHODS, true)) {
            $method .= 'Function';
        }

        return new Operation(
            function: $function,
            group: $group,
            method: $method,
            summary: Text::clean(is_string($operation['summary'] ?? null) ? $operation['summary'] : $function),
            description: Text::firstParagraph(is_string($operation['description'] ?? null) ? $operation['description'] : ''),
            readOnly: ($operation['x-readonly'] ?? false) === true,
            deprecated: ($operation['deprecated'] ?? false) === true,
            parameters: $this->parameters($operation),
            docsUrl: $this->docsUrl($operation, $tags, $tagGroups),
            skipReason: $this->skipReason($operation),
            module: $module,
            title: $title,
        );
    }

    /**
     * @param  array<array-key, mixed>  $operation
     * @return list<Parameter>
     */
    private function parameters(array $operation): array
    {
        $raw = [];

        foreach ((array) ($operation['parameters'] ?? []) as $parameter) {
            $parameter = is_array($parameter) ? $this->spec->resolve($parameter) : [];

            if (is_string($parameter['name'] ?? null)) {
                $raw[] = [
                    'name' => $parameter['name'],
                    'required' => ($parameter['required'] ?? false) === true,
                    'schema' => is_array($parameter['schema'] ?? null) ? $parameter['schema'] : [],
                    'description' => is_string($parameter['description'] ?? null) ? $parameter['description'] : '',
                ];
            }
        }

        $form = data_get($operation, 'requestBody.content.application/x-www-form-urlencoded.schema');

        if (is_array($form)) {
            $form = $this->spec->resolve($form);
            $required = array_filter((array) ($form['required'] ?? []), is_string(...));

            foreach ((array) ($form['properties'] ?? []) as $name => $schema) {
                $schema = is_array($schema) ? $schema : [];
                $raw[] = [
                    'name' => (string) $name,
                    'required' => in_array((string) $name, $required, true),
                    'schema' => $schema,
                    'description' => is_string($schema['description'] ?? null) ? $schema['description'] : '',
                ];
            }
        }

        $parameters = [];
        $names = [];
        $variables = [];

        foreach ($raw as $parameter) {
            $name = $parameter['name'];

            if ($name === 'api.version' || isset($names[$name]) || ! Naming::isUsableParameter($name)) {
                continue;
            }

            $variable = Naming::camel($name);

            if (in_array($variable, self::RESERVED_VARIABLES, true)) {
                $variable .= 'Value';
            }

            if ($variable === '' || isset($variables[$variable])) {
                continue;   // still reachable through $extra
            }

            $names[$name] = true;
            $variables[$variable] = true;

            $parameters[] = new Parameter(
                name: $name,
                variable: $variable,
                required: $parameter['required'],
                types: $this->types->map($parameter['schema']),
                description: Text::firstSentence($parameter['description']),
            );
        }

        return $parameters;
    }

    /**
     * @param  array<array-key, mixed>  $operation
     */
    private function skipReason(array $operation): ?string
    {
        $content = data_get($operation, 'requestBody.content');

        if (! is_array($content)) {
            return null;
        }

        foreach (array_keys($content) as $type) {
            if ($type === 'application/json') {
                return 'takes a JSON request body';
            }

            if ($type === 'multipart/form-data') {
                return 'takes a file upload';
            }
        }

        return null;
    }

    /**
     * @param  array<array-key, mixed>  $operation
     * @param  list<string>  $tags
     * @param  array<string, string>  $tagGroups
     */
    private function docsUrl(array $operation, array $tags, array $tagGroups): string
    {
        $groupNames = array_flip(array_values($tagGroups));
        $tag = $tags[0] ?? 'api';

        foreach ($tags as $candidate) {
            if (! isset($groupNames[$candidate])) {
                $tag = $candidate;

                break;
            }
        }

        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($tag)), '-');
        $id = strtolower(is_string($operation['operationId'] ?? null) ? $operation['operationId'] : '');
        $spec = $this->kind === self::UAPI ? 'cpanel' : 'whm';

        return "https://api.docs.cpanel.net/specifications/{$spec}.openapi/{$slug}/{$id}";
    }
}
