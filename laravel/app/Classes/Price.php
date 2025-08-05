<?php
/** 
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/> 
 */
namespace App\Classes;

class Price
{
    public static function format($yuan, $dot = 2)
    {
        return bcmul($yuan, 1, $dot);
    }
}
