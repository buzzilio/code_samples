<?php

namespace finance\Models;

/**
 * Domain model
 *
 * @package FinanceNews
 * @author  Alexey Vasilkov
 */

use finance\Log;
use finance\lib\Parser;
use finance\lib\RollingCurl;
use finance\lib\RollingCurlRequest;
use finance\lib\RollingCurlException;

class GoogleRSS
{
    protected $news_list          = [];

    public static function url($ticker)
    {
        return vsprintf("https://www.google.com/finance/company_news?q=%s&output=rss", $ticker);
    }

    public function parse($task_list)
    {
        $rc = new RollingCurl([$this, 'response']);

        foreach ($task_list as $task) {
            $request = new RollingCurlRequest($this->url($task->ticker), 'GET', null, null, default_curl_opts(), [
                'queue_id'  => $task->queue_id,
                'ticker_id' => $task->ticker_id
            ]);
            $rc->add($request);
        }

        $rc->execute(5);

        return $this->news_list;
    }

    public function response($response, $info, $request) {

        $opts = $request->store;

        $this->news_list[$opts['queue_id']] = [
            'ticker_id'     => $opts['ticker_id'],
            'list'          => []
        ];

        try {
            $items = Parser::RSS($response);
        } catch(\Exception $e) {
//            var_dump($response);
            $this->news_list[$opts['queue_id']]['error'] = $e->getMessage();
            return;
        }

        $items = array_filter($items, function($item) {
            return !empty($item['link']) && !empty($item['pubDate']);
        });

        $items = array_map(function($item) {
            $item['time'] = strtotime($item['pubDate']);
            return $item;
        }, $items);

        foreach($items as $item)
            if (arr_get($item, 'link', false))
                $this->news_list[$opts['queue_id']]['list'][] = [
                    'url'       => arr_get($item, 'link'),
                    'title'     => arr_get($item, 'title', ''),
                    'brief'     => arr_get($item, 'description', ''),
                    'pubdate'   => $item['time']
                ];
    }
}
