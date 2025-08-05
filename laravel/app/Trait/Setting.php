<?php

namespace App\Trait;

use Illuminate\Http\Request;

trait Setting
{
    public static function getData($title, $setting)
    {
        if ($title) {
            if (is_array($title)) {
                $res = [];
                foreach ($title as $v) {
                    if (strpos($v, '*') !== false) {
                        $v = str_replace('*', '', $v);
                        foreach ($setting as $k => $v1) {
                            if (strpos($k, $v) !== false) {
                                $res[$k] = $v1;
                            }
                        }
                    } else {
                        $res[$v] = $setting[$v] ?? '';
                    }
                }
                return $res;
            } else {
                if (strpos($title, '*') !== false) {
                    $v = str_replace('*', '', $title);
                    $res = [];
                    foreach ($setting as $k => $v1) {
                        if (strpos($k, $v) !== false) {
                            $res[$k] = $v1;
                        }
                    }
                    return $res;
                } else {
                    return $setting[$title] ?? '';
                }
            }
        } else {
            return $setting;
        }
    }
}
