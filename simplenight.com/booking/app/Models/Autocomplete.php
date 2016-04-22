<?php

class Autocomplete extends \Simplenight\ApiResource
{
    const RESOURCE = 'autocomplete';

    public static function get($search_uuid, $query, $filters = [])
    {
        $filters['q'] = $query;

        $response = self::getHttpClient()->post(static::RESOURCE .'/'. urlencode($search_uuid), $filters);

        return [
            'suggestions' => self::transform(array_get($response, 'response_body.result', []), $query)
        ];
    }

    protected static function transform($result, $query)
    {
        foreach ($result as & $entry) {
            $entry = [
                'value' => array_get($entry, 'name'),
                'data' => [
                    'uuid' => array_get($entry, 'uuid'),
                    'text' => array_get($entry, 'text'),
                    'type' => strtolower(array_get($entry, 'product_type', ''))
                ]
            ];
        }

        array_unshift($result, [
            'value' => trans('results.show_all_results_for', ['query' => $query]),
            'data' => [
                'type' => 'all'
            ]
        ]);

        return $result;
    }
}