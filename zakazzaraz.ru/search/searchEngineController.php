<?php

/**
 * User: Alexey Vasilkov (vasilkov.net@gmail.com)
 * Date: 06.09.13
 */

namespace zzr\search;

use zzr\engine\tpl;
use zzr\search\searchEngine;

class searchEngineController
{
    public function display()
    {
        $se         = new searchEngine();

        $text       = isset($_REQUEST['text']) ? filter_var($_REQUEST['text'], FILTER_SANITIZE_STRING) : false;
        $page       = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 1;

        return (new tpl())->render('views/search/results.html', []);
    }
}