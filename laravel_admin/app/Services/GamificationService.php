<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Coupon;
use App\Models\UserGamification;

class GamificationService
{
    protected array $achievementDefinitions;
    protected array $badgeDefinitions;
    protected array $levelDefinitions;
    protected array $streakBonuses;
    
    public function __construct()
    {
        $this->initializeAchievements();
        $this->initializeBadges();
        $this->initializeLevels();
        $this->initializeStreakBonuses();
    }
    
    /**
     * Initialize achievement definitions
     */
    protected function initializeAchievements(): void
    {
        $this->achievementDefinitions = [
            'first_coupon' => [
                'name' => 'أول كوبون',
                'description' => 'استخدم أول كوبون',
                'points' => 50,
                'badge_id' => 'first_coupon_badge',
                'type' => 'milestone'
            ],
            'coupon_collector' => [
                'name' => 'جامع الكوبونات',
                'description' => 'استخدم 10 كوبونات',
                'points' => 100,
                'badge_id' => 'collector_badge',
                'type' => 'milestone'
            ],
            'power_user' => [
                'name' => 'مستخدم قوي',
                'description' => 'استخدم 50 كوبون',
                'points' => 500,
                'badge_id' => 'power_user_badge',
                'type' => 'milestone'
            ],
            'savings_master' => [
                'name' => 'خبير التوفير',
                'description' => 'وفر 1000 ريال',
                'points' => 200,
                'badge_id' => 'savings_badge',
                'type' => 'financial'
            ],
            'category_explorer' => [
                'name' => 'مستكشف الفئات',
                'description' => 'استخدم كوبونات من 5 فئات مختلفة',
                'points' => 150,
                'badge_id' => 'explorer_badge',
                'type' => 'diversity'
            ],
            'week_streak' => [
                'name' => 'أسبوع نشط',
                'description' => 'استخدم كوبون لمدة 7 أيام متتالية',
                'points' => 300,
                'badge_id' => 'week_streak_badge',
                'type' => 'streak'
            ],
            'month_streak' => [
                'name' => 'شهر نشط',
                'description' => 'استخدم كوبون لمدة 30 يوم متتالية',
                'points' => 1000,
                'badge_id' => 'month_streak_badge',
                'type' => 'streak'
            ],
            'social_sharer' => [
                'name' => 'ناشر اجتماعي',
                'description' => 'شارك 10 كوبونات',
                'points' => 150,
                'badge_id' => 'social_badge',
                'type' => 'social'
            ],
            'early_bird' => [
                'name' => 'الطائر المبكر',
                'description' => 'استخدم كوبون قبل الساعة 9 صباحاً',
                'points' => 25,
                'badge_id' => 'early_bird_badge',
                'type' => 'time_based'
            ],
            'night_owl' => [
                'name' => 'البومة الليلية',
                'description' => 'استخدم كوبون بعد الساعة 9 مساءً',
                'points' => 25,
                'badge_id' => 'night_owl_badge',
                'type' => 'time_based'
            ],
            'big_saver' => [
                'name' => 'موفر كبير',
                'description' => 'استخدم كوبون بخصم 50% أو أكثر',
                'points' => 75,
                'badge_id' => 'big_saver_badge',
                'type' => 'value'
            ],
            'loyal_customer' => [
                'name' => 'عميل مخلص',
                'description' => 'استخدم 5 كوبونات من نفس المتجر',
                'points' => 125,
                'badge_id' => 'loyal_badge',
                'type' => 'loyalty'
            ],
            'trendsetter' => [
                'name' => 'صائح الموضة',
                'description' => 'استخدم كوبون عالي الشعبية',
                'points' => 60,
                'badge_id' => 'trendsetter_badge',
                'type' => 'popularity'
            ],
            'reviewer' => [
                'name' => 'ناقد',
                'description' => 'قيم 5 كوبونات',
                'points' => 100,
                'badge_id' => 'reviewer_badge',
                'type' => 'engagement'
            ],
            'helper' => [
                'name' => 'مساعد',
                'description' => 'ساعد 5 مستخدمين جدد',
                'points' => 200,
                'badge_id' => 'helper_badge',
                'type' => 'community'
            ]
        ];
    }
    
