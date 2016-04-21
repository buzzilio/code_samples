<?php
/**
 * Wordstat cron tasks
 * User: Alexey Vasilkov
 * Date: 10.07.13
 */

namespace zzr\yandex\api;

use zzr\yandex\api\Wordstat;
use zzr\yandex\api\WordstatLogger;
use zzr\yandex\api\WordstatPhrase;
use zzr\yandex\api\WordstatReport;
use zzr\yandex\api\YandexApiException;

class WordstatCron
{
    /*
     * Find unrequested phrases and create report with them
     *
     * return bool
     */
    public static function CreateReport()
    {
        try {

            // find unrequested phrases
            // 10 - maximum size of phrase list that Yandex accepts in one report
            $phrase_list = WordstatPhrase::getList(WordstatPhrase::PHRASE_STATUS_START, 10);

            if (!sizeof($phrase_list))
                return false;

            $only_phrases = [];
            foreach ($phrase_list as $phrase)
                $only_phrases[] = $phrase['phrase'];

            if (!($report = (new Wordstat)->CreateReport($only_phrases)))
                return false;

            // set phrases status and link to report
            foreach ($only_phrases as $phrase)
                WordstatPhrase::assignReport($phrase, $report->report_id);

            // wait 3 minutes till request results
            $report->tryLater(3);

            return true;

        } catch (YandexApiException $e) {
            WordstatLogger::i()->log($e->getMessage());
        }

        return false;
    }

    /*
     * Get results of requested report
     *
     * return bool
     */
    public static function GetReport()
    {
        if (!($report = WordstatReport::getRequested()))
            return false;

        try {
            (new Wordstat)->GetReport($report->report_id);
            // delete report
            try {
                (new Wordstat)->DeleteReport($report->report_id);
            } catch (YandexApiException $e) {
                WordstatLogger::i()->log($e->getMessage());
            }
            return true;
        } catch (YandexApiException $e) {
            WordstatLogger::i()->log($e->getMessage());
            $report->addError($e->getMessage());
            // if error count exceeded 3 - fail report
            if (sizeof($report->error_list) > 3) {
                $report->failed();
            }else {
                $report->tryLater(5);
            }
        }

        return false;
    }
}