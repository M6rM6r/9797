<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Notification;
use App\Models\Coupon;
use App\Mail\CouponNotification;
use App\Jobs\SendPushNotification;
use App\Jobs\SendEmailNotification;
use App\Jobs\SendSMSNotification;

class OptimizedNotificationService
{
    protected array $notificationChannels;
    protected array $notificationTemplates;
    protected array $userPreferences;
    protected array $notificationRules;
    
    public function __construct()
    {
        $this->initializeChannels();
        $this->initializeTemplates();
        $this->initializeRules();
    }
    
    /**
     * Initialize notification channels
     */
    protected function initializeChannels(): void
    {
        $this->notificationChannels = [
            'push' => [
                'name' => 'Push Notifications',
                'enabled' => true,
                'priority' => 1,
                'delivery_time' => 'instant',
                'max_retries' => 3,
                'retry_delay' => 300
            ],
            'email' => [
                'name' => 'Email',
                'enabled' => true,
                'priority' => 2,
                'delivery_time' => '5_minutes',
                'max_retries' => 5,
                'retry_delay' => 600
            ],
            'sms' => [
                'name' => 'SMS',
                'enabled' => true,
                'priority' => 3,
                'delivery_time' => 'instant',
                'max_retries' => 3,
                'retry_delay' => 180
            ],
            'in_app' => [
                'name' => 'In-App',
                'enabled' => true,
                'priority' => 0,
                'delivery_time' => 'instant',
                'max_retries' => 1,
                'retry_delay' => 0
            ]
        ];
    }
    
    /**
     * Initialize notification templates
     */
    protected function initializeTemplates(): void
    {
        $this->notificationTemplates = [
            'coupon_expiring' => [
                'title' => 'كوبون على وشك الانتهاء!',
                'body' => 'كوبون {coupon_code} من {store_name} سينتهي خلال {hours_left} ساعة. استخدمه الآن!',
                'action_text' => 'استخدم الكوبون',
                'priority' => 'high',
                'channels' => ['push', 'email', 'sms', 'in_app'],
                'send_when' => '24_hours_before_expiry'
            ],
            'new_coupon' => [
                'title' => 'كوبون جديد متاح!',
                'body' => 'كوبون {discount_percent}% من {store_name} متاح الآن. خصم يصل إلى {max_discount} ريال!',
                'action_text' => 'عرض الكوبون',
                'priority' => 'medium',
                'channels' => ['push', 'email', 'in_app'],
                'send_when' => 'immediately'
            ],
            'price_drop' => [
                'title' => 'انخفاض في السعر!',
                'body' => 'سعر المنتج {product_name} انخفض بنسبة {price_drop}%. الكوبون الجديد: {coupon_code}',
                'action_text' => 'شاهد العرض',
                'priority' => 'high',
                'channels' => ['push', 'email', 'in_app'],
                'send_when' => 'immediately'
            ],
            'back_in_stock' => [
                'title' => 'عاد للتوفر!',
                'body' => 'المنتج {product_name} عاد للمخزون. استخدم كوبون {coupon_code} للحصول على خصم {discount_percent}%',
                'action_text' => 'اشتر الآن',
                'priority' => 'medium',
                'channels' => ['push', 'email', 'in_app'],
                'send_when' => 'immediately'
            ],
            'personalized_recommendation' => [
                'title' => 'توصية مخصصة لك!',
                'body' => 'بناءً على اهتماماتك، نعتقد أنك ستحب كوبون {store_name} بخصم {discount_percent}%',
                'action_text' => 'عرض التوصية',
                'priority' => 'medium',
                'channels' => ['push', 'email', 'in_app'],
                'send_when' => 'daily'
            ],
            'trending_coupon' => [
                'title' => 'كوبون رائج!',
                'body' => 'كوبون {coupon_code} من {store_name} شائع هذا الأسبوع. {usage_count} شخص استخدمه!',
                'action_text' => 'انضم للرائجين',
                'priority' => 'low',
                'channels' => ['push', 'in_app'],
                'send_when' => 'weekly'
            ],
            'milestone_achievement' => [
                'title' => 'إنجاز جديد! 🎉',
                'body' => 'مبارك! لقد حققت إنجاز {achievement_name}. مكافأة: {points} نقطة',
                'action_text' => 'عرض الإنجازات',
                'priority' => 'medium',
                'channels' => ['push', 'email', 'in_app'],
                'send_when' => 'immediately'
            ],
            'level_up' => [
                'title' => 'ترقية إلى مستوى جديد! ⬆️',
                'body' => 'أحسنت! وصلت إلى المستوى {new_level} - {level_name}',
                'action_text' => 'عرض الملف الشخصي',
                'priority' => 'medium',
                'channels' => ['push', 'email', 'in_app'],
                'send_when' => 'immediately'
            ],
            'streak_milestone' => [
                'title' => 'سلسلة انتصارات رائعة! 🔥',
                'body' => 'لقد حافظت على سلسلة نشاط لمدة {streak_days} أيام! مكافأة: {bonus_points} نقطة',
                'action_text' => 'استمر في النشاط',
                'priority' => 'high',
                'channels' => ['push', 'email', 'in_app'],
                'send_when' => 'immediately'
            ],
            'friend_joined' => [
                'title' => 'انضم صديق! 👥',
                'body' => 'صديقك {friend_name} انضم باستخدام دعوتك. مكافأة: {referral_points} نقطة',
                'action_text' => 'عرض الأصدقاء',
                'priority' => 'low',
                'channels' => ['push', 'email', 'in_app'],
                'send_when' => 'immediately'
            ],
            'abandoned_cart' => [
                'title' => 'هل نسيت شيئاً؟ 🛒',
                'body' => 'العناصر في سلة التسوق الخاصة بك تنتظر. استخدم كوبون {coupon_code} للحصول على خصم {discount_percent}%',
                'action_text' => 'أكمل الشراء',
                'priority' => 'medium',
                'channels' => ['push', 'email', 'sms'],
                'send_when' => '2_hours_after_abandonment'
            ],
            'location_based' => [
                'title' => 'عرض قريب منك! 📍',
                'body' => 'متجر {store_name} يقدم خصم {discount_percent}% على بعد {distance} كم من موقعك الحالي',
                'action_text' => 'اعرض الخريطة',
                'priority' => 'medium',
                'channels' => ['push', 'in_app'],
                'send_when' => 'when_nearby'
            ],
            'seasonal_promotion' => [
                'title' => 'عرض موسمي! 🎊',
                'body' => 'خصم {discount_percent}% على جميع المنتجات من {store_name} بمناسبة {occasion}',
                'action_text' => 'استفد من العرض',
                'priority' => 'medium',
                'channels' => ['push', 'email', 'in_app'],
                'send_when' => 'seasonal'
            ]
        ];
    }
    
