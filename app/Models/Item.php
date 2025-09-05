<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'category_id',
        'name',
        'description',
        'price',
        'promotional_price',
        'image',
        'is_active',
        'is_available',
        'is_featured',
        'preparation_time',
        'order_display',
        'tags',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'promotional_price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
        'preparation_time' => 'integer',
        'order_display' => 'integer',
        'tags' => 'array',
    ];

    protected $appends = [
        'current_price',
        'has_promotion',
        'discount_percentage',
        'formatted_price',
        'formatted_promotional_price',
        'addons_count',
        'full_image_url',
    ];

    /**
     * Relacionamentos
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(ItemAddon::class);
    }

    public function activeAddons(): HasMany
    {
        return $this->addons()->where('is_active', true);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOnPromotion($query)
    {
        return $query->whereNotNull('promotional_price')
                    ->where('promotional_price', '>', 0);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_display')->orderBy('name');
    }

    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhereJsonContains('tags', $search);
        });
    }

    public function scopePriceRange($query, $minPrice = null, $maxPrice = null)
    {
        if ($minPrice !== null) {
            $query->where('price', '>=', $minPrice);
        }
        
        if ($maxPrice !== null) {
            $query->where('price', '<=', $maxPrice);
        }
        
        return $query;
    }

    public function scopePreparationTime($query, $maxTime)
    {
        return $query->where('preparation_time', '<=', $maxTime);
    }

    public function scopeByTags($query, array $tags)
    {
        foreach ($tags as $tag) {
            $query->whereJsonContains('tags', $tag);
        }
        
        return $query;
    }

    /**
     * Accessors
     */
    public function getCurrentPriceAttribute(): float
    {
        return $this->promotional_price && $this->promotional_price > 0 
            ? $this->promotional_price 
            : $this->price;
    }

    public function getHasPromotionAttribute(): bool
    {
        return $this->promotional_price && $this->promotional_price > 0 && $this->promotional_price < $this->price;
    }

    public function getDiscountPercentageAttribute(): ?float
    {
        if (!$this->has_promotion) {
            return null;
        }
        
        return round((($this->price - $this->promotional_price) / $this->price) * 100, 1);
    }

    public function getFormattedPriceAttribute(): string
    {
        return 'R$ ' . number_format($this->price, 2, ',', '.');
    }

    public function getFormattedPromotionalPriceAttribute(): ?string
    {
        return $this->promotional_price 
            ? 'R$ ' . number_format($this->promotional_price, 2, ',', '.') 
            : null;
    }

    public function getAddonsCountAttribute(): int
    {
        return $this->activeAddons()->count();
    }

    public function getFullImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    /**
     * Métodos de verificação
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isAvailable(): bool
    {
        return $this->is_available;
    }

    public function isFeatured(): bool
    {
        return $this->is_featured;
    }

    public function hasAddons(): bool
    {
        return $this->activeAddons()->exists();
    }

    /**
     * Métodos de gerenciamento
     */
    public function activate(): bool
    {
        $this->is_active = true;
        return $this->save();
    }

    public function deactivate(): bool
    {
        $this->is_active = false;
        return $this->save();
    }

    public function markAsAvailable(): bool
    {
        $this->is_available = true;
        return $this->save();
    }

    public function markAsUnavailable(): bool
    {
        $this->is_available = false;
        return $this->save();
    }

    public function markAsFeatured(): bool
    {
        $this->is_featured = true;
        return $this->save();
    }

    public function unmarkAsFeatured(): bool
    {
        $this->is_featured = false;
        return $this->save();
    }

    public function setPromotionalPrice(float $price): bool
    {
        if ($price >= $this->price) {
            return false;
        }
        
        $this->promotional_price = $price;
        return $this->save();
    }

    public function removePromotionalPrice(): bool
    {
        $this->promotional_price = null;
        return $this->save();
    }

    public function updateDisplayOrder(int $order): bool
    {
        $this->order_display = $order;
        return $this->save();
    }

    /**
     * Métodos estáticos
     */
    public static function getNextOrder($categoryId): int
    {
        return self::where('category_id', $categoryId)->max('order_display') + 1;
    }

    public static function reorderItems($categoryId, array $itemIds): bool
    {
        foreach ($itemIds as $index => $itemId) {
            self::where('id', $itemId)
                ->where('category_id', $categoryId)
                ->update(['order_display' => $index + 1]);
        }
        
        return true;
    }

    /**
     * Métodos de estatísticas
     */
    public function getStats(): array
    {
        return [
            'total_orders' => $this->orderItems()->count(),
            'total_quantity_sold' => $this->orderItems()->sum('quantity'),
            'total_revenue' => $this->orderItems()
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.status', Order::STATUS_DELIVERED)
                ->sum('order_items.total_price'),
            'average_rating' => $this->orderItems()
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.status', Order::STATUS_DELIVERED)
                ->whereNotNull('orders.rating')
                ->avg('orders.rating'),
            'last_ordered_at' => $this->orderItems()
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->max('orders.created_at'),
        ];
    }

    public function calculateTotalPrice(array $selectedAddons = []): float
    {
        $total = $this->current_price;
        
        if (!empty($selectedAddons)) {
            $addonsTotal = $this->activeAddons()
                ->whereIn('id', $selectedAddons)
                ->sum('price');
            
            $total += $addonsTotal;
        }
        
        return $total;
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($item) {
            if (is_null($item->order_display)) {
                $item->order_display = self::getNextOrder($item->category_id);
            }
        });
    }
}