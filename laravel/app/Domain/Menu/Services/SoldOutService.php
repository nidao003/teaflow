<?php

namespace App\Domain\Menu\Services;

use App\Domain\Menu\Models\SoldOutLog;
use App\Domain\Menu\Models\StoreMenuItem;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * 售罄域服务：记录售罄/恢复日志，按门店查询当前售罄商品与日志。
 */
class SoldOutService
{
    public function record(
        int $storeId,
        int $productSkuId,
        string $action,
        ?int $operatorId = null,
        ?string $note = null,
    ): SoldOutLog {
        if ($storeId <= 0 || $productSkuId <= 0) {
            throw new InvalidArgumentException('store_id / product_sku_id 必须为正整数');
        }
        if (! in_array($action, SoldOutLog::ACTIONS, true)) {
            throw new InvalidArgumentException("不支持的售罄动作「{$action}」");
        }

        $trimmedNote = $note === null ? null : trim($note);

        return SoldOutLog::create([
            'store_id' => $storeId,
            'product_sku_id' => $productSkuId,
            'action' => $action,
            'operator_id' => $operatorId,
            'note' => $trimmedNote === null || $trimmedNote === '' ? null : mb_substr($trimmedNote, 0, 255),
        ])->refresh();
    }

    /**
     * 按门店查询当前售罄的门店菜单项。
     */
    public function soldOutItems(int $storeId): Collection
    {
        return StoreMenuItem::query()
            ->where('store_id', $storeId)
            ->where('sold_out', true)
            ->orderBy('product_sku_id')
            ->get();
    }

    /**
     * 按门店查询售罄日志（最新在前）。
     */
    public function logsForStore(int $storeId, int $limit = 50): Collection
    {
        return SoldOutLog::query()
            ->where('store_id', $storeId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }
}
