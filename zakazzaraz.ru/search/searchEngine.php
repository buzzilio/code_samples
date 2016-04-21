<?php

/**
 * User: Alexey Vasilkov (vasilkov.net@gmail.com)
 * Date: 06.09.13
 */

namespace zzr\search;

use zzr\search\engine\searchEngineMongo;
use zzr\search\mistake\mistakeModel;

class searchEngine extends searchEngineMongo
{
    public function search($phrase, $limit = 10, $skip = 0, $filter_type = '')
    {
        return parent::search((new mistakeModel())->filter($phrase), $limit, $skip, $filter_type);
    }

    public function total($phrase, $filter_type = '')
    {
        return parent::total((new mistakeModel())->filter($phrase), $filter_type);
    }
}