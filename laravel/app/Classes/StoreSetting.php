<?php
/**
 * 获取门店配置信息
 * Setting::get("alipay_*") 以 alipay_ 开头的配置
 * Setting::get("alipay_app_id") 获取支付宝app_id
 * Setting::get(["alipay_app_id","alipay_secret_key"]) 获取支付宝app_id和支付宝密钥
 * Setting::get(["alipay_*"])  以 alipay_ 开头的配置
 */

namespace App\Classes;

use App\Models\StoreSetting as SettingModel;

class StoreSetting
{
    use \App\Trait\Setting;
    public static function get($store_id = 0, $title = '')
    {
        $data = SettingModel::where('store_id', $store_id)->get()->toArray();

        /**
         * 返回 title=>content
         */
        $setting = array_column($data, 'value', 'key');
        return self::getData($title, $setting);
    }


}
