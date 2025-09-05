<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    // Status do pedido
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PREPARING = 'preparing';
    const STATUS_READY = 'ready';
    const STATUS_DISPATCHED = 'dispatched';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    // Status do pagamento
    const PAYMENT_PENDING = 'pending';
    const PAYMENT_PROCESSING = 'processing';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_FAILED = 'failed';
    const PAYMENT_REFUNDED = 'refunded';

    // Métodos de pagamento
    const PAYMENT_CASH = 'cash';
    const PAYMENT_CARD = 'card';
    const PAYMENT_PIX = 'pix';

    protected $fillable = [
        'user_id',
        'company_id',
        'deliveryman_id',
        'coupon_id',
        'order_number',
        'status',
        'payment_status',
        'payment_method',
        'subtotal',
        'delivery_fee',
        'discount_amount',
        'total_amount',
        'delivery_address',
        'delivery_number',
        'delivery_complement',
        'delivery_neighborhood',
        'delivery_city',
        'delivery_state',
        'delivery_zipcode',
        'delivery_latitude',
        'delivery_longitude',
        'delivery_instructions',
        'preparation_time',
        'estimated_delivery_time',
        'actual_delivery_time',
        'confirmed_at',
        'prepared_at',
        'dispatched_at',
        'delivered_at',
        'cancelled_at',
        'cancelled_reason',
        'cancelled_by',
        'rating',
        'rating_comment',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'delivery_latitude' => 'decimal:8',
        'delivery_longitude' => 'decimal:8',
        'preparation_time' => 'integer',
        'estimated_delivery_time' => 'datetime',
        'actual_delivery_time' => 'datetime',
        'confirmed_at' => 'datetime',
        'prepared_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'rating' => 'integer',
    ];

    protected $appends = [
        'formatted_subtotal',
        'formatted_delivery_fee',
        'formatted_discount_amount',
        'formatted_total_amount',
        'full_delivery_address',
        'status_label',
        'payment_status_label',
        'can_cancel',
        'can_rate',
        'delivery_time_remaining',
        'is_late',
    ];

    /**
     * Relacionamentos
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function deliveryman(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deliveryman_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    /**
     * Scopes
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public function scopePreparing($query)
    {
        return $query->where('status', self::STATUS_PREPARING);
    }

    public function scopeReady($query)
    {
        return $query->where('status', self::STATUS_READY);
    }

    public function scopeDispatched($query)
    {
        return $query->where('status', self::STATUS_DISPATCHED);
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_DELIVERED, self::STATUS_CANCELLED]);
    }

    public function scopeCompleted($query)
    {
        return $query->whereIn('status', [self::STATUS_DELIVERED, self::STATUS_CANCELLED]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', Carbon::today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('order_number', 'like', "%{$search}%")
              ->orWhereHas('user', function ($userQuery) use ($search) {
                  $userQuery->where('name', 'like', "%{$search}%")
                           ->orWhere('email', 'like', "%{$search}%")
                           ->orWhere('phone', 'like', "%{$search}%");
              });
        });
    }

    public function scopeWithRating($query)
    {
        return $query->whereNotNull('rating');
    }

    public function scopeWithoutRating($query)
    {
        return $query->whereNull('rating');
    }

    public function scopeLate($query)
    {
        return $query->where('estimated_delivery_time', '<', Carbon::now())
                    ->whereNotIn('status', [self::STATUS_DELIVERED, self::STATUS_CANCELLED]);
    }

    /**
     * Accessors
     */
    public function getFormattedSubtotalAttribute(): string
    {
        return 'R$ ' . number_format($this->subtotal, 2, ',', '.');
    }

    public function getFormattedDeliveryFeeAttribute(): string
    {
        return 'R$ ' . number_format($this->delivery_fee, 2, ',', '.');
    }

    public function getFormattedDiscountAmountAttribute(): string
    {
        return 'R$ ' . number_format($this->discount_amount, 2, ',', '.');
    }

    public function getFormattedTotalAmountAttribute(): string
    {
        return 'R$ ' . number_format($this->total_amount, 2, ',', '.');
    }

    public function getFullDeliveryAddressAttribute(): string
    {
        $address = $this->delivery_address;
        
        if ($this->delivery_number) {
            $address .= ', ' . $this->delivery_number;
        }
        
        if ($this->delivery_complement) {
            $address .= ', ' . $this->delivery_complement;
        }
        
        if ($this->delivery_neighborhood) {
            $address .= ' - ' . $this->delivery_neighborhood;
        }
        
        if ($this->delivery_city) {
            $address .= ', ' . $this->delivery_city;
        }
        
        if ($this->delivery_state) {
            $address .= ' - ' . $this->delivery_state;
        }
        
        if ($this->delivery_zipcode) {
            $address .= ' - CEP: ' . $this->delivery_zipcode;
        }
        
        return $address;
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            self::STATUS_PENDING => 'Pendente',
            self::STATUS_CONFIRMED => 'Confirmado',
            self::STATUS_PREPARING => 'Preparando',
            self::STATUS_READY => 'Pronto',
            self::STATUS_DISPATCHED => 'Saiu para entrega',
            self::STATUS_DELIVERED => 'Entregue',
            self::STATUS_CANCELLED => 'Cancelado',
        ];
        
        return $labels[$this->status] ?? 'Desconhecido';
    }

    public function getPaymentStatusLabelAttribute(): string
    {
        $labels = [
            self::PAYMENT_PENDING => 'Pendente',
            self::PAYMENT_PROCESSING => 'Processando',
            self::PAYMENT_PAID => 'Pago',
            self::PAYMENT_FAILED => 'Falhou',
            self::PAYMENT_REFUNDED => 'Reembolsado',
        ];
        
        return $labels[$this->payment_status] ?? 'Desconhecido';
    }

    public function getCanCancelAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    public function getCanRateAttribute(): bool
    {
        return $this->status === self::STATUS_DELIVERED && is_null($this->rating);
    }

    public function getDeliveryTimeRemainingAttribute(): ?int
    {
        if (!$this->estimated_delivery_time || $this->status === self::STATUS_DELIVERED) {
            return null;
        }
        
        $remaining = Carbon::now()->diffInMinutes($this->estimated_delivery_time, false);
        return $remaining > 0 ? $remaining : 0;
    }

    public function getIsLateAttribute(): bool
    {
        if (!$this->estimated_delivery_time || in_array($this->status, [self::STATUS_DELIVERED, self::STATUS_CANCELLED])) {
            return false;
        }
        
        return Carbon::now()->isAfter($this->estimated_delivery_time);
    }

    /**
     * Métodos de negócio
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isPreparing(): bool
    {
        return $this->status === self::STATUS_PREPARING;
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function isDispatched(): bool
    {
        return $this->status === self::STATUS_DISPATCHED;
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isActive(): bool
    {
        return !in_array($this->status, [self::STATUS_DELIVERED, self::STATUS_CANCELLED]);
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, [self::STATUS_DELIVERED, self::STATUS_CANCELLED]);
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_CONFIRMED]);
    }

    public function canRate(): bool
    {
        return $this->status === self::STATUS_DELIVERED && is_null($this->rating);
    }

    public function confirm(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }
        
        $this->status = self::STATUS_CONFIRMED;
        $this->confirmed_at = Carbon::now();
        
        return $this->save();
    }

    public function startPreparing(): bool
    {
        if ($this->status !== self::STATUS_CONFIRMED) {
            return false;
        }
        
        $this->status = self::STATUS_PREPARING;
        
        return $this->save();
    }

    public function markAsReady(): bool
    {
        if ($this->status !== self::STATUS_PREPARING) {
            return false;
        }
        
        $this->status = self::STATUS_READY;
        $this->prepared_at = Carbon::now();
        
        return $this->save();
    }

    public function dispatch(): bool
    {
        if ($this->status !== self::STATUS_READY) {
            return false;
        }
        
        $this->status = self::STATUS_DISPATCHED;
        $this->dispatched_at = Carbon::now();
        
        return $this->save();
    }

    public function deliver(): bool
    {
        if ($this->status !== self::STATUS_DISPATCHED) {
            return false;
        }
        
        $this->status = self::STATUS_DELIVERED;
        $this->delivered_at = Carbon::now();
        $this->actual_delivery_time = Carbon::now();
        
        return $this->save();
    }

    public function cancel(string $reason = null, string $cancelledBy = null): bool
    {
        if (!$this->canCancel()) {
            return false;
        }
        
        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_at = Carbon::now();
        $this->cancelled_reason = $reason;
        $this->cancelled_by = $cancelledBy;
        
        return $this->save();
    }

    public function rate(int $rating, string $comment = null): bool
    {
        if (!$this->canRate() || $rating < 1 || $rating > 5) {
            return false;
        }
        
        $this->rating = $rating;
        $this->rating_comment = $comment;
        
        return $this->save();
    }

    public function calculateTotal(): void
    {
        $this->total_amount = $this->subtotal + $this->delivery_fee - $this->discount_amount;
    }

    public function updateEstimatedDeliveryTime(): void
    {
        if ($this->preparation_time && $this->confirmed_at) {
            $this->estimated_delivery_time = $this->confirmed_at->addMinutes($this->preparation_time);
        }
    }

    public function getTotalItems(): int
    {
        return $this->items->sum('quantity');
    }

    public function getItemsCount(): int
    {
        return $this->items->count();
    }

    public function getDeliveryDistance(): ?float
    {
        if (!$this->delivery_latitude || !$this->delivery_longitude || !$this->company) {
            return null;
        }
        
        return $this->company->calculateDistance($this->delivery_latitude, $this->delivery_longitude);
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($order) {
            if (!$order->order_number) {
                $order->order_number = self::generateOrderNumber();
            }
        });
        
        static::updating(function ($order) {
            // Registrar mudança de status no histórico
            if ($order->isDirty('status')) {
                OrderStatusHistory::create([
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'changed_at' => Carbon::now(),
                    'notes' => 'Status alterado automaticamente',
                ]);
            }
        });
    }

    /**
     * Gera número único do pedido
     */
    public static function generateOrderNumber(): string
    {
        do {
            $number = 'PED' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('order_number', $number)->exists());
        
        return $number;
    }

    /**
     * Obtém estatísticas do pedido
     */
    public function getStats(): array
    {
        return [
            'total_items' => $this->getTotalItems(),
            'items_count' => $this->getItemsCount(),
            'delivery_distance' => $this->getDeliveryDistance(),
            'preparation_time' => $this->preparation_time,
            'delivery_time_remaining' => $this->delivery_time_remaining,
            'is_late' => $this->is_late,
            'can_cancel' => $this->can_cancel,
            'can_rate' => $this->can_rate,
        ];
    }
}