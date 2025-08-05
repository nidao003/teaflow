<?php
/**
 * 优惠券
 */

namespace App\Classes;

use App\Models\CouponUser;
use App\Models\CouponOrder;
use App\Models\Order;
use App\Models\Coupon as CouponModel;

class Coupon
{
    /**
     * 已发放的优惠券
     */
    public static $hasSend = [];

    /**
     * 未使用的优惠券退回
     */
    public static function refund($order_id)
    {
        $res = Order::where('id', $order_id)->first();
        if (!$res) {
            return;
        }
        if ($res->order_status == 'expired') {
            $model = CouponOrder::where('order_id', $order_id)->first();
            if (!$model) {
                return;
            }
            $coupon_id = $model->coupon_id;
            $model->status = 'returned';
            $model->save();
            CouponUser::where('id', $coupon_id)->update(['status' => 'wait']);
        }
    }
    /**
     * 使用优惠券
     * $coupon_id 是 coupon_user表的id
     */
    public static function use($user_id, $order_id, $coupon_id, $price = 0)
    {
        $coupon = CouponUser::where('user_id', $user_id)
                    ->where('id', $coupon_id)
                    ->where('status', 'wait')
                    ->first();
        if ($coupon) {
            if ($price < 0) {
                $price = bcsub(0, $price, 2);
            }
            $model = new CouponOrder();
            $model->user_id = $user_id;
            $model->coupon_id = $coupon_id;
            $model->order_id = $order_id;
            $model->price = $price;
            $model->status = 'used';
            $model->save();
            $coupon->status = 'used';
            $coupon->save();
            return true;
        }
        return false;
    }
    /**
     * 重新计算金额
     */
    public static function getAmount(&$cart, &$cart_items)
    {
        $coupon_id = request()->input('coupon_id', '');
        if ($coupon_id) {
            $coupon = CouponUser::where('id', $coupon_id)->first();
            if ($coupon) {
                $coupon->coupon->getNewPrice($cart, $cart_items);
            }
        }
    }

    /**
     * 当日登录时用户自动领取优惠券
     */
    public static function init($user_id)
    {
        self::$hasSend = [];
        self::NewUser($user_id);
        self::EveryDay($user_id);
        $coupon_ids = self::$hasSend;
        if (count($coupon_ids) > 0) {
            return CouponModel::whereIn('id', $coupon_ids)->get();
        }
        return [];
    }
    /**
     * 新用户专享
     */
    public static function NewUser($user_id)
    {
        $all = CouponModel::where('send_way', 'NewUser')
                    ->where('source', 'local')
                    ->where('num', '>', 0)
                    ->get();
        if (!$all) {
            return;
        }
        $first = CouponUser::where('user_id', $user_id)
                    ->where('get_way', 'EveryDay')
                    ->first();
        if ($first) {
            return false;
        }
        /**
         * 发放优惠券
         */
        foreach ($all as $key => $value) {
            $days = $value->days;
            $discount = $value->discount;
            $num = $value->num;
            for ($i = 0; $i < $num; $i++) {
                $coupon = new CouponUser();
                $coupon->user_id = $user_id;
                $coupon->coupon_id = $value->id;
                $coupon->status = 'wait';
                $coupon->get_way = 'NewUser';
                $coupon->expire_at = date('Y-m-d', strtotime("+$days day"));
                $coupon->save();
                self::$hasSend[] = $value->id;
            }
        }

        return true;
    }
    /**
     * 每日优惠
     */
    public static function EveryDay($user_id)
    {
        $all = CouponModel::where('send_way', 'EveryDay')
                    ->where('source', 'local')
                    ->where('num', '>', 0)
                    ->get();
        if (!$all) {
            return;
        }
        $first = CouponUser::where('user_id', $user_id)
                    ->whereDate('created_at', date('Y-m-d'))
                    ->where('get_way', 'EveryDay')
                    ->first();
        if ($first) {
            return false;
        }
        /**
         * 发放优惠券
         */
        foreach ($all as $key => $value) {
            $days = $value->days;
            $discount = $value->discount;
            $num = $value->num;
            for ($i = 0; $i < $num; $i++) {
                $coupon = new CouponUser();
                $coupon->user_id = $user_id;
                $coupon->coupon_id = $value->id;
                $coupon->get_way = 'EveryDay';
                $coupon->status = 'wait';
                $coupon->expire_at = date('Y-m-d', strtotime("+$days day"));
                $coupon->save();
                self::$hasSend[] = $value->id;
            }
        }
        return true;
    }
}
