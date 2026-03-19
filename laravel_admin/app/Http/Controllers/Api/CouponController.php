<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Cache;
use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\DocumentReference;

class CouponController extends Controller
{
    protected FirestoreClient $firestore;

    public function __construct()
    {
        $this->firestore = new FirestoreClient([
            'projectId' => config('firebase.project_id'),
            'keyFile' => json_decode(config('firebase.private_key'), true),
        ]);
    }

    /**
     * Display a listing of coupons.
     */
    public function index(Request $request): JsonResponse
    {
        // Create cache key based on request parameters
        $cacheKey = 'coupons_' . md5(serialize($request->all()));

        // Try to get from cache first (cache for 5 minutes)
        $cachedResult = Cache::get($cacheKey);
        if ($cachedResult) {
            return response()->json($cachedResult);
        }

        $query = Coupon::with('store')
            ->when($request->category, function ($q, $category) {
                return $q->where('category', $category);
            })
            ->when($request->store_id, function ($q, $storeId) {
                return $q->where('store_id', $storeId);
            })
            ->when($request->is_verified !== null, function ($q) use ($request) {
                return $q->where('is_verified', $request->boolean('is_verified'));
            })
            ->when($request->is_active !== null, function ($q) use ($request) {
                return $q->where('is_active', $request->boolean('is_active'));
            })
            ->when($request->search, function ($q, $search) {
                return $q->where(function ($subQuery) use ($search) {
                    $subQuery->where('code', 'like', "%{$search}%")
                             ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy($request->sort_by ?? 'created_at', $request->sort_order ?? 'desc')
            ->paginate($request->per_page ?? 15);

        $result = [
            'success' => true,
            'data' => $query->items(),
            'pagination' => [
                'current_page' => $query->currentPage(),
                'last_page' => $query->lastPage(),
                'per_page' => $query->perPage(),
                'total' => $query->total(),
            ],
        ];

        // Cache the result for 5 minutes
        Cache::put($cacheKey, $result, now()->addMinutes(5));

        return response()->json($result);
    }

    /**
     * Store a newly created coupon.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), Coupon::validationRules(), Coupon::validationMessages());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $coupon = Coupon::create($validator->validated());

            // Sync to Firebase
            $this->syncCouponToFirebase($coupon);

            // Clear cache
            Cache::flush(); // Simple approach - in production, use tags

            return response()->json([
                'success' => true,
                'message' => 'Coupon created successfully',
                'data' => $coupon->load('store'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create coupon: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified coupon.
     */
    public function show(Coupon $coupon): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $coupon->load('store'),
        ]);
    }

    /**
     * Update the specified coupon.
     */
    public function update(Request $request, Coupon $coupon): JsonResponse
    {
        $validator = Validator::make($request->all(), Coupon::validationRules(), Coupon::validationMessages());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $coupon->update($validator->validated());

            // Sync to Firebase
            $this->syncCouponToFirebase($coupon);

            // Clear cache
            Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'Coupon updated successfully',
                'data' => $coupon->load('store'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update coupon: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified coupon.
     */
    public function destroy(Coupon $coupon): JsonResponse
    {
        try {
            // Delete from Firebase first
            if ($coupon->firebase_document_id) {
                $this->firestore->collection('coupons')->document($coupon->firebase_document_id)->delete();
            }

            $coupon->delete();

            // Clear cache
            Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'Coupon deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete coupon: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle coupon status (active/inactive).
     */
    public function toggleStatus(Coupon $coupon): JsonResponse
    {
        try {
            $coupon->update(['is_active' => !$coupon->is_active]);

            // Sync to Firebase
            $this->syncCouponToFirebase($coupon);

            // Clear cache
            Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'Coupon status updated successfully',
                'data' => $coupon->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update coupon status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify coupon.
     */
    public function verify(Coupon $coupon): JsonResponse
    {
        try {
            $coupon->update(['is_verified' => true]);

            // Sync to Firebase
            $this->syncCouponToFirebase($coupon);

            // Clear cache
            Cache::flush();

            return response()->json([
                'success' => true,
                'message' => 'Coupon verified successfully',
                'data' => $coupon->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify coupon: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get coupon statistics.
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_coupons' => Coupon::count(),
                'active_coupons' => Coupon::active()->count(),
                'verified_coupons' => Coupon::verified()->count(),
                'expired_coupons' => Coupon::where('expires_at', '<', now())->count(),
                'expiring_soon' => Coupon::expiringSoon()->count(),
                'total_usage' => Coupon::sum('usage_count'),
                'by_category' => Coupon::selectRaw('category, COUNT(*) as count')
                    ->groupBy('category')
                    ->get()
                    ->pluck('count', 'category'),
                'by_store' => Coupon::with('store')
                    ->selectRaw('store_id, COUNT(*) as count')
                    ->groupBy('store_id')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'store_name' => $item->store->name ?? 'Unknown',
                            'count' => $item->count,
                        };
                    }),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync coupon to Firebase Firestore.
     */
    private function syncCouponToFirebase(Coupon $coupon): void
    {
        try {
            $data = $coupon->toFirestoreData();
            
            if ($coupon->firebase_document_id) {
                // Update existing document
                $docRef = $this->firestore->collection('coupons')->document($coupon->firebase_document_id);
                $docRef->set($data, ['merge' => true]);
            } else {
                // Create new document
                $docRef = $this->firestore->collection('coupons')->newDocument();
                $docRef->set($data);
                
                // Save the Firebase document ID
                $coupon->update(['firebase_document_id' => $docRef->id()]);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the request
            \Log::error('Failed to sync coupon to Firebase: ' . $e->getMessage());
        }
    }

    /**
     * Bulk sync all coupons to Firebase.
     */
    public function bulkSync(): JsonResponse
    {
        try {
            $coupons = Coupon::with('store')->get();
            $synced = 0;
            $failed = 0;

            foreach ($coupons as $coupon) {
                try {
                    $this->syncCouponToFirebase($coupon);
                    $synced++;
                } catch (\Exception $e) {
                    $failed++;
                    \Log::error("Failed to sync coupon {$coupon->id}: " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Bulk sync completed. Synced: {$synced}, Failed: {$failed}",
                'data' => [
                    'total' => $coupons->count(),
                    'synced' => $synced,
                    'failed' => $failed,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk sync: ' . $e->getMessage(),
            ], 500);
        }
    }
}
