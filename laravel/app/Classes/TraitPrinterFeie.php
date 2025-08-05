<?php

namespace App\Classes;

use Power\TicketPrinter\Feie;
use Power\TicketPrinter\Label;
use App\Models\Order;
use App\Models\OrderPrinter;
use App\Classes\Setting;
use App\Classes\StoreSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

trait TraitPrinterFeie
{
    protected $feie_label_obj;
    protected $user_info;
    protected $phone;
    public function initFeie()
    {
        $f = new Feie();
        $f->user = Setting::get("feie_user");
        $f->ukey = Setting::get("feie_key");
        $f->backUrl = env('APP_URL') . '/api_printer_callback';
        return $f;
    }

    public function doInfo($sn)
    {
        $f = $this->initFeie();
        if (strpos($sn, '#') !== false) {
            $sn = substr($sn, 0, strpos($sn, '#'));
        }
        $res = $f->info($sn);
        if ($res['ret'] != 0) {
            return [
                'ret' => 1,
            ];
        }
        $model = $res['data']['model'];
        $status = $res['data']['status'];
        return [
            'ret' => $res['ret'],
            'model' => $model,
            'status' => $status,
        ];
    }

    public function doBind($sn)
    {
        $f = $this->initFeie();
        $res = $this->doInfo($sn);
        if ($res['ret'] != 0) {
            return $f->add($sn);
        }
    }

    public function doPrint($order_number)
    { 
        $f = $this->initFeie();
        $f_label = $this->initFeie();
        
        $order = Order::where('order_number', $order_number)->first();
        if (!$order) {
            Log::error('打印机 订单不存在');
            return $this->error('订单不存在');
        }  
        $store_id = $order->store_id; 
        $f->sn = StoreSetting::get($store_id, "feie_58");
        $f_label->sn = StoreSetting::get($store_id, "feie_label");
        $this->feie_label_obj = $f_label;

        Log::info('>>> 打印机： 小票机SN：'. $f->sn." 标签机SN：" . $f_label->sn);

        $orderPrinter = OrderPrinter::where('order_number', $order_number) 
            ->first();
        $items = $order->orderItems;
        $this->user_info = $order->user;
        $phone = $this->user_info->phone ?? '';
        if ($phone) {
            $this->phone = substr($phone, -4);
        }
        $list = [];
        foreach ($items as $item) {
            $title = $item->product_title;
            $sub_title = '';
            $has_attr = false;
            $sub_title = '';
            $title_1 = $title;
            if ($item->attr) {
                $attr = $item->attr;
                $attr = str_replace('|', ' ', $attr);
                $attr = trim($attr);
                $sub_title .= ' ' . $attr;
                $has_attr = true;
            }
            if ($item->mix) {
                $mix =  $item->mix;
                $mix = str_replace('|', ' ', $mix);
                $mix = trim($mix);
                $sub_title .= ' ' . $mix;
                $has_attr = true;
            }
            if ($has_attr) {
                $title_1 = $title_1 . ' (' . trim($sub_title) . ')';
            }
            $list[] = [
                'num' => $item->quantity,
                'title' => $title_1,
            ];
            $label_list[] = [
                'num' => $item->quantity,
                'title' => $title,
                'sub_title' => trim($sub_title),
            ];
        }
        /**
         * 打印标签
         */
        Log::info('>>> 打印机： 打印标签');
        foreach ($label_list as $v) {
            $num = $v['num'];
            if ($num > 1) {
                for ($i = 1; $i <= $num; $i++) {
                    $this->doPrintLabel($order, $v, $i, $num);
                }
            } else {
                $this->doPrintLabel($order, $v);
            }
        }
        Log::info('>>> 打印机： 打印小票');
        /**
         * 打印58mm
         */
        $top = [
            'num' => '数量|1',
            'title' => '名称|*',
            'tag' => "L|W|br",
        ];
        $order_number = $order->order_number;
        $arr = [];
        $arr[] = [
            'title' => $order->store->title,
            'tag' => 'cb|br'
        ];
        $cache_key = Qr::getType('out') . $order_number;
        Cache::put($cache_key, json_encode([
            'order_number' => $order_number,
            'store_id' => $store_id,
        ]), now()->addMinutes(60 * 60));

        $arr[] = [
            'title' => $cache_key,
            'tag' => 'code_abc|cb|l|br'
        ];

        $arr[] = [
            'title' => '取餐码： ' . $order->get_code,
            'tag' => 'cb|br'
        ];

        $delivery_status = $order->delivery_status;
        $label = "自提";
        switch ($delivery_status) {
            case 'delivery':
                $label = '外卖';
                break;
        }

        $arr[] = [
            'title' => $label,
            'tag' => 'cb|br'
        ];

        $arr[] = [
            'title' => '订单号： ' . $order_number,
            'tag' => 'br'
        ];
        if ($phone) {
            $arr[] = [
                'title' => '手机尾号： ' . $phone,
                'tag' => 'br'
            ];
        }
        $arr[] = [
            'tag' => 'line|br',
        ];
        $arr[] = [
            'top' => $top,
            'list' => $list,
        ];
        $arr[] = [
            'tag' => 'line|br',
        ];
        $arr[] = [
            'title' => "小计：" . $order->paid_amount,
            'tag' => 'r|br|',
        ];
        if($order->note){
            $arr[] = [
                'title' => '备注：'. $order->note,
                'tag' => 'br'
            ];
        }
        $arr[] = [
            'title' => '打印时间：' . now(),
            'tag' => 'br|br'
        ];

        $arr[] = ['tag' => 'cut'];
        $res = $f->print($arr);
        if ($res['ret'] != 0) {
            return $this->error($res['msg']);
        }
        $sn = $res['data'];
        if (!$orderPrinter) {
            OrderPrinter::create([
                'order_number' => $order_number,
                'printer_no' => $sn,
                'status' => 'printing',
                'type' => '58mm',
            ]);
        }
    }

