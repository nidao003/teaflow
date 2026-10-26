<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Inventory\Enums\WarehouseType;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $table = 'warehouses';

    protected $casts = [
        'store_id' => 'integer',
        'type' => WarehouseType::class,
    ];

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class, 'warehouse_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'warehouse_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'warehouse_id');
    }

    public function wastages(): HasMany
    {
        return $this->hasMany(Wastage::class, 'warehouse_id');
    }

    public function isCentral(): bool
    {
        return $this->type === WarehouseType::CENTRAL;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
