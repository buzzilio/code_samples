<?php

namespace zzr\yandex\api;

use zzr\engine\databases\mw;
use zzr\search\mistake\mistakeGenerator;

class WordstatPhrase
{
    const   PHRASE_COLLECTION               = 'wordstat_phrase_list';
    const   MISTAKES_COLLECTION             = 'phrase_mistakes_list';

    const   PHRASE_STATUS_START             = 'start';
    const   PHRASE_STATUS_REQUEST           = 'request';
    const   PHRASE_STATUS_DONE              = 'done';

    public static function add($phrase)
    {
        if (self::getOne($phrase))
            return false;

        $result = mw::i()->zzr->{self::PHRASE_COLLECTION}->insert([
            '_id'           => self::mongoKey($phrase),
            'phrase'        => $phrase,
            'status'        => self::PHRASE_STATUS_START,
            'tries'         => 0,
            'report_id'     => 0,
            'keywords'      => [],
            'also'          => [],
            'date_add'      => new \MongoDate()
        ]);

        return (bool)$result['ok'];
    }

    public static function update($phrase, $update)
    {
        return self::updateByKey(self::mongoKey($phrase), $update);
    }

    public static function updateByKey($mongoKey, $update)
    {
        if (isset($update['phrase']))
            unset($update['phrase']);

        if (!sizeof($update))
            return false;

        $result = mw::i()->zzr->{self::PHRASE_COLLECTION}->update(['_id' => $mongoKey], [
            '$set' => $update
        ]);

        return (bool)$result['n'];
    }

    public static function delete($phrase)
    {
        return self::deleteByKey(self::mongoKey($phrase));
    }

    public static function deleteByKey($mongoKey)
    {
        $result = mw::i()->zzr->{self::PHRASE_COLLECTION}->remove(['_id' => $mongoKey]);
        return (bool)$result['n'];
    }

    public static function assignReport($phrase, $report_id)
    {
        $result = mw::i()->zzr->{self::PHRASE_COLLECTION}->update(['_id' => self::mongoKey($phrase)], [
            '$set' => [
                'status'        => self::PHRASE_STATUS_REQUEST,
                'report_id'     => (int)$report_id
            ],
            '$inc' => ['tries' => 1]
        ]);

        return (bool)$result['n'];
    }

    public static function getOne($phrase)
    {
        return mw::i()->zzr->{self::PHRASE_COLLECTION}->findOne(['_id' => self::mongoKey($phrase)]);
    }

    public static function getOneByKey($mongoKey)
    {
        return mw::i()->zzr->{self::PHRASE_COLLECTION}->findOne(['_id' => $mongoKey]);
    }

    public static function getList($status = null, $limit = 10, $skip = 0)
    {
        $criteria = [];

        if (!is_null($status))
            $criteria = ['status' => $status];

        $result = mw::i()->zzr->{self::PHRASE_COLLECTION}->find($criteria);

        $result->sort(['date_add' => -1, 'phrase' => 1]);

        if ((int)$limit)
            $result->limit($limit);

        if ((int)$skip)
            $result->skip($skip);

        if (!$result->count())
            return [];

        $list = [];
        foreach ($result as $phrase)
            $list[] = $phrase;

        return $list;
    }

    public static function makeMistakes($phrase, $transcription = '')
    {
        $transcription = $transcription ?: mistakeGenerator::transcribe($phrase);

        $result = mw::i()->zzr->{self::MISTAKES_COLLECTION}->update(
            ['_id' => self::mongoKey($phrase)],
            [
                '_id'               => self::mongoKey($phrase),
                'phrase'            => $phrase,
                'transcription'     => $transcription,
                'mistakes'          => array_merge(
                    mistakeGenerator::generate($phrase),
                    $transcription ? [$transcription] : [],
                    $transcription ? mistakeGenerator::generate($transcription) : []
                )
            ],
            ['upsert' => true]
        );

        return isset($result['ok']) && (bool)$result['ok'];
    }

    public static function getMistakes($mongoKey)
    {
        return mw::i()->zzr->{self::MISTAKES_COLLECTION}->findOne(['_id' => $mongoKey]);
    }

    public static function updateMistakes($mongoKey, $update)
    {
        $result = mw::i()->zzr->{self::MISTAKES_COLLECTION}->update(
            ['_id' => $mongoKey],
            ['$set' => $update]
        );

        return isset($result['ok']) && (bool)$result['ok'];
    }

    public static function mongoKey($phrase)
    {
        if (!is_string($phrase))
            throw new YandexApiException('Phrase must be string');

        return md5(mb_strtolower($phrase, 'UTF8'));
    }
}