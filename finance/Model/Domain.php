<?php

namespace finance\Models;

/**
 * Domain model
 *
 * @package FinanceNews
 * @author  Alexey Vasilkov
 */

use finance\lib\ORM;

class Domain
{
    const DOMAINS_TBL               = 'domains';
    const HISTORY_TBL               = 'domain_history';
    const TICKERS_DOMAINS_TBL       = 'domains_to_ticker';

    /**
     * Get domain item
     *
     * @param int|string $domain
     * @return array
     */
    public static function get($domain)
    {
        if (empty($domain) || (!is_string($domain) && !is_int($domain)))
            return false;

        return ORM::for_table(self::DOMAINS_TBL)
            ->where(is_int($domain) ? 'id' : 'domain', $domain)
            ->find_one();
    }

    /**
     * Add domain item
     *
     * @param string $domain
     * @return bool
     */
    public static function add($domain)
    {
        if (empty($domain) || !is_string($domain))
            return false;

        $exist = ORM::for_table(self::DOMAINS_TBL)
            ->select('id')
            ->where('domain', $domain)
            ->find_one();

        if ($exist)
            return (int)$exist->id;

        $item = ORM::for_table(self::DOMAINS_TBL)->create();
        $item->set('domain', $domain);
        $item->save();

        return (int)$item->id();
    }

    /**
     * Update domain item
     *
     * @param string|int $domain
     * @return bool
     */
    public static function update($domain, $update)
    {
        if (!($domain = self::get($domain)))
            return false;

        foreach ($update as $field => $value)
            $domain->set($field, $value);

        return $domain->save();
    }

    /**
     * Get domain tasks for server $server_key and lock them
     *
     * @param string $server_key
     * @return array domain list
     */
    public static function getTasks($server_key)
    {
        if (empty($server_key))
            return [];

        // If there's tasks that this server hasn't finished
        $exist = ORM::for_table(self::DOMAINS_TBL)
            ->where('locked', $server_key)
            ->find_one();

        // Lock tickers for requested server
        if (!$exist)
            ORM::q('UPDATE `' . self::DOMAINS_TBL . '` SET `locked` = :locked'
                . ' WHERE `locked` = "" AND (`last_upd` < CURDATE() OR `last_upd` IS NULL) ORDER BY `last_upd` DESC LIMIT 1', ['locked' => $server_key]);

        // Get this locked tickers
        $task_list = ORM::for_table(self::DOMAINS_TBL)
            ->select_many('id', 'domain')
            ->where('locked', $server_key)
            ->find_many();

        $output = [];
        foreach ($task_list as $task)
            $output[] = ['id' => $task->id, 'domain' => $task->domain];

        return $output;
    }

    /**
     * Insert domain history
     *
     * @param int $domain_id
     * @param array $params
     * @return bool
     */
    public static function history($domain_id, $params)
    {
        $item = ORM::for_table(self::HISTORY_TBL)->create();

        $item->set('domain_id', (int)$domain_id);
        $item->set_expr('ctime', 'NOW()');
        $item->set('error', '');

        foreach ($params as $key => $value)
            $item->set($key, $value);

        return (bool)$item->save();
    }

    /**
     * Get list for user`s ticker
     *
     * @param int $user_id
     * @param int $ticker_id
     * @return array
     */
    public static function getList($user_id, $ticker_id)
    {
        return ORM::for_table(self::TICKERS_DOMAINS_TBL)
            ->table_alias('td')
            ->select('d.*')
            ->join(self::DOMAINS_TBL, ['td.domain_id', '=', 'd.id'], 'd')
            ->where('td.user_id', (int)$user_id)
            ->where('td.ticker_id', (int)$ticker_id)
            ->order_by_asc('d.domain')
            ->find_array();
    }

    /**
     * Get domain assigned to ticker by user
     *
     * @param int $ticker_id
     * @param int $user_id
     * @return array
     */
    public static function getTickerDomain($ticker_id, $user_id)
    {
        $result = ORM::for_table(self::TICKERS_DOMAINS_TBL)
            ->table_alias('td')
            ->select_many('d.id', 'd.domain')
            ->join(self::DOMAINS_TBL, 'd.id = td.domain_id', 'd')
            ->where('td.ticker_id', (int)$ticker_id)
            ->where('td.user_id', (int)$user_id)
            ->find_array();

        return $result ? $result[0] : false;
    }

    /**
     * Set ticker domain
     *
     * @param int $ticker_id
     * @param int $user_id
     * @param int|string $domain
     * @return bool
     */
    public static function setTickerDomain($ticker_id, $user_id, $domain)
    {
        ORM::for_table(self::TICKERS_DOMAINS_TBL)
            ->where('ticker_id', (int)$ticker_id)
            ->where('user_id', (int)$user_id)
            ->delete_many();

        if (!$domain)
            return true;

        if (is_string($domain) && (false === ($domain = self::add($domain))))
            return false;
        
        $item = ORM::for_table(self::TICKERS_DOMAINS_TBL)->create();
        $item->set('ticker_id', (int)$ticker_id);
        $item->set('user_id', (int)$user_id);
        $item->set('domain_id', (int)$domain);

        return $item->save();
    }

    /**
     * Get domain tasks from main server
     *
     * @throws \Exception
     * @return array tickers list
     */
    public static function getFromMainServer()
    {
        try {
            
            $curl = new RollingCurl();
            $curl->request(Cfg::get('master_server') . vsprintf('?r=parser.getdomains&server_key=%s', Cfg::get('server_key')));
            if (!($response = $curl->execute()))
                throw new Exception('Main server empty response');

            return ($task_list = json_decode($response)) ? $task_list : [];

        } catch (RollingCurlException $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Send domain results to main server
     *
     * @param string $json_result
     * @throws \Exception
     * @return void
     */
    public static function updateMainServer($json_results)
    {
        try {

            // Send news list from main server
            $curl = new RollingCurl();
            $curl->post(Cfg::get('master_server') . vsprintf('?r=parser.updatedomains&server_key=%s', Cfg::get('server_key')), array('domain_list' => $json_results));
            if (!($response = $curl->execute()))
                throw new Exception('Main server empty response');

            return ;

        } catch (RollingCurlException $e) {
            throw new Exception($e->getMessage());
        }
    }
}