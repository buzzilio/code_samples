<?php

namespace Simplenight\Booking\Utils;

class SortManager
{
    const DEFAULT_SORT = '-unset';

    /**
     * @var string
     */
    protected $value = null;

    protected $options = [
        '-relevance',
//        'distance',
//        '-distance',
        'name',
        '-name',
        'starting_at',
        '-starting_at'
    ];

    public function __construct()
    {
        $this->value = self::DEFAULT_SORT;
    }

    /**
     * Set sort value
     *
     * @param string $value
     */
    public function set($value)
    {
        $this->value = $value && in_array($value, $this->options) ? $value : self::DEFAULT_SORT;
    }

    /**
     * Get sort field
     *
     * @return string
     */
    public function value()
    {
        return $this->value;
    }

    /**
     * Get sort options
     *
     * @return array
     */
    public function options()
    {
        return $this->options;
    }

    /**
     * Get default sort value
     *
     * @return string
     */
    public function getDefault()
    {
        return self::DEFAULT_SORT;
    }

    /**
     * Get filter params for API request
     *
     * @return array
     */
    public function getApiParams()
    {
        return ['sort' => $this->value];
    }
}