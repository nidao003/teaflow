<?php
/** 
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/> 
 */
namespace App\Classes;

class Distance
{
    /**
     * 计算两点地理坐标之间的距离
     * @param  Decimal $lng_1 起点经度
     * @param  Decimal $lat_1  起点纬度
     * @param  Decimal $lng_2 终点经度
     * @param  Decimal $lat_2  终点纬度
     * @return Decimal          返回距离，单位：公里
     */
    public static function get($lng_1, $lat_1, $lng_2, $lat_2)
    {
        $earth_radius = 6370.996; // 地球半径系数
        $pi = 3.1415926;
        $rad_lat_1 = $lat_1 * $pi / 180.0;
        $rad_lat_2 = $lat_2 * $pi / 180.0;
        $rad_lng_1 = $lng_1 * $pi / 180.0;
        $rad_lng_2 = $lng_2 * $pi / 180.0;
        $a = $rad_lat_1 - $rad_lat_2;
        $b = $rad_lng_1 - $rad_lng_2;
        $distance = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($rad_lat_1) * cos($rad_lat_2) * pow(sin($b / 2), 2)));
        $distance = $distance * $earth_radius * 1000;
        $distance = $distance / 1000;
        return round($distance, 2);
    }
    /**
     * 格式化距离
     * @param  Decimal $distance 距离
     */
    public static function getStr($distance)
    {
        if ($distance < 1) {
            return round($distance * 1000, 2) . 'm';
        }
        return round($distance, 2) . 'km';
    }
}
