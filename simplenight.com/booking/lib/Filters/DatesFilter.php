<?php

namespace Simplenight\Booking\Filters;

class DatesFilter extends AbstractFilter
{
    protected function setOptions()
    {
        if (!\SearchContext::exists()) {
            return false;
        }

        for ($date = \SearchContext::from(); $date <= \SearchContext::to(); $date->addDay()) {
            $this->options[\AppLocale::internalDate($date)] = clone $date;
        }
    }

    public function value($key)
    {
        if (isset($this->options[$key])) {
            return \AppLocale::weekdayDate($this->options[$key]);
        }

        return '';
    }

    public function applied()
    {
        return !$this->manager->type()->selected('transportation');
    }

    public function aggregation()
    {
        $values = [];

        foreach ($this->options as $key => $value) {
            $values[] = [
                'key' => $key,
                'value' => \AppLocale::weekdayDate($value),
                'details' => [
                    'month' => $value->format('M'),
                    'day' => $value->format('d')
                ],
                'total' => $this->total($key)
            ];
        }

        return $values;
    }

    public function setTotal($aggregations)
    {
        $type_filtered = array_filter(explode(',', $this->manager->type()->filtered()));

        foreach ($aggregations as $aggregation) {

            if ($type_filtered && !in_array(strtolower($aggregation->type->code), $type_filtered)) {
                continue;
            }

            /** @var \Simplenight\Search\Aggregation\Date $item */
            foreach (array_get($aggregation, 'dates',[]) as $item) {
                $this->total[$item->date] = array_get($this->total, $item->date, 0) + $item->count;
            }
        }
    }
}
