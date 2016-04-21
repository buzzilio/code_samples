<?php

namespace finance\Model;

/**
 * News model
 *
 * @package FinanceNews
 * @author  Alexey Vasilkov
 */

use finance\lib\ORM;

class News
{
    const NEWS_TBL              = 'news';
    const NEWS_TO_TICKER_TBL    = 'news_to_ticker';

    /**
     * Add news item
     *
     * @param array $item
     * @return bool
     */
    public static function add($item)
    {
        if (empty($item['url']))
            return false;

        // Try to find or add new domain
        $domain = parse_url($item['url'], PHP_URL_HOST);
        if (!($item['domain_id'] = Domain::add($domain)))
            return false;

        // Url hash for quick search in news table by url
        $item['hash'] = md5($item['url']);

        // News item exists
        $exist = ORM::for_table(self::NEWS_TBL)
            ->select('id')
            ->where('hash', $item['hash'])
            ->find_one();

        if ($exist)
            return $exist->id;

        // Add
        $news_item = ORM::for_table(self::NEWS_TBL)->create();

        $news_item->set('domain_id',    (int)$item['domain_id']);
        $news_item->set('url',          $item['url']);
        $news_item->set('hash',         $item['hash']);
        $news_item->set('title',        $item['title']);
        $news_item->set('brief',        $item['brief']);
        $news_item->set('pubdate',      date('Y-m-d H:i:s', (int)$item['pubdate']));
        $news_item->set('error',        '');

        $news_item->save();

        return $news_item->id();
    }

    /**
     * Add link news <-> tickers
     *
     * @param int $news_id
     * @param int $ticker_id
     * @return bool
     */
    public static function link($news_id, $ticker_id)
    {
        $link = ORM::for_table(self::NEWS_TO_TICKER_TBL)
            ->where('news_id', (int)$news_id)
            ->where('ticker_id', (int)$ticker_id)
            ->find_one();

        if (!empty($link->news_id))
            return true;

        ORM::q('INSERT INTO `' . self::NEWS_TO_TICKER_TBL . '` (`news_id`, `ticker_id`) VALUES (' . (int)$news_id . ', ' . (int)$ticker_id . ')');
    }

    /**
     * Get list of news for $ticker_id
     *
     * @param int $ticker_id
     * @param array $params
     * @return array
     */
    public static function getList($ticker_id, $params = [])
    {
        $result = ORM::for_table(self::NEWS_TBL)
            ->select('*')
            ->select_expr('UNIX_TIMESTAMP(`pubdate`)', 'pubdate')
            ->where_raw('`id` IN (SELECT `news_id` FROM `' . self::NEWS_TO_TICKER_TBL . '` WHERE `ticker_id` = ' . (int)$ticker_id . ')');

        if (isset($params['from']))
            $result->where_gt('pubdate', date('Y-m-d H:i:s', $params['from']));
        
        if (isset($params['till']))
            $result->where_lt('pubdate', date('Y-m-d H:i:s', $params['till']));

        if (isset($params['limit']))
            $result->limit((int)$params['limit']);

        if (isset($params['offset']))
            $result->offset((int)$params['offset']);

        if (isset($params['orderby'])) {
            if (isset($params['sort']) && $params['sort'] == 'desc')
                $result->order_by_desc($params['orderby']);
            else
                $result->order_by_asc($params['orderby']);
        }else {
            $result->order_by_desc('pubdate');
        }

        return $result->find_array();
    }

    /**
     * Count news for $ticker_id
     *
     * @param int $ticker_id
     * @param array $params
     * @return int
     */
    public static function count($ticker_id, $params = [])
    {
        $result = ORM::for_table(self::NEWS_TBL)
            ->where_raw('`id` IN (SELECT `news_id` FROM `' . self::NEWS_TO_TICKER_TBL . '` WHERE `ticker_id` = ' . (int)$ticker_id . ')');

        if (isset($params['from']))
            $result->where_gt('pubdate', date('Y-m-d H:i:s', $params['from']));
        
        if (isset($params['till']))
            $result->where_lt('pubdate', date('Y-m-d H:i:s', $params['till']));

        return $result->count();
    }
}