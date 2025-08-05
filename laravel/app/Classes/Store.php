<?php
/** 
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/> 
 */
namespace App\Classes;

use Illuminate\Support\Facades\Auth;
use App\Models\StoreAccount;

class Store
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
            $store_id = $user->seller->store->id ?? '';
            if (!$store_id) {
                if ($err) {
                    throw new \Exception("缺少门店信息");
                } else {
                    return;
                }
            }
            $cur_store_id = self::getRequestStoreId();
            if (Seller::isAllowStoreId($cur_store_id)) {
                return $cur_store_id;
            }
            return $store_id;
        } elseif ($user->type == 'store') {
            $res = StoreAccount::where('user_id', $user->id)->first();
            return $res->store_id ?? '';
        }
    }

    public static function getRequestStoreId()
    {
        return request()->input('current_store_id');
    }

}
