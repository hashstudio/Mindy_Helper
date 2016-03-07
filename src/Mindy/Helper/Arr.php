<?php
/**
 * 
 *
 * All rights reserved.
 * 
 * @author Falaleev Maxim
 * @email max@studio107.ru
 * @version 1.0
 * @company Studio107
 * @site http://studio107.ru
 * @date 06/07/14.07.2014 18:23
 */

namespace Mindy\Helper;

/**
 * Class Arr
 * @package Mindy\Helper
 */
class Arr 
{
    /**
     * TODO wtf?
     * @DEPRECATED
     * @param array $data
     * @param $by int
     * @return array
     */
    public static function splitBy(array $data, $by)
    {
        $cnt = count($data);
        $result = [];
        $tmp = [];
        foreach($data as $item) {
            $tmp[] = $item;
            $limit = ($cnt - count($result) * $by) >= $by;
            if($limit && count($tmp) == $by) {
                $result[] = $tmp;
                $tmp = [];
            }
        }
        if(!empty($tmp)) {
            $result[] = $tmp;
        }
        unset($tmp);
        return $result;
    }

    public static function cleanArrays(array $data)
    {
        $new = [];
        foreach ($data as $key => $item) {
            $tmp = is_array($item) ? array_filter($item) : $item;
            if (empty($tmp)) {
                continue;
            }
            $new[$key] = $tmp;
        }
        return $new;
    }
}
