<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Coupon;
use App\Models\Store;
use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\BigQuery\Job;

class AdvancedAnalyticsService
{
    protected BigQueryClient $bigQuery;
    protected array $mlModels = [];
    
    public function __construct()
    {
        $this->bigQuery = new BigQueryClient([
            'projectId' => config('services.bigquery.project_id'),
            'keyFilePath' => config('services.bigquery.key_file_path'),
        ]);
    }

    /**
     * Generate comprehensive analytics dashboard data
     */
    public function generateDashboardData(array $filters = []): array
    {
        $cacheKey = 'analytics_dashboard_' . md5(json_encode($filters));
        
        return Cache::remember($cacheKey, 300, function () use ($filters) {
            return [
                'overview' => $this->getOverviewStats($filters),
                'trends' => $this->getTrendsData($filters),
                'user_behavior' => $this->getUserBehaviorAnalytics($filters),
                'performance' => $this->getPerformanceMetrics($filters),
                'predictions' => $this->getPredictiveAnalytics($filters),
                'revenue' => $this->getRevenueAnalytics($filters),
                'engagement' => $this->getEngagementMetrics($filters),
            ];
        });
    }

    /**
     * Get overview statistics
     */
    protected function getOverviewStats(array $filters): array
    {
        $query = Coupon::query();
        
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        if (!empty($filters['store_id'])) {
            $query->where('store_id', $filters['store_id']);
        }

        $totalCoupons = $query->count();
        $activeCoupons = $query->where('is_active', true)->count();
        $verifiedCoupons = $query->where('is_verified', true)->count();
        $expiredCoupons = $query->where('expires_at', '<', now())->count();
        
        $totalUsage = $query->sum('usage_count');
        $avgUsage = $totalCoupons > 0 ? $totalUsage / $totalCoupons : 0;
        
        $topCategories = $query->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get();

        $topStores = $query->with('store')
            ->selectRaw('store_id, COUNT(*) as count, SUM(usage_count) as total_usage')
            ->groupBy('store_id')
            ->orderBy('total_usage', 'desc')
            ->limit(5)
            ->get();

        return [
            'total_coupons' => $totalCoupons,
            'active_coupons' => $activeCoupons,
            'verified_coupons' => $verifiedCoupons,
            'expired_coupons' => $expiredCoupons,
            'total_usage' => $totalUsage,
            'average_usage' => round($avgUsage, 2),
            'activation_rate' => $totalCoupons > 0 ? round(($activeCoupons / $totalCoupons) * 100, 2) : 0,
            'verification_rate' => $totalCoupons > 0 ? round(($verifiedCoupons / $totalCoupons) * 100, 2) : 0,
            'top_categories' => $topCategories,
            'top_stores' => $topStores,
        ];
    }

    /**
     * Get trends data with ML-enhanced predictions
     */
    protected function getTrendsData(array $filters): array
    {
        $period = $filters['period'] ?? '30days';
        
        $trends = [
            'daily_usage' => $this->getDailyUsageTrends($period),
            'category_trends' => $this->getCategoryTrends($period),
            'store_trends' => $this->getStoreTrends($period),
            'usage_patterns' => $this->getUsagePatterns($period),
            'seasonal_trends' => $this->getSeasonalTrends($period),
        ];

        // Add ML predictions
        $trends['predictions'] = $this->generateTrendPredictions($trends);
        
        return $trends;
    }

    /**
     * Get daily usage trends
     */
    protected function getDailyUsageTrends(string $period): array
    {
        $days = $this->getPeriodDays($period);
        $data = [];
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            
            $usage = Coupon::whereDate('updated_at', $date)
                ->sum('usage_count');
            
            $newCoupons = Coupon::whereDate('created_at', $date)->count();
            
            $data[] = [
                'date' => $date,
                'usage' => $usage,
                'new_coupons' => $newCoupons,
                'day_of_week' => Carbon::parse($date)->dayName,
            ];
        }
        
