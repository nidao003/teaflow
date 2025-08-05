<?php
/** 
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/> 
 */
namespace App\Classes\Pay;

use Yansongda\Pay\Pay;
use Yansongda\Pay\Contract\HttpClientInterface;
use App\Classes\Setting;
use Illuminate\Support\Facades\Log;

class Base
{
    use \App\Classes\TraitPaid;
    use \App\Classes\TraitPrinterFeie;
    
    protected $pay;
    protected $setting;
    protected $base_path = 'alipay';
    /**
     * 支付成功后
     */
    protected function afterPay($pay)
    {
        Log::info('afterPay 支付宝异步通知成功信息', $pay);
        $order_num = $pay['order_num']; 
        Log::info('afterPay 订单号：'. $order_num);
        $this->createPayInfo($order_num); 
    }

    protected function strToFile($name, $key)
    {
        $value = $this->setting[$key] ?? '';
        if ($value) {
            $file = storage_path('cert/'.$this->base_path.'/'.$name.'.pem');
            if (!file_exists($file)) {
                //目录不存在时创建目录
                if (!is_dir(dirname($file))) {
                    mkdir(dirname($file), 0777, true);
                }
                file_put_contents($file, $value);
            }
            /**
             * 比较内容是否一样
             */
            if (md5($value) != md5(file_get_contents($file))) {
                file_put_contents($file, $value);
            }
            $value = $file;
        }
        return $value;
    }
    /**
     * 获取付款码类型
     */
    public static function getAuthCodeType($code)
    {
        if (self::authCodeIsWeixin($code)) {
            return 'weixin';
        } elseif (self::authCodeIsAlipay($code)) {
            return 'alipay';
        } else {
            return '';
        }
    }

    /**
    * 是否是支付宝付款码
    * https://opendocs.alipay.com/open/194/106039/
    * 25-30 开头 ，16-24 位
    */
    public static function authCodeIsAlipay($code)
    {
        $top = substr($code, 0, 2);
        $len = strlen($code);
        if ($top >= 25 && $top <= 30 && $len >= 16 && $len <= 24) {
            return true;
        } else {
            return false;
        }
    }

    /**
    * 是否是微信付款码
    * https://pay.weixin.qq.com/wiki/doc/api/micropay.php?chapter=5_1
    * 用户付款码规则：18位纯数字，前缀以10、11、12、13、14、15开头
    */
    public static function authCodeIsWeixin($code)
    {
        $top = substr($code, 0, 2);
        $len = strlen($code);
        if ($top >= 10 && $top <= 15 && $len == 18) {
            return true;
        } else {
            return false;
        }
    }
}
