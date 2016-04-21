<?php

namespace finance\Model;

/**
 * Queue model
 *
 * @package FinanceNews
 * @author  Alexey Vasilkov
 */

use finance\lib\Cfg;
use finance\lib\ORM;
use finance\lib\RollingCurl;
use finance\lib\RollingCurlException;

class Queue
{
    const QUEUE_TBL             = 'queue';

    /**
     * Fill queue with tasks
     *
     * @return int total tickers added to queue
     */
    public static function fill()
    {
        $ticker_list = ORM::for_table(Ticker::TICKERS_TBL)
            ->raw_query('SELECT `id` FROM `' . Ticker::TICKERS_TBL . '`'
                . ' WHERE `id` IN (SELECT `ticker_id` FROM `' . Ticker::USERS_TICKERS_TBL . '` GROUP BY `ticker_id`)'
                . ' AND `id` NOT IN (SELECT `ticker_id` FROM `' . self::QUEUE_TBL . '` WHERE `done` IS NULL GROUP BY `ticker_id`)')
            ->find_many();

        $count = 0;
        foreach ($ticker_list as $ticker) {
            $queue = ORM::for_table(self::QUEUE_TBL)->create();
            $queue->set_expr('created', 'NOW()');
            $queue->set('ticker_id', (int)$ticker->id);
            $queue->set('error', '');
            
            $count += $queue->save() ? 1 : 0;
        }

        return $count;
    }

    /**
     * Finish queue task
     *
     * @param int $id
     * @param string $error
     * @return bool
     */
    public static function finish($id, $error = '')
    {
        $item = ORM::for_table(self::QUEUE_TBL)->find_one($id);

        if (empty($item->id))
            return false;

        $item->set_expr('done', date('Y-m-d H:i:s'));

        if (!empty($error))
            $item->set('error', $error);

        return (bool)$item->save();
    }

    /**
     * Get queue tasks for server $server_key and lock them
     *
     * @param string $server_key
     * @return array tickers list
     */
    public static function tasks($server_key)
    {
        if (empty($server_key))
            return [];

        // If there's tasks that this server hasn't finished
        $exist = ORM::for_table(self::QUEUE_TBL)
            ->where('locked', $server_key)
            ->where_raw('`done` IS NULL')
            ->find_one();

        // Lock tickers for requested server
        if (!$exist)
            ORM::q('UPDATE `' . self::QUEUE_TBL . '` SET `locked` = :locked'
                . ' WHERE `locked` = "" AND `done` IS NULL'
                . ' ORDER BY `created` LIMIT 1', ['locked' => $server_key]);

        // Get this locked tickers
        $task_list = ORM::for_table(self::QUEUE_TBL)
            ->table_alias('q')
            ->select_many(['queue_id' => 'q.id', 'ticker_id' => 't.id'], 't.ticker')
            ->where('locked', $server_key)
            ->where_raw('done IS NULL')
            ->join(Ticker::TICKERS_TBL, ['q.ticker_id', '=', 't.id'], 't')
            ->order_by_asc('q.created')
            ->limit(1)
            ->find_many();

        $output = [];
        foreach ($task_list as $task)
            $output[] = ['queue_id' => $task->queue_id, 'ticker_id' => $task->ticker_id, 'ticker' => $task->ticker];

        return $output;
    }

    /**
     * Get queue tasks from main server
     *
     * @throws \Exception
     * @return array tickers list
     */
    public static function getFromMainServer()
    {
        try {

            // Get tickers list from main server
            $curl = new RollingCurl();
            $curl->request(Cfg::get('master_server') . vsprintf('?r=parser.gettickers&server_key=%s', Cfg::get('server_key')));
            if (!($response = $curl->execute()))
                throw new \Exception('Main server empty response');

            return ($task_list = json_decode($response)) ? $task_list : array();

        } catch (RollingCurlException $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Sends tickers news to main server
     *
     * @param string $json_news_list
     * @throws \Exception
     * @return void
     */
    public static function updateMainServer($json_news_list)
    {
        try {

            // Send news list from main server
            $curl = new RollingCurl();
            $curl->post(Cfg::get('master_server') . vsprintf('?r=parser.updatetickers&server_key=%s', Cfg::get('server_key')), ['news_list' => $json_news_list]);
            if (!($response = $curl->execute()))
                throw new \Exception('Main server empty response');

            return ;

        } catch (RollingCurlException $e) {
            throw new \Exception($e->getMessage());
        }
    }
}