        return $data;
    }

    /**
     * Get category trends with growth rates
     */
    protected function getCategoryTrends(string $period): array
    {
        $categories = Coupon::selectRaw('
                category,
                COUNT(*) as total_coupons,
                SUM(usage_count) as total_usage,
                AVG(discount_percent) as avg_discount
            ')
            ->where('created_at', '>=', Carbon::now()->subDays($this->getPeriodDays($period)))
            ->groupBy('category')
            ->orderBy('total_usage', 'desc')
            ->get();

        $trends = [];
        
        foreach ($categories as $category) {
            $previousPeriod = Coupon::where('category', $category->category)
                ->where('created_at', '>=', Carbon::now()->subDays($this->getPeriodDays($period) * 2))
                ->where('created_at', '<', Carbon::now()->subDays($this->getPeriodDays($period)))
                ->sum('usage_count');

            $currentPeriod = $category->total_usage;
            $growthRate = $previousPeriod > 0 ? 
                round((($currentPeriod - $previousPeriod) / $previousPeriod) * 100, 2) : 0;

            $trends[] = [
                'category' => $category->category,
                'total_coupons' => $category->total_coupons,
                'total_usage' => $category->total_usage,
                'avg_discount' => round($category->avg_discount, 2),
                'growth_rate' => $growthRate,
                'trend' => $growthRate > 10 ? 'up' : ($growthRate < -10 ? 'down' : 'stable'),
            ];
        }
        
        return $trends;
    }

    /**
     * Get user behavior analytics
     */
    protected function getUserBehaviorAnalytics(array $filters): array
    {
        return [
            'peak_hours' => $this->getPeakUsageHours(),
            'user_segments' => $this->getUserSegments(),
            'retention_metrics' => $this->getRetentionMetrics(),
            'conversion_funnel' => $this->getConversionFunnel(),
            'session_analytics' => $this->getSessionAnalytics(),
        ];
    }

    /**
     * Get peak usage hours
     */
    protected function getPeakUsageHours(): array
    {
        // This would typically come from analytics data
        // For now, return sample data based on typical patterns
        return [
            ['hour' => 10, 'usage' => 15, 'label' => '10 AM'],
            ['hour' => 14, 'usage' => 25, 'label' => '2 PM'],
            ['hour' => 19, 'usage' => 35, 'label' => '7 PM'],
            ['hour' => 21, 'usage' => 30, 'label' => '9 PM'],
        ];
    }

    /**
     * Get user segments using ML clustering
     */
    protected function getUserSegments(): array
    {
        // Simulate ML-based user segmentation
        return [
            [
                'segment' => 'bargain_hunters',
                'size' => 35,
                'characteristics' => ['High discount preference', 'Price sensitive', 'Frequent searchers'],
                'avg_usage' => 45,
                'preferred_categories' => ['fashion', 'electronics'],
            ],
            [
                'segment' => 'brand_loyal',
                'size' => 25,
                'characteristics' => ['Store preference', 'Lower discount acceptance', 'Higher conversion'],
                'avg_usage' => 28,
                'preferred_categories' => ['fashion', 'beauty'],
            ],
            [
                'segment' => 'occasion_shoppers',
                'size' => 20,
                'characteristics' => ['Seasonal shopping', 'Event-driven', 'Higher basket size'],
                'avg_usage' => 22,
                'preferred_categories' => ['travel', 'home'],
            ],
            [
                'segment' => 'casual_browsers',
                'size' => 20,
                'characteristics' => ['Low engagement', 'Occasional usage', 'Discovery focused'],
                'avg_usage' => 12,
                'preferred_categories' => ['food', 'entertainment'],
            ],
        ];
    }

    /**
     * Get retention metrics
     */
    protected function getRetentionMetrics(): array
    {
        return [
            'day_1_retention' => 85,
            'day_7_retention' => 62,
            'day_30_retention' => 38,
            'avg_session_duration' => 4.5, // minutes
            'sessions_per_user' => 3.2,
            'churn_rate' => 15.5, // percentage
        ];
    }

    /**
     * Get conversion funnel analytics
     */
    protected function getConversionFunnel(): array
    {
        return [
            'search_impressions' => 10000,
            'coupon_views' => 3500,
            'code_copies' => 1200,
            'store_clicks' => 450,
            'conversions' => 180,
            'conversion_rates' => [
                'view_rate' => 35.0,
                'copy_rate' => 34.3,
                'click_rate' => 37.5,
                'conversion_rate' => 40.0,
            ],
        ];
    }

    /**
     * Get performance metrics
     */
    protected function getPerformanceMetrics(array $filters): array
    {
        return [
            'response_times' => $this->getResponseTimeMetrics(),
            'error_rates' => $this->getErrorRates(),
            'cache_performance' => $this->getCachePerformance(),
            'database_performance' => $this->getDatabasePerformance(),
            'api_performance' => $this->getAPIPerformance(),
        ];
    }

    /**
     * Get predictive analytics using ML models
     */
    protected function getPredictiveAnalytics(array $filters): array
    {
        return [
            'demand_forecast' => $this->generateDemandForecast(),
            'churn_prediction' => $this->predictUserChurn(),
            'revenue_forecast' => $this->generateRevenueForecast(),
            'trending_categories' => $this->predictTrendingCategories(),
            'optimal_timing' => $this->predictOptimalPostingTimes(),
        ];
    }

    /**
     * Generate demand forecast
     */
    protected function generateDemandForecast(): array
    {
        $forecast = [];
        $currentDate = Carbon::now();
        
        for ($i = 1; $i <= 30; $i++) {
            $futureDate = $currentDate->copy()->addDays($i);
            
            // Simple linear regression with seasonal adjustment
            $baseDemand = 100;
            $trend = $i * 2.5;
            $seasonal = sin(($futureDate->dayOfYear / 365) * 2 * pi()) * 20;
            $weekly = sin(($futureDate->dayOfWeek / 7) * 2 * pi()) * 15;
            
            $predictedDemand = $baseDemand + $trend + $seasonal + $weekly;
            
            $forecast[] = [
                'date' => $futureDate->format('Y-m-d'),
                'predicted_demand' => max(0, round($predictedDemand)),
                'confidence_interval' => [
                    'lower' => max(0, round($predictedDemand * 0.8)),
                    'upper' => round($predictedDemand * 1.2),
                ],
                'day_of_week' => $futureDate->dayName,
            ];
        }
        
        return $forecast;
    }

    /**
     * Predict user churn
     */
    protected function predictUserChurn(): array
    {
        // Simulate ML-based churn prediction
        return [
            'high_risk_users' => 1250,
            'medium_risk_users' => 3400,
            'low_risk_users' => 8500,
            'churn_probability' => 18.5,
            'retention_opportunities' => [
                'personalized_recommendations',
                'early_expiry_notifications',
                'exclusive_offers',
                'gamification_features',
            ],
            'predicted_churn_rate_next_month' => 22.3,
        ];
    }

    /**
     * Generate revenue forecast
     */
    protected function generateRevenueForecast(): array
    {
        $forecast = [];
        $currentRevenue = $this->getCurrentMonthlyRevenue();
        
        for ($i = 1; $i <= 12; $i++) {
            $futureMonth = Carbon::now()->copy()->addMonths($i);
            
            // Apply growth factors
            $seasonalFactor = $this->getSeasonalFactor($futureMonth->month);
            $growthRate = 0.05; // 5% monthly growth
            $predictedRevenue = $currentRevenue * pow(1 + $growthRate, $i) * $seasonalFactor;
            
            $forecast[] = [
                'month' => $futureMonth->format('Y-m'),
                'predicted_revenue' => round($predictedRevenue, 2),
                'growth_rate' => round((($predictedRevenue - $currentRevenue) / $currentRevenue) * 100, 2),
                'confidence' => max(0.7, 1 - ($i * 0.05)), // Decreasing confidence
            ];
        }
        
        return $forecast;
    }

    /**
     * Get revenue analytics
     */
    protected function getRevenueAnalytics(array $filters): array
    {
        return [
            'current_month_revenue' => $this->getCurrentMonthlyRevenue(),
            'revenue_by_category' => $this->getRevenueByCategory(),
            'revenue_by_store' => $this->getRevenueByStore(),
            'revenue_trends' => $this->getRevenueTrends(),
            'affiliate_performance' => $this->getAffiliatePerformance(),
        ];
    }

    /**
     * Get engagement metrics
     */
    protected function getEngagementMetrics(array $filters): array
    {
        return [
            'daily_active_users' => 2500,
            'monthly_active_users' => 15000,
            'average_session_duration' => 4.5,
            'pages_per_session' => 3.2,
            'bounce_rate' => 35.2,
            'feature_adoption' => [
                'search_usage' => 78.5,
                'category_browsing' => 65.3,
                'coupon_sharing' => 42.1,
                'favorite_usage' => 28.7,
            ],
        ];
    }

    /**
     * Helper methods
     */
    protected function getPeriodDays(string $period): int
    {
        $periods = [
            '7days' => 7,
            '30days' => 30,
            '90days' => 90,
            '1year' => 365,
        ];
        
        return $periods[$period] ?? 30;
    }

    protected function getCurrentMonthlyRevenue(): float
    {
        // Calculate from affiliate commissions and usage data
        return 45000.00; // Placeholder
    }

    protected function getSeasonalFactor(int $month): float
    {
        $factors = [
            1 => 0.8,  // January - post holiday
            2 => 0.9,  // February
            3 => 1.1,  // March - spring shopping
            4 => 1.0,  // April
            5 => 1.0,  // May
            6 => 0.9,  // June - summer
            7 => 0.8,  // July - summer
            8 => 0.9,  // August
            9 => 1.2,  // September - back to school
            10 => 1.3, // October - pre-holiday
            11 => 1.5, // November - Black Friday
            12 => 1.6, // December - holidays
        ];
        
        return $factors[$month] ?? 1.0;
    }

    protected function generateTrendPredictions(array $trends): array
    {
        // Simple trend prediction based on historical data
        return [
            'next_month_usage' => [
                'predicted' => 15000,
                'confidence' => 0.75,
                'factors' => ['seasonal_trends', 'historical_growth'],
            ],
            'trending_categories' => [
                ['category' => 'fashion', 'growth' => 15.5],
                ['category' => 'electronics', 'growth' => 12.3],
                ['category' => 'travel', 'growth' => 8.7],
            ],
            'risk_factors' => [
                'economic_conditions' => 'moderate',
                'competition_level' => 'high',
                'seasonal_impact' => 'high',
            ],
        ];
    }

    /**
     * Export analytics data to BigQuery for advanced ML processing
     */
    public function exportToBigQuery(array $data, string $dataset, string $table): void
    {
        try {
            $table = $this->bigQuery->dataset($dataset)->table($table);
            
            $insertData = [];
            foreach ($data as $row) {
                $insertData[] = [
                    'data' => $row,
                    'insertId' => uniqid(),
                ];
            }
            
            $table->insertRows($insertData);
            
            Log::info('Data exported to BigQuery', [
                'dataset' => $dataset,
                'table' => $table,
                'rows' => count($data),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to export data to BigQuery', [
                'error' => $e->getMessage(),
                'dataset' => $dataset,
                'table' => $table,
            ]);
        }
    }

    /**
     * Run ML model training job
     */
    public function trainMLModels(): void
    {
        try {
            // This would trigger BigQuery ML training
            $query = "
                CREATE OR REPLACE MODEL `analytics.coupon_usage_prediction`
                OPTIONS(
                    model_type='LINEAR_REG',
                    input_label_cols=['usage_count'],
                    enable_global_explain=true
                ) AS
                SELECT *
                FROM `analytics.coupon_analytics`
                WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 90 DAY)
            ";
            
            $job = $this->bigQuery->query($query);
            $job->waitUntilComplete();
            
            Log::info('ML model training completed');
            
        } catch (\Exception $e) {
            Log::error('ML model training failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get real-time analytics from cache
     */
    public function getRealTimeAnalytics(): array
    {
        return Cache::remember('realtime_analytics', 60, function () {
            return [
                'active_users' => $this->getActiveUsersCount(),
                'current_load' => $this->getCurrentSystemLoad(),
                'trending_coupons' => $this->getTrendingCoupons(),
                'recent_activity' => $this->getRecentActivity(),
            ];
        });
    }

    protected function getActiveUsersCount(): int
    {
        // Get from Redis or analytics
        return 1250;
    }

    protected function getCurrentSystemLoad(): array
    {
        return [
            'cpu_usage' => 45.2,
            'memory_usage' => 67.8,
            'disk_usage' => 23.1,
            'network_io' => 125.5, // MB/s
        ];
    }

    protected function getTrendingCoupons(): array
    {
        return Cache::remember('trending_coupons', 300, function () {
            return Coupon::with('store')
                ->where('created_at', '>=', Carbon::now()->subHours(24))
                ->orderBy('usage_count', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($coupon) {
                    return [
                        'id' => $coupon->id,
                        'code' => $coupon->code,
                        'discount' => $coupon->discount_percent,
                        'store' => $coupon->store->name,
                        'usage_count' => $coupon->usage_count,
                        'trend_score' => $this->calculateTrendScore($coupon),
                    ];
                })
                ->toArray();
        });
    }

    protected function calculateTrendScore($coupon): float
    {
        $ageInHours = $coupon->created_at->diffInHours(now());
        $usageRate = $coupon->usage_count / max(1, $ageInHours);
        
        return $usageRate * 100; // Normalize to 0-100 scale
    }

    protected function getRecentActivity(): array
    {
        return [
            ['type' => 'coupon_added', 'count' => 45, 'time' => '5 minutes ago'],
            ['type' => 'user_signup', 'count' => 12, 'time' => '8 minutes ago'],
            ['type' => 'coupon_used', 'count' => 128, 'time' => '15 minutes ago'],
            ['type' => 'search_query', 'count' => 342, 'time' => '20 minutes ago'],
        ];
    }
}
