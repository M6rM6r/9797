<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;
use App\Models\UserGamification;

class CleanGamificationService
{
    protected array $achievements;
    protected array $badges;
    protected array $levels;
    
    public function __construct()
    {
        $this->initializeAchievements();
        $this->initializeBadges();
        $this->initializeLevels();
    }
    
    /**
     * Initialize achievements
     */
    protected function initializeAchievements(): void
    {
        $this->achievements = [
            'first_coupon' => [
                'id' => 'first_coupon',
                'name' => 'أول كوبون',
                'description' => 'استخدم أول كوبون',
                'points' => 50,
                'badge_id' => 'first_coupon_badge',
                'type' => 'milestone',
                'icon' => '🎯'
            ],
            'coupon_collector' => [
                'id' => 'coupon_collector',
                'name' => 'جامع الكوبونات',
                'description' => 'استخدم 10 كوبونات',
                'points' => 100,
                'badge_id' => 'collector_badge',
                'type' => 'milestone',
                'icon' => '📚'
            ],
            'power_user' => [
                'id' => 'power_user',
                'name' => 'مستخدم قوي',
                'description' => 'استخدم 50 كوبون',
                'points' => 500,
                'badge_id' => 'power_user_badge',
                'type' => 'milestone',
                'icon' => '⚡'
            ],
            'savings_master' => [
                'id' => 'savings_master',
                'name' => 'خبير التوفير',
                'description' => 'وفر 1000 ريال',
                'points' => 200,
                'badge_id' => 'savings_badge',
                'type' => 'financial',
                'icon' => '💰'
            ],
            'category_explorer' => [
                'id' => 'category_explorer',
                'name' => 'مستكشف الفئات',
                'description' => 'استخدم كوبونات من 5 فئات مختلفة',
                'points' => 150,
                'badge_id' => 'explorer_badge',
                'type' => 'diversity',
                'icon' => '🧭'
            ],
            'week_streak' => [
                'id' => 'week_streak',
                'name' => 'أسبوع نشط',
                'description' => 'استخدم كوبون لمدة 7 أيام متتالية',
                'points' => 300,
                'badge_id' => 'week_streak_badge',
                'type' => 'streak',
                'icon' => '🔥'
            ],
            'month_streak' => [
                'id' => 'month_streak',
                'name' => 'شهر نشط',
                'description' => 'استخدم كوبون لمدة 30 يوم متتالية',
                'points' => 1000,
                'badge_id' => 'month_streak_badge',
                'type' => 'streak',
                'icon' => '👑'
            ]
        ];
    }
    
    /**
     * Initialize badges
     */
    protected function initializeBadges(): void
    {
        $this->badges = [
            'first_coupon_badge' => [
                'id' => 'first_coupon_badge',
                'name' => 'بادئ',
                'icon' => '🎯',
                'color' => '#4CAF50',
                'rarity' => 'common'
            ],
            'collector_badge' => [
                'id' => 'collector_badge',
                'name' => 'جامع',
                'icon' => '📚',
                'color' => '#2196F3',
                'rarity' => 'uncommon'
            ],
            'power_user_badge' => [
                'id' => 'power_user_badge',
                'name' => 'قوي',
                'icon' => '⚡',
                'color' => '#FF9800',
                'rarity' => 'rare'
            ],
            'savings_badge' => [
                'id' => 'savings_badge',
                'name' => 'موفر',
                'icon' => '💰',
                'color' => '#F44336',
                'rarity' => 'epic'
            ],
            'explorer_badge' => [
                'id' => 'explorer_badge',
                'name' => 'مستكشف',
                'icon' => '🧭',
                'color' => '#9C27B0',
                'rarity' => 'uncommon'
            ],
            'week_streak_badge' => [
                'id' => 'week_streak_badge',
                'name' => 'نشط',
                'icon' => '🔥',
                'color' => '#FF5722',
                'rarity' => 'rare'
            ],
            'month_streak_badge' => [
                'id' => 'month_streak_badge',
                'name' => 'أسطوري',
                'icon' => '👑',
                'color' => '#FFD700',
                'rarity' => 'legendary'
            ]
        ];
    }
    
