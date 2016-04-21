<?php

/**
 * User: Alexey Vasilkov (vasilkov.net@gmail.com)
 * Date: 06.09.13
 */

namespace zzr\search\indexer;

use zzr\search\indexer\searchIndexerInterface;

class searchIndexerSphinx implements searchIndexerInterface
{
    public function index()
    {
        return false;
    }
}