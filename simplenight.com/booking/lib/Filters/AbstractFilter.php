<?php

namespace Simplenight\Booking\Filters;

use \Simplenight\Search\SearchAggregation;

abstract class AbstractFilter
{
    /**
     * @var FiltersManager
     */
    protected $manager = null;

    /**
     * Filters list of options
     * @var array
     */
    protected $options = [];

    /**
     * Current values of all filters
     * @var array
     */
    protected $values = [];

    /**
     * Values of products for each filter option
     * @var array
     */
    protected $total = [];

    public function __construct(FiltersManager $manager)
    {
        $this->manager = $manager;
        $this->setOptions();
    }

    abstract protected function setOptions();

    /**
     * Get string representation of filter name
     * CamelCasedFilter class name transforms into string-dashed-name
     *
     * @return string
     */
    public function getName()
    {
        $class_name = explode('\\', get_called_class());

        $filter_name = preg_replace('/filter$/i', '', array_pop($class_name));
        $filter_name = preg_replace_callback('/([A-Z])/', function ($match) {
            return '-' . strtolower($match[1]);
        }, $filter_name);

        return preg_replace('/^-/', '', $filter_name);
    }

    public function getTemplate()
    {
        return $this->getName();
    }

    /**
     * Get filters options
     *
     * @return array
     */
    public function options()
    {
        return $this->options;
    }

    /**
     * Set filter values
     *
     * @param array $values
     */
    public function set($values)
    {
        $this->values = [];

        foreach ($values as $value) {
            if (isset($this->options[$value])) {
                $this->values[] = $value;
            }
        }
    }

    /**
     * Get filter value for $key
     *
     * @param $key
     * @return string
     */
    public function value($key)
    {
        return array_get($this->options, $key, '');
    }

    /**
     * Get applied values of filter
     *
     * @return array
     */
    public function values()
    {
        return $this->values;
    }

    /**
     * Is value selected?
     *
     * @param string $value
     * @return bool
     */
    public function selected($value)
    {
        return in_array($value, $this->values);
    }

    /**
     * @param SearchAggregation[] $aggregations
     * @return void
     */
    abstract public function setTotal($aggregations);

    public function getTotal()
    {
        return $this->total;
    }

    /**
     * Get experience count related to filter's $key
     *
     * @param string $key
     * @return int
     */
    public function total($key)
    {
        return (int)array_get($this->total, $key, 0);
    }

    /**
     * Is filter can be applied?
     *
     * @return bool
     */
    public function applied()
    {
        return true;
    }

    /**
     * Get filter params for API request
     *
     * @return array
     */
    public function getApiParams()
    {
        $values = $this->values();

        return $values ? [$this->getName() => implode(',', $values)] : [];
    }

    public function aggregation()
    {
        $values = [];

        foreach ($this->options as $key => $value) {
            $values[] = [
                'key' => $key,
                'value' => $value,
                'total' => $this->total($key)
            ];
        }

        return $values;
    }

    /**
     * @param array $enum Enum::toArray()
     * @param string $prefix translate prefix
     * @return array
     */
    protected function fromEnum(array $enum, $prefix)
    {
        $data = [];

        foreach($enum as $val) {
            $data[$val] = trans($prefix . '.' . strtolower($val));
        }

        setlocale(LC_COLLATE, \App::getLocale());
        uasort($data, 'strcoll');

        if (isset($data['OTHER'])) {
            $tr = $data['OTHER'];
            unset($data['OTHER']);
            $data['OTHER'] = $tr;
        }

        return $data;
    }

}