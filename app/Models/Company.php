<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'email',
        'phone',
        'whatsapp',
        'address',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
        'zipcode',
        'latitude',
        'longitude',
        'logo',
        'banner',
        'is_active',
        'is_open',
        'opening_hours',
        'delivery_fee',
        'minimum_order',
        'delivery_time',
        'delivery_radius',
        'accepts_cash',
        'accepts_card',
        'accepts_pix',
        'rating',
        'total_reviews',
        'featured',
        'order_display',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_open' => 'boolean',
        'opening_hours' => 'array',
        'delivery_fee' => 'decimal:2',
        'minimum_order' => 'decimal:2',
        'delivery_time' => 'integer',
        'delivery_radius' => 'decimal:2',
        'accepts_cash' => 'boolean',
        'accepts_card' => 'boolean',
        'accepts_pix' => 'boolean',
        'rating' => 'decimal:1',
        'total_reviews' => 'integer',
        'featured' => 'boolean',
        'order_display' => 'integer',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    protected $appends = [
        'full_address',
        'is_open_now',
        'accepted_payment_methods',
        'logo_url',
        'banner_url',
    ];

    /**
     * Relacionamentos
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function deliverymen(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_deliverymen')
                    ->withPivot('is_active')
                    ->withTimestamps();
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function activeCategories(): HasMany
    {
        return $this->categories()->active()->ordered();
    }

    public function activeItems(): HasMany
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

    public function scopeOpen($query)
    {
        return $query->where('is_open', true);
    }

    public function scopeByLocation($query, $latitude, $longitude, $radius = null)
    {
        $radius = $radius ?? 10; // 10km por padrão
        
        return $query->selectRaw(
            '*, (
                6371 * acos(
                    cos(radians(?))
                    * cos(radians(latitude))
                    * cos(radians(longitude) - radians(?))
                    + sin(radians(?))
                    * sin(radians(latitude))
                )
            ) AS distance',
            [$latitude, $longitude, $latitude]
        )->having('distance', '<=', $radius)
         ->orderBy('distance');
    }

    public function scopeByNameOrDescription($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
    }

    public function scopeFeatured($query)
    {
        return $query->where('featured', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_display')->orderBy('name');
    }

    /**
     * Accessors
     */
    public function getFullAddressAttribute(): string
    {
        $address = $this->address;
        
        if ($this->number) {
            $address .= ', ' . $this->number;
        }
        
        if ($this->complement) {
            $address .= ', ' . $this->complement;
        }
        
        if ($this->neighborhood) {
            $address .= ' - ' . $this->neighborhood;
        }
        
        if ($this->city) {
            $address .= ', ' . $this->city;
        }
        
        if ($this->state) {
            $address .= ' - ' . $this->state;
        }
        
        if ($this->zipcode) {
            $address .= ' - CEP: ' . $this->zipcode;
        }
        
        return $address;
    }

    public function getIsOpenNowAttribute(): bool
    {
        if (!$this->is_active || !$this->is_open) {
            return false;
        }
        
        if (!$this->opening_hours) {
            return true;
        }
        
        $now = Carbon::now();
        $dayOfWeek = strtolower($now->format('l')); // monday, tuesday, etc.
        
        if (!isset($this->opening_hours[$dayOfWeek])) {
            return false;
        }
        
        $hours = $this->opening_hours[$dayOfWeek];
        
        if (!$hours['open'] || !$hours['close']) {
            return false;
        }
        
        $openTime = Carbon::createFromFormat('H:i', $hours['open']);
        $closeTime = Carbon::createFromFormat('H:i', $hours['close']);
        
        return $now->between($openTime, $closeTime);
    }

    public function getAcceptedPaymentMethodsAttribute(): array
    {
        $methods = [];
        
        if ($this->accepts_cash) {
            $methods[] = 'Dinheiro';
        }
        
        if ($this->accepts_card) {
            $methods[] = 'Cartão';
        }
        
        if ($this->accepts_pix) {
            $methods[] = 'PIX';
        }
        
        return $methods;
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }

    public function getBannerUrlAttribute(): ?string
    {
        return $this->banner ? asset('storage/' . $this->banner) : null;
    }

    /**
     * Métodos de negócio
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function open(): bool
    {
        $this->is_open = true;
        return $this->save();
    }

    public function close(): bool
    {
        $this->is_open = false;
        return $this->save();
    }

    public function calculateDeliveryFee($latitude, $longitude): float
    {
        $distance = $this->calculateDistance($latitude, $longitude);
        
        if ($distance <= 3) {
            return $this->delivery_fee;
        }
        
        // Taxa adicional por km extra
        $extraKm = $distance - 3;
        $extraFee = $extraKm * 2.00; // R$ 2,00 por km extra
        
        return $this->delivery_fee + $extraFee;
    }

    public function calculateDistance($latitude, $longitude): float
    {
        if (!$this->latitude || !$this->longitude) {
            return 0;
        }
        
        $earthRadius = 6371; // Raio da Terra em km
        
        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($latitude);
        $lonTo = deg2rad($longitude);
        
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }

    public function isWithinDeliveryRadius($latitude, $longitude): bool
    {
        $distance = $this->calculateDistance($latitude, $longitude);
        return $distance <= $this->delivery_radius;
    }

    public function getStats(): array
    {
        return [
            'total_orders' => $this->orders()->count(),
            'total_revenue' => $this->orders()->where('status', Order::STATUS_DELIVERED)->sum('total_amount'),
            'average_rating' => $this->rating,
            'total_reviews' => $this->total_reviews,
            'active_items' => $this->activeItems()->count(),
            'active_categories' => $this->activeCategories()->count(),
            'orders_today' => $this->orders()->today()->count(),
            'revenue_today' => $this->orders()->today()->where('status', Order::STATUS_DELIVERED)->sum('total_amount'),
            'orders_this_week' => $this->orders()->thisWeek()->count(),
            'revenue_this_week' => $this->orders()->thisWeek()->where('status', Order::STATUS_DELIVERED)->sum('total_amount'),
            'orders_this_month' => $this->orders()->thisMonth()->count(),
            'revenue_this_month' => $this->orders()->thisMonth()->where('status', Order::STATUS_DELIVERED)->sum('total_amount'),
        ];
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($company) {
            if (is_null($company->order_display)) {
                $company->order_display = self::max('order_display') + 1;
            }
        });
    }
}