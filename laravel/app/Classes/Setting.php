<?php
/**
 * 获取配置信息
 * Setting::get("alipay_*") 以 alipay_ 开头的配置
 * Setting::get("alipay_app_id") 获取支付宝app_id
 * Setting::get(["alipay_app_id","alipay_secret_key"]) 获取支付宝app_id和支付宝密钥
 * Setting::get(["alipay_*"])  以 alipay_ 开头的配置
 */

namespace App\Classes;

use App\Models\Setting as SettingModel;
use Illuminate\Support\Facades\Auth as Author;

class Setting
{
    use \App\Trait\Setting;

    public static function get($title = '')
    {
        $data = SettingModel::get()->toArray();
        /**
         * 返回 title=>content
         */
        $setting = array_column($data, 'content', 'title');
        return self::getData($title, $setting);
    }
    /**
     * 获取允许的数据
     */
    public static function getAllowData($data)
    {
        $user = Author::user();
        $type = $user->type;
        $admin_fields = self::getAdminFields();
        if ($type != 'admin') {
            foreach ($data as $k => $v) {
                if (in_array($k, $admin_fields)) {
                    unset($data[$k]);
                }
            }
        }
        return $data;
    }
    /**
     * 只有管理员才会显示的字段
     */
    public static function getAdminFields()
    {
        return [
            'weixin_pay_cert_path',
            'weixin_pay_key_path',
            'weixin_pay_platform_cert',
            'alipay_secret_key',
            'alipay_app_public_cert_path',
            'alipay_public_cert_path',
            'alipay_root_cert_path',
        ];
    }


}