    /**
     * Initialize badge definitions
     */
    protected function initializeBadges(): void
    {
        $this->badgeDefinitions = [
            'first_coupon_badge' => [
                'name' => 'بادئ',
                'icon' => '🎯',
                'color' => '#4CAF50',
                'rarity' => 'common'
            ],
            'collector_badge' => [
                'name' => 'جامع',
                'icon' => '📚',
                'color' => '#2196F3',
                'rarity' => 'uncommon'
            ],
            'power_user_badge' => [
                'name' => 'قوي',
                'icon' => '⚡',
                'color' => '#FF9800',
                'rarity' => 'rare'
            ],
            'savings_badge' => [
                'name' => 'موفر',
                'icon' => '💰',
                'color' => '#F44336',
                'rarity' => 'epic'
            ],
            'explorer_badge' => [
                'name' => 'مستكشف',
                'icon' => '🧭',
                'color' => '#9C27B0',
                'rarity' => 'uncommon'
            ],
            'week_streak_badge' => [
                'name' => 'نشط',
                'icon' => '🔥',
                'color' => '#FF5722',
                'rarity' => 'rare'
            ],
            'month_streak_badge' => [
                'name' => 'أسطوري',
                'icon' => '👑',
                'color' => '#FFD700',
                'rarity' => 'legendary'
            ],
            'social_badge' => [
                'name' => 'اجتماعي',
                'icon' => '🌐',
                'color' => '#00BCD4',
                'rarity' => 'uncommon'
            ],
            'early_bird_badge' => [
                'name' => 'مبكر',
                'icon' => '🌅',
                'color' => '#FFEB3B',
                'rarity' => 'common'
            ],
            'night_owl_badge' => [
                'name' => 'ليلي',
                'icon' => '🌙',
                'color' => '#3F51B5',
                'rarity' => 'common'
            ],
            'big_saver_badge' => [
                'name' => 'مدخر',
                'icon' => '💎',
                'color' => '#E91E63',
                'rarity' => 'rare'
            ],
            'loyal_badge' => [
                'name' => 'مخلص',
                'icon' => '❤️',
                'color' => '#C2185B',
                'rarity' => 'uncommon'
            ],
            'trendsetter_badge' => [
                'name' => 'رائد',
                'icon' => '🌟',
                'color' => '#FFC107',
                'rarity' => 'rare'
            ],
            'reviewer_badge' => [
                'name' => 'ناقد',
                'icon' => '⭐',
                'color' => '#607D8B',
                'rarity' => 'common'
            ],
            'helper_badge' => [
                'name' => 'مساعد',
                'icon' => '🤝',
                'color' => '#795548',
                'rarity' => 'epic'
            ]
        ];
    }
    
    /**
     * Initialize level definitions
     */
    protected function initializeLevels(): void
    {
        $this->levelDefinitions = [
            1 => ['name' => 'مبتدئ', 'min_points' => 0, 'color' => '#9E9E9E'],
            2 => ['name' => 'متعلم', 'min_points' => 100, 'color' => '#607D8B'],
            3 => ['name' => 'مشارك', 'min_points' => 250, 'color' => '#2196F3'],
            4 => ['name' => 'نشط', 'min_points' => 500, 'color' => '#4CAF50'],
            5 => ['name' => 'متقدم', 'min_points' => 1000, 'color' => '#FF9800'],
            6 => ['name' => 'خبير', 'min_points' => 2000, 'color' => '#F44336'],
            7 => ['name' => 'محترف', 'min_points' => 5000, 'color' => '#9C27B0'],
            8 => ['name' => 'أسطورة', 'min_points' => 10000, 'color' => '#FFD700'],
            9 => ['name' => 'بطل', 'min_points' => 25000, 'color' => '#FF6B35'],
            10 => ['name' => 'إله', 'min_points' => 50000, 'color' => '#E91E63']
        ];
    }
    
