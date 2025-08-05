<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class OrderStoreExport implements FromView
{
    protected $store_id;
    public function __construct($store_id)
    {
        $this->store_id = $store_id;
    }
    public function view(): View
    {
        $start_date	= request()->input('start_date', date('Y-m-d', strtotime('-1 month')));
        $end_date	= request()->input('end_date', date('Y-m-d'));
        $query = Order::whereBetween('created_at', [$start_date.' 00:00:00', $end_date.' 23:59:59'])
                ->where('store_id', $this->store_id)
                ->orderBy('id', 'desc');
        $orders = $query->get();
        $total  = $query->sum("total_quantity");
        $amount = $query->sum('total_amount');
        return view('exports.orders', [
            'orders' => $orders,
            'total' => $total,
            'amount' => bcmul($amount, 1, 2)
        ]);
    }
}
