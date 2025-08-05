<?php

namespace App\Classes;

use EasyWeChat\MiniApp\Application;
use App\Models\Oauth;
use App\Models\Setting;

class WeixinApplet
{
    public static $type = 'weixin';
    public static function init()
    {
        $list = [
            'WEIXIN_APPLET_APPID',
            'WEIXIN_APPLET_SECRET',
            'WEIXIN_APPLET_TOKEN',
            'WEIXIN_APPLET_AESKEY',
        ];
        $setting = Setting::whereIn('title', $list)->where('seller_id', 0)->get();
        $setting = $setting->keyBy('title');
        $setting = $setting->toArray();
        $setting = array_column($setting, 'content', 'title');
        $config = [
            'app_id' => $setting['WEIXIN_APPLET_APPID'] ?? '',
            'secret' => $setting['WEIXIN_APPLET_SECRET'] ?? '',
            'token' =>  $setting['WEIXIN_APPLET_TOKEN'] ?? '',
            'aes_key' => $setting['WEIXIN_APPLET_AESKEY'] ?? '',
            'http' => [
                'throw'  => true,
                'timeout' => 5.0,
                'retry' => true,
            ],
        ];
        return new Application($config);
    }
    /**
     * 小程序手机号授权登录
     */
    public static function login($code, $iv, $encryptedData)
    {
        $utils = self::init()->getUtils();
        $res = $utils->codeToSession($code);
        $session_key = $res['session_key'] ?? '';
        $openid = $res['openid'] ?? '';
        $unionid = $res['unionid'] ?? '';
        $session = $utils->decryptSession($session_key, $iv, $encryptedData);
        $phone = $session['phoneNumber'] ?? '';
        $pure_phone = $session['purePhoneNumber'] ?? '';
        $country_code = $session['countryCode'] ?? '';
        $data = [
            'openid' => $openid,
            'country_code' => $country_code,
            'unionid' => $unionid,
            'phone' => $phone,
            'pure_phone' => $pure_phone,
            'type' => self::$type,
        ];
        $model = Oauth::where('phone', $phone)->where('type', self::$type)->first();
        if (!$model) {
            $model = new Oauth();
            $model->phone = $phone;
            $model->openid = $openid;
            $model->country_code = $country_code;
            $model->pure_phone = $pure_phone;
            $model->type = self::$type;
            $model->save();
        }
        $output = [];
        $output['token'] = $model->user->getToken();
        $output['user_id'] = $model->user->id;
        $output['type'] = $model->user->type;
        $output['openid'] = $openid;
        return $output;
    }
}
