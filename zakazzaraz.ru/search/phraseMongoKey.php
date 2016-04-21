<?php

/**
* User: Alexey Vasilkov (vasilkov.net@gmail.com)
* Date: 06.09.13
*/

namespace zzr\search;

class phraseMongoKey
{
    /**
     * @var string low case phrase md5 hash
     */
    protected       $key                        = null;

    /**
     * Constructor
     *
     * @param string $key
     */
    public function __construct($key)
    {
        $this->key = $key;
    }

    /**
     * Creates key instance from phrase
     *
     * @param string $phrase
     *
     * @return phraseMongoKey
     */
    public static function fromPhrase($phrase)
    {
        return new self(md5(mb_strtolower($phrase, 'UTF-8')));
    }

    /**
     * Magic method returns string representation of class instance
     *
     * @return string
     */
    public function __toString()
    {
        return $this->key;
    }
}