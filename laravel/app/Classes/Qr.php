<?php
/** 
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/> 
 */
namespace App\Classes;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\ValidationException;

class Qr
{
    public static function getType($name = 'user')
    {
        $arr = [
            'user' => 80,  // 会员码
            'out'  => 81,   // 出餐码
            'take' => 82,   // 取餐码
        ];
        return $arr[$name];
    }

    public static function output($conent)
    {
        $writer = new PngWriter();
        $qrCode = new QrCode(
            data: $conent,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Low,
            size: 300,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255)
        );
        $result = $writer->write($qrCode);
        $url = '/uploads/qr/'.md5(time().$conent).'.png';
        //创建目录
        if (!is_dir(public_path().'/uploads/qr')) {
            mkdir(public_path().'/uploads/qr', 0777, true);
        }
        $result->saveToFile(public_path().$url);
        return env('APP_URL').$url;
    }

}
