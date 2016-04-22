<?php

namespace Simplenight\Booking\Utils;

class RefineSearchManager {

    /**
     * @var string
     */
    protected $value = '';

    public function __construct()
    {
        $this->value = \Input::get('refine', '');
    }

    /**
     * Set refine value
     *
     * @param string $value
     */
    public function set($value)
    {
        $this->value = trim($value);
    }

    /**
     * Get refine search value
     *
     * @return string
     */
    public function value()
    {
        return $this->value;
    }

    /**
     * Get filter params for API request
     *
     * @return array
     */
    public function getApiParams()
    {
        return $this->value
            ? ['search' => $this->value]
            : [];
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->value;
    }
}