<?php
/**
 * User: Alexey Vasilkov
 * Date: 12.07.13
 * Time: 14:15
 */

namespace zzr\yandex;

use zzr\config\C;
use zzr\engine\tpl;
use zzr\engine\databases\mw;
use zzr\yandex\api\WordstatReport;
use zzr\yandex\api\WordstatPhrase;
use zzr\info\brand\brandModel;
use zzr\catalog\catalogModel;
use zzr\search\phraseMongoKey;
use zzr\search\mistake\mistakeModel;
use zzr\utils\dummyUriProcessor;

class YandexController
{
    const       PAGE_LIMIT              = 50;

    public function displayWordstatListActionProtected()
    {
        $collection = mw::i()->zzr->{WordstatPhrase::PHRASE_COLLECTION};
        $page       = isset($_REQUEST['page']) ? (int)$_REQUEST['page'] : 0;

        $data = [
            'pagination'    => [],
            'list'          => [],
            'reload'        => '/router/?route=yandex/displayWordstatList&page=' . $page
        ];

        for ($i = 0; $i <= floor($collection->count() / self::PAGE_LIMIT); $i++)
            $data['pagination'][] = [
                'number'    => $i + 1,
                'link'      => '/router/?route=yandex/displayWordstatList&page=' . $i,
                'current'   => $page == $i
            ];

        $result = $collection->find()->sort(['date' => -1])->skip($page * self::PAGE_LIMIT)->limit(self::PAGE_LIMIT);
        foreach ($result as $row) {
            $row['date'] = $row['date']->sec;
            $data['list'][] = $row;
        }

        echo (new tpl)->include('views/admin/modules/wordstat/list.html', $data);
    }

    public function displayWordstatPhraseActionProtected()
    {
        if (!isset($_REQUEST['id'])) {
            echo 'Error: not found [id] parameter';
            return ;
        }

        if (!($phrase = WordstatPhrase::getOneByKey($_REQUEST['id']))) {
            echo 'Error: not found phrase by id';
            return ;
        }

        usort($phrase['also'], [$this, 'sortByShows']);

        // get current assigned report
        if ((int)$phrase['report_id'] && ($report = new WordstatReport((int)$phrase['report_id']))) {
            $phrase['report']               = $report->data();
            $phrase['report']['created']    = $phrase['report']['created']->sec;
            $phrase['report']['next_try']   = $phrase['report']['next_try']->sec;
            $phrase['report']['error_list'] = array_map(function ($message) {
                return ['message' => $message];
            }, $phrase['report']['error_list']);
        }

        // get mistakes
        $phrase['mistake'] = (new mistakeModel())->find(new phraseMongoKey($_REQUEST['id']));
        $phrase['mistake']['mistakes'] = !empty($phrase['mistake']['mistakes']) ? implode("\n", $phrase['mistake']['mistakes']) : '';

        echo (new tpl)->include('views/admin/modules/wordstat/phrase.html', $phrase);
    }

    public function displayWordstatPhraseAddFormActionProtected()
    {
        echo (new tpl)->include('views/admin/modules/wordstat/add_form.html');
    }

    public function fillWordstatAddFormActionProtected()
    {
        if (empty($_REQUEST['type'])) {
            echo "Not found fill type";
            return ;
        }

        switch ($_REQUEST['type']) {
            case 'brands':
                echo implode("\n", (new brandModel())->getActiveBrandList());
                break;
            case 'categories':
                $up                 = new dummyUriProcessor();
                $category_list      = (new catalogModel())->aggregate([['category' => true]]);
                foreach ($category_list as $category) {
                    echo $up->getName($category['_id']['catCategory']) . "\n";
                }
                break;
            case 'facets':
                $facet_list         = (new catalogModel())->getFacetValuesList();
                foreach ($facet_list as $facet)
                    echo $facet['_id']['en'] . "\n" . $facet['_id']['ru'] . "\n";
                break;
        }
    }

    public function fillWordstatFillActionProtected()
    {
        if (empty($_REQUEST['phrase_list'])) {
            echo 'error';
            return false;
        }

        $mm = new mistakeModel();

        $data = [
            'phrase_list'   => [],
            'reload'        => '/router/?route=yandex/displayWordstatList'
        ];

        $phrase_list = array_map('trim', explode("\n", trim($_REQUEST['phrase_list'])));
        foreach ($phrase_list as $phrase)
            if ($phrase) {
                $data['phrase_list'][] = [
                    'phrase'        => $phrase,
                    'result'        => WordstatPhrase::add($phrase),
                    'add_mistake'   => (int)$_REQUEST['make_mistakes'] ? $mm->generate($phrase) : false
                ];
            }

        echo (new tpl)->include('views/admin/modules/wordstat/add_result.html', $data);
    }

