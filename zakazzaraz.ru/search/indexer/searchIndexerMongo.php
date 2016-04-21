<?php

/**
 * User: Alexey Vasilkov (vasilkov.net@gmail.com)
 * Date: 06.09.13
 */

namespace zzr\search\indexer;

use zzr\engine\databases\mw;
use zzr\search\indexer\searchIndexerInterface;
use zzr\search\engine\searchEngineMongo;
use zzr\search\indexer\searchIndexerModel;
use zzr\utils\dummyUriProcessor;
use zzr\info\brand\brandModel;
use zzr\search\phraseMongoKey;

class searchIndexerMongo implements searchIndexerInterface
{
    /**
     * @var mw instance
     */
    protected       $mw                         = null;

    /**
     * @var dummyUriProcessor instance
     */
    protected       $up                         = null;

    /**
     * @const name of search indexer settings collection
     */
    const           INDEXER_COLLECTION          = 'search_indexer';

    /**
     * @var \MongoCollection contains search index
     */
    protected       $search_index               = null;

    /**
     * @var \MongoCollection autocomplete collection
     */
    protected       $autocomplete               = null;

    /**
     * @var \MongoCollection contains indexer daily parameters
     */
    protected       $indexer                    = null;

    /**
     * @var array contains daily parameters
     */
    protected       $settings                   = [];

