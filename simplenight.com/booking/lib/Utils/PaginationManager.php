<?php

namespace Simplenight\Booking\Utils;

class PaginationManager
{
    const GRID_VIEW_LIMIT = 36;
    const LIST_VIEW_LIMIT = 20;

    /**
     * @var int
     */
    protected $per_page = self::GRID_VIEW_LIMIT;

    /**
     * @var int
     */
    protected $page = 1;

    /**
     * @var int
     */
    protected $total = 0;

    public function __construct()
    {
        $this->page = (int) \Input::get('page', 1);
    }

    /**
     * Set items number per page
     *
     * @param int $per_page
     */
    public function perPage($per_page)
    {
        $this->per_page = (int)$per_page;
    }

    /**
     * Get current page
     *
     * @return int
     */
    public function page()
    {
        return $this->page;
    }

    /**
     * Get total items found
     *
     * @return int
     */
    public function total()
    {
        return $this->total;
    }

    /**
     * Set total items found
     *
     * @param $total
     */
    public function setTotal($total)
    {
        $this->total = (int)$total;
    }

    /**
     * Get total pages number
     *
     * @return int
     */
    public function totalPages()
    {
        return (int)ceil($this->total / $this->per_page);
    }

    /**
     * Get API request params
     *
     * @return array
     */
    public function getApiParams()
    {
        return [
            'per_page' => $this->per_page,
            'page' => $this->page
        ];
    }
}