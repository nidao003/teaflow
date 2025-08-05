<?php
/**
 * 清空打印机队列
 *
 * php artisan app:clean-printer
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/> 
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Classes\Printer\Feie;
use App\Classes\Setting;
use App\Models\StoreSetting;

class CleanPrinter extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clean-printer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '清空打印机队列，这会清空所有打印任务';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $f = new Feie();
        $f->user = Setting::get("feie_user");
        $f->ukey = Setting::get("feie_key");

        //58mm小票机
        $all = StoreSetting::where('key', 'feie_58')->get();
        if ($all) {
            foreach ($all as $v) {
                $f->clear($v->value);
            }
        }
        //标签机
        $all = StoreSetting::where('key', 'feie_label')->get();
        if ($all) {
            foreach ($all as $v) {
                $f->clear($v->value);
            }
        }

        $this->info('清空打印机队列成功');
    }
}
