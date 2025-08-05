<?php

namespace App\Http\Controllers\Seller;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Put;
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use App\Http\Middleware\SellerAuth;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Store;
use App\Models\StoreSetting;

/**
 * @Apidoc\Title("商家-门店设置字段")
 */
#[Prefix('api/v1/seller/store_setting')]
#[Middleware(SellerAuth::class)]
class StoreSettingController extends \App\Http\Controllers\Controller
{
    use \App\Classes\TraitPrinterFeie;
    /**
     * @Apidoc\Title("获取")
     * @Apidoc\Tag("门店")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/store_setting/get_setting")
     */
    #[Post('get_setting')]
    public function get_setting(Request $request)
    {
        $store_id = \App\Classes\Store::getId();
        $all = StoreSetting::where('store_id', $store_id)->get();
        $data = [];
        foreach ($all as $item) {
            $data[$item->key] = $item->value;
        }
        return $this->success("", $data);
    }
    /**
     * @Apidoc\Title("设置")
     * @Apidoc\Tag("门店")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/store_setting/update_setting")
     * @Apidoc\Query("test", type="string",require=false, desc="")
     */
    #[Post('update_setting')]
    public function update_setting(Request $request)
    {
        $store_id = \App\Classes\Store::getId();
        $all = $request->all();
        foreach ($all as $key => $value) {
            if ($key == 'store_id' || substr($key, 0, 1) == '_') {
                continue;
            }
            $item = StoreSetting::where('store_id', $store_id)->where('key', $key)->first();
            if (!$item) {
                $item = new StoreSetting();
                $item->store_id = $store_id;
                $item->key = $key;
            }
            $item->value = $value;
            $item->save();
        }
        $feie_58 = $all['feie_58'];
        $feie_58_key = $all['feie_58_key'] ?? '';
        $feie_label = $all['feie_label'];
        $feie_label_key = $all['feie_label_key'] ?? '';
        if ($feie_58 && $feie_58_key) {
            $res = $this->doBind($feie_58.'#'.$feie_58_key.'#小票打印机');
            $err = $res['data']['no'][0] ?? '';
            if ($err) {
                return $this->error($err);
            }
        }
        if ($feie_label && $feie_label_key) {
            $res = $this->doBind($feie_label.'#'.$feie_label_key.'#标签打印机');
            $err = $res['data']['no'][0] ?? '';
            if ($err) {
                return $this->error($err);
            }
        }
        return $this->success("更新成功");
    }

}
