<?php

/**
 * User: Alexey Vasilkov (vasilkov.net@gmail.com)
 * Date: 12.09.13
 */

namespace zzr\search\mistake;

use zzr\engine\databases\mw;
use zzr\search\phraseMongoKey;

class mistakeModel
{
    /**
     * @var mw instance
     */
    protected       $mw                         = null;

    /**
     * @const name of search index collection
     */
    const           COLLECTION                  = 'search_mistakes';

    /**
     * @var \MongoCollection search index collection
     */
    protected       $collection                 = null;

    public function __construct()
    {
        $this->mw                   = mw::i();
        $this->collection           = $this->mw->zzr->{self::COLLECTION};
    }

    /**
     * Add phrase to mistakes collection
     *
     * @param string $phrase
     *
     * @return bool
     */
    public function add($phrase)
    {
        $mongoKey = phraseMongoKey::fromPhrase($phrase);

        if ($this->find($mongoKey))
            return false;

        $result = $this->collection->insert([
            '_id'               => (string)phraseMongoKey::fromPhrase($phrase),
            'phrase'            => $phrase,
            'transcription'     => '',
            'mistakes'          => []
        ]);

        return isset($result['ok']) && (bool)$result['ok'];
    }

    /**
     * Find phrase by key
     *
     * @param phraseMongoKey $key
     *
     * @return array
     */
    public function find(phraseMongoKey $key)
    {
        return $this->collection->findOne([
            '_id' => (string)$key
        ]);
    }

    /**
     * Update transcription of phrase by its key
     *
     * @param phraseMongoKey $key
     * @param string $transcription
     *
     * @return bool
     */
    public function updateTranscription(phraseMongoKey $key, $transcription)
    {
        $result = $this->collection->update([
            '_id'   => (string)$key
        ], [
            '$set'  => ['transcription' => $transcription]
        ]);

        return isset($result['n']) && (bool)$result['n'];
    }

    /**
     * Adds a list of mistakes to phrase with key $key
     *
     * @param phraseMongoKey $key
     * @param array $mistakes
     *
     * @return bool
     */
    public function addMistakes(phraseMongoKey $key, $mistakes = [])
    {
        $result = $this->collection->update([
            '_id'   => (string)$key
        ], [
            '$push' => ['mistakes' => ['$each' => $mistakes]]
        ]);

        return isset($result['ok']) && (bool)$result['ok'];
    }

    /**
     * Updates whole list of mistakes of phrase with key $key
     *
     * @param phraseMongoKey $key
     * @param array $mistakes
     *
     * @return bool
     */
    public function updateMistakes(phraseMongoKey $key, $mistakes = [])
    {
        $result = $this->collection->update([
            '_id'   => (string)$key
        ], [
            '$set'  => ['mistakes' => $mistakes]
        ]);

        return isset($result['ok']) && (bool)$result['ok'];
    }

    /**
     * Generate list of mistakes of phrase with key $key
     *
     * @param phraseMongoKey $key
     *
     * @return bool
     */
    public function generate($phrase, $transcription = '')
    {
        $mongoKey = phraseMongoKey::fromPhrase($phrase);

        if (!($mistake = $this->find($mongoKey)))
            $this->add($phrase);

        if (!isset($mistake['mistakes']) || !$mistake['mistakes']) {

            $transcription = $transcription ?: mistakeGenerator::transcribe($phrase);
            return $this->updateTranscription($mongoKey, $transcription) && $this->updateMistakes($mongoKey, array_merge(
                mistakeGenerator::generate($phrase),
                $transcription ? [$transcription] : [],
                $transcription ? mistakeGenerator::generate($transcription) : []
            ));

        }


        return false;
    }

    /**
     * Filters $phrase replacing all mistakes found inside of it
     *
     * @param $phrase
     *
     * @return string
     */
    public function filter($phrase)
    {
        $word_list      = array_map('trim', explode(' ', $phrase));
        foreach ($word_list as &$word)
            $word = mb_strtolower($word, 'UTF-8');

        $word_count     = sizeof($word_list);

        $variations = [];
        for ($i = 1; $i <= $word_count; $i++)
            for ($j = 0; $j < $word_count; $j++)
                if ($i + $j <= $word_count)
                    $variations[] = implode(' ', array_slice($word_list, $j, $i));

        $result = $this->collection->aggregate([
            ['$project' => ['_id' => false, 'phrase' => true, 'mistakes' => true]],
            ['$unwind' => '$mistakes'],
            ['$project' => ['phrase' => true, 'mistake' => ['$toLower' => '$mistakes']]],
            ['$match' => ['mistake' => ['$in' => $variations]]]
        ]);

        if (!isset($result['result']))
            return $phrase;

        foreach ($result['result'] as $mistake)
            $phrase = preg_replace('/' . $mistake['mistake'] . '/iu', $mistake['phrase'], $phrase);

        return $phrase;
    }

    /**
     * Delete mistake
     *
     * @param phraseMongoKey $key
     *
     * @return bool
     */
    public function delete(phraseMongoKey $key)
    {
        $result = $this->collection->remove([
            '_id'   => (string)$key
        ]);

        return isset($result['n']) && (bool)$result['n'];
    }
}