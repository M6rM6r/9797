<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'store_id',
        'discount_percent',
        'description',
        'expires_at',
        'usage_count',
        'category',
        'is_verified',
        'affiliate_link',
        'is_active',
        'app_only',
        'firebase_document_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'app_only' => 'boolean',
        'usage_count' => 'integer',
        'discount_percent' => 'integer',
    ];

    /**
     * Get the store that owns the coupon.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Scope a query to only include active coupons.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include verified coupons.
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope a query to only include non-expired coupons.
     */
    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', Carbon::now());
    }

    /**
     * Scope a query to only include expiring soon coupons.
     */
    public function scopeExpiringSoon($query, $days = 3)
    {
        return $query->where('expires_at', '<=', Carbon::now()->addDays($days))
                    ->where('expires_at', '>', Carbon::now());
    }

    /**
     * Scope a query to only include app-only coupons.
     */
    public function scopeAppOnly($query)
    {
        return $query->where('app_only', true);
    }

    /**
     * Check if coupon is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if coupon is expiring soon.
     */
    public function isExpiringSoon(int $days = 3): bool
    {
        return $this->expires_at->lessThanOrEqualTo(Carbon::now()->addDays($days)) &&
               $this->expires_at->greaterThan(Carbon::now());
    }

    /**
     * Get discount text.
     */
    public function getDiscountTextAttribute(): string
    {
        return $this->discount_percent . '%';
    }

    /**
     * Get expiry text in Arabic.
     */
    public function getExpiryTextAttribute(): string
    {
        if ($this->isExpired()) {
            return 'منتهي الصلاحية';
        }

        $diff = $this->expires_at->diffForHumans(now(), true);
        
        if ($this->expires_at->diffInDays(now()) > 0) {
            return 'ينتهي خلال ' . $this->expires_at->diffInDays(now()) . ' يوم';
        } elseif ($this->expires_at->diffInHours(now()) > 0) {
            return 'ينتهي خلال ' . $this->expires_at->diffInHours(now()) . ' ساعة';
        } else {
            return 'ينتهي قريباً';
        }
    }

    /**
     * Get category display name in Arabic.
     */
    public function getCategoryDisplayNameAttribute(): string
    {
        return match($this->category) {
            'fashion' => 'تسوق وأزياء',
            'electronics' => 'إلكترونيات',
            'food' => 'طعام ومطاعم',
            'travel' => 'سفر وفنادق',
            default => $this->category,
        };
    }

    /**
     * Prepare data for Firebase Firestore.
     */
    public function toFirestoreData(): array
    {
        return [
            'code' => $this->code,
            'storeId' => $this->store_id,
            'discountPercent' => $this->discount_percent,
            'description' => $this->description,
            'expiresAt' => $this->expires_at,
            'usageCount' => $this->usage_count,
            'category' => $this->category,
            'isVerified' => $this->is_verified,
            'affiliateLink' => $this->affiliate_link,
            'isActive' => $this->is_active,
            'appOnly' => $this->app_only,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
            'storeName' => $this->store?->name,
            'storeLogo' => $this->store?->logo_url,
            'storeCashback' => $this->store?->cashback_percent,
        ];
    }

    /**
     * Increment usage count.
     */
    public function incrementUsage(): bool
    {
        return $this->increment('usage_count');
    }

    /**
     * Get validation rules for the model.
     */
    public static function validationRules(): array
    {
        return [
            'code' => 'required|string|max:50|unique:coupons,code',
            'store_id' => 'required|exists:stores,id',
            'discount_percent' => 'required|integer|min:0|max:100',
            'description' => 'required|string|max:500',
            'expires_at' => 'required|date|after:now',
            'category' => 'required|string|max:50',
            'is_verified' => 'boolean',
            'affiliate_link' => 'required|url|max:500',
            'is_active' => 'boolean',
            'app_only' => 'boolean',
        ];
    }

    /**
     * Get validation messages in Arabic.
     */
    public static function validationMessages(): array
    {
        return [
            'code.required' => 'حقل الكود مطلوب',
            'code.unique' => 'الكود مستخدم بالفعل',
            'store_id.required' => 'حقل المتجر مطلوب',
            'store_id.exists' => 'المتجر المحدد غير موجود',
            'discount_percent.required' => 'حقل نسبة الخصم مطلوب',
            'discount_percent.min' => 'نسبة الخصم يجب أن تكون 0 على الأقل',
            'discount_percent.max' => 'نسبة الخصم يجب أن لا تتجاوز 100%',
            'description.required' => 'حقل الوصف مطلوب',
            'expires_at.required' => 'حقل تاريخ الانتهاء مطلوب',
            'expires_at.after' => 'تاريخ الانتهاء يجب أن يكون في المستقبل',
            'category.required' => 'حقل الفئة مطلوب',
            'affiliate_link.required' => 'حقل الرابط التابع مطلوب',
            'affiliate_link.url' => 'الرابط التابع يجب أن يكون رابط صحيح',
        ];
    }
}
