<?php

namespace Simplenight\Core\Provider\Foursquare;

class FoursquareKey
{
    /**
     * @var string
     */
    protected $hash = '';

    /**
     * @var string
     */
    protected $client_id = '';

    /**
     * @var string
     */
    protected $client_secret = '';

    public function __construct($client_id, $client_secret)
    {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;

        $this->hash = md5($this->client_id . $this->client_secret);
    }

    public function getClientId()
    {
        return $this->client_id;
    }

    public function getClientSecret()
    {
        return $this->client_secret;
    }

    public function getProcessHash()
    {
        return \Cache::get('foursquare_keys.' . $this->hash . '.process_hash');
    }

    public function getLastUsed()
    {
        return \Cache::get('foursquare_keys.' . $this->hash . '.last_use', 0);
    }

    public function setUsedBy($process_hash)
    {
        \Cache::forever('foursquare_keys.' . $this->hash . '.process_hash', $process_hash);
        \Cache::forever('foursquare_keys.' . $this->hash . '.last_use', time());
    }

    public function isValid($process_hash)
    {
        return $this->getProcessHash() == $process_hash;
    }

    public function toArray()
    {
        return [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret
        ];
    }

}