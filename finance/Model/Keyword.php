<?php

namespace finance\Model;

/**
 * Keyword model
 *
 * @package FinanceNews
 * @author  Alexey Vasilkov
 */

use finance\lib\ORM;

class Keyword
{
    const KEYWORDS_TBL              = 'keywords';
    const TICKERS_KEYWORDS_TBL      = 'keywords_to_ticker';

    /**
     * Add keyword
     *
     * @param int $ticker_id
     * @param int $user_id
     * @return bool|int
     */
    public static function add($keyword)
    {
    	$exist = ORM::for_table(self::KEYWORDS_TBL)
    		->where('keyword', $keyword)
    		->find_one();

    	if ($exist)
    		return $exist->id;

    	$item = ORM::for_table(self::KEYWORDS_TBL)->create();
    	$item->set('keyword', $keyword);

    	return $item->save() ? $item->id() : false;
    }

    /**
     * Get ticker`s keyword
     *
     * @param int $ticker_id
     * @param int $user_id
     * @return bool|array
     */
    public static function getTickerKeyword($ticker_id, $user_id)
    {
    	$result = ORM::for_table(self::TICKERS_KEYWORDS_TBL)
    		->table_alias('tk')
    		->select_many('k.id', 'k.keyword')
    		->join(self::KEYWORDS_TBL, 'k.id = tk.keyword_id', 'k')
    		->where('tk.ticker_id', (int)$ticker_id)
    		->where('tk.user_id', (int)$user_id)
    		->find_array();

    	return $result ? $result[0] : false;
    }

    /**
     * Set ticker`s keyword
     *
     * @param int $ticker_id
     * @param int $user_id
     * @param string $keyword
     * @return bool
     */
    public static function setTicketKeyword($ticker_id, $user_id, $keyword)
    {
    	ORM::for_table(self::TICKERS_KEYWORDS_TBL)
    		->where('ticker_id', (int)$ticker_id)
    		->where('user_id', (int)$user_id)
    		->delete_many();

    	if (!$keyword)
    		return true;

    	if (is_string($keyword) && (false === ($keyword = self::add($keyword))))
    		return false;

    	$item = ORM::for_table(self::TICKERS_KEYWORDS_TBL)->create();
    	$item->set('ticker_id', (int)$ticker_id);
    	$item->set('user_id', (int)$user_id);
    	$item->set('keyword_id', (int)$keyword);

    	return $item->save();
    }
}