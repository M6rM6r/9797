<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowImport;
use App\Imports\CouponsImport;

class BulkUploadController extends Controller
{
    /**
     * Upload and process CSV file for bulk coupon creation.
     */
    public function uploadCoupons(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240', // 10MB max
        ], [
            'file.required' => 'الملف مطلوب',
            'file.mimes' => 'يجب أن يكون الملف من نوع CSV, XLSX, أو XLS',
            'file.max' => 'حجم الملف يجب أن لا يتجاوز 10 ميجابايت',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('uploads', $fileName, 'local');

            // Process the file
            $import = new CouponsImport();
            Excel::import($import, $filePath);

            // Get import results
            $results = $import->getResults();

            // Clean up the file
            Storage::disk('local')->delete($filePath);

            return response()->json([
                'success' => true,
                'message' => 'File processed successfully',
                'data' => [
                    'total_rows' => $results['total_rows'],
                    'imported' => $results['imported'],
                    'failed' => $results['failed'],
                    'errors' => $results['errors'],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download sample CSV template.
     */
    public function downloadTemplate(): JsonResponse
    {
        try {
            $template = [
                [
                    'code' => 'SAMPLE123',
                    'store_name' => 'نون',
                    'discount_percent' => '20',
                    'description' => 'خصم 20% على جميع المنتجات',
                    'expires_at' => now()->addDays(30)->format('Y-m-d'),
                    'category' => 'fashion',
                    'is_verified' => '1',
                    'affiliate_link' => 'https://www.noon.com/affiliate/sample',
                    'is_active' => '1',
                    'app_only' => '0',
                ]
            ];

            $filename = 'coupon_template_' . date('Y-m-d') . '.csv';
            
            $callback = function () use ($template) {
                $file = fopen('php://output', 'w');
                
                // Add BOM for UTF-8 support
                fwrite($file, "\xEF\xBB\xBF");
                
                // Header
                fputcsv($file, [
                    'code',
                    'store_name', 
                    'discount_percent',
                    'description',
                    'expires_at',
                    'category',
                    'is_verified',
                    'affiliate_link',
                    'is_active',
                    'app_only'
                ]);
                
                // Sample data
                foreach ($template as $row) {
                    fputcsv($file, $row);
                }
                
                fclose($file);
            };

            return response()->stream($callback, 200, [
                'Content-Type' => 'text/csv; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate uploaded file before processing.
     */
    public function validateFile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('file');
            $fileName = time() . '_validate_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('temp', $fileName, 'local');

            // Read first few rows for validation
            $import = new HeadingRowImport();
            $headings = Excel::toArray($import, $filePath)[0][0] ?? [];

            $requiredColumns = ['code', 'store_name', 'discount_percent', 'description', 'expires_at'];
            $missingColumns = array_diff($requiredColumns, $headings);

            // Clean up
            Storage::disk('local')->delete($filePath);

            if (!empty($missingColumns)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required columns',
                    'data' => [
                        'required_columns' => $requiredColumns,
                        'missing_columns' => $missingColumns,
                        'found_columns' => $headings,
                    ],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'File structure is valid',
                'data' => [
                    'columns' => $headings,
                    'required_columns' => $requiredColumns,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get upload history.
     */
    public function uploadHistory(Request $request): JsonResponse
    {
        try {
            // This would typically come from a database table tracking uploads
            // For now, return a mock response
            $history = [
                [
                    'id' => 1,
                    'filename' => 'coupons_batch_1.csv',
                    'uploaded_at' => now()->subDays(1)->toDateTimeString(),
                    'status' => 'completed',
                    'total_rows' => 150,
                    'imported' => 145,
                    'failed' => 5,
                    'uploaded_by' => 'admin',
                ],
                [
                    'id' => 2,
                    'filename' => 'coupons_batch_2.csv',
                    'uploaded_at' => now()->subDays(3)->toDateTimeString(),
                    'status' => 'completed',
                    'total_rows' => 200,
                    'imported' => 198,
                    'failed' => 2,
                    'uploaded_by' => 'admin',
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $history,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get upload history: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available stores for dropdown.
     */
    public function getStores(): JsonResponse
    {
        try {
            $stores = Store::active()->get(['id', 'name']);

            return response()->json([
                'success' => true,
                'data' => $stores,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get stores: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available categories.
     */
    public function getCategories(): JsonResponse
    {
        try {
            $categories = [
                ['value' => 'fashion', 'label' => 'تسوق وأزياء'],
                ['value' => 'electronics', 'label' => 'إلكترونيات'],
                ['value' => 'food', 'label' => 'طعام ومطاعم'],
                ['value' => 'travel', 'label' => 'سفر وفنادق'],
            ];

            return response()->json([
                'success' => true,
                'data' => $categories,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get categories: ' . $e->getMessage(),
            ], 500);
        }
    }
}