    /**
     * Initialize levels
     */
    protected function initializeLevels(): void
    {
        $this->levels = [
            1 => ['id' => 1, 'name' => 'مبتدئ', 'min_points' => 0, 'color' => '#9E9E9E'],
            2 => ['id' => 2, 'name' => 'متعلم', 'min_points' => 100, 'color' => '#607D8B'],
            3 => ['id' => 3, 'name' => 'مشارك', 'min_points' => 250, 'color' => '#2196F3'],
            4 => ['id' => 4, 'name' => 'نشط', 'min_points' => 500, 'color' => '#4CAF50'],
            5 => ['id' => 5, 'name' => 'متقدم', 'min_points' => 1000, 'color' => '#FF9800'],
            6 => ['id' => 6, 'name' => 'خبير', 'min_points' => 2000, 'color' => '#F44336'],
            7 => ['id' => 7, 'name' => 'محترف', 'min_points' => 5000, 'color' => '#9C27B0'],
            8 => ['id' => 8, 'name' => 'أسطورة', 'min_points' => 10000, 'color' => '#FFD700']
        ];
    }
    
    /**
     * Process user action
     */
    public function processUserAction(int $userId, string $action, array $context = []): array
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                return ['success' => false, 'error' => 'User not found'];
            }
            
            // Get or create user gamification data
            $userGamification = $this->getOrCreateUserGamification($userId);
            
            // Process action and award points
            $pointsAwarded = $this->awardPoints($userGamification, $action, $context);
            
            // Check for achievements
            $achievementsUnlocked = $this->checkAchievements($userGamification, $action, $context);
            
            // Update streak
            $this->updateStreak($userGamification, $action);
            
            // Calculate and update level
            $levelUp = $this->updateLevel($userGamification);
            
            // Save changes
            $userGamification->save();
            
            // Cache user data
            $this->cacheUserGamification($userGamification);
            
            Log::info('User action processed', [
                'user_id' => $userId,
                'action' => $action,
                'points_awarded' => $pointsAwarded,
                'achievements_unlocked' => count($achievementsUnlocked)
            ]);
            
            return [
                'success' => true,
                'points_awarded' => $pointsAwarded,
                'achievements_unlocked' => $achievementsUnlocked,
                'level_up' => $levelUp,
                'new_level' => $userGamification->level,
                'total_points' => $userGamification->total_points
            ];
            
        } catch (\Exception $e) {
            Log::error('Error processing user action', [
                'user_id' => $userId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Get or create user gamification data
     */
    protected function getOrCreateUserGamification(int $userId): UserGamification
    {
        $userGamification = UserGamification::where('user_id', $userId)->first();
        
        if (!$userGamification) {
            $userGamification = UserGamification::create([
                'user_id' => $userId,
                'total_points' => 0,
                'level' => 1,
                'current_streak' => 0,
                'longest_streak' => 0,
                'coupons_used' => 0,
                'total_savings' => 0,
                'achievements' => json_encode([]),
                'badges' => json_encode([]),
                'categories_explored' => json_encode([]),
                'stores_visited' => json_encode([]),
                'last_activity' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        return $userGamification;
    }
    
    /**
     * Award points for action
     */
    protected function awardPoints(UserGamification $userGamification, string $action, array $context): int
    {
        $points = 0;
        
        switch ($action) {
            case 'coupon_used':
                $points = 10;
                $userGamification->coupons_used += 1;
                if (isset($context['savings'])) {
                    $userGamification->total_savings += $context['savings'];
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
                $points = 5;
                break;
                
            case 'coupon_favorited':
                $points = 2;
                break;
                
            case 'review_written':
                $points = 15;
                break;
                
            case 'friend_invited':
                $points = 25;
                break;
                
            case 'daily_login':
                $points = 3;
                break;
        }
        
        $userGamification->total_points += $points;
        
        return $points;
    }
    
    /**
     * Check for achievements
     */
    protected function checkAchievements(UserGamification $userGamification, string $action, array $context): array
    {
        $unlockedAchievements = [];
        $currentAchievements = json_decode($userGamification->achievements, true) ?? [];
        
        foreach ($this->achievements as $achievementId => $achievement) {
            if (in_array($achievementId, $currentAchievements)) {
                continue; // Already unlocked
            }
            
            if ($this->checkAchievementCriteria($achievementId, $userGamification, $action, $context)) {
                $currentAchievements[] = $achievementId;
                $unlockedAchievements[] = $achievement;
                
                // Award achievement points
                $userGamification->total_points += $achievement['points'];
                
                // Add badge if applicable
                $this->addBadge($userGamification, $achievement['badge_id']);
            }
        }
        
        $userGamification->achievements = json_encode($currentAchievements);
        
        return $unlockedAchievements;
    }
    
    /**
     * Check achievement criteria
     */
    protected function checkAchievementCriteria(string $achievementId, UserGamification $userGamification, string $action, array $context): bool
    {
        switch ($achievementId) {
            case 'first_coupon':
                return $userGamification->coupons_used >= 1;
                
            case 'coupon_collector':
                return $userGamification->coupons_used >= 10;
                
            case 'power_user':
                return $userGamification->coupons_used >= 50;
                
            case 'savings_master':
                return $userGamification->total_savings >= 1000;
                
            case 'category_explorer':
                $categories = json_decode($userGamification->categories_explored, true) ?? [];
                return count($categories) >= 5;
                
            case 'week_streak':
                return $userGamification->current_streak >= 7;
                
            case 'month_streak':
                return $userGamification->current_streak >= 30;
                
            default:
                return false;
        }
    }
    
    /**
     * Add badge to user
     */
    protected function addBadge(UserGamification $userGamification, string $badgeId): void
    {
        $currentBadges = json_decode($userGamification->badges, true) ?? [];
        
        if (!in_array($badgeId, $currentBadges)) {
            $currentBadges[] = $badgeId;
            $userGamification->badges = json_encode($currentBadges);
        }
    }
    
    /**
     * Update streak
     */
    protected function updateStreak(UserGamification $userGamification, string $action): void
    {
        if ($action !== 'coupon_used') {
            return;
        }
        
        $lastActivity = $userGamification->last_activity;
        $today = now()->startOfDay();
        $lastActivityDay = $lastActivity->copy()->startOfDay();
        
        if ($lastActivityDay->eq($today)) {
            // Same day - no change to streak
            return;
        }
        
        if ($lastActivityDay->diffInDays($today) === 1) {
            // Consecutive day - increment streak
            $userGamification->current_streak += 1;
            
            if ($userGamification->current_streak > $userGamification->longest_streak) {
                $userGamification->longest_streak = $userGamification->current_streak;
            }
        } else {
            // Gap in days - reset streak
            $userGamification->current_streak = 1;
        }
        
        $userGamification->last_activity = now();
    }
    
    /**
     * Update level
     */
    protected function updateLevel(UserGamification $userGamification): bool
    {
        $currentLevel = $userGamification->level;
        $newLevel = $this->calculateLevel($userGamification->total_points);
        
        if ($newLevel > $currentLevel) {
            $userGamification->level = $newLevel;
            return true;
        }
        
        return false;
    }
    
    /**
     * Calculate level based on points
     */
    protected function calculateLevel(int $totalPoints): int
    {
        foreach ($this->levels as $level => $data) {
            if ($totalPoints >= $data['min_points']) {
                $currentLevel = $level;
            }
        }
        
        return $currentLevel ?? 1;
    }
    
    /**
     * Get user statistics
     */
    public function getUserStats(int $userId): array
    {
        try {
            $userGamification = UserGamification::where('user_id', $userId)->first();
            
            if (!$userGamification) {
                return $this->getDefaultStats();
            }
            
            return [
                'user_id' => $userId,
                'total_points' => $userGamification->total_points,
                'level' => $userGamification->level,
                'level_name' => $this->levels[$userGamification->level]['name'] ?? 'مبتدئ',
                'current_streak' => $userGamification->current_streak,
                'longest_streak' => $userGamification->longest_streak,
                'coupons_used' => $userGamification->coupons_used,
                'total_savings' => $userGamification->total_savings,
                'achievements' => json_decode($userGamification->achievements, true) ?? [],
                'badges' => json_decode($userGamification->badges, true) ?? [],
                'categories_explored' => json_decode($userGamification->categories_explored, true) ?? [],
                'stores_visited' => json_decode($userGamification->stores_visited, true) ?? [],
                'next_level_points' => $this->getNextLevelPoints($userGamification->level),
                'progress_to_next_level' => $this->getProgressToNextLevel($userGamification)
            ];
            
        } catch (\Exception $e) {
            Log::error('Error getting user stats', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return $this->getDefaultStats();
        }
    }
    
    /**
     * Get default stats
     */
    protected function getDefaultStats(): array
    {
        return [
            'user_id' => 0,
            'total_points' => 0,
            'level' => 1,
            'level_name' => 'مبتدئ',
            'current_streak' => 0,
            'longest_streak' => 0,
            'coupons_used' => 0,
            'total_savings' => 0,
            'achievements' => [],
            'badges' => [],
            'categories_explored' => [],
            'stores_visited' => [],
            'next_level_points' => 100,
            'progress_to_next_level' => [
                'current_points' => 0,
                'next_level_points' => 100,
                'progress_percentage' => 0,
                'points_needed' => 100
            ]
        ];
    }
    
    /**
     * Get next level points
     */
    protected function getNextLevelPoints(int $currentLevel): ?int
    {
        $nextLevel = $currentLevel + 1;
        return $this->levels[$nextLevel]['min_points'] ?? null;
    }
    
    /**
     * Get progress to next level
     */
    protected function getProgressToNextLevel(UserGamification $userGamification): array
    {
        $currentLevel = $userGamification->level;
        $currentPoints = $userGamification->total_points;
        $nextLevelPoints = $this->getNextLevelPoints($currentLevel);
        
        if (!$nextLevelPoints) {
            return [
                'current_points' => $currentPoints,
                'next_level_points' => $currentPoints,
                'progress_percentage' => 100,
                'points_needed' => 0
            ];
        }
        
        $levelMinPoints = $this->levels[$currentLevel]['min_points'];
        $pointsInCurrentLevel = $currentPoints - $levelMinPoints;
        $pointsNeededForNextLevel = $nextLevelPoints - $levelMinPoints;
        $progressPercentage = ($pointsInCurrentLevel / $pointsNeededForNextLevel) * 100;
        
        return [
            'current_points' => $currentPoints,
            'next_level_points' => $nextLevelPoints,
            'progress_percentage' => round($progressPercentage, 2),
            'points_needed' => max(0, $nextLevelPoints - $currentPoints)
        ];
    }
    
    /**
     * Get leaderboard
     */
    public function getLeaderboard(string $type = 'points', int $limit = 50): array
    {
        try {
            $query = UserGamification::select('user_id', 'total_points', 'level', 'current_streak', 'total_savings')
                ->with('user:id,name,email')
                ->orderBy($this->getLeaderboardOrder($type), 'desc')
                ->limit($limit);
            
            $entries = $query->get();
            
            $leaderboard = [];
            $rank = 1;
            
            foreach ($entries as $entry) {
                $leaderboard[] = [
                    'rank' => $rank++,
                    'user_id' => $entry->user_id,
                    'username' => $entry->user->name ?? 'Anonymous',
                    'avatar' => $entry->user->avatar ?? null,
                    'score' => $this->getLeaderboardScore($entry, $type),
                    'level' => $entry->level
                ];
            }
            
            return [
                'type' => $type,
                'entries' => $leaderboard,
                'total' => count($leaderboard)
            ];
            
        } catch (\Exception $e) {
            Log::error('Error getting leaderboard', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            
            return ['type' => $type, 'entries' => [], 'total' => 0];
        }
    }
    
    /**
     * Get leaderboard order column
     */
    protected function getLeaderboardOrder(string $type): string
    {
        switch ($type) {
            case 'points':
                return 'total_points';
            case 'streak':
                return 'current_streak';
            case 'savings':
                return 'total_savings';
            default:
                return 'total_points';
        }
    }
    
    /**
     * Get leaderboard score
     */
    protected function getLeaderboardScore(UserGamification $entry, string $type): int
    {
        switch ($type) {
            case 'points':
                return $entry->total_points;
            case 'streak':
                return $entry->current_streak;
            case 'savings':
                return (int) $entry->total_savings;
            default:
                return $entry->total_points;
        }
    }
    
    /**
     * Get user rank
     */
    public function getUserRank(int $userId, string $type = 'points'): int
    {
        try {
            $userGamification = UserGamification::where('user_id', $userId)->first();
            
            if (!$userGamification) {
                return 0;
            }
            
            $score = $this->getLeaderboardScore($userGamification, $type);
            
            $rank = UserGamification::where($this->getLeaderboardOrder($type), '>', $score)
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
     * Cache user gamification data
     */
    protected function cacheUserGamification(UserGamification $userGamification): void
    {
        $cacheKey = "user_gamification_{$userGamification->user_id}";
        $cacheData = [
            'total_points' => $userGamification->total_points,
            'level' => $userGamification->level,
            'current_streak' => $userGamification->current_streak,
            'longest_streak' => $userGamification->longest_streak,
            'coupons_used' => $userGamification->coupons_used,
            'total_savings' => $userGamification->total_savings,
            'achievements' => json_decode($userGamification->achievements, true) ?? [],
            'badges' => json_decode($userGamification->badges, true) ?? [],
            'categories_explored' => json_decode($userGamification->categories_explored, true) ?? [],
            'stores_visited' => json_decode($userGamification->stores_visited, true) ?? [],
            'last_activity' => $userGamification->last_activity
        ];
        
        Cache::put($cacheKey, $cacheData, 3600); // 1 hour
    }
    
    /**
     * Get all achievements
     */
    public function getAllAchievements(): array
    {
        return $this->achievements;
    }
    
    /**
     * Get all badges
     */
    public function getAllBadges(): array
    {
        return $this->badges;
    }
    
    /**
     * Get all levels
     */
    public function getAllLevels(): array
    {
        return $this->levels;
    }
}
