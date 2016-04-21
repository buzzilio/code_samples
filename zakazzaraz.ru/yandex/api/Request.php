<?php
/**
 * Sends raw json post request
 * User: Alexey Vasilkov
 * Date: 01.07.13
 * Time: 15:08
 */

namespace zzr\yandex\api;

use zzr\config\C;
use zzr\config\UndefinedConfigVariable;
use zzr\yandex\api\YandexApiException;

class Request
{
    protected   $config                     = null;

    protected   $request_error              = '';

    public function __construct()
    {
        try {
            $this->config = C::i()->wordstat;
        }catch (UndefinedConfigVariable $e) {
            throw new YandexApiException('Wordstat part in config not found');
        }
    }

    // Makes raw json post request up to Yandex api
    public function request($method, $params)
    {
        $post = json_encode([
            'login'             => $this->config['login'],
            'application_id'    => $this->config['application_id'],
            'token'             => $this->config['token'],
            'method'            => $method,
            'param'             => $params
        ]);

        $request = curl_init($this->config['api_url']);

        curl_setopt($request, CURLOPT_HEADER,           false);
        curl_setopt($request, CURLOPT_SSL_VERIFYPEER,   false);
        curl_setopt($request, CURLOPT_RETURNTRANSFER,   true);
        curl_setopt($request, CURLOPT_HTTPHEADER,       ["Content-type: application/json"]);
        curl_setopt($request, CURLOPT_POST,             true);
        curl_setopt($request, CURLOPT_POSTFIELDS,       $post);

        if (false === ($json_response = curl_exec($request))) {
            $this->request_error = curl_error($request);
            return false;
        }

        curl_close($request);

        return json_decode($json_response, true);
    }
}