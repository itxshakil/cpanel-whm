<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Modules;

use InvalidArgumentException;
use Itxshakil\CpanelWhm\Data\Value;
use Itxshakil\CpanelWhm\Enums\HttpMethod;
use Itxshakil\CpanelWhm\Exceptions\WhmException;
use Itxshakil\CpanelWhm\WhmResponse;

/**
 * Feature lists (what a package's accounts may use in cPanel):
 * get_featurelists, get_featurelist_data, create_featurelist,
 * update_featurelist, delete_featurelist.
 *
 * Features are keyed by their id, e.g. ['autossl' => true, 'changemx' => false].
 */
class FeatureLists extends Module
{
    /**
     * The names of the feature lists the token can see.
     *
     * @return list<string>
     *
     * @throws WhmException
     *
     * @see https://api.docs.cpanel.net/specifications/whm.openapi/packages/featurelists-get_featurelists
     */
    public function list(): array
    {
        return Value::strings($this->client->call('get_featurelists')->get('featurelists'));
    }

    /**
     * Every feature in a list and whether the list turns it on.
     *
     * @return array<string, bool>
     *
     * @throws WhmException
     *
     * @see https://api.docs.cpanel.net/specifications/whm.openapi/packages/featurelists-get_featurelist_data
     */
    public function features(string $featureList): array
    {
        $features = [];

        foreach ($this->rows($this->client->call('get_featurelist_data', ['featurelist' => $featureList])->get('features')) as $row) {
            $id = Value::string($row['id'] ?? null);

            if ($id !== null) {
                $features[$id] = Value::bool($row['value'] ?? false);
            }
        }

        return $features;
    }

    /**
     * Create a feature list. Features left out are off.
     *
     * @param  array<string, bool>  $features
     *
     * @throws WhmException
     *
     * @see https://api.docs.cpanel.net/specifications/whm.openapi/packages/featurelists-create_featurelist
     */
    public function create(string $featureList, array $features = [], bool $overwrite = false): WhmResponse
    {
        return $this->client->call('create_featurelist', [
            ...$this->featureParams($features),
            'featurelist' => $featureList,
            'overwrite' => $overwrite,
        ], HttpMethod::Post);
    }

    /**
     * Turn features on or off in an existing list.
     *
     * @param  array<string, bool>  $features  at least one
     *
     * @throws InvalidArgumentException when $features is empty
     * @throws WhmException
     *
     * @see https://api.docs.cpanel.net/specifications/whm.openapi/packages/featurelists-update_featurelist
     */
    public function update(string $featureList, array $features): WhmResponse
    {
        // update_featurelist with no features turns every feature in the list off.
        if ($features === []) {
            throw new InvalidArgumentException("Name at least one feature to change in feature list [{$featureList}]; WHM would turn them all off.");
        }

        return $this->client->call('update_featurelist', [
            ...$this->featureParams($features),
            'featurelist' => $featureList,
        ], HttpMethod::Post);
    }

    /**
     * @throws WhmException
     *
     * @see https://api.docs.cpanel.net/specifications/whm.openapi/packages/featurelists-delete_featurelist
     */
    public function delete(string $featureList): WhmResponse
    {
        return $this->client->call('delete_featurelist', ['featurelist' => $featureList], HttpMethod::Post);
    }

    /**
     * @param  array<string, bool>  $features
     * @return array<string, bool>
     */
    private function featureParams(array $features): array
    {
        foreach (array_keys($features) as $feature) {
            if (in_array($feature, ['featurelist', 'overwrite'], true)) {
                throw new InvalidArgumentException("[{$feature}] is a parameter of the call, not a feature.");
            }
        }

        return $features;
    }
}
