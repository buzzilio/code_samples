<?php

/**
 * User: Alexey Vasilkov (vasilkov.net@gmail.com)
 * Date: 06.09.13
 */

namespace zzr\search\engine;

interface searchEngineInterface
{
    public function search($phrase, $limit = 10, $skip = 0, $filter_type = []);

    public function autocomplete($phrase, $limit = 5);

    public function total($phrase, $filter_type = []);

    public function index();
}