<?php

/**
 * User: Alexey Vasilkov (vasilkov.net@gmail.com)
 * Date: 06.09.13
 */

namespace zzr\search\indexer;

use zzr\engine\databases\mw;
use zzr\catalog\catalogModel;
use zzr\info\brand\brandModel;
use zzr\info\shop\shopModel;

class searchIndexerModel
{
    /**
     * Get partial product list collection
     *
     * @return \MongoCursor
     */
    public static function getProductList($limit, $skip = 0)
    {
        return mw::i()->zzr->catalog->find()->limit($limit)->skip($skip);
    }

    /**
     * Get full category list (root, category, gender, brand crosses)
     *
     * @return array
     */
    public static function getCategoryList()
    {
        $groupings = [
            ['root' => true],
            ['gender' => true, 'root' => true],
            ['gender' => true, 'root' => true, 'brand' => true],
            ['gender' => true, 'root' => true, 'parameters' => ['subCategory']],
            ['gender' => true, 'root' => true, 'parameters' => ['subCategory'], 'brand' => true],

            ['category' => true],
            ['gender' => true, 'category' => true],
            ['gender' => true, 'category' => true, 'brand' => true],
            ['gender' => true, 'category' => true, 'parameters' => ['subCategory']],
            ['gender' => true, 'category' => true, 'parameters' => ['subCategory'], 'brand' => true]
        ];

        return (new catalogModel)->aggregate($groupings);
    }

    /**
     * Get full shop list
     *
     * @return array
     */
    public static function getShopList()
    {
        return (new shopModel())->getList();
    }

    /**
     * Get full brand list
     *
     * @return \MongoCursor
     */
    public static function getBrandList()
    {
        return (new brandModel())->getBrandList();
    }
}