<?php

namespace App\Classes;

use Illuminate\Support\Facades\Auth;
use App\Models\Store;

class Seller
{
    public static function getId($err = true)
    {
        $user = Auth::user();
        if (!$user) {
            if ($err) {
                throw new \Exception("缺少用户信息");
            } else {
                return;
            }
        }
        if ($user->type == 'seller') {
            $seller_id = $user->seller->id ?? '';
            if (!$seller_id) {
                if ($err) {
                    throw new \Exception("缺少商家信息");
                } else {
                    return;
                }
            }
            return $seller_id;
        }
    }

    public static function getStoreId()
    {
        $seller_id = self::getId(false);
        if (!$seller_id) {
            return;
        }
        $store_id = Store::where('seller_id', $seller_id)->get();
        if (!$store_id) {
            if ($err) {
                throw new \Exception("缺少门店信息");
            } else {
                return;
            }
        }
        $in = [];
        foreach ($store_id as $item) {
            $in[] = $item->id;
        }
        return $in;
    }

    public static function isAllowStoreId($store_id)
    {
        $in = self::getStoreId();
        if (!$in) {
            return false;
        }
        if (in_array($store_id, $in)) {
            return true;
        }
        return false;
    }
}
