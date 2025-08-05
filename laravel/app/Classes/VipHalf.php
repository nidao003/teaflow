<?php
/** 
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/> 
 */
namespace App\Classes;

class VipHalf
{
    /**
     * 取当天是否是会员第二 半价
     */
    public static function check()
    {
        $arr = self::getDayNum();
        $day = Setting::get('vip_half_day');
        $in = [];
        if ($day) {
            $day = explode(',', $day);
            foreach ($day as $v) {
                $in[] = $arr[$v];
            }
        }
        if ($in && in_array(self::getDay(), $in)) {
            return 1;
        } else {
            return 0;
        }
    }
    /**
     * 取当天是周几
     */
    public static function getDay()
    {
        return date('w');
    }
    /**
     * 定义周一到周日对应getDay()返回的数字
     */
    public static function getDayNum()
    {
        return [
            '周一' => 1,
            '周二' => 2,
            '周三' => 3,
            '周四' => 4,
            '周五' => 5,
            '周六' => 6,
            '周日' => 0,
        ];
    }
}
