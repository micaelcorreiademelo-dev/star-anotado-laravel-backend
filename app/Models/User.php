<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'phone',
        'birth_date',
        'cpf',
        'address',
        'number',
        'complement',
        'neighborhood',
        'city',
        'state',
        'zipcode',
        'latitude',
        'longitude',
        'is_active',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'birth_date' => 'date',
        'is_active' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    protected $appends = [
        'full_address',
        'formatted_phone',
        'avatar_url',
    ];

    /**
     * Relacionamentos
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function activeOrders(): HasMany
    {
        return $this->orders()->active();
    }

    public function pendingOrders(): HasMany
    {
        return $this->orders()->pending();
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%")
              ->orWhere('phone', 'like', "%{$search}%");
        });
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

    public function getFormattedPhoneAttribute(): ?string
    {
        if (!$this->phone) {
            return null;
        }
        
        $phone = preg_replace('/\D/', '', $this->phone);
        
        if (strlen($phone) === 11) {
            return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 5) . '-' . substr($phone, 7);
        }
        
        if (strlen($phone) === 10) {
            return '(' . substr($phone, 0, 2) . ') ' . substr($phone, 2, 4) . '-' . substr($phone, 6);
        }
        
        return $this->phone;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/' . $this->avatar) : null;
    }

    /**
     * Métodos de verificação
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function hasVerifiedEmail(): bool
    {
        return !is_null($this->email_verified_at);
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

    /**
     * Métodos de estatísticas
     */
    public function getStats(): array
    {
        $totalOrders = $this->orders()->count();
        $completedOrders = $this->orders()->where('status', Order::STATUS_DELIVERED)->count();
        $totalSpent = $this->orders()->where('status', Order::STATUS_DELIVERED)->sum('total_amount');
        $averageOrderValue = $completedOrders > 0 ? $totalSpent / $completedOrders : 0;
        
        return [
            'total_orders' => $totalOrders,
            'completed_orders' => $completedOrders,
            'cancelled_orders' => $this->orders()->where('status', Order::STATUS_CANCELLED)->count(),
            'pending_orders' => $this->activeOrders()->count(),
            'total_spent' => $totalSpent,
            'average_order_value' => $averageOrderValue,
            'favorite_company' => $this->getFavoriteCompany(),
            'last_order_date' => $this->orders()->latest()->first()?->created_at,
            'orders_this_month' => $this->orders()->whereMonth('created_at', Carbon::now()->month)->count(),
            'spent_this_month' => $this->orders()
                ->where('status', Order::STATUS_DELIVERED)
                ->whereMonth('created_at', Carbon::now()->month)
                ->sum('total_amount'),
        ];
    }

    private function getFavoriteCompany()
    {
        return $this->orders()
            ->selectRaw('company_id, COUNT(*) as order_count')
            ->groupBy('company_id')
            ->orderBy('order_count', 'desc')
            ->with('company')
            ->first()?->company;
    }
}