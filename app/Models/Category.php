<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'image',
        'is_active',
        'order_display',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order_display' => 'integer',
    ];

    protected $appends = [
        'items_count',
        'active_items_count',
        'image_url',
    ];

    /**
     * Relacionamentos
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function activeItems(): HasMany
    {
        return $this->items()->active();
    }

    public function availableItems(): HasMany
    {
        return $this->items()->active()->available();
    }

    public function featuredItems(): HasMany
    {
        return $this->items()->featured()->active()->available();
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_display')->orderBy('name');
    }

    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    public function scopeWithItems($query)
    {
        return $query->has('items');
    }

    public function scopeWithActiveItems($query)
    {
        return $query->has('activeItems');
    }

    /**
     * Accessors
     */
    public function getItemsCountAttribute(): int
    {
        return $this->items()->count();
    }

    public function getActiveItemsCountAttribute(): int
    {
        return $this->activeItems()->count();
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/' . $this->image) : null;
    }

    /**
     * Métodos de negócio
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function hasItems(): bool
    {
        return $this->items()->exists();
    }

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

    public function updateOrder(int $order): bool
    {
        $this->order_display = $order;
        return $this->save();
    }

    public static function getNextOrder($companyId): int
    {
        return self::where('company_id', $companyId)->max('order_display') + 1;
    }

    public static function reorderCategories($companyId, array $categoryIds): bool
    {
        foreach ($categoryIds as $index => $categoryId) {
            self::where('id', $categoryId)
                ->where('company_id', $companyId)
                ->update(['order_display' => $index + 1]);
        }
        
        return true;
    }

    public function getStats(): array
    {
        return [
            'total_items' => $this->items()->count(),
            'active_items' => $this->activeItems()->count(),
            'available_items' => $this->availableItems()->count(),
            'featured_items' => $this->featuredItems()->count(),
            'total_orders' => $this->items()->withCount('orderItems')->get()->sum('order_items_count'),
            'total_revenue' => $this->items()
                ->join('order_items', 'items.id', '=', 'order_items.item_id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.status', Order::STATUS_DELIVERED)
                ->sum('order_items.total_price'),
        ];
    }

    public function getBestSellingItems($limit = 5)
    {
        return $this->items()
            ->withCount('orderItems')
            ->orderBy('order_items_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($category) {
            if (is_null($category->order_display)) {
                $category->order_display = self::getNextOrder($category->company_id);
            }
        });
    }
}