    /**
     * @const number of products updated in one execution
     */
    const           PRODUCT_LIMIT               = 10000;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->mw                   = mw::i();
        $this->up                   = new dummyUriProcessor;
        $this->indexer              = $this->mw->zzr->{self::INDEXER_COLLECTION};
        $this->autocomplete         = $this->mw->zzr->{searchEngineMongo::AUTOCOMPLETE_COLLECTION};
        $this->search_index         = $this->mw->zzr->{searchEngineMongo::SEARCH_COLLECTION};
        $this->settings['_id']      = date('Ymd');
    }

    /**
     * Main function must be called in cron
     *
     * @return bool
     */
    public function index()
    {
        // already start this day, continue
        if ($settings = $this->indexer->findOne(['_id' => $this->settings['_id']])) {
            $this->settings = $settings;
        }else {
            $this->settings = array_merge($this->settings, [
                'product'           => false,
                'product_skip'      => 0,
                'category'          => false,
                'brand'             => false,
                'shop'              => false,
                'notified'          => false,
                'dob'               => new \MongoDate()
            ]);
            $this->indexer->insert($this->settings);
        }

        if (!$this->settings['product'])
            return $this->indexProductList($this->settings['product_skip']);

        if (!$this->settings['category'])
            return $this->indexCategoryList();

        if (!$this->settings['brand'])
            return $this->indexBrandList();

        if (!$this->settings['shop'])
            return $this->indexShopList();

        return false;
    }

    /**
     * Index product list %)
     *
     * @param int $skip - skip products number (limit - self::PRODUCT_LIMIT)
     *
     * @return bool
     */
    protected function indexProductList($skip)
    {
        $product_list = searchIndexerModel::getProductList(self::PRODUCT_LIMIT, $skip);

        if (!$product_list->hasNext())
            return $this->updateSettings(['product' => true]);

        $this->updateSettings(['product_skip' => $skip + self::PRODUCT_LIMIT]);

        foreach ($product_list as $product) {

            $v = [];
            $p = $this->mw->zzr->products->findOne([
                '_id'                   => $product['_id']['p']
            ], [
                '_id'                   => false,
                'dob'                   => true,
                'dou'                   => true,
                'variations.attributes' => true,
                'variations.furl'       => true
            ]);

            $dou = is_null($p['dou']) ? $p['dob'] : $p['dou'];

            // do we need to update product?
            if ($this->search_index->findOne(['_id' => $product['value']['furl'], 'dou' => ['$gte' => $dou]], ['_id' => true])) {
                // set checked mark
                $this->search_index->update(['_id' => $product['value']['furl']], ['$set' => ['checked' => new \MongoDate()]]);
                continue;
            }

            if (isset($p['variations']) && sizeof($p['variations']))
                foreach ($p['variations'] as $variation)
                    if ($variation['furl'] == $product['_id']['v']) {
                        $v = $variation;
                        break;
                    }

            $title = self::clearBrandName($product['value']['name'], $product['value']['brandName']);

            $category = isset($product['value']['category']['catGender'])
                ? $this->up->getGenderCategory(isset($product['value']['category']['catCategory']) ? $product['value']['category']['catCategory'] : $product['value']['category']['catRoot'], $product['value']['category']['catGender'])
                : $this->up->getName(isset($product['value']['category']['catCategory']) ? $product['value']['category']['catCategory'] : $product['value']['category']['catRoot']);

            $gender = $this->getGender($product['value']['category']);

            $facets = [];
            if (isset($product['value']['parameters']))
                foreach ($product['value']['parameters'] as $parameter)
                    $facets[] = self::lower($parameter['value']) . ', ' . self::lower($parameter['ruValue']);

            $this->search_index->update([
                '_id'           => $product['value']['furl']
            ], [
                '_id'           => $product['value']['furl'],
                'type'          => 'product',
                'id'            => $product['_id']['p'],
                'v'             => $product['_id']['v'],
                'title'         => $title,
                'attribute'     => $v && !empty($v['attributes']['color']) ? $v['attributes']['color'] : '',
                'gender'        => implode(', ', $gender),
                'brand'         => $product['value']['brandName'],
                'category'      => $category,
                'facets'        => implode(', ', $facets),
                'dou'           => $dou,
                'checked'       => new \MongoDate()
            ], ['upsert' => true]);

            $this->indexAutocomplete($title
                . ' ' . ($v && !empty($v['attributes']['color']) ? $v['attributes']['color'] : '')
                . ' ' . $category . ' ' . implode(' ', $gender) . ' ' . implode(' ', $facets));
        }

        return true;
    }

    /**
     * Index categories
     *
     * @return bool
     */
    protected function indexCategoryList()
    {
        $brand_model    = new brandModel();
        $brand_list     = [];

        foreach (searchIndexerModel::getCategoryList() as $category) {

            $url = $this->up->makeUri($category['_id']);

            if ($this->search_index->findOne(['_id' => $url], ['_id' => true])) {
                // set checked mark
                $this->search_index->update(['_id' => $url], ['$set' => ['checked' => new \MongoDate()]]);
                continue;
            }

            $title      = $this->getCategoryTitle($category['_id']);
            $gender     = $this->getGender($category['_id']);

            if (isset($category['_id']['brands'])) {
                $brand_name = $category['_id']['brands'];

                if (isset($brand_list[$category['_id']['brands']])) {
                    $brand_name = $brand_list[$category['_id']['brands']];
                }else {
                    if ($brand = $brand_model->getBrandByFurl($category['_id']['brands'])) {
                        $brand_name = $brand['name'];
                        $brand_list[$category['_id']['brands']] = $brand['name'];
                    }
                }

                $title .= ' ' . $brand_name;
            }

            $this->search_index->update([
                '_id'           => $url
            ], [
                '_id'           => $url,
                'type'          => 'category',
                'title'         => $title,
                'gender'        => implode(', ', $gender),
                'checked'       => new \MongoDate()
            ], ['upsert' => true]);
        }

        return $this->updateSettings(['category' => true]);
    }

    /**
     * Get human category title name by its parameters
     *
     * @param array $category
     *
     * @return string
     */
    protected function getCategoryTitle($category)
    {
        if (isset($category['parameters'])) {

            if (isset($category['catGender'])) {

                if (false !== strpos($category['catGender'], 'muzh'))
                    return 'Мужские ' . self::lower($this->up->getName($category['parameters']));

                if (false !== strpos($category['catGender'], 'zhen'))
                    return 'Женские ' . self::lower($this->up->getName($category['parameters']));

                if (false !== strpos($category['catGender'], 'dlya-devochek'))
                    return $this->up->getName($category['parameters']) . ' для девочек';

                if (false !== strpos($category['catGender'], 'dlya-malchikov'))
                    return $this->up->getName($category['parameters']) . ' для мальчиков';

            }

            return $this->up->getName($category['parameters']);
        }

        if (isset($category['catGender']))
            return $this->up->getGenderCategory(isset($category['catCategory']) ? $category['catCategory'] : $category['catRoot'], $category['catGender']);

        return $this->up->getName(isset($category['catCategory']) ? $category['catCategory'] : $category['catRoot']);
    }

    /**
     * Get human gender variations by category parameter catGender
     *
     * @param array $category
     *
     * @return array
     */
    protected function getGender($category)
    {
        if (!isset($category['catGender']))
            return [];

        if (false !== strpos($category['catGender'], 'muzh'))
            return ['мужское', 'для мужчин'];

        if (false !== strpos($category['catGender'], 'zhen'))
            return ['женское', 'для женщин'];

        if (false !== strpos($category['catGender'], 'dlya-devochek'))
            return ['детское', 'для детей', 'для девочек'];

        if (false !== strpos($category['catGender'], 'dlya-malchikov'))
            return ['детское', 'для детей', 'для мальчиков'];

        return [self::lower($this->up->getName($category['catGender']))];
    }

    /**
     * Index brands
     *
     * @return bool
     */
    protected function indexBrandList()
    {
        foreach (searchIndexerModel::getBrandList() as $brand) {

            $url = '/brand/' . $brand['furl'] . '/';

            // do we need to update category?
            if ($this->search_index->findOne(['_id' => $url], ['_id' => true])) {
                // set checked mark
                $this->search_index->update(['_id' => $url], ['$set' => ['checked' => new \MongoDate()]]);
                continue;
            }

            $this->search_index->update([
                '_id'           => $url
            ], [
                '_id'           => $url,
                'type'          => 'brand',
                'title'         => $brand['name'],
                'attribute'     => $brand['name'] . ', брэнд, бренд, brand',
                'checked'       => new \MongoDate()
            ], ['upsert' => true]);

        }

        return $this->updateSettings(['brand' => true]);
    }

    /**
     * Index shops
     *
     * @return bool
     */
    protected function indexShopList()
    {
        foreach (searchIndexerModel::getShopList() as $shop) {

            $url = '/www/' . $shop['furl'] . '/';

            // do we need to update category?
            if ($this->search_index->findOne(['_id' => $url], ['_id' => true])) {
                // set checked mark
                $this->search_index->update(['_id' => $url], ['$set' => ['checked' => new \MongoDate()]]);
                continue;
            }

            $this->search_index->update([
                '_id'           => $url
            ], [
                '_id'           => $url,
                'type'          => 'shop',
                'title'         => $shop['name'],
                'attribute'     => 'магазин, shop',
                'checked'       => new \MongoDate()
            ], ['upsert' => true]);

        }

        return $this->updateSettings(['shop' => true]);
    }

    /**
     * Clean search index
     *
     * @return bool
     */
    public function clean()
    {
        $this->search_index->remove([
            'checked' => ['$lt' => new \MongoDate(time() - 2 * 24 * 3600)]
        ]);

        return true;
    }

    /**
     * Index autocomplete suggestions for $phrase (explode $phrase into spaces and keep words in autocomplete collection)
     *
     * @param string $phrase
     *
     * @return bool
     */
    protected function indexAutocomplete($phrase)
    {
        foreach (explode(' ', $phrase) as $word)
            if (($word = trim($word, '\t\n\r\0\x0B ,.!()"\\')) && mb_strlen($word, 'UTF-8') > 3) {
                $key = phraseMongoKey::fromPhrase($word);
                $this->autocomplete->update(['_id' => (string)$key], [
                    '_id'       => (string)$key,
                    'word'      => self::lower($word),
                    'dou'       => new \MongoDate()
                ], ['upsert' => true]);
            }
    }

    /**
     * Update indexer settings
     *
     * @param array $update
     *
     * @return bool
     */
    protected function updateSettings($update)
    {
        $result = $this->indexer->update(['_id' => $this->settings['_id']], ['$set' => $update]);
        return isset($result['ok']) && (bool)$result['ok'];
    }

    /**
     * Lower phrase
     *
     * @param string $word
     *
     * @return string
     */
    public static function lower($word) {
        return mb_strtolower($word, 'UTF-8');
    }

    /**
     * Clear brand name from product title (brand name is a part of product name now and in order to clean search results we need to store it separately in search index)
     *
     * @param string $title
     * @param string $brandName
     *
     * @return string
     */
    public static function clearBrandName($title, $brandName) {
        return $brandName && (0 === mb_strpos($title, $brandName, 0, 'UTF-8'))
            ? trim(mb_strcut($title, mb_strlen($brandName, 'UTF-8'), mb_strlen($title, 'UTF-8'), 'UTF-8'))
            : $title;
    }
}