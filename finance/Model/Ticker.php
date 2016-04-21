<?php

namespace finance\Model;

/**
 * Ticker model
 *
 * @package FinanceNews
 * @author  Alexey Vasilkov
 */

use finance\lib\ORM;

class Ticker
{
    const TICKERS_TBL               = 'tickers';
    const USERS_TICKERS_TBL         = 'users_tickers';

    /**
     * Add ticker
     *
     * @return bool|int
     */
    public static function add($ticker)
    {

    }

    /**
     * Get ticker item
     *
     * @param int|string $ticker
     * @return bool|array
     */
    public static function get($ticker)
    {
        if (empty($ticker) || (!is_string($ticker) && !is_int($ticker)))
            return false;

        return ORM::for_table(self::TICKERS_TBL)
            ->where(is_int($ticker) ? 'id' : 'ticker', $ticker)
            ->find_one();
    }

    /**
     * Get tickers list
     *
     * @param array $params
     * @return false|array
     */
    public static function getList($params)
    {
        $table = ORM::for_table(self::TICKERS_TBL);

        if (isset($params['id']) && is_array($params['id']))
            $table->where_in('id', $params['id']);

        if (isset($params['user_id']) && is_int($params['user_id']))
            $table->where_raw('`id` IN (SELECT `ticker_id` FROM `' . self::USERS_TICKERS_TBL . '` WHERE `user_id` = ' . $params['user_id'] . ')');

        if (!empty($params['orderby']))
            if ('desc' == $params['sort'])
                $table->order_by_desc($params['orderby']);
            else
                $table->order_by_asc($params['orderby']);

        if (isset($params['limit']) && is_int($params['limit']))
            $table->limit($params['limit']);

        if (isset($params['offset']) && is_int($params['offset']))
            $table->offset($params['offset']);

        return $table->find_array();
    }

    /**
     * Get users tickers id list
     *
     * @param int $user_id
     * @param int|array $watchlist_id
     * @return array
     */
    public static function getUserTickers($user_id, $watchlist_id = null)
    {
        $result = ORM::for_table(self::USERS_TICKERS_TBL)
            ->select_many('ticker_id', 'watchlist_id')
            ->where('user_id', (int)$user_id);

        if (is_int($watchlist_id))
            $result->where('watchlist_id', $watchlist_id);
        elseif (is_array($watchlist_id))
            $result->where_in('watchlist_id', $watchlist_id);

        $ids = [];
        foreach ($result->find_array() as $row)
            $ids[$row['ticker_id']] = $row;

        return $ids;
    }

    /**
     * Updates user's ticker list
     *
     * @param int $user_id
     * @param array $tickers
     * @return bool
     */
    public static function updateUserTickers($user_id, $tickers)
    {
        ORM::for_table(self::USERS_TICKERS_TBL)
            ->where('user_id', (int)$user_id)
            ->delete_many();

        foreach ($tickers as $ticker_id) {
            $link = ORM::for_table(self::USERS_TICKERS_TBL)->create();
            $link->set('user_id', (int)$user_id);
            $link->set('ticker_id', (int)$ticker_id);
            $link->save();
        }

        return true;
    }

    /**
     * Count tickers
     *
     * @return false|int
     */
    public static function count($user_id = null)
    {

    }
}