<?php
/**
 * Wordstat Yandex api class
 * User: Alexey Vasilkov
 * Date: 01.07.13
 * Time: 16:42
 */

namespace zzr\yandex\api;

use zzr\engine\databases\mw;
use zzr\yandex\api\Request as YandexApiRequest;
use zzr\yandex\api\WordstatReport;
use zzr\yandex\api\WordstatPhrase;
use zzr\yandex\api\YandexApiException;

class Wordstat extends YandexApiRequest
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Send task to generate Yandex wordstat report for array of phrases
     * Returns Yandex internal id for new report
     *
     * @throws YandexApiException
     *
     * @return WordstatReport
     */
    public function CreateReport($phrase_list)
    {
        if (!sizeof($phrase_list))
            throw new YandexApiException('Phrase list is empty');

        if (false === ($result = $this->request('CreateNewWordstatReport', ['Phrases' => array_values($phrase_list)])))
            throw new YandexApiException('Report creation failed, connection error');

        if (!isset($result['data']) || !is_numeric($result['data'])) {
            $this->request_error = isset($result['error_str']) ? $result['error_str'] : '';
            throw new YandexApiException('Report creation failed, yandex error' .
                (isset($result['error_str']) ? "\n" . $result['error_str'] : ''));
        }

        return WordstatReport::add((int)$result['data'], $phrase_list);
    }

    /**
     * Check readyness of report and get it by its internal number
     *
     * @param int $number
     *
     * @return bool
     */
    public function GetReport($number)
    {
        if (false === ($result = $this->request('GetWordstatReport', (int)$number)))
            throw new YandexApiException('Unable get report #' . (int)$number . ', connection error');

        if (!isset($result['data'])) {
            $this->request_error = isset($result['error_str']) ? $result['error_str'] : '';
            throw new YandexApiException('Unable get report #' . (int)$number . ', yandex error');
        }

        // update keywords
        try {
            $report = new WordstatReport($number);
            $report->updateKeywords($result['data']);
            $report->done();
        }catch (\Exception $e) {
            throw new YandexApiException($e->getMessage());
        }

        return true;
    }

    /**
     * Delete report by its internal number
     *
     * @param int $number
     *
     * @return bool
     */
    public function DeleteReport($number)
    {
        if (false === ($result = $this->request('DeleteWordstatReport', (int)$number)))
            throw new YandexApiException('Unable delete report #' . (int)$number . ', connection error');

        if (!isset($result['data']) || 1 != $result['data']) {
            $this->request_error = isset($result['error_str']) ? $result['error_str'] : '';
            throw new YandexApiException('Unable delete report #' . (int)$number . ', yandex error');
        }

        return true;
    }
}