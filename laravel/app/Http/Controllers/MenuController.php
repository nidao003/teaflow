<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use App\Http\Middleware\Auth;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\User;

/**
 * @Apidoc\Title("通用-菜单")
 */
#[Prefix('api/v1/menu')]
#[Middleware(Auth::class)]
class MenuController extends Controller
{
    /**
     * @Apidoc\Title("商家端菜单")
     * @Apidoc\Tag("PC")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/menu/index")
     *
     * @Apidoc\Returned("id", type="string", desc="用户ID")
     * @Apidoc\Returned("phone", type="string", desc="手机号")
     * @Apidoc\Returned("oauth",type="object",desc="oauth",children={
     *     @Apidoc\Param ("openid",type="string",desc=""),
     *     @Apidoc\Param ("country_code",type="string",desc=""),
     *     @Apidoc\Param ("phone",type="string",desc=""),
     *     @Apidoc\Param ("pure_phone",type="string",desc=""),
     *     @Apidoc\Param ("unionid",type="string",desc=""),
     *     @Apidoc\Param ("expires_in",type="string",desc=""),
     * })
     *
     */
    #[Post('index')]
    public function index(Request $request)
    {
        /**
         * 取当前登录用户
         */
        $user = Author::user();
        switch ($user->type) {
            case 'admin':
                $data = $this->getAdminMenu();
                break;
            case 'seller':
                $data = $this->getSellerMenu();
                break;
            case 'store':
                $data = $this->getSellerMenu();
                break;
        }
        return $this->success("", $data);
    }

    protected function getAdminMenu()
    {
        return [
            [
                'title' => '商家管理',
                'icon' => 'Shop',
                'children' => [
                    ['title' => '商家列表', 'url' => '/seller/list','icon' => 'List'],
                    ['title' => '添加商家', 'url' => '/seller/form','icon' => 'Plus'],
                ]
            ],
            [
                'title' => '门店管理',
                'icon' => 'Suitcase',
                'children' => [
                    ['title' => '门店列表', 'url' => '/store/list','icon' => 'List'],
                    ['title' => '添加门店', 'url' => '/store/form','icon' => 'Plus'],
                ]
            ],
            [
                'title' => '订单管理',
                'icon' => 'Document',
                'children' => [
                    ['title' => '订单管理', 'url' => '/orders/list','icon' => 'List'],
                    ['title' => '订单概况', 'url' => '/orders/stat','icon' => 'Histogram'],
                    ['title' => '按月统计', 'url' => '/orders/month','icon' => 'Wallet'],
                    ['title' => '按天统计', 'url' => '/orders/day','icon' => 'Sunny'],
                    ['title' => '申请退款', 'url' => '/orders/refund','icon' => 'Wallet'],
                    ['title' => '退款记录', 'url' => '/orders/refund-list','icon' => 'Tickets'],
                ]
            ],
            [
                'title' => '商品管理',
                'icon' => 'Goods',
                'children' => [
                    ['title' => '商品上下架', 'url' => '/products/updown','icon' => 'CoffeeCup'],
                    ['title' => '商品管理', 'url' => '/products/list','icon' => 'List'],
                    ['title' => '商品类型', 'url' => '/product_type/list','icon' => 'Document'],
                    ['title' => '属性', 'url' => '/attr/list','icon' => 'IceCream'],
                    ['title' => '小料', 'url' => '/mix/list','icon' => 'Mug'],
                ]
            ],
            [
                'title' => '优惠管理',
                'icon' => 'Discount',
                'children' => [
                    ['title' => '优惠券 列表', 'url' => '/coupon/list','icon' => 'Ticket'],
                    ['title' => '优惠券 领取记录', 'url' => '/coupon_get/list','icon' => 'Document'],
                    ['title' => '会员日半价','url' => '/vip/half','icon' => 'Filter'],
                ]
            ],
            [
                'title' => '文章管理',
                'icon' => 'Reading',
                'children' => [
                    ['title' => '文章列表', 'url' => '/articles/list','icon' => 'Document'],
                    ['title' => '文章类型', 'url' => '/article_type/list','icon' => 'Document'],
                ]
            ],
            [
                'title' => '会员管理',
                'icon' => 'User',
                'children' => [
                    ['title' => '会员列表', 'url' => '/users/list','icon' => 'List'],
                    ['title' => '充值及消费记录', 'url' => '/users/money','icon' => 'Wallet'],
                ]
            ],
            [
                'title' => '小程序模板',
                'icon' => 'List',
                'children' => [
                    ['title' => '首页模板', 'url' => '/template/list','icon' => 'List'],
                ]
            ],
            [
                'title' => '系统设置',
                'url' => '/settings',
                'icon' => 'Setting',
                'children' => [
                    ['title' => '支付设置', 'url' => '/settings/payment','icon' => 'Wallet'],
                    ['title' => '小程序设置', 'url' => '/settings/applet','icon' => 'Baseball'],
                    ['title' => '打印机设置', 'url' => '/settings/printeruk','icon' => 'Printer'],
                    ['title' => '地图设置', 'url' => '/settings/map','icon' => 'MapLocation'],
                    ['title' => '搜索关键词', 'url' => '/settings/keywords','icon' => 'Search'],
                ]
            ]
        ];
    }

    protected function getSellerMenu()
    {
        $menu = [];
        $menu[] = [
            'title' => '代客下单',
            'icon' => 'ShoppingCart',
            'url' => '/seller_manage/orders/create'
        ];
        $menu[] = [
            'title' => '商品上下架',
            'icon' => 'Goods',
            'url' => '/seller_manage/products/updown'
        ];

        $menu[] = [
            'title' => '订单列表',
            'icon' => 'List',
            'url' => '/seller_manage/orders/list'
        ];

        /**
         * 判断商家下有几个门店
         */
        $user = Author::user();
        if ($user->type == 'seller') {
            $menu[] = [
                'title' => '订单统计',
                'icon' => 'Histogram',
                'children' => [
                    ['title' => '概述', 'url' => '/seller_manage/orders/statistics','icon' => 'Histogram'],
                    ['title' => '按月', 'url' => '/seller_manage/orders/month','icon' => 'Wallet'],
                    ['title' => '按天', 'url' => '/seller_manage/orders/day','icon' => 'Sunny'],
                ]
            ];

            $seller = \App\Models\Seller::where('user_id', $user->id)->first();
            $stores = \App\Models\Store::where('seller_id', $seller->id)->get();
            if ($stores->count() > 1) {
                $menu[] =  [
                    'title' => '门店管理',
                    'icon' => 'Suitcase',
                    'url' => '/seller_manage/store/list'
                ];
            }
        }
        $menu[] =  [
            'title' => '系统设置',
            'url' => '/settings',
            'icon' => 'Setting',
            'children' => [
                ['title' => '营业状态', 'url' => '/settings/open','icon' => 'Clock'],
                ['title' => '打印机设置', 'url' => '/settings/printer','icon' => 'Printer'],
                ['title' => '系统设置', 'url' => '/settings','icon' => 'Setting'],
            ]
        ];
        return $menu;

    }
}
