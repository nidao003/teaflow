<?php
/**
 * 未支付订单过期退回优惠券
 *
 * php artisan app:order-refund
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Classes\Setting;
use App\Models\Order;
use App\Classes\Coupon;

class OrderRefund extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:order-refund';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '未支付订单过期退回优惠券';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $all = Order::where('status', 'wait')->limit(1000)->get();
        $i = 0;
        if ($all) {
            foreach ($all as $v) {
                Coupon::refund($v->id);
                $i++;
            }
        }
        $this->info('操作成功, 共 '.$i.' 条数据');
    }
}