    public function deleteWordstatPhraseActionProtected()
    {
        if (!isset($_REQUEST['id'])) {
            echo json_encode(['error' => 'Not found $_GET[id] parameter']);
            return ;
        }

        $mm = new mistakeModel();

        $output     = ['delete' => [], 'no_delete' => []];
        foreach ($_REQUEST['id'] as $id) {
            $result = WordstatPhrase::deleteByKey($id) && $mm->delete(new phraseMongoKey($id));
            $output[$result ? 'delete' : 'no_delete'][] = $id;
        }

        echo json_encode($output);
    }

    public function fillWordstatPhraseMistakesActionProtected()
    {
        if (!isset($_REQUEST['phrase_id']) || !isset($_REQUEST['phrase_mistakes'])) {
            echo 'Ошибка: не хватает необходимых параметров';
            return false;
        }

        if (!($phrase = WordstatPhrase::getOneByKey($_REQUEST['phrase_id']))) {
            echo 'Ошибка: фраза не найдена';
            return false;
        }

        $mistake_list = [];
        foreach (explode("\n", $_REQUEST['phrase_mistakes']) as $mistake)
            if (($mistake = trim($mistake)))
                $mistake_list[] = $mistake;

        $mm = new mistakeModel();
        $mm->add($phrase['phrase']);

        echo $mm->updateMistakes(new phraseMongoKey($_REQUEST['phrase_id']), $mistake_list)
            ? 'Список ошибок обновлён'
            : 'Ошибка: обновление не удалось';
    }

    public function fillWordstatPhraseUpdateKeywordActionProtected()
    {
        if (!isset($_REQUEST['phrase_id']) || !isset($_REQUEST['keyword']) || !isset($_REQUEST['action'])) {
            echo json_encode(['error' => 'Ошибка: не хватает необходимых параметров']);
            return false;
        }

        if (!($phrase = WordstatPhrase::getOneByKey($_REQUEST['phrase_id']))) {
            echo json_encode(['error' => 'Ошибка: фраза не найдена']);
            return false;
        }

        if (!isset($phrase['keywords'])) {
            echo json_encode(['error' => 'Ошибка: список ключевых слов у фразы не найден']);
            return false;
        }

        $result = [];
        foreach ($phrase['keywords'] as &$keyword) {
            if ($keyword['Phrase'] == $_REQUEST['keyword']) {
                if ('disable' == $_REQUEST['action']) {
                    $keyword['disabled'] = 1;
                }elseif ('enable' == $_REQUEST['action'] && isset($keyword['disabled'])) {
                    unset($keyword['disabled']);
                }
                // TODO: no good fetch() use (send it to tpl class)
                $tpl = new tpl;
                $tpl->load(file_get_contents(C::i()->site . C::i()->paths['templates'] . 'views/admin/modules/wordstat/phrase.html'));
                $result['html'] = $tpl->fetch('/keywords', $keyword);
            }
        }

        if (!WordstatPhrase::updateByKey($_REQUEST['phrase_id'], ['keywords' => $phrase['keywords']])) {
            echo json_encode(['error' => 'Ошибка: список ключевых слов не обновлён']);
            return false;
        }

        echo json_encode($result);
        return true;
    }

    public function fillWordstatPhraseTranscriptionActionProtected()
    {
        if (!isset($_REQUEST['phrase_id']) || !isset($_REQUEST['phrase_transcription'])) {
            echo 'Ошибка: не хватает необходимых параметров';
            return false;
        }

        if (!($phrase = WordstatPhrase::getOneByKey($_REQUEST['phrase_id']))) {
            echo 'Ошибка: фраза не найдена';
            return false;
        }

        $transcription = filter_var($_REQUEST['phrase_transcription'], FILTER_SANITIZE_STRING);

        $mm = new mistakeModel();
        $mm->add($phrase['phrase']);

        echo $mm->updateTranscription(new phraseMongoKey($_REQUEST['phrase_id']), $transcription)
            ? 'Транскрипция обновлена'
            : 'Ошибка: обновление не удалось';
    }

    public static function sortByShows($a, $b)
    {
        return (int)$a['Shows'] <= (int)$b['Shows'];
    }
}