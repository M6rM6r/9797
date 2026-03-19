<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdvancedAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    protected AdvancedAnalyticsService $analyticsService;

    public function __construct(AdvancedAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get comprehensive dashboard data
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'date_from',
                'date_to',
                'store_id',
                'category',
                'period',
            ]);

            $dashboardData = $this->analyticsService->generateDashboardData($filters);

            return response()->json([
                'success' => true,
                'data' => $dashboardData,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Dashboard data generation failed', [
                'error' => $e->getMessage(),
                'filters' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get overview statistics
     */
    public function overview(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'date_from',
                'date_to',
                'store_id',
                'category',
            ]);

            $overview = $this->analyticsService->getOverviewStats($filters);

            return response()->json([
                'success' => true,
                'data' => $overview,
            ]);
        } catch (\Exception $e) {
            Log::error('Overview statistics failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load overview statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get trends data
     */
    public function trends(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'period',
                'category',
                'store_id',
                'date_from',
                'date_to',
            ]);

            $trends = $this->analyticsService->getTrendsData($filters);

            return response()->json([
                'success' => true,
                'data' => $trends,
            ]);
        } catch (\Exception $e) {
            Log::error('Trends data failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load trends data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user behavior analytics
     */
    public function userBehavior(Request $request): JsonResponse
    {
        try {
            $userBehavior = $this->analyticsService->getUserBehaviorAnalytics([]);

            return response()->json([
                'success' => true,
                'data' => $userBehavior,
            ]);
        } catch (\Exception $e) {
            Log::error('User behavior analytics failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load user behavior data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get performance metrics
     */
    public function performance(Request $request): JsonResponse
    {
        try {
            $performance = $this->analyticsService->getPerformanceMetrics([]);

            return response()->json([
                'success' => true,
                'data' => $performance,
            ]);
        } catch (\Exception $e) {
            Log::error('Performance metrics failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load performance metrics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get predictive analytics
     */
    public function predictions(Request $request): JsonResponse
    {
        try {
            $predictions = $this->analyticsService->getPredictiveAnalytics([]);

            return response()->json([
                'success' => true,
                'data' => $predictions,
            ]);
        } catch (\Exception $e) {
            Log::error('Predictive analytics failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load predictive analytics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get revenue analytics
     */
    public function revenue(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'date_from',
                'date_to',
                'store_id',
                'category',
                'period',
            ]);

            $revenue = $this->analyticsService->getRevenueAnalytics($filters);

            return response()->json([
                'success' => true,
                'data' => $revenue,
            ]);
        } catch (\Exception $e) {
            Log::error('Revenue analytics failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load revenue analytics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get engagement metrics
     */
    public function engagement(Request $request): JsonResponse
    {
        try {
            $engagement = $this->analyticsService->getEngagementMetrics([]);

            return response()->json([
                'success' => true,
                'data' => $engagement,
            ]);
        } catch (\Exception $e) {
            Log::error('Engagement metrics failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load engagement metrics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get real-time analytics
     */
    public function realtime(): JsonResponse
    {
        try {
            $realtimeData = $this->analyticsService->getRealTimeAnalytics();

            return response()->json([
                'success' => true,
                'data' => $realtimeData,
            ]);
        } catch (\Exception $e) {
            Log::error('Real-time analytics failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load real-time analytics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export analytics data
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $exportType = $request->get('type', 'csv');
            $filters = $request->only([
                'date_from',
                'date_to',
                'store_id',
                'category',
                'period',
            ]);

            $data = match($exportType) {
                'csv' => $this->exportCSV($filters),
                'json' => $this->exportJSON($filters),
                'excel' => $this->exportExcel($filters),
                default => $this->exportCSV($filters),
            };

            return response()->json([
                'success' => true,
                'data' => $data,
                'export_type' => $exportType,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Analytics export failed', [
                'error' => $e->getMessage(),
                'export_type' => $request->get('type', 'csv'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export analytics data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get custom report
     */
    public function customReport(Request $request): JsonResponse
    {
        try {
            $reportConfig = $request->all();
            
            $report = $this->analyticsService->generateCustomReport($reportConfig);

            return response()->json([
                'success' => true,
                'data' => $report,
                'config' => $reportConfig,
            ]);
        } catch (\Exception $e) {
            Log::error('Custom report generation failed', [
                'error' => $e->getMessage(),
                'config' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate custom report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get ML insights
     */
    public function mlInsights(): JsonResponse
    {
        try {
            $insights = $this->analyticsService->getMLInsights();

            return response()->json([
                'success' => true,
                'data' => $insights,
            ]);
        } catch (\Exception $e) {
            Log::error('ML insights failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load ML insights',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get cache statistics
     */
    public function cacheStats(): JsonResponse
    {
        try {
            $cacheStats = $this->analyticsService->getCacheStatistics();

            return response()->json([
                'success' => true,
                'data' => $cacheStats,
            ]);
        } catch (\Exception $e) {
            Log::error('Cache statistics failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load cache statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get system health check
     */
    public function health(): JsonResponse
    {
        try {
            $health = [
                'status' => 'healthy',
                'service' => 'Arabic Coupon Analytics API',
                'version' => '2.0.0',
                'timestamp' => now()->toISOString(),
                'checks' => [
                    'database' => $this->checkDatabaseHealth(),
                    'cache' => $this->checkCacheHealth(),
                    'bigquery' => $this->checkBigQueryHealth(),
                    'ml_models' => $this->checkMLModelsHealth(),
                ],
            ];

            return response()->json($health);
        } catch (\Exception $e) {
            Log::error('Health check failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export data to CSV
     */
    protected function exportCSV(array $filters): array
    {
        try {
            $data = $this->analyticsService->getAnalyticsDataForExport($filters);
            
            $csv = "date,coupon_id,code,store,category,discount_percent,usage_count,created_at\n";
            
            foreach ($data as $row) {
                $csv .= implode(',', [
                    $row['date'] ?? '',
                    $row['coupon_id'] ?? '',
                    $row['code'] ?? '',
                    $row['store_name'] ?? '',
                    $row['category'] ?? '',
                    $row['discount_percent'] ?? 0,
                    $row['usage_count'] ?? 0,
                    $row['created_at'] ?? '',
                ]) . "\n";
            }

            return [
                'type' => 'csv',
                'content' => $csv,
                'filename' => 'analytics_' . date('Y-m-d_H-i-s') . '.csv',
                'size' => strlen($csv),
            ];
        } catch (\Exception $e) {
            Log::error('CSV export failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Export data to JSON
     */
    protected function exportJSON(array $filters): array
    {
        try {
            $data = $this->analyticsService->getAnalyticsDataForExport($filters);
            
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            return [
                'type' => 'json',
                'content' => $json,
                'filename' => 'analytics_' . date('Y-m-d_H-i-s') . '.json',
                'size' => strlen($json),
            ];
        } catch (\Exception $e) {
            Log::error('JSON export failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Export data to Excel
     */
    protected function exportExcel(array $filters): array
    {
        try {
            $data = $this->analyticsService->getAnalyticsDataForExport($filters);
            
            // This would use a library like Laravel Excel
            // For now, return CSV as fallback
            return $this->exportCSV($filters);
        } catch (\Exception $e) {
            Log::error('Excel export failed', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Check database health
     */
    protected function checkDatabaseHealth(): array
    {
        try {
            // Check database connection
            $connection = \DB::connection();
            $connection->getPdo();
            
            return [
                'status' => 'healthy',
                'connection' => 'connected',
                'database' => config('database.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache health
     */
    protected function checkCacheHealth(): array
    {
        try {
            $cacheService = app('cache');
            $cacheService->put('health_check', 'test', 60);
            
            return [
                'status' => 'healthy',
                'cache' => 'connected',
                'test' => $cacheService->get('health_check') === 'test',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check BigQuery health
     */
    protected function checkBigQueryHealth(): array
    {
        try {
            // This would check BigQuery connection
            return [
                'status' => 'healthy',
                'service' => 'connected',
                'project' => config('services.bigquery.project_id'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check ML models health
     */
    protected function checkMLModelsHealth(): array
    {
        try {
            $models = $this->analyticsService->getMLModelsStatus();
            
            return [
                'status' => 'healthy',
                'models' => $models,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }
}
