<?php

namespace finance\Controller;

use finance\Model\Queue as QueueModel;
use finance\Model\Domain as DomainModel;
use finance\Model\News as NewsModel;

class Controller_Parser extends Controller
{
    protected   $no_auth            = true;

    public function action_gettickers()
    {
        if (!$this->is_master())
            throw new \Exception('Get tickers only for master server');

        $server_key = _get('server_key');

        if (empty($server_key))
            throw new \Exception('Not found server key');

        return $this->json(QueueModel::tasks($server_key));
    }

    public function action_updatetickers()
    {
        if (!$this->is_master())
            throw new \Exception('News action only for master server');

        if (!($news_list = json_decode(_post('news_list'))))
            throw new \Exception('News update not found');

        foreach ($news_list as $queue_id => $result) {
            
            if (!empty($result->error)) {
                QueueModel::finish((int)$queue_id, $result->error);
                continue;
            }

            if (!empty($result->list)) {
                
                foreach ($result->list as $item) {
                    $id = NewsModel::add((array)$item);
                    if (!$id)
                        continue;
                    NewsModel::link($id, $result->ticker_id);
                }

                QueueModel::finish((int)$queue_id);
                
            }else {
                QueueModel::finish((int)$queue_id, 'Result list not found');
            }
        }

        die('ok');
    }

    public function action_getdomains()
    {
        if (!$this->is_master())
            throw new \Exception('Get domains only for master server');

        $server_key = _get('server_key');

        if (empty($server_key))
            throw new \Exception('Not found server key');

        return $this->json(DomainModel::tasks($server_key));
    }

    public function action_updatedomains()
    {
        if (!$this->is_master())
            throw new \Exception('Update domains only for master server');

        $server_key = _get('server_key');

        if (empty($server_key))
            throw new \Exception('Not found server key');

        if (!($domain_list = json_decode(_post('domain_list'))))
            throw new \Exception('Domain update not found');

        foreach ($domain_list as $id => $result) {
            
            $result = (array)$result;

            $update = [];

            if (!empty($result['alexa_rank']))
                $update['alexa_rank'] = $result['alexa_rank'];
            if (!empty($result['google_pr']))
                $update['google_pr'] = $result['google_pr'];
            if (!empty($result['backlinks']))
                $update['backlinks'] = $result['backlinks'];

            DomainModel::update((int)$id, array_merge($update, [
                'locked'        => '',
                'last_upd'      => date('Y-m-d')
            ]));

            DomainModel::history((int)$id, array_merge($update, [
                'server_key'    => $server_key,
                'error'         => !empty($result['error']) ? $result['error'] : ''
            ]));

        }

        die('ok');
    }
}
