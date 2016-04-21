<?php

/**
 * User: Alexey Vasilkov (vasilkov.net@gmail.com)
 * Date: 06.09.13
 */

namespace zzr\search;

use zzr\engine\tpl;
use zzr\product\product;
use zzr\product\productException;
use zzr\pricing\pricing;
use zzr\search\searchEngine;

class searchController
{
    const   PRODUCTS_PER_PAGE                   = 16;

    public function display()
    {
        if (isset($_REQUEST['autocomplete']))
            return $this->autocomplete();

        $se                     = new searchEngine();

        $data                   = [];

        $data['text']           = isset($_REQUEST['text']) ? trim(filter_var($_REQUEST['text'], FILTER_SANITIZE_STRING)) : false;
        $data['page']           = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 1;

        if (!$data['text']) {
            (new tpl())->render($data, 'views/search/results.html');
            return ;
        }

        if ($this->searchByArticle($data['text']))
            return ;

        $data['total']          = $se->total($data['text'], 'product');
        $data['stuff']          = [];
        $data['product']        = [];

        $data['url_pattern']    = '/search/?text=%s%s';
        $data['pagination']     = $this->getPagination($data);

        if (1 == $data['page']) {
            foreach($se->search($data['text'], 6, 0, ['brand', 'category', 'shop']) as $row) {
                $data['stuff'][] = [
                    'title'         => $row['obj']['title'],
                    'type'          => $row['obj']['type'],
                    'url'           => $row['obj']['_id']
                ];
            }
        }

        foreach($se->search($data['text'], self::PRODUCTS_PER_PAGE, (($data['page'] - 1) * self::PRODUCTS_PER_PAGE), 'product') as $row)
            if ($product = $this->getProduct($row))
                $data['product'][] = $product;

        $data['found']          = (bool)$data['text'] && ((bool)sizeof($data['stuff']) || (bool)sizeof($data['product']));

        (new tpl())->render($data, 'views/search/results.html', [
            'name'      => 'Поиск по сайту'
        ]);
    }

    protected function autocomplete()
    {
        $text       = isset($_REQUEST['term']) ? trim(filter_var($_REQUEST['term'], FILTER_SANITIZE_STRING)) : false;
        $text       = explode(' ', $text);

        $phrase     = array_pop($text);
        $output     = [];

        foreach ((new searchEngine())->autocomplete($phrase) as $word) {
            $search = $text;
            array_push($search, $word);
            $output[] = implode(' ', $search);
        }

        echo json_encode($output);

        return ;
    }

    protected function searchByArticle($text)
    {
        try {

            $product = (new product)->loadProductObjectFromMongoByArticle($text);

            $data       = [
                'text'          => $text,
                'article'       => true,
                'total'         => 1,
                'found'         => true,
                'stuff'         => [],
                'product'       => [[
                    'url'       => '/+/' . $product->furl . '/',
                    'title'     => $product->name,
                    'img'       => $product->variations[0]['tmb'],
                    'price'     => pricing::i()->getRURPriceForCatalog($product->variations[0]['usPrice'], $product->autoCalculatedDelivery)
                ]]
            ];

            (new tpl())->render($data, 'views/search/results.html');

            return true;

        } catch (productException $error) {
            return false;
        }
    }

    protected function getProduct($row)
    {
        try {
            $product = (new product())->loadProductObjectFromMongoBy_id($row['obj']['id']);

            $variation = null;

            if (!isset($product->variations))
                return false;

            foreach ($product->variations as $v)
                if (isset($v['furl']) && $row['obj']['v'] == $v['furl'])
                    $variation = $v;

            if (is_null($variation))
                return false;

            return [
                'url'       => $row['obj']['_id'],
                'title'     => $row['obj']['title'],
                'img'       => $variation['tmb'],
                'price'     => pricing::i()->getRURPriceForCatalog($variation['usPrice'], $product->autoCalculatedDelivery)
            ];

        } catch (productException $e) {
            return false;
        }
    }

    protected function getPagination($data)
    {
        $pagination = [];

        $totalPages = ceil($data['total'] / self::PRODUCTS_PER_PAGE);

        if ($data['page'] > 1)
            $pagination[] = ['name' => '<span class="glyphicon glyphicon-arrow-left"></i>', 'furl' => sprintf($data['url_pattern'], urlencode($data['text']), '')];

        // to the first page
        if ($data['page'] >= 4) {
            $pagination[] = ['name' => 1, 'furl' => sprintf($data['url_pattern'], urlencode($data['text']), '')];
            if ($data['page'] > 4)
                $pagination[] = ['name' => '&hellip;'];
        }

        // +- 2 pages range
        for ($i = $data['page'] - 2 > 1 ? $data['page'] - 2 : 1; $i <= ($data['page'] + 2 <= $totalPages ? $data['page'] + 2 : $totalPages); $i++)
            if ($i == $data['page'])
                $pagination[] = ['name' => $i, 'furl' => sprintf($data['url_pattern'], urlencode($data['text']), '&page=' . $i), 'current' => true];
            else
                $pagination[] = ['name' => $i, 'furl' => sprintf($data['url_pattern'], urlencode($data['text']), '&page=' . $i)];

        // to the last page
        if ($totalPages - $data['page'] >= 3) {
            if ($totalPages - $data['page'] > 3)
                $pagination[] = ['name' => '&hellip;'];
            $pagination[] = ['name' => $totalPages, 'furl' => sprintf($data['url_pattern'], urlencode($data['text']), '&page=' . $totalPages)];
        }

        if ($data['page'] < $totalPages)
            $pagination[] = ['name' => '<i class="glyphicon glyphicon-arrow-right"></i>', 'furl' => sprintf($data['url_pattern'], urlencode($data['text']), '&page=' . ($data['page'] + 1))];

        return $pagination;
    }
}