    /**
     * Initialize streak bonuses
     */
    protected function initializeStreakBonuses(): void
    {
        $this->streakBonuses = [
            3 => ['multiplier' => 1.1, 'bonus_points' => 10],
            7 => ['multiplier' => 1.25, 'bonus_points' => 50],
            14 => ['multiplier' => 1.5, 'bonus_points' => 150],
            30 => ['multiplier' => 2.0, 'bonus_points' => 500],
            60 => ['multiplier' => 2.5, 'bonus_points' => 1500],
            90 => ['multiplier' => 3.0, 'bonus_points' => 3000],
            180 => ['multiplier' => 3.5, 'bonus_points' => 7500],
            365 => ['multiplier' => 5.0, 'bonus_points' => 20000]
        ];
    }
    
    /**
     * Process user action and award points
     */
    public function processUserAction(int $userId, string $action, array $context = []): array
    {
        try {
            $userGamification = $this->getUserGamification($userId);
            $pointsAwarded = 0;
            $achievementsUnlocked = [];
            $levelUp = false;
            
            // Calculate base points for action
            $basePoints = $this->getActionPoints($action, $context);
            
            // Apply streak multiplier
            $streakMultiplier = $this->getStreakMultiplier($userId);
            $finalPoints = (int) ($basePoints * $streakMultiplier);
            
            // Award points
            $userGamification->total_points += $finalPoints;
            $pointsAwarded = $finalPoints;
            
            // Update action-specific counters
            $this->updateActionCounters($userGamification, $action, $context);
            
            // Check for new achievements
            $newAchievements = $this->checkAchievements($userGamification, $action, $context);
            foreach ($newAchievements as $achievement) {
                $userGamification->total_points += $achievement['points'];
                $pointsAwarded += $achievement['points'];
                $achievementsUnlocked[] = $achievement;
            }
            
            // Check for level up
            $oldLevel = $userGamification->level;
            $newLevel = $this->calculateLevel($userGamification->total_points);
            if ($newLevel > $oldLevel) {
                $userGamification->level = $newLevel;
                $levelUp = true;
            }
            
            // Update streak
            $this->updateStreak($userGamification, $action);
            
            // Save changes
            $userGamification->save();
            
            // Cache updated data
            $this->cacheUserGamification($userId, $userGamification);
            
            Log::info('User gamification updated', [
                'user_id' => $userId,
                'action' => $action,
                'points_awarded' => $pointsAwarded,
                'achievements_unlocked' => count($achievementsUnlocked),
                'level_up' => $levelUp
            ]);
            
            return [
                'success' => true,
                'points_awarded' => $pointsAwarded,
                'achievements_unlocked' => $achievementsUnlocked,
                'level_up' => $levelUp,
                'new_level' => $levelUp ? $newLevel : null,
                'total_points' => $userGamification->total_points,
                'streak_multiplier' => $streakMultiplier
            ];
            
        } catch (\Exception $e) {
            Log::error('Error processing user action', [
                'user_id' => $userId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get user gamification data
     */
    public function getUserGamification(int $userId): UserGamification
    {
        return Cache::remember("user_gamification_{$userId}", 3600, function () use ($userId) {
            return UserGamification::firstOrCreate(
                ['user_id' => $userId],
                [
                    'total_points' => 0,
                    'level' => 1,
                    'current_streak' => 0,
                    'longest_streak' => 0,
                    'last_activity_date' => now(),
                    'achievements' => json_encode([]),
                    'badges' => json_encode([]),
                    'coupons_used' => 0,
                    'total_savings' => 0,
                    'categories_explored' => json_encode([]),
                    'stores_visited' => json_encode([]),
                    'social_shares' => 0,
                    'reviews_written' => 0,
                    'friends_invited' => 0
                ]
            );
        });
    }
    
    /**
     * Get points for specific action
     */
    protected function getActionPoints(string $action, array $context): int
    {
        $actionPoints = [
            'coupon_used' => 10,
            'coupon_shared' => 5,
            'coupon_reviewed' => 15,
            'friend_invited' => 50,
            'daily_login' => 2,
            'profile_completed' => 25,
            'first_visit' => 5,
            'category_explored' => 3,
            'store_visited' => 2,
            'search_performed' => 1,
            'favorite_added' => 3,
            'achievement_unlocked' => 20
        ];
        
        $basePoints = $actionPoints[$action] ?? 0;
        
        // Apply context-based bonuses
        if (isset($context['discount_percent'])) {
            $discount = $context['discount_percent'];
            if ($discount >= 50) {
                $basePoints += 10; // Big discount bonus
            } elseif ($discount >= 25) {
                $basePoints += 5; // Medium discount bonus
            }
        }
        
        if (isset($context['savings_amount'])) {
            $savings = $context['savings_amount'];
            $basePoints += min((int) ($savings / 10), 50); // Savings bonus, max 50 points
        }
        
        if (isset($context['is_new_category']) && $context['is_new_category']) {
            $basePoints += 5; // New category bonus
        }
        
        if (isset($context['is_new_store']) && $context['is_new_store']) {
            $basePoints += 3; // New store bonus
        }
        
        return $basePoints;
    }
    
    /**
     * Get streak multiplier for user
     */
    protected function getStreakMultiplier(int $userId): float
    {
        $userGamification = $this->getUserGamification($userId);
        $streak = $userGamification->current_streak;
        
        foreach ($this->streakBonuses as $days => $bonus) {
            if ($streak >= $days) {
                $multiplier = $bonus['multiplier'];
            }
        }
        
        return $multiplier ?? 1.0;
    }
    
    /**
     * Update action counters
     */
    protected function updateActionCounters(UserGamification $userGamification, string $action, array $context): void
    {
        switch ($action) {
            case 'coupon_used':
                $userGamification->coupons_used++;
                if (isset($context['savings_amount'])) {
                    $userGamification->total_savings += $context['savings_amount'];
                }
                if (isset($context['category'])) {
                    $categories = json_decode($userGamification->categories_explored, true) ?? [];
                    if (!in_array($context['category'], $categories)) {
                        $categories[] = $context['category'];
                        $userGamification->categories_explored = json_encode($categories);
                    }
                }
                if (isset($context['store_id'])) {
                    $stores = json_decode($userGamification->stores_visited, true) ?? [];
                    if (!in_array($context['store_id'], $stores)) {
                        $stores[] = $context['store_id'];
                        $userGamification->stores_visited = json_encode($stores);
                    }
                }
                break;
                
            case 'coupon_shared':
                $userGamification->social_shares++;
                break;
                
            case 'coupon_reviewed':
                $userGamification->reviews_written++;
                break;
                
            case 'friend_invited':
                $userGamification->friends_invited++;
                break;
        }
    }
    
    /**
     * Check for new achievements
     */
    protected function checkAchievements(UserGamification $userGamification, string $action, array $context): array
    {
        $unlockedAchievements = [];
        $currentAchievements = json_decode($userGamification->achievements, true) ?? [];
        
        foreach ($this->achievementDefinitions as $achievementId => $achievement) {
            if (in_array($achievementId, $currentAchievements)) {
                continue; // Already unlocked
            }
            
            if ($this->isAchievementUnlocked($achievement, $userGamification, $action, $context)) {
                $unlockedAchievements[] = [
                    'id' => $achievementId,
                    'name' => $achievement['name'],
                    'description' => $achievement['description'],
                    'points' => $achievement['points'],
                    'badge_id' => $achievement['badge_id'],
                    'unlocked_at' => now()->toISOString()
                ];
                
                $currentAchievements[] = $achievementId;
            }
        }
        
        if (!empty($unlockedAchievements)) {
            $userGamification->achievements = json_encode($currentAchievements);
        }
        
        return $unlockedAchievements;
    }
    
    /**
     * Check if specific achievement is unlocked
     */
    protected function isAchievementUnlocked(array $achievement, UserGamification $userGamification, string $action, array $context): bool
    {
        switch ($achievement['type']) {
            case 'milestone':
                return $this->checkMilestoneAchievement($achievement, $userGamification);
                
            case 'financial':
                return $this->checkFinancialAchievement($achievement, $userGamification);
                
            case 'diversity':
                return $this->checkDiversityAchievement($achievement, $userGamification);
                
            case 'streak':
                return $this->checkStreakAchievement($achievement, $userGamification);
                
            case 'social':
                return $this->checkSocialAchievement($achievement, $userGamification);
                
            case 'time_based':
                return $this->checkTimeBasedAchievement($achievement, $action);
                
            case 'value':
                return $this->checkValueAchievement($achievement, $context);
                
            case 'loyalty':
                return $this->checkLoyaltyAchievement($achievement, $userGamification, $context);
                
            case 'popularity':
                return $this->checkPopularityAchievement($achievement, $context);
                
            case 'engagement':
                return $this->checkEngagementAchievement($achievement, $userGamification);
                
            case 'community':
                return $this->checkCommunityAchievement($achievement, $userGamification);
                
            default:
                return false;
        }
    }
    
    /**
     * Check milestone achievements
     */
    protected function checkMilestoneAchievement(array $achievement, UserGamification $userGamification): bool
    {
        switch ($achievement['name']) {
            case 'أول كوبون':
                return $userGamification->coupons_used >= 1;
                
            case 'جامع الكوبونات':
                return $userGamification->coupons_used >= 10;
                
            case 'مستخدم قوي':
                return $userGamification->coupons_used >= 50;
                
            default:
                return false;
        }
    }
    
    /**
     * Check financial achievements
     */
    protected function checkFinancialAchievement(array $achievement, UserGamification $userGamification): bool
    {
        return $userGamification->total_savings >= 1000; // 1000 SAR savings
    }
    
    /**
     * Check diversity achievements
     */
    protected function checkDiversityAchievement(array $achievement, UserGamification $userGamification): bool
    {
        $categories = json_decode($userGamification->categories_explored, true) ?? [];
        return count($categories) >= 5;
    }
    
    /**
     * Check streak achievements
     */
    protected function checkStreakAchievement(array $achievement, UserGamification $userGamification): bool
    {
        switch ($achievement['name']) {
            case 'أسبوع نشط':
                return $userGamification->current_streak >= 7;
                
            case 'شهر نشط':
                return $userGamification->current_streak >= 30;
                
            default:
                return false;
        }
    }
    
    /**
     * Check social achievements
     */
    protected function checkSocialAchievement(array $achievement, UserGamification $userGamification): bool
    {
        return $userGamification->social_shares >= 10;
    }
    
    /**
     * Check time-based achievements
     */
    protected function checkTimeBasedAchievement(array $achievement, string $action): bool
    {
        $hour = now()->hour;
        
        switch ($achievement['name']) {
            case 'الطائر المبكر':
                return $action === 'coupon_used' && $hour >= 5 && $hour < 9;
                
            case 'البومة الليلية':
                return $action === 'coupon_used' && $hour >= 21 || $hour < 2;
                
            default:
                return false;
        }
    }
    
    /**
     * Check value achievements
     */
    protected function checkValueAchievement(array $achievement, array $context): bool
    {
        return isset($context['discount_percent']) && $context['discount_percent'] >= 50;
    }
    
    /**
     * Check loyalty achievements
     */
    protected function checkLoyaltyAchievement(array $achievement, UserGamification $userGamification, array $context): bool
    {
        if (!isset($context['store_id'])) {
            return false;
        }
        
        $stores = json_decode($userGamification->stores_visited, true) ?? [];
        $storeCounts = array_count_values($stores);
        
        return isset($storeCounts[$context['store_id']]) && $storeCounts[$context['store_id']] >= 5;
    }
    
    /**
     * Check popularity achievements
     */
    protected function checkPopularityAchievement(array $achievement, array $context): bool
    {
        return isset($context['is_trending']) && $context['is_trending'];
    }
    
    /**
     * Check engagement achievements
     */
    protected function checkEngagementAchievement(array $achievement, UserGamification $userGamification): bool
    {
        return $userGamification->reviews_written >= 5;
    }
    
    /**
     * Check community achievements
     */
    protected function checkCommunityAchievement(array $achievement, UserGamification $userGamification): bool
    {
        return $userGamification->friends_invited >= 5;
    }
    
    /**
     * Calculate user level based on points
     */
    protected function calculateLevel(int $totalPoints): int
    {
        foreach ($this->levelDefinitions as $level => $definition) {
            if ($totalPoints >= $definition['min_points']) {
                $currentLevel = $level;
            }
        }
        
        return $currentLevel ?? 1;
    }
    
    /**
     * Update user streak
     */
    protected function updateStreak(UserGamification $userGamification, string $action): void
    {
        if ($action !== 'coupon_used') {
            return;
        }
        
        $lastActivity = $userGamification->last_activity_date;
        $today = now()->startOfDay();
        $lastActivityDay = $lastActivity->copy()->startOfDay();
        
        if ($today->diffInDays($lastActivityDay) === 1) {
            // Consecutive day
            $userGamification->current_streak++;
        } elseif ($today->diffInDays($lastActivityDay) === 0) {
            // Same day, no change
            return;
        } else {
            // Streak broken
            $userGamification->current_streak = 1;
        }
        
        // Update longest streak
        if ($userGamification->current_streak > $userGamification->longest_streak) {
            $userGamification->longest_streak = $userGamification->current_streak;
        }
        
        $userGamification->last_activity_date = now();
    }
    
    /**
     * Cache user gamification data
     */
    protected function cacheUserGamification(int $userId, UserGamification $userGamification): void
    {
        Cache::put("user_gamification_{$userId}", $userGamification, 3600);
    }
    
    /**
     * Get leaderboard
     */
    public function getLeaderboard(string $type = 'points', int $limit = 50): array
    {
        try {
            $query = UserGamification::query();
            
            switch ($type) {
                case 'points':
                    $query->orderByDesc('total_points');
                    break;
                case 'streak':
                    $query->orderByDesc('current_streak');
                    break;
                case 'savings':
                    $query->orderByDesc('total_savings');
                    break;
                case 'coupons':
                    $query->orderByDesc('coupons_used');
                    break;
            }
            
            $leaderboard = $query->with('user')
                ->limit($limit)
                ->get()
                ->map(function ($item) use ($type) {
                    return [
                        'user_id' => $item->user_id,
                        'username' => $item->user->name ?? 'مستخدم',
                        'avatar' => $item->user->avatar ?? null,
                        'score' => $item->{$type === 'points' ? 'total_points' : 
                                 ($type === 'streak' ? 'current_streak' : 
                                 ($type === 'savings' ? 'total_savings' : 'coupons_used'))},
                        'level' => $item->level,
                        'rank' => 0 // Will be set after ordering
                    ];
                })
                ->toArray();
            
            // Set ranks
            foreach ($leaderboard as $index => &$entry) {
                $entry['rank'] = $index + 1;
            }
            
            return $leaderboard;
            
        } catch (\Exception $e) {
            Log::error('Error getting leaderboard', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }
    
    /**
     * Get user rank in leaderboard
     */
    public function getUserRank(int $userId, string $type = 'points'): int
    {
        try {
            $userGamification = $this->getUserGamification($userId);
            $scoreColumn = $type === 'points' ? 'total_points' : 
                         ($type === 'streak' ? 'current_streak' : 
                         ($type === 'savings' ? 'total_savings' : 'coupons_used'));
            
            $rank = UserGamification::where($scoreColumn, '>', $userGamification->{$scoreColumn})
                ->count() + 1;
            
            return $rank;
            
        } catch (\Exception $e) {
            Log::error('Error getting user rank', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            
            return 0;
        }
    }
    
    /**
     * Get user statistics
     */
    public function getUserStatistics(int $userId): array
    {
        try {
            $userGamification = $this->getUserGamification($userId);
            $achievements = json_decode($userGamification->achievements, true) ?? [];
            $badges = [];
            
            // Get badges from achievements
            foreach ($achievements as $achievementId) {
                if (isset($this->achievementDefinitions[$achievementId])) {
                    $badgeId = $this->achievementDefinitions[$achievementId]['badge_id'];
                    if (isset($this->badgeDefinitions[$badgeId])) {
                        $badges[] = array_merge(
                            $this->badgeDefinitions[$badgeId],
                            ['unlocked_at' => now()->toISOString()]
                        );
                    }
                }
            }
            
            return [
                'user_id' => $userId,
                'total_points' => $userGamification->total_points,
                'level' => $userGamification->level,
                'level_name' => $this->levelDefinitions[$userGamification->level]['name'] ?? 'مبتدئ',
                'current_streak' => $userGamification->current_streak,
                'longest_streak' => $userGamification->longest_streak,
                'coupons_used' => $userGamification->coupons_used,
                'total_savings' => $userGamification->total_savings,
                'social_shares' => $userGamification->social_shares,
                'reviews_written' => $userGamification->reviews_written,
                'friends_invited' => $userGamification->friends_invited,
                'achievements_count' => count($achievements),
                'badges_count' => count($badges),
                'achievements' => $achievements,
                'badges' => $badges,
                'categories_explored' => json_decode($userGamification->categories_explored, true) ?? [],
                'stores_visited' => json_decode($userGamification->stores_visited, true) ?? [],
                'rank_points' => $this->getUserRank($userId, 'points'),
                'rank_streak' => $this->getUserRank($userId, 'streak'),
                'rank_savings' => $this->getUserRank($userId, 'savings'),
                'next_level_points' => $this->getNextLevelPoints($userGamification->level),
                'progress_to_next_level' => $this->getProgressToNextLevel($userGamification)
            ];
            
        } catch (\Exception $e) {
            Log::error('Error getting user statistics', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }
    
    /**
     * Get points needed for next level
     */
    protected function getNextLevelPoints(int $currentLevel): int
    {
        $nextLevel = $currentLevel + 1;
        return $this->levelDefinitions[$nextLevel]['min_points'] ?? 0;
    }
    
    /**
     * Get progress to next level
     */
    protected function getProgressToNextLevel(UserGamification $userGamification): array
    {
        $currentLevel = $userGamification->level;
        $currentPoints = $userGamification->total_points;
        $nextLevelPoints = $this->getNextLevelPoints($currentLevel);
        
        if ($currentLevel >= count($this->levelDefinitions)) {
            return [
                'current_points' => $currentPoints,
                'next_level_points' => $currentPoints,
                'progress_percentage' => 100,
                'points_needed' => 0
            ];
        }
        
        $currentLevelPoints = $this->levelDefinitions[$currentLevel]['min_points'];
        $pointsNeeded = $nextLevelPoints - $currentLevelPoints;
        $pointsEarned = $currentPoints - $currentLevelPoints;
        $progressPercentage = ($pointsEarned / $pointsNeeded) * 100;
        
        return [
            'current_points' => $currentPoints,
            'next_level_points' => $nextLevelPoints,
            'progress_percentage' => min($progressPercentage, 100),
            'points_needed' => max(0, $nextLevelPoints - $currentPoints)
        ];
    }
    
    /**
     * Get gamification settings
     */
    public function getGamificationSettings(): array
    {
        return [
            'achievements' => $this->achievementDefinitions,
            'badges' => $this->badgeDefinitions,
            'levels' => $this->levelDefinitions,
            'streak_bonuses' => $this->streakBonuses,
            'action_points' => [
                'coupon_used' => 10,
                'coupon_shared' => 5,
                'coupon_reviewed' => 15,
                'friend_invited' => 50,
                'daily_login' => 2,
                'profile_completed' => 25,
                'first_visit' => 5,
                'category_explored' => 3,
                'store_visited' => 2,
                'search_performed' => 1,
                'favorite_added' => 3,
                'achievement_unlocked' => 20
            ]
        ];
    }
    
    /**
     * Reset user gamification data
     */
    public function resetUserGamification(int $userId): bool
    {
        try {
            $userGamification = UserGamification::where('user_id', $userId)->first();
            if ($userGamification) {
                $userGamification->delete();
            }
            
            Cache::forget("user_gamification_{$userId}");
            
            Log::info('User gamification reset', ['user_id' => $userId]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Error resetting user gamification', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
}
