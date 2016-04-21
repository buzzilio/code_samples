<?php

/**
 * User: Alexey Vasilkov (vasilkov.net@gmail.com)
 * Date: 06.09.13
 */

namespace zzr\search\mistake;

class mistakeGenerator
{
    const ENCODING			= 'UTF-8';

    protected static $keyboard_mistakes = [
        '1' => '`2wqёй', '2' => '13ewqцй', '3' => '24rewкуц', '4' => '35treеку', '5' => '46ytrнек', '6' => '57uytгне', '7' => '68iuyшгн', '8' => '79oiuщшг', '9' => '80poiзщш', '0' => '9-[poзщ', '-' => '0=][pхз', '=' => '-][ъх',
        'q' => '12waцф', 'w' => '23esaqуыфй', 'e' => '34rdswквыц', 'r' => '45tfdeеаву', 't' => '56ygfrнпак', 'y' => '67uhgtгрпе', 'u' => '78ijhyшорн', 'i' => '89okjuщлог', 'o' => '90plkiздлш', 'p' => '0-[;loхждщ', '[' => '-=]\';pъэжз', ']' => '=\\\'[эх',
        'a' => 'qwszйцыя', 's' => 'wedxzaцувчяф', 'd' => 'erfcxsукасчы', 'f' => 'rtgvcdкепмсв', 'g' => 'tyhbvfенрима', 'h' => 'yujnbgнготип', 'j' => 'uikmnhгшльтр', 'k' => 'iol,mjшщдбьо', 'l' => 'op;.,kщзжюбл', ';' => 'p[\'/.lзхэюд', '\'' => '[]/;.ж',
        'z' => 'asxфыч', 'x' => 'zsdcяывс', 'c' => 'xdfvчвам', 'v' => 'cfgbсапи', 'b' => 'vghnмпрт', 'n' => 'bhjmироь', 'm' => 'njk,толб', ',' => 'mkl.ьлдю', '.' => ',l;/бдж.;\'юэ', '/' => '.;\'южэ',
        'й' => '12waцф', 'ц' => '23esaqуыфй', 'у' => '34rdswквыц', 'к' => '45tfdeеаву', 'е' => '56ygfrнпак', 'н' => '67uhgtгрпе', 'г' => '78ijhyшорн', 'ш' => '89okjuщлог', 'щ' => '90plkiздлш', 'з' => '0-[;loхждщ', 'х' => '-=]\';pъэжз', 'ъ' => '=\\\'[эх',
        'ф' => 'qwszйцыя', 'ы' => 'wedxzaцувчяф', 'в' => 'erfcxsукасчы', 'а' => 'rtgvcdкепмсв', 'п' => 'tyhbvfенрима', 'р' => 'yujnbgнготип', 'о' => 'uikmnhгшльтр', 'л' => 'iol,mjшщдбьо', 'д' => 'op;.,kщзжюбл', 'ж' => 'p[\'/.lзхэюд', 'э' => '[]/;.ж',
        'я' => 'asxфыч', 'ч' => 'zsdcяывс', 'с' => 'xdfvчвам', 'м' => 'cfgbсапи', 'и' => 'vghnмпрт', 'т' => 'bhjmироь', 'ь' => 'njk,толб', 'б' => 'mkl.ьлдю', 'ю' => ',l;/бдж'
    ];

    protected static $transcription_rules = [
        ['ough' => 'о', 'eigh' => 'эй', 'igh' => 'ай', 'oot' => 'ут', 'ate' => 'эйт', 'ade' => 'эйд', 'out' => 'аут', 'dolce' => 'дольче', 'her' => 'хё', 'oun' => 'аун', 'ing' => 'ин', 'ice' => 'айс', 'ike' => 'айк', 'ife' => 'айф', 'iri' => 'ири', 'oor' => 'о'],

        ['ee' => 'и', 'ea' => 'и', 'ei' => 'и', 'ie' => 'и', 'eo' => 'и', 'aw' => 'о', 'oa' => 'о', 'oo' => 'у', 'au' => 'о', 'ou' => 'а', 'ow' => 'о'
        , 'ay' => 'эй', 'ai' => 'эй', 'ui' => 'ай', 'ye' => 'ай', 'oy' => 'ой', 'oi' => 'ой', 'ue' => 'уэ', 'ck' => 'к'
        , 'ch' => 'ч', 'ph' => 'ф', 'gh' => 'ф', 'th' => 'т', 'sc' => 'с', 'sh' => 'ш', 'er' => 'э', 'ew' => 'ью', 'ei' => 'яй', 'ir' => 'ё', 'ce' => 'с', 'ow' => 'ау', 'wh' => 'в', 'ly' => 'ли', 'wo' => 'ву'],

        ['q' => 'ку', 'w' => 'в', 'e' => 'э', 'r' => 'р', 't' => 'т', 'y' => 'й', 'u' => 'у', 'i' => 'и', 'o' => 'о', 'p' => 'п'
        , 'a' => 'а', 's' => 'с', 'd' => 'д', 'f' => 'ф', 'g' => 'г', 'h' => 'х', 'j' => 'ж', 'k' => 'к', 'l' => 'л'
        , 'z' => 'з', 'x' => 'кс', 'c' => 'к', 'v' => 'в', 'b' => 'б', 'n' => 'н', 'm' => 'м']
    ];

