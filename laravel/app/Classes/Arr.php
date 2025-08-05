<?php

namespace App\Classes;

class Arr
{
    public static function toTree($list, $pk = 'id', $pid = 'parent_id', $son = 'children')
    {
        $tree = [];
        $refer = [];
        foreach ($list as $k => $v) {
            $refer[$v[$pk]] = &$list[$k];
        }
        foreach ($list as $k => $v) {
            $parentId = $v[$pid];
            if ($parentId == 0) {
                $tree[] = &$list[$k];
            } else {
                if (isset($refer[$parentId])) {
                    $parent = &$refer[$parentId];
                    $parent[$son][] = &$list[$k];
                }
            }
        }
        return $tree;
    }

    /**
    * 对二维数组进行group by操作
    */
    public static function groupBy($arr, $groupby = "sid")
    {
        static $array = array();
        static $key = array();
        foreach ($arr as $k => $v) {
            $g = $v[$groupby];
            if (!in_array($g, $key)) {
                $key[$k] = $g;
            }
            $array[$g][] = $v;
        }
        return $array;
    }


    /**
     * 数组排序
     * Arr::orderBy($row,$order,SORT_DESC);
     */
    public static function orderBy()
    {
        $args = func_get_args();
        $data = array_shift($args);
        foreach ($args as $n => $field) {
            if (is_string($field)) {
                $tmp = array();
                if (!$data) {
                    return;
                }
                foreach ($data as $key => $row) {
                    $tmp[$key] = $row[$field];
                }
                $args[$n] = $tmp;
            }
        }
        $args[] = &$data;
        if ($args) {
            call_user_func_array('array_multisort', $args);
            return array_pop($args);
        }
        return;
    }
}
