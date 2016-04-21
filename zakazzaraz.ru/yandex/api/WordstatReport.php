<?php

namespace zzr\yandex\api;

use zzr\engine\databases\mw;
use zzr\utils\Mailer;
use zzr\yandex\api\WordstatPhrase;
use zzr\yandex\api\YandexApiException;

class WordstatReport
{
    const       REPORT_COLLECTION       = 'wordstat_report_list';

    const       STATUS_REQUEST          = 'request';
    const       STATUS_DONE             = 'done';
    const       STATUS_FAILED           = 'failed';

    protected   $collection             = null;

    protected   $data                   = [];

    public function __construct($number = null)
    {
        $this->collection = mw::i()->zzr->{self::REPORT_COLLECTION};

        if (!is_null($number)) {

            if (!(int)$number)
                throw new YandexApiException('Report number must be integer');

            if (!($this->data = $this->collection->findOne(['report_id' => (int)$number])))
                throw new YandexApiException('Report #' . $number . ' not found in collection');

            $this->id = new \MongoId($this->data['_id']);

        }
    }

    /**
     * Adds information about report into mongo collection
     *
     * @param int $number
     * @param array $phrase_list
     *
     * @return bool or self
     */
    public static function add($number, $phrase_list)
    {
        // add report to mongo collection
        $result = mw::i()->zzr->{self::REPORT_COLLECTION}->insert([
            '_id'           => new \MongoId(),
            'report_id'     => (int)$number,
            'created'       => new \MongoDate(),
            'status'        => self::STATUS_REQUEST,
            'phrase_list'   => array_values($phrase_list),
            'next_try'      => 0,
            'error_list'    => []
        ]);

        return $result['ok'] ? new self((int)$number) : false;
    }

    /**
     * Get full report data
     *
     * @return mixed
     */
    public function data()
    {
        return $this->data;
    }

    /**
     * Magic method for obtaining report data
     *
     * @return mixed
     */
    public function __get($key)
    {
        return isset($this->data[$key]) ? $this->data[$key] : false;
    }

    /**
     * Add error during check report process
     *
     * @throws YandexApiException
     * @return self (for chaining sugar)
     */
    public function addError($error)
    {
        if (!$this->id)
            throw new YandexApiException('Report was not initialized *addError()');

        $result = $this->collection->update(['_id' => $this->id], [
            '$push' => ['error_list' => $error]
        ]);

        if (!(int)$result['n'])
            throw new YandexApiException('Report was not updated in *addError()');

        $this->data['error_list'][] = $error;

        return $this;
    }

    /**
     * Sets time of next action
     *
     * @throws YandexApiException
     * @return self (for chaining sugar)
     */
    public function tryLater($minutes = 5)
    {
        if (!$this->id)
            throw new YandexApiException('Report was not initialized in *tryLater()');

        $result = $this->collection->update(['_id' => $this->id], [
            '$set' => ['next_try' => new \MongoDate(time() + $minutes * 60)]
        ]);

        if (!(int)$result['n'])
            throw new YandexApiException('Report was not updated in *tryLater()');

        return $this;
    }

    /**
     * Update keywords
     *
     * @throws YandexApiException
     * @return bool
     */
    public function updateKeywords($keywords)
    {
        if (!$this->id)
            throw new YandexApiException('Report was not initialized in *updateKeywords()');

        foreach ($keywords as $k => $result) {

            if (!isset($this->data['phrase_list'][$k]))
                continue;

            $update = ['status' => WordstatPhrase::PHRASE_STATUS_DONE];
            if (isset($result['SearchedWith']))
                $update['keywords'] = $result['SearchedWith'];
            if (isset($result['SearchedAlso']))
                $update['also'] = $result['SearchedAlso'];

            WordstatPhrase::update($this->data['phrase_list'][$k], $update);

        }

        return true;
    }

    /**
     * Done project
     *
     * @throws YandexApiException
     * @return bool
     */
    public function done()
    {
        if (!$this->id)
            throw new YandexApiException('Report was not initialized in *done()');

        $this->collection->update(['_id' => $this->id], [
            '$set' => ['status' => self::STATUS_DONE]
        ]);

        return true;
    }

    /**
     * Fail project
     *
     * @throws YandexApiException
     * @return bool
     */
    public function failed()
    {
        if (!$this->id)
            throw new YandexApiException('Report was not initialized in *failed()');

        Mailer::send('vasilkov.net@gmail.com', 'Failed report #' . $this->data['report_id'], implode("\r\n", $this->data['error_list']));

        $this->collection->update(['_id' => $this->id], [
            '$set' => ['status' => self::STATUS_FAILED]
        ]);

        // set all report phrases into start position so they again set in stack
        foreach ($this->data['phrase_list'] as $phrase)
            WordstatPhrase::update($phrase, [
                'status' => WordstatPhrase::PHRASE_STATUS_START
            ]);

        return true;
    }

    /**
     * Find requested project
     *
     * @throws YandexApiException
     * @return WordstatReport
     */
    public static function getRequested()
    {
        $result = mw::i()->zzr->{self::REPORT_COLLECTION}->findOne([
            'status'        => self::STATUS_REQUEST,
            'next_try'      => ['$lt' => new \MongoDate()]
        ], [
            'report_id'     => 1
        ]);

        return $result ? new self((int)$result['report_id']) : false;
    }
}