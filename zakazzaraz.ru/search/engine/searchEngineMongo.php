<?php

/**
 * User: Alexey Vasilkov (vasilkov.net@gmail.com)
 * Date: 06.09.13
 */

namespace zzr\search\engine;

use zzr\engine\databases\mw;
use zzr\search\engine\searchEngineInterface;
use zzr\search\indexer\searchIndexerMongo;

class searchEngineMongo implements searchEngineInterface
{
    /**
     * @var mw instance
     */
    protected       $mw                         = null;

    /**
     * @const name of search index collection
     */
    const           SEARCH_COLLECTION           = 'search';

    /**
     * @const name of search index collection
     */
    const           AUTOCOMPLETE_COLLECTION     = 'search_autocomplete';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->mw                   = mw::i();
    }

    /**
     * Get search results
     *
     * @param string $phrase - search phrase
     * @param int $limit - limit found records
     * @param int $skip - skip records
     * @param string|array $filter_type - can specify which type of records you need to find (product, category, brand, shop)
     *
     * @return array
     */
    public function search($phrase, $limit = 10, $skip = 0, $filter_type = '')
    {
        $command = [
            'text'		=> self::SEARCH_COLLECTION,
            'search'	=> $phrase,
            'limit'     => $skip + $limit
        ];

        if ($filter_type && is_string($filter_type))
            $command['filter'] = ['type' => $filter_type];
        elseif ($filter_type && is_array($filter_type))
            $command['filter'] = ['type' => ['$in' => $filter_type]];

        $output     = [];
        $result     = $this->mw->zzr->command($command);

        if (!isset($result['results']))
            return [];

        for ($i = $skip; $i <= $skip + $limit; $i++)
            if (isset($result['results'][$i]))
                $output[] = $result['results'][$i];

        return $output;
    }

    /**
     * Get autocomplete suggestions
     *
     * @param string $phrase - search phrase
     * @param int $limit - limit found records
     *
     * @return array
     */
    public function autocomplete($phrase, $limit = 5) {

        $result = $this->mw->zzr->{self::AUTOCOMPLETE_COLLECTION}->find(
            ['word' => ['$regex' =>  '^' . $phrase]],
            ['_id' => false, 'word' => true]
        )->limit($limit);

        if (!$result->count())
            return [];

        $output = [];
        foreach ($result as $word)
            $output[] = $word['word'];

        return $output;
    }

    /**
     * Count total found records (max 1000 cause mongodb does not have function for it and we have to get this number by simple search query)
     *
     * @param string $phrase - search phrase
     * @param string|array $filter_type - can specify which type of records you need to find (product, category, brand, shop)
     *
     * @return array
     */
    public function total($phrase, $filter_type = '')
    {
        $command = [
            'text'		=> self::SEARCH_COLLECTION,
            'search'	=> $phrase,
            'limit'     => 1000,
            'project'   => [
                '_id'   => true
            ]
        ];

        if ($filter_type && is_string($filter_type))
            $command['filter'] = ['type' => $filter_type];
        elseif ($filter_type && is_array($filter_type))
            $command['filter'] = ['type' => ['$in' => $filter_type]];

        $result = $this->mw->zzr->command($command);

        return (int)$result['stats']['nfound'];
    }

    /**
     * Interfaced index function
     *
     * @return bool
     */
    public function index()
    {
        return (new searchIndexerMongo())->index();
    }
}