    protected static $russian_mistakes = [
        'и' => 'ые', 'а' => 'оэ', 'о' => 'а', 'е' => 'и', 'с' => 'з', 'э' => 'е'
    ];

    public static $function_list = [
        'missLetter', 'switchLetters', 'doubleLetter', 'wrongKeyPress', 'russianMistakes'
    ];

    public static function generate($word)
    {
        $list = [];
        foreach (self::$function_list as $function)
            $list = array_merge($list, self::$function($word));
        return array_values($list);
    }

    public static function missLetter($word)
    {
        $word		= self::prepare($word);
        $length		= self::length($word);

        $list = [];
        for ($i = 0; $i < $length; $i++)
            $list[] = mb_substr($word, 0, $i, self::ENCODING) . mb_substr($word, $i + 1, $length, self::ENCODING);
        return $list;
    }

    public static function switchLetters($word)
    {
        $word		= self::prepare($word);
        $length		= self::length($word);

        $list = [];
        for ($i = 0; $i < $length - 1; $i++)
            $list[] = mb_substr($word, 0, $i, self::ENCODING)
                . mb_substr($word, $i + 1, 1, self::ENCODING)
                . mb_substr($word, $i, 1, self::ENCODING)
                . ($i < $length ? mb_substr($word, $i + 2, $length, self::ENCODING) : '');
        return $list;
    }

    public static function doubleLetter($word)
    {
        $word		= self::prepare($word);
        $length		= self::length($word);

        $list = [];
        for ($i = 0; $i < $length; $i++)
            $list[] = mb_substr($word, 0, $i + 1, self::ENCODING)
                . mb_substr($word, $i, 1, self::ENCODING)
                . mb_substr($word, $i + 1, $length, self::ENCODING);
        return $list;
    }

    public static function wrongKeyPress($word)
    {
        $word		= self::prepare($word);
        $length		= self::length($word);

        $list = [];
        for ($i = 0; $i < $length; $i++) {
            $letter = mb_substr($word, $i, 1, self::ENCODING);
            if (isset(self::$keyboard_mistakes[$letter]))
                for ($j = 0; $j < self::length(self::$keyboard_mistakes[$letter]); $j++)
                    $list[] = mb_substr($word, 0, $i, self::ENCODING)
                        . mb_substr(self::$keyboard_mistakes[$letter], $j, 1, self::ENCODING)
                        . mb_substr($word, $i + 1, $length, self::ENCODING);
        }
        return $list;
    }

    public static function russianMistakes($word)
    {
        $word		= self::prepare($word);
        $length		= self::length($word);

        $list = [];
        for ($i = 0; $i < $length; $i++) {
            $letter = mb_substr($word, $i, 1, self::ENCODING);
            if (isset(self::$russian_mistakes[$letter]))
                for ($j = 0; $j < self::length(self::$russian_mistakes[$letter]); $j++)
                    $list[] = mb_substr($word, 0, $i, self::ENCODING)
                        . mb_substr(self::$russian_mistakes[$letter], $j, 1, self::ENCODING)
                        . mb_substr($word, $i + 1, $length, self::ENCODING);
        }
        return $list;
    }

    public static function transcribe($word)
    {
        $transcribe = self::prepare($word);

        foreach (self::$transcription_rules as $level)
            $transcribe = strtr($transcribe, $level);

        return $transcribe == self::prepare($word) ? false : $transcribe;
    }

    public static function length($word)
    {
        return mb_strlen($word, self::ENCODING);
    }

    public static function prepare($word)
    {
        return mb_strtolower($word, self::ENCODING);
    }
}