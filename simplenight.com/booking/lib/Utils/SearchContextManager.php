<?php

namespace Simplenight\Booking\Utils;

use \Simplenight\Search\SearchRequest;

class SearchContextManager {

    /**
     * Context id
     */
    protected $search_id = null;

    /**
     * Context array
     */
    protected $context = [];

    protected $params = [];

    protected $pagination = [];

    /**
     * Init search context
     *
     * @param string $search_id
     */
    public function init($search_id)
    {
        $this->search_id    = $search_id;
        $this->context      = $this->get('context', []);
        $this->pagination   = $this->get('pagination', []);
        $this->params       = $this->get('params', []);
    }

    /**
     * Is context exists?
     *
     * @return bool
     */
    public function exists()
    {
        return $this->search_id && !empty($this->context);
    }

    /**
     * Get search id
     *
     * @return string
     */
    public function id()
    {
        return $this->search_id;
    }

    /**
     * Get whole search context
     *
     * @return \Simplenight\Search\SearchRequest
     */
    public function context()
    {
        return new SearchRequest($this->context);
    }

    /**
     * Get "From" date
     *
     * @return \Carbon\Carbon|null
     */
    public function from()
    {
        $from = array_get($this->context, 'date_from');

        return $from ? new \Carbon\Carbon($from) : null;
    }

    /**
     * Get "To" date
     *
     * @return \Carbon\Carbon|null
     */
    public function to()
    {
        $to = array_get($this->context, 'date_to');

        return $to ? new \Carbon\Carbon($to) : null;
    }

    /**
     * Get location
     *
     * @return string
     */
    public function location()
    {
        return array_get($this->context, 'location.address');
    }

    /**
     * Get vehicle search context
     *
     * @param $key
     * @param null $default
     * @return mixed
     */
    public function vehicle($key, $default = null)
    {
        return array_get($this->context, 'vehicle.' . $key, $default);
    }

    /**
     * Get $key value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return \Session::get('search.' . $this->search_id . '.' . $key, $default);
    }

    /**
     * Put $value in storage under $key
     *
     * @param string $key
     * @param mixed $value
     */
    public function put($key, $value)
    {
        \Session::put('search.' . $this->search_id . '.' . $key, $value);
    }

    /**
     * Create new search context
     *
     * @param string $search_id
     * @param \Simplenight\Search $search
     * @return self
     */
    public function create($search_id, \Simplenight\Search $search)
    {
        $this->search_id    = $search_id;
        $this->context      = $search->getData()->toArray();
        $this->params       = $search->getParams();
        $this->pagination   = $search->getPagination();

        \Session::put('search.' . $search_id, [
            'context'       => $this->context,
            'pagination'    => $this->pagination,
            'params'        => $this->params,
            'filters'       => [],
            'expanded'      => [],
            'sort'          => [],
        ]);
    }

    /**
     * @param \Simplenight\Search $search
     */
    public function update(\Simplenight\Search $search)
    {
        $this->context      = $search->getData()->toArray();
        $this->params       = $search->getParams();
        $this->pagination   = $search->getPagination();

        $this->put('context', $this->context);
        $this->put('pagination', $this->pagination);
        $this->put('params', $this->params);
    }

    /**
     * Delete search context
     */
    public function delete()
    {
        \Session::forget('search.' . $this->search_id);

        $this->search_id    = null;
        $this->context      = [];
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @return array
     */
    public function getPagination()
    {
        return $this->pagination;
    }

    public function hasTravelData()
    {
        return isset($this->context['travel_data'])
            && isset($this->context['travel_data']['pick_up'])
            && isset($this->context['travel_data']['pick_up']['location'])
            && isset($this->context['travel_data']['pick_up']['date'])
            && isset($this->context['travel_data']['pick_up']['time'])
            && isset($this->context['travel_data']['drop_off'])
            && isset($this->context['travel_data']['drop_off']['location'])
            ;
    }

    public function getTravelData()
    {
        return array_get($this->context, 'travel_data', []);
    }

    public function getPickUp()
    {
        return array_get($this->context, 'travel_data.pick_up', []);
    }

    public function getPickUpAddress()
    {
        return $this->formattedAddress(array_get($this->getPickUp(), 'location', []));
    }

    public function getDropOff()
    {
        return array_get($this->context, 'travel_data.drop_off', []);
    }

    public function getDropOffAddress()
    {
        return $this->formattedAddress(array_get($this->getDropOff(), 'location', []));
    }

    /**
     * Get distance in miles for travel data points
     * @return float kilometers
     */
    public function getDistance()
    {
        $context    = $this->context();

        $from       = $context->travel_data->pick_up->location;
        $to         = $context->travel_data->drop_off->location;

        return \App\Models\Product\Location::getDistance($from->latitude, $from->longitude, $to->latitude, $to->longitude);
    }

    /**
     * Duration for travel data points
     * @return string
     */
    public function getDuration()
    {
        $time = round($this->getDistance() / 40 * 60);
        return \Carbon\Carbon::now()->diffForHumans(\Carbon\Carbon::now()->subMinutes($time), true);
    }

    /**
     * @param array $location
     * @return array
     */
    public function formattedAddress($location)
    {
        if (is_array($location)) {
            $location = new \App\Models\Product\Location($location);
        }
        return implode(', ', [(string)$location->street_name, (string)$location->city, (string)$location->country]);
    }

    public function getTypeProgress($type = "")
    {
        $type = explode(',', $type);
        $aggregator = 0;

        foreach ($this->context['progress'] as $progress) {
            if (in_array(strtolower($progress['type']), $type)) {
                $aggregator += $progress['percentage'];
            }
        }

        return count($type) ? $aggregator / count($type) : 0;
    }

    public function getTypeTotal($type = "")
    {
        $type = explode(',', $type);
        $aggregator = 0;

        foreach ($this->context['aggregation'] as $aggregation) {
            if (in_array(strtolower($aggregation['type']['code']), $type)) {
                $aggregator += $aggregation['total'];
            }
        }

        return $aggregator;
    }
}