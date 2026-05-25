<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'barcode',
        'description',
        'price',
        'cost',
        'stock',
        'min_stock',
        'unit',
        'status',
        'track_inventory',
    ];

    protected $casts = [
        'price'           => 'decimal:2',
        'cost'            => 'decimal:2',
        'track_inventory' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function storeStocks(): HasMany
    {
        return $this->hasMany(StoreStock::class);
    }

    public function stockForStore(int $storeId): int
    {
        return $this->storeStocks->where('store_id', $storeId)->first()?->stock ?? 0;
    }

    public function isLowStock(): bool
    {
        return $this->stock <= $this->min_stock && $this->min_stock > 0;
    }
}
