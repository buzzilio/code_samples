<?php

namespace Simplenight\Booking\Utils;

use Simplenight\Search;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Simplenight\Booking\Filters\FiltersManager;

class SearchManager
{
    /**
     * @var \Simplenight\Booking\Filters\FiltersManager
     */
    protected $filters = null;

    /**
     * @var PaginationManager
     */
    protected $pagination = null;

    /**
     * @var SortManager
     */
    protected $sort = null;

    /**
     * @var RefineSearchManager
     */
    protected $refine = null;

    /**
     * @var string
     */
    protected $view = 'grid';

    /**
     * @var Search\Search
     */
    protected $data = [];

    /**
     * @var Search
     */
    protected $search;

    public function __construct()
    {
        $this->filters      = new FiltersManager();
        $this->refine       = new RefineSearchManager();
        $this->sort         = new SortManager();
        $this->pagination   = new PaginationManager();
    }

    /**
     *
     * @param mixed $filter
     * @return \Simplenight\Booking\Filters\FiltersManager | \Simplenight\Booking\Filters\AbstractFilter | \Simplenight\Booking\Filters\AbstractFilter[]
     */
    public function filters($filter = null)
    {
        if (!is_null($filter)) {
            return $this->filters->get($filter);
        }

        return $this->filters;
    }

    /**
     * @return RefineSearchManager
     */
    public function refine()
    {
        return $this->refine;
    }

    /**
     * @return PaginationManager
     */
    public function pagination()
    {
        return $this->pagination;
    }

    /**
     * @return SortManager
     */
    public function sort()
    {
        return $this->sort;
    }

    /**
     * @return string
     */
    public function view()
    {
        return $this->view;
    }

    /**
     * Set input
     *
     * @param string|array $input
     */
    public function input($input)
    {
        if (is_string($input)) {
            $input = self::deserialize($input);
        }

        // View
        if ('list' === array_get($input, 'view') || 'list' === array_get($input, 'view.0')) {
            $this->view = 'list';
            $this->pagination->perPage(PaginationManager::LIST_VIEW_LIMIT);
        }

        // Update filters values
        $this->filters->set(array_get($input, 'filters', []));

        // Update sort value
        $value = array_get($input, 'sort', '');
        $this->sort->set(is_array($value) ? array_get($value, 0, '') : $value);

        // Update refine value
        $value = array_get($input, 'refine', '');
        $this->refine->set(is_array($value) ? array_get($value, 0, '') : $value);
    }

    /**
     * Request API experience
     *
     * @return bool
     * @throws \Exception
     */
    public function request()
    {
        $params = array_merge(
            ['currency' => \Config::get('currency')],
            $this->filters->getApiParams(),
            $this->pagination->getApiParams(),
            $this->sort->getApiParams(),
            $this->refine->getApiParams()
        );

        $this->search = Search::get(\SearchContext::id(), $params);

        $this->data = $this->search->getData();

        \SearchContext::update($this->search);

        $this->filters->setTotal($this->search);
        $this->pagination->setTotal(array_get($this->search->getPagination(), 'total', 0));

        return true;
    }

    public function products()
    {
        return $this->data->results;
    }

    /**
     * Count product list hash
     *
     * @return string
     */
    public function hash()
    {
        $string = $this->view;

        foreach ($this->data->results as $product) {
            $string .= implode('', array_only($product, ['uuid', 'name', 'thumbnail', 'price_starting_at']));
        }

        return md5($string);
    }

    /**
     * Get total found items
     *
     * @return int
     */
    public function total()
    {
        return (int) array_get($this->search->getPagination(), 'total', 0);
    }

    /**
     * Get search progress
     *
     * @return array
     */
    public function progress()
    {
        return array_map(function ($item) {
            $item['type'] = strtolower(array_get($item, 'type'));
            return $item;
        }, $this->data->progress);
    }

    /**
     * Deserialize filters string
     *
     * @param $string
     * @return array
     */
    public static function deserialize($string)
    {
        $params = [];

        foreach (explode(';', $string) as $param) {
            $param = explode(':', $param);
            $key = array_shift($param);
            $params[$key] = explode(',', implode(':', $param));
        }

        // Emulate input
        $params['filters'] = $params;

        return $params;
    }

    /**
     * Is type or overall search ready
     *
     * @param null|string $type
     * @return bool
     */
    public function ready($type = null)
    {
        if ('all' === $type) {
            return true;
        }

        if ($type) {
            $type = explode(',', $type);
            $progress = 0;
            foreach ($this->data->progress as $status) {
                if (in_array(strtolower($status['type']), $type)) {
                    $progress += (int) array_get($status, 'percentage', 0);
                }
            }
            return $progress >= count($type) * 100;
        }

        foreach ($this->data->progress as $status) {
            if (array_get($status, 'percentage', 0) < 100) {
                return false;
            }
        }

        return true;
    }

//    /**
//     * You can get not ready types or check type by name
//     * @param null $type
//     * @return array|bool
//     */
//    public function notReadyTypes($type = null){
//        $types = [];
//        foreach ($this->data->progress as $status){
//            if ($status['percentage']!=100){
//                $types[] = $status['type'];
//            }
//        }
//        if ($type!==null){
//            $type = strtoupper($type);
//            return in_array($type, $types);
//        }
//        return $types;
//    }
//
//    public function shouldStopSearch(){
//        $total = $this->aggregationTotal();
//        return count($this->notReadyTypes()) === 1 && $this->notReadyTypes('transportation') && $total === 0;
//    }
//
//    /**
//     * @return int
//     */
//    public function aggregationTotal()
//    {
//        $aggregations = array_get($this->data, 'aggregation', []);
//        $total = 0;
//        foreach ($aggregations as $agg) {
//            $total += $agg->total;
//        }
//        return $total;
//    }
}