    protected function doPrintLabel($order, $v, $i = 1, $num = 1)
    {
        $delivery_status = $order->delivery_status;
        $label = "自提";
        switch ($delivery_status) {
            case 'delivery':
                $label = '外卖';
                break;
        }
        $get_code = $order->get_code;
        $f_label = $this->feie_label_obj;
        $title = $v['title'];
        $sub_title = $v['sub_title'] ?? "";
        $layoutData = [
            [
                'title' => $get_code,
                'x' => 1,
                'y' => 1,
            ],
            ['title' => date('m-d/H:i'), 'font' => 12, 'x' => 20, 'y' => 1,],
            [
                'title' => $title,
                'x' => 1,
                'y' => 6,
                'w' => 1,
                'h' => 2
            ],
        ];
        if ($sub_title) {
            $layoutData[] = [
                'title' => $sub_title,
                'x' => 1,
                'y' => 13 // 毫米
            ];
        }

        $desc = $order->note;
        if ($desc) {
            //如果文字过长，需要截取
            $desc = \Power\Str::cut($desc, 15);
            $layoutData[] = [
                'title' => $desc,
                'x' => 1,
                'y' => 17
            ];
        }
        if ($this->phone) {
            $layoutData[] = [
                'title' => '手机尾号:' . $this->phone,
                'font' => 12,
                'y' => 25,
                'x' =>  18
            ];
        }

        /*$layoutData[] = [
            'type' => 'QR',
            'text' => 'QR123',
            'x' => 23,
            'y' => 6,
            'e' => 'L',
            'w' => 5,
        ];*/

        $data = Label::run($layoutData);
        $res = $f_label->printMsgLabel($f_label->sn, $data, 1);
        return $res;
    }

    public function doBack($input = [])
    {
        $order_id = $input['orderId'] ?? '';
        $status   = $input['status'] ?? '';
        $sign     = $input['sign'] ?? '';
        $stime    = $input['stime'] ?? '';
        $str = '';
        $public_key = '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA2LDlvyuClqrnKW01FqYg
valPy1/e09ZWlvjb5Nu+0T1PsGhKjF4WBb+D7x3Dy/Db5IHMcpG/Eps6ew6n/6rw
v8Ctu+uZI33YNv9sqAMPjG2EN+WcqCrVrMGUmjITVpIkQEjTdkuismf+VL3x+eJo
W1y/TaLb9vchReBc6IZowRu2yItC+tFbock5Nupsl5uOCKltm3s0VqtiHUrpgVeV
8dVJHLhmLENnLgcTqrkZeKogFDT+fTOhzQPZVEqQgdat/6kcmD44lN4UI7EvVNfe
amwRLgy4e/CpInD9cql+t5eiRLem0+rgPq9RLivM1pRt67crH0WGY1xXtAtzWO0M
MwIDAQAB
-----END PUBLIC KEY-----';
        if ($order_id && $status && $sign) {
            $data_to_verify = $this->generateSignatureString($input);
            $openssl_get = openssl_get_publickey($public_key);
            if ($sign) {
                $sign = base64_decode($sign);
            }
            $res = openssl_verify($data_to_verify, $sign, $openssl_get, OPENSSL_ALGO_SHA256);
            if ($res) {
                OrderPrinter::where('printer_no', $order_id)
                    ->update([
                        'status' => 'success'
                    ]);
                echo 'SUCCESS';
            }
        } else {
            echo 'FAIL';
        }
    }


    protected function generateSignatureString($input)
    {
        // 过滤参数
        $filteredParams = [];
        foreach ($input as $key => $value) {
            // 剔除字节类型参数和 sign 字段
            if (!is_resource($value) && $key !== 'sign' && !empty($value)) {
                $filteredParams[$key] = $value;
            }
        }

        // 按键值的ASCII码递增顺序排序
        ksort($filteredParams, SORT_STRING);

        // 组合参数
        $signatureString = '';
        foreach ($filteredParams as $key => $value) {
            if ($signatureString !== '') {
                $signatureString .= '&';
            }
            $signatureString .= $key . '=' . $value;
        }

        return $signatureString;
    }
}
