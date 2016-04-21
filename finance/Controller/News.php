<?php

namespace finance\Controller;

use finance\app\User;
use finance\Model\Ticker as TickerModel;
use finance\Model\News as NewsModel;
use finance\Model\Domain as DomainModel;
use finance\Model\Keyword as KeywordModel;

class News extends Controller
{
    public function action_tickerinfo()
    {
        $ticker = _get('ticker', '', 'string');

        if (empty($ticker) || !($ticker = TickerModel::get($ticker)))
            return false;

        $this->context->id          = $ticker->id;
        $this->context->ticker      = $ticker->ticker;

        $this->context->total       = NewsModel::count((int)$ticker->id, ['from' => mktime(0, 0, 0)]);

        $watchlist                  = TickerModel::getUserTickers(User::id(), [1, 2, 5]);
        $this->context->tickers     = TickerModel::getList(['id' => array_keys($watchlist)]);

        $this->context->profile     = [
            'domain'    => DomainModel::getTickerDomain($ticker->id, User::id()),
            'keyword'   => KeywordModel::getTickerKeyword($ticker->id, User::id())
        ];

        foreach ($this->context->tickers as &$ticker)
            $ticker['watchlist_id'] = $watchlist[$ticker['id']]['watchlist_id'];

        return $this->display('tickers/news_widget.html');
    }

    public function action_tickerprofile()
    {
        $ticker_id = _get('id', null, 'int');

        if (!$ticker_id)
            $this->json(['error' => 'Not found ticker']);

        $ticker     = TickerModel::get($ticker_id);
        $domain     = DomainModel::getTickerDomain($ticker_id, User::id());
        $keyword    = KeywordModel::getTickerKeyword($ticker_id, User::id());

        $this->json([
            'total'     => NewsModel::count($ticker_id, ['from' => mktime(0, 0, 0)]),
            'domain'    => $domain ? $domain['domain'] : '',
            'keyword'   => $keyword ? $keyword['keyword'] : ''
        ]);
    }

    public function action_savetickerprofile()
    {
        $ticker_id = _post('ticker_id', null, 'int');

        if (!$ticker_id)
            $this->json(['error' => 'Ticker not found in request']);

        $this->json(DomainModel::setTickerDomain($ticker_id, User::id(), _post('domain', '', 'string'))
            && KeywordModel::setTickerKeyword($ticker_id, User::id(), _post('keyword', '', 'string'))
            ? ['result' => 'Ticker profile saved']
            : ['error' => 'Ticker profile not saved']);
    }
}