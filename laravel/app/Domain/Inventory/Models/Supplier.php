<?php

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    protected $table = 'suppliers';

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class, 'supplier_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
