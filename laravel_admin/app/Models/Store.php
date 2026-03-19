<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'logo_url',
        'description',
        'affiliate_base_url',
        'cashback_percent',
        'is_active',
        'firebase_document_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'cashback_percent' => 'decimal:2',
    ];

    /**
     * Get the coupons for the store.
     */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    /**
     * Scope a query to only include active stores.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get active coupons count.
     */
    public function getActiveCouponsCountAttribute(): int
    {
        return $this->coupons()->active()->notExpired()->count();
    }

    /**
     * Get total usage count.
     */
    public function getTotalUsageCountAttribute(): int
    {
        return $this->coupons()->sum('usage_count');
    }

    /**
     * Prepare data for Firebase Firestore.
     */
    public function toFirestoreData(): array
    {
        return [
            'name' => $this->name,
            'logoUrl' => $this->logo_url,
            'description' => $this->description,
            'affiliateBaseUrl' => $this->affiliate_base_url,
            'cashbackPercent' => (float) $this->cashback_percent,
            'isActive' => $this->is_active,
            'createdAt' => $this->created_at,
            'updatedAt' => $this->updated_at,
        ];
    }

    /**
     * Get validation rules for the model.
     */
    public static function validationRules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'logo_url' => 'required|url|max:500',
            'description' => 'required|string|max:500',
            'affiliate_base_url' => 'required|url|max:500',
            'cashback_percent' => 'required|numeric|min:0|max:50',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get validation messages in Arabic.
     */
    public static function validationMessages(): array
    {
        return [
            'name.required' => 'حقل اسم المتجر مطلوب',
            'logo_url.required' => 'حقل رابط الشعار مطلوب',
            'logo_url.url' => 'رابط الشعار يجب أن يكون رابط صحيح',
            'description.required' => 'حقل الوصف مطلوب',
            'affiliate_base_url.required' => 'حقل الرابط التابع للمتجر مطلوب',
            'affiliate_base_url.url' => 'الرابط التابع يجب أن يكون رابط صحيح',
            'cashback_percent.required' => 'حقل نسبة الكاش باك مطلوب',
            'cashback_percent.min' => 'نسبة الكاش باك يجب أن تكون 0 على الأقل',
            'cashback_percent.max' => 'نسبة الكاش باك يجب أن لا تتجاوز 50%',
        ];
    }
}
