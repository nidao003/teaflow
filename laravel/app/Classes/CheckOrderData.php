<?php

namespace App\Classes;

use App\Models\Order;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\OrderItem;
use App\Models\OrderPay;
use App\Models\OrderDelivery;
use App\Classes\OrderNumber;
use Illuminate\Support\Facades\Log;
use App\Models\Store;
use App\Classes\MapTiandi;
use App\Models\ProductStore;
use App\Models\Product;
use App\Classes\GetCode;
use DB;

class CheckOrderData
{
    public static function check($store_id, $data)
    {
        $total_quantity = 0;
        $total_amount = 0;
        $total_mix_amount = 0;
        foreach ($data as $key => $value) {
            if (!is_array($value)) {
                Log::error('异常下单：商品数据格式有误');
                return '商品数据格式有误';
            }
            $spec = $value['spec'];
            if (!$spec) {
                Log::error('异常下单：商品杯型不能为空');
                return '商品杯型不能为空';
            }
            $attr = $value['attr'];
            if (!$attr) {
                Log::error('异常下单：商品规格不能为空');
                return '商品规格不能为空';
            }
            $product_id = $value['product_id'];
            $num = $value['num'];
            if (!$product_id || !$num || $num <= 0) {
                Log::error('异常下单：商品数据有误');
                return '商品数据有误';
            }
            $product = Product::idCache($product_id);
            if (!$product) {
                Log::error('异常下单：商品不存在');
                return '商品不存在';
            }
            $specs = $product->specs;
            if (!$specs) {
                Log::error('异常下单：商品规格不存在,请确认平台已正确设置商品信息');
                return '商品规格不存在,请确认平台已正确设置商品信息';
            }
            $spec_price  = [];
            foreach ($specs as $spec_key => $spec_value) {
                $spec_price[$spec_value['type']] = $spec_value['price'];
            }
            if (!isset($spec_price[$spec])) {
                Log::error('异常下单：商品杯型不存在');
                return '商品杯型不存在';
            }
            $price = $spec_price[$spec];
            if ($price <= 0) {
                Log::error('异常下单：商品杯型价格有误');
                return '商品杯型价格有误';
            }
            $product_store = ProductStore::checkCanBuy($store_id, $product_id, '', $num);
            if (!$product_store) {
                Log::error('异常下单：商品库存不足');
                return '商品已下架或库存不足';
            }
            /**
             * 判断商品属性选择是否正确
             */
            $attr_lists = $product['attr_list']->toArray();
            $attr_list = [];
            foreach ($attr_lists as $attr_key => $attr_value) {
                foreach ($attr_value['values'] as $attr_value_key => $attr_value_value) {
                    $attr_list[$attr_value['title']][] = $attr_value_value['value'];
                }
            }
            $count_attr = count($attr_list);
            $re_attr_list = [];
            foreach ($attr_list as $attr_key => $attr_value) {
                foreach ($attr_value as $attr_value_key => $attr_value_value) {
                    $re_attr_list[$attr_value_value] = $attr_key;
                }
            }
            if (is_array($attr)) {
                $data_attr_arr = $attr;
            } else {
                $data_attr_arr = explode("|", $attr);
            }
            $fined_attr = [];
            foreach ($data_attr_arr as $attr_key => $attr_value) {
                if (isset($re_attr_list[$attr_value])) {
                    $fined_attr[$re_attr_list[$attr_value]] = true;
                }
            }
            if (count($fined_attr) != $count_attr) {
                Log::error('异常下单：商品规格有误');
                return '商品规格有误';
            }
            /**
             * 小料
             */
            $mix_amount = 0;
            $mix_lists = $product['mix_list']->toArray() ?? [];
            $mix_list = [];
            $mix = $value['mix'] ?? '';
            if ($mix_lists) {
                foreach ($mix_lists as $mix_key => $mix_value) {
                    $mix_list[$mix_value['title']] = $mix_value['price'] ?: 0;
                }
                if ($mix) {
                    $data_mix_arr = explode("|", $mix);
                    $fined_mix = [];
                    foreach ($data_mix_arr as $mix_value) {
                        if (isset($mix_list[$mix_value])) {
                            $mix_amount = bcadd($mix_amount, $mix_list[$mix_value], 2);
                        }
                    }
                    $total_mix_amount = bcadd($total_mix_amount, $mix_amount, 2);
                }
            }
            /**
             * 处理小计等
            */
            $product_title = $product->title;
            $product_image = $product->image;
            if (!$product_title || !$product_image) {
                Log::error('异常下单：商品信息有误');
                return '商品信息有误';
            }
            $data[$key]['product_title'] = $product_title;
            $data[$key]['product_image'] = $product_image;
            $data[$key]['price'] = $price;
            $data[$key]['quantity'] = $num;
            $data[$key]['spec'] = $spec;
            $data[$key]['attr'] = $attr;
            $data[$key]['mix'] = $mix;
            $data[$key]['data'] = [
                'mix' => $mix_list,
            ];
            $subtotal = bcmul($price, $num, 2);
            //subtotal是总金额
            $subtotal = bcadd($subtotal, $mix_amount, 2);
            $data[$key]['subtotal'] = $subtotal;
            $data[$key]['mix_amount'] = $mix_amount;
            $total_quantity += $num;
            $total_amount = bcadd($total_amount, $subtotal, 2);
        }
        return [
            'total_quantity' => $total_quantity,
            'total_amount' => $total_amount,
            'total_mix_amount' => $total_mix_amount,
            'data' => $data
        ];
    }
}