    /**
     * Initialize notification rules
     */
    protected function initializeRules(): void
    {
        $this->notificationRules = [
            'rate_limiting' => [
                'max_per_hour' => 10,
                'max_per_day' => 50,
                'cooldown_between_same_type' => 300
            ],
            'quiet_hours' => [
                'enabled' => true,
                'start' => '22:00',
                'end' => '08:00',
                'emergency_only' => true,
                'timezone' => 'Asia/Riyadh'
            ],
            'frequency_capping' => [
                'same_coupon_max_per_day' => 2,
                'same_store_max_per_day' => 5,
                'same_type_max_per_day' => 3
            ],
            'content_filtering' => [
                'max_title_length' => 100,
                'max_body_length' => 500,
                'allowed_html_tags' => ['strong', 'em', 'br'],
                'profanity_filter' => true
            ],
            'delivery_optimization' => [
                'batch_size' => 100,
                'batch_timeout' => 30,
                'retry_exponential_backoff' => true,
                'max_retry_delay' => 3600
            ]
        ];
    }
    
    /**
     * Send notification to user
     */
    public function sendNotification(int $userId, string $type, array $data = []): array
    {
        try {
            if (!isset($this->notificationTemplates[$type])) {
                return [
                    'success' => false,
                    'error' => 'Invalid notification type: ' . $type
                ];
            }
            
            $template = $this->notificationTemplates[$type];
            $user = $this->getUser($userId);
            
            if (!$user) {
                return [
                    'success' => false,
                    'error' => 'User not found'
                ];
            }
            
            $userPreferences = $this->getUserNotificationPreferences($userId);
            
            if (!$this->checkRateLimiting($userId, $type)) {
                return [
                    'success' => false,
                    'error' => 'Rate limit exceeded'
                ];
            }
            
            if (!$this->checkQuietHours($type)) {
                return [
                    'success' => false,
                    'error' => 'Quiet hours active'
                ];
            }
            
            if (!$this->checkFrequencyCapping($userId, $type, $data)) {
                return [
                    'success' => false,
                    'error' => 'Frequency cap exceeded'
                ];
            }
            
            $notificationData = $this->prepareNotificationContent($template, $data, $user);
            $notificationData = $this->filterContent($notificationData);
            
            $channels = $this->determineChannels($template, $userPreferences);
            
            $notification = $this->createNotificationRecord($userId, $type, $notificationData, $channels);
            
            $results = [];
            foreach ($channels as $channel) {
                $result = $this->queueNotification($notification, $channel, $notificationData);
                $results[$channel] = $result;
            }
            
            $this->updateRateLimitingCache($userId, $type);
            
            Log::info('Notification sent', [
                'user_id' => $userId,
                'type' => $type,
                'channels' => $channels,
                'notification_id' => $notification->id
            ]);
            
            return [
                'success' => true,
                'notification_id' => $notification->id,
                'channels' => $results,
                'sent_at' => now()->toISOString()
            ];
            
        } catch (\Exception $e) {
            Log::error('Error sending notification', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send bulk notifications
     */
    public function sendBulkNotifications(array $userIds, string $type, array $data = []): array
    {
        try {
            $results = [];
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($userIds as $userId) {
                $result = $this->sendNotification($userId, $type, $data);
                $results[$userId] = $result;
                
                if ($result['success']) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            }
            
            return [
                'success' => true,
                'total_users' => count($userIds),
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'results' => $results
            ];
            
        } catch (\Exception $e) {
            Log::error('Error sending bulk notifications', [
                'user_ids' => $userIds,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get user
     */
    protected function getUser(int $userId): ?User
    {
        return Cache::remember("user_{$userId}", 3600, function () use ($userId) {
            return User::find($userId);
        });
    }
    
    /**
     * Get user notification preferences
     */
    protected function getUserNotificationPreferences(int $userId): array
    {
        return Cache::remember("user_notification_preferences_{$userId}", 1800, function () use ($userId) {
            $user = User::find($userId);
            return $user ? json_decode($user->notification_preferences, true) : [];
        });
    }
    
    /**
     * Check rate limiting
     */
    protected function checkRateLimiting(int $userId, string $type): bool
    {
        $rateLimitKey = "notification_rate_limit_{$userId}";
        $currentData = Cache::get($rateLimitKey, []);
        
        $hourlyCount = $currentData['hourly'][$type] ?? 0;
        $dailyCount = $currentData['daily'][$type] ?? 0;
        $lastSent = $currentData['last_sent'][$type] ?? 0;
        
        $rules = $this->notificationRules['rate_limiting'];
        
        if ($hourlyCount >= $rules['max_per_hour']) {
            return false;
        }
        
        if ($dailyCount >= $rules['max_per_day']) {
            return false;
        }
        
        if (time() - $lastSent < $rules['cooldown_between_same_type']) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check quiet hours
     */
    protected function checkQuietHours(string $type): bool
    {
        $quietHours = $this->notificationRules['quiet_hours'];
        
        if (!$quietHours['enabled']) {
            return true;
        }
        
        $template = $this->notificationTemplates[$type] ?? [];
        $priority = $template['priority'] ?? 'low';
        
        if ($quietHours['emergency_only'] && $priority !== 'high') {
            return false;
        }
        
        $currentTime = now()->setTimezone($quietHours['timezone']);
        $startTime = Carbon::parse($quietHours['start'])->setTimezone($quietHours['timezone']);
        $endTime = Carbon::parse($quietHours['end'])->setTimezone($quietHours['timezone']);
        
        if ($currentTime->between($startTime, $endTime)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check frequency capping
     */
    protected function checkFrequencyCapping(int $userId, string $type, array $data): bool
    {
        $capKey = "notification_freq_cap_{$userId}";
        $currentData = Cache::get($capKey, []);
        
        $rules = $this->notificationRules['frequency_capping'];
        $today = now()->toDateString();
        
        if (isset($data['coupon_id'])) {
            $couponCount = $currentData['coupon'][$data['coupon_id']][$today] ?? 0;
            if ($couponCount >= $rules['same_coupon_max_per_day']) {
                return false;
            }
        }
        
        if (isset($data['store_id'])) {
            $storeCount = $currentData['store'][$data['store_id']][$today] ?? 0;
            if ($storeCount >= $rules['same_store_max_per_day']) {
                return false;
            }
        }
        
        $typeCount = $currentData['type'][$type][$today] ?? 0;
        if ($typeCount >= $rules['same_type_max_per_day']) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Prepare notification content
     */
    protected function prepareNotificationContent(array $template, array $data, User $user): array
    {
        $content = [
            'title' => $template['title'],
            'body' => $template['body'],
            'action_text' => $template['action_text'] ?? null,
            'action_url' => $data['action_url'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'priority' => $template['priority'],
            'user_id' => $user->id,
            'user_email' => $user->email,
            'user_phone' => $user->phone,
            'user_name' => $user->name,
            'created_at' => now()->toISOString()
        ];
        
        foreach ($data as $key => $value) {
            $content['title'] = str_replace('{' . $key . '}', $value, $content['title']);
            $content['body'] = str_replace('{' . $key . '}', $value, $content['body']);
        }
        
        return $content;
    }
    
    /**
     * Filter content
     */
    protected function filterContent(array $content): array
    {
        $rules = $this->notificationRules['content_filtering'];
        
        if (strlen($content['title']) > $rules['max_title_length']) {
            $content['title'] = substr($content['title'], 0, $rules['max_title_length'] - 3) . '...';
        }
        
        if (strlen($content['body']) > $rules['max_body_length']) {
            $content['body'] = substr($content['body'], 0, $rules['max_body_length'] - 3) . '...';
        }
        
        if (!empty($rules['allowed_html_tags'])) {
            $content['body'] = strip_tags($content['body'], '<' . implode('><', $rules['allowed_html_tags']) . '>');
        }
        
        if ($rules['profanity_filter']) {
            $content['title'] = $this->filterProfanity($content['title']);
            $content['body'] = $this->filterProfanity($content['body']);
        }
        
        return $content;
    }
    
    /**
     * Filter profanity
     */
    protected function filterProfanity(string $text): string
    {
        $profanity = ['كلمة سيئة', 'bad word'];
        foreach ($profanity as $word) {
            $text = str_ireplace($word, str_repeat('*', strlen($word)), $text);
        }
        return $text;
    }
    
    /**
     * Determine channels to use
     */
    protected function determineChannels(array $template, array $userPreferences): array
    {
        $templateChannels = $template['channels'] ?? ['push'];
        $availableChannels = array_keys($this->notificationChannels);
        
        $userChannels = $userPreferences['enabled_channels'] ?? $availableChannels;
        
        $channels = array_intersect($templateChannels, $userChannels, $availableChannels);
        
        $sortedChannels = [];
        foreach ($channels as $channel) {
            $priority = $this->notificationChannels[$channel]['priority'] ?? 999;
            $sortedChannels[$channel] = $priority;
        }
        
        asort($sortedChannels);
        
        return array_keys($sortedChannels);
    }
    
    /**
     * Create notification record
     */
    protected function createNotificationRecord(int $userId, string $type, array $data, array $channels): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $data['title'],
            'body' => $data['body'],
            'data' => json_encode($data),
            'channels' => json_encode($channels),
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'pending',
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    /**
     * Queue notification for specific channel
     */
    protected function queueNotification(Notification $notification, string $channel, array $data): array
    {
        try {
            $channelConfig = $this->notificationChannels[$channel] ?? [];
            
            switch ($channel) {
                case 'push':
                    SendPushNotification::dispatch($notification, $data)
                        ->delay(now()->addSeconds($channelConfig['delivery_delay'] ?? 0));
                    break;
                    
                case 'email':
                    SendEmailNotification::dispatch($notification, $data)
                        ->delay(now()->addMinutes($channelConfig['delivery_delay'] ?? 0));
                    break;
                    
                case 'sms':
                    SendSMSNotification::dispatch($notification, $data)
                        ->delay(now()->addSeconds($channelConfig['delivery_delay'] ?? 0));
                    break;
                    
                case 'in_app':
                    $this->storeInAppNotification($notification, $data);
                    break;
            }
            
            return [
                'channel' => $channel,
                'success' => true,
                'queued_at' => now()->toISOString()
            ];
            
        } catch (\Exception $e) {
            Log::error('Error queueing notification', [
                'notification_id' => $notification->id,
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);
            
            return [
                'channel' => $channel,
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Store in-app notification
     */
    protected function storeInAppNotification(Notification $notification, array $data): void
    {
        Cache::put("in_app_notification_{$notification->id}", $data, 86400);
    }
    
    /**
     * Update rate limiting cache
     */
    protected function updateRateLimitingCache(int $userId, string $type): void
    {
        $rateLimitKey = "notification_rate_limit_{$userId}";
        $currentData = Cache::get($rateLimitKey, []);
        
        $now = time();
        $today = now()->toDateString();
        
        $currentData['hourly'][$type] = ($currentData['hourly'][$type] ?? 0) + 1;
        $currentData['daily'][$type] = ($currentData['daily'][$type] ?? 0) + 1;
        $currentData['last_sent'][$type] = $now;
        
        $lastHour = $currentData['last_hour_reset'] ?? 0;
        if (date('H', $now) !== date('H', $lastHour)) {
            $currentData['hourly'] = [];
            $currentData['last_hour_reset'] = $now;
        }
        
        $lastDay = $currentData['last_day_reset'] ?? 0;
        if ($today !== date('Y-m-d', $lastDay)) {
            $currentData['daily'] = [];
            $currentData['last_day_reset'] = $now;
        }
        
        Cache::put($rateLimitKey, $currentData, 3600);
    }
    
    /**
     * Get notification statistics
     */
    public function getNotificationStatistics(array $filters = []): array
    {
        try {
            $query = Notification::query();
            
            if (isset($filters['date_from'])) {
                $query->where('created_at', '>=', $filters['date_from']);
            }
            
            if (isset($filters['date_to'])) {
                $query->where('created_at', '<=', $filters['date_to']);
            }
            
            if (isset($filters['type'])) {
                $query->where('type', $filters['type']);
            }
            
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            if (isset($filters['channel'])) {
                $query->where('channels', 'like', "%{$filters['channel']}%");
            }
            
            $notifications = $query->get();
            
            $stats = [
                'total_notifications' => $notifications->count(),
                'by_type' => [],
                'by_status' => [],
                'by_channel' => [],
                'by_priority' => [],
                'delivery_rate' => 0,
                'open_rate' => 0,
                'click_rate' => 0
            ];
            
            foreach ($notifications as $notification) {
                $type = $notification->type;
                $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
                
                $status = $notification->status;
                $stats['by_status'][$status] = ($stats['by_status'][$status] ?? 0) + 1;
                
                $channels = json_decode($notification->channels, true);
                foreach ($channels as $channel) {
                    $stats['by_channel'][$channel] = ($stats['by_channel'][$channel] ?? 0) + 1;
                }
                
                $priority = $notification->priority;
                $stats['by_priority'][$priority] = ($stats['by_priority'][$priority] ?? 0) + 1;
            }
            
            $totalSent = $stats['by_status']['sent'] ?? 0;
            $totalFailed = $stats['by_status']['failed'] ?? 0;
            
            if ($totalSent + $totalFailed > 0) {
                $stats['delivery_rate'] = ($totalSent / ($totalSent + $totalFailed)) * 100;
            }
            
            return $stats;
            
        } catch (\Exception $e) {
            Log::error('Error getting notification statistics', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }
    
    /**
     * Get user notifications
     */
    public function getUserNotifications(int $userId, array $filters = []): array
    {
        try {
            $query = Notification::where('user_id', $userId);
            
            if (isset($filters['type'])) {
                $query->where('type', $filters['type']);
            }
            
            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            if (isset($filters['priority'])) {
                $query->where('priority', $filters['priority']);
            }
            
            if (isset($filters['limit'])) {
                $query->limit($filters['limit']);
            }
            
            if (isset($filters['offset'])) {
                $query->offset($filters['offset']);
            }
            
            $notifications = $query->orderBy('created_at', 'desc')->get();
            
            return $notifications->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'data' => json_decode($notification->data, true),
                    'priority' => $notification->priority,
                    'status' => $notification->status,
                    'channels' => json_decode($notification->channels, true),
                    'created_at' => $notification->created_at->toISOString(),
                    'sent_at' => $notification->sent_at?->toISOString(),
                    'read_at' => $notification->read_at?->toISOString()
                ];
            })->toArray();
            
        } catch (\Exception $e) {
            Log::error('Error getting user notifications', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }
    
    /**
     * Mark notification as read
     */
    public function markNotificationAsRead(int $notificationId, int $userId): bool
    {
        try {
            $notification = Notification::where('id', $notificationId)
                ->where('user_id', $userId)
                ->first();
                
            if ($notification) {
                $notification->read_at = now();
                $notification->save();
                
                Cache::forget("in_app_notification_{$notificationId}");
                
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('Error marking notification as read', [
                'notification_id' => $notificationId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Get notification settings
     */
    public function getNotificationSettings(): array
    {
        return [
            'channels' => $this->notificationChannels,
            'templates' => $this->notificationTemplates,
            'rules' => $this->notificationRules
        ];
    }
}
