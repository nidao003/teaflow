<?php
/**
 *  http://lbs.tianditu.gov.cn/server/search2.html
 */

namespace App\Classes;

use App\Models\Setting;

class MapTiandi
{
    public static function get($address)
    {
        $res = Setting::where('title', 'tianditu')->where('seller_id', 0)->first();
        $key     = $res['content'] ?? '';
        $address = urlencode($address);
        $url = 'http://api.tianditu.gov.cn/geocoder?ds={"keyWord":"'.$address.'"}&tk='.$key;
        $res = self::get_request($url); 
        if ($res['status'] == 0) {
            $lat = $res['location']['lat'];
            $lng = $res['location']['lon'];
            return MapConvert::wgs84ToGcj02($lat, $lng);
        }
    }


    public static function getAddress($lat, $lng)
    {
        $res = Setting::where('title', 'tianditu')->where('seller_id', 0)->first();
        $key     = $res['content'] ?? '';
        $url = "http://api.tianditu.gov.cn/geocoder?postStr={'lon':".$lng.",'lat':".$lat.",'ver':1}&type=geocode&tk=".$key;
        $res = self::get_request($url);
        if ($res['status'] == 0) {
            $res = $res['result'];
            $list = [];
            $list['address'] = $res['formatted_address'];
            $a = $res['addressComponent'];
            $list['parse'] = [
                'nation' => $a['nation'],
                'province' => $a['province'],
                'county' => $a['county'],
                'address' => $a['address'],
            ];
            return $list;
        }
    }

    public static function get_request($url)
    {
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 3000
            )
        ));
        $res = file_get_contents($url, 0, $context);
        $res = json_decode($res, true);
        return $res;
    }
}
