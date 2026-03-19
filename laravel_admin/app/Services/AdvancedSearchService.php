<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Client;
use Carbon\Carbon;

class AdvancedSearchService
{
    protected Client $elasticsearch;
    protected array $arabicConfig;
    protected array $searchConfig;
    
    public function __construct()
    {
        $this->elasticsearch = ClientBuilder::create()
            ->setHosts(config('elasticsearch.hosts'))
            ->setBasicAuthentication(
                config('elasticsearch.username'),
                config('elasticsearch.password')
            )
            ->build();
            
        $this->arabicConfig = [
            'normalizers' => [
                'arabic_normalizer' => [
                    'type' => 'custom',
                    'char_filter' => [
                        'arabic_normalizer',
                        'arabic_stemmer',
                        'arabic_stopwords',
                    ],
                    'token_filter' => [
                        'lowercase',
                        'arabic_stemmer',
                        'arabic_stopwords',
                    ],
                ],
            ],
            'analyzers' => [
                'arabic_analyzer' => [
                    'type' => 'custom',
                    'tokenizer' => 'standard',
                    'char_filter' => [
                        'arabic_normalizer',
                        'arabic_stemmer',
                    ],
                    'filter' => [
                        'lowercase',
                        'arabic_stemmer',
                        'arabic_stopwords',
                        'synonym',
                    ],
                ],
            ],
        ];
        
        $this->searchConfig = [
            'index' => 'arabic_coupons',
            'type' => '_doc',
            'size' => 20,
            'from' => 0,
        ];
    }

    /**
     * Initialize Elasticsearch index with Arabic support
     */
    public function initializeIndex(): bool
    {
        try {
            $indexExists = $this->elasticsearch->indices()->exists(['index' => $this->searchConfig['index']]);
            
            if (!$indexExists) {
                $this->createIndex();
            }
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to initialize search index', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Create Elasticsearch index with Arabic mapping
     */
    protected function createIndex(): void
    {
        $params = [
            'index' => $this->searchConfig['index'],
            'body' => [
                'settings' => [
                    'number_of_shards' => 3,
                    'number_of_replicas' => 1,
                    'analysis' => $this->arabicConfig,
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'keyword'],
                        'code' => [
                            'type' => 'text',
                            'analyzer' => 'arabic_analyzer',
                            'fields' => [
                                'keyword' => ['type' => 'keyword'],
                            ],
                        ],
                        'description' => [
                            'type' => 'text',
                            'analyzer' => 'arabic_analyzer',
                            'fields' => [
                                'keyword' => ['type' => 'keyword'],
                            ],
                        ],
                        'store_name' => [
                            'type' => 'text',
                            'analyzer' => 'arabic_analyzer',
                            'fields' => [
                                'keyword' => ['type' => 'keyword'],
                            ],
                        ],
                        'category' => [
                            'type' => 'keyword',
                            'fields' => [
                                'text' => [
                                    'type' => 'text',
                                    'analyzer' => 'arabic_analyzer',
                                ],
                            ],
                        ],
                        'discount_percent' => ['type' => 'integer'],
                        'usage_count' => ['type' => 'integer'],
                        'is_verified' => ['type' => 'boolean'],
                        'is_active' => ['type' => 'boolean'],
                        'expires_at' => ['type' => 'date'],
                        'created_at' => ['type' => 'date'],
                        'updated_at' => ['type' => 'date'],
                        'tags' => [
                            'type' => 'text',
                            'analyzer' => 'arabic_analyzer',
                            'fields' => [
                                'keyword' => ['type' => 'keyword'],
                            ],
                        ],
                        'affiliate_link' => ['type' => 'keyword'],
                        'cashback' => ['type' => 'float'],
                        'min_purchase' => ['type' => 'float'],
                        'app_only' => ['type' => 'boolean'],
                        'store_id' => ['type' => 'keyword'],
                        'popularity_score' => ['type' => 'float'],
                        'search_keywords' => [
                            'type' => 'text',
                            'analyzer' => 'arabic_analyzer',
                            'fields' => [
                                'keyword' => ['type' => 'keyword'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        
        $this->elasticsearch->indices()->create($params);
        
        Log::info('Elasticsearch index created successfully', [
            'index' => $this->searchConfig['index'],
        ]);
    }

    /**
     * Index coupon in Elasticsearch
     */
    public function indexCoupon(array $couponData): bool
    {
        try {
            $params = [
                'index' => $this->searchConfig['index'],
                'id' => $couponData['id'],
                'body' => $this->prepareCouponDocument($couponData),
            ];
            
            $response = $this->elasticsearch->index($params);
            
            Log::info('Coupon indexed in Elasticsearch', [
                'coupon_id' => $couponData['id'],
                'response' => $response,
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to index coupon', [
                'coupon_id' => $couponData['id'],
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Bulk index coupons
     */
    public function bulkIndexCoupons(array $coupons): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];
        
        try {
            $params = ['body' => []];
            
            foreach ($coupons as $coupon) {
                $params['body'][] = [
                    'index' => [
                        '_index' => $this->searchConfig['index'],
                        '_id' => $coupon['id'],
                    ],
                ];
                
                $params['body'][] = $this->prepareCouponDocument($coupon);
            }
            
            $response = $this->elasticsearch->bulk($params);
            
            if (isset($response['errors']) && $response['errors']) {
                foreach ($response['items'] as $item) {
                    if (isset($item['index']['error'])) {
                        $results['failed']++;
                        $results['errors'][] = $item['index']['error'];
                    } else {
                        $results['success']++;
                    }
                }
            } else {
                $results['success'] = count($coupons);
            }
            
            Log::info('Bulk indexing completed', $results);
            
        } catch (\Exception $e) {
            Log::error('Failed to bulk index coupons', [
                'error' => $e->getMessage(),
                'coupon_count' => count($coupons),
            ]);
            $results['failed'] = count($coupons);
            $results['errors'][] = $e->getMessage();
        }
        
        return $results;
    }

    /**
     * Search coupons with advanced Arabic support
     */
    public function searchCoupons(array $searchParams): array
    {
        try {
            $query = $this->buildSearchQuery($searchParams);
            
            $params = [
                'index' => $this->searchConfig['index'],
                'body' => $query,
            ];
            
            $response = $this->elasticsearch->search($params);
            
            $results = [
                'coupons' => [],
                'total' => $response['hits']['total']['value'] ?? 0,
                'max_score' => $response['hits']['max_score'] ?? 0,
                'took' => $response['took'] ?? 0,
                'aggregations' => $response['aggregations'] ?? [],
            ];
            
            foreach ($response['hits']['hits'] as $hit) {
                $coupon = $hit['_source'];
                $coupon['_score'] = $hit['_score'];
                $coupon['_id'] = $hit['_id'];
                $results['coupons'][] = $coupon;
            }
            
            // Log search analytics
            $this->logSearchAnalytics($searchParams, $results);
            
            return $results;
            
        } catch (\Exception $e) {
            Log::error('Search failed', [
                'search_params' => $searchParams,
                'error' => $e->getMessage(),
            ]);
            return $this->getFallbackResults($searchParams);
        }
    }

    /**
     * Build advanced search query
     */
    protected function buildSearchQuery(array $searchParams): array
    {
        $query = [
            'query' => [
                'bool' => [
                    'must' => [],
                    'filter' => [],
                    'should' => [],
                ],
            ],
            'sort' => [],
            'highlight' => [
                'fields' => [
                    'code' => ['fragment_size' => 50, 'number_of_fragments' => 3],
                    'description' => ['fragment_size' => 100, 'number_of_fragments' => 3],
                    'store_name' => ['fragment_size' => 50, 'number_of_fragments' => 2],
                ],
                'pre_tags' => ['<mark>'],
                'post_tags' => ['</mark>'],
            ],
            'aggs' => [
                'categories' => [
                    'terms' => [
                        'field' => 'category',
                        'size' => 10,
                    ],
                ],
                'stores' => [
                    'terms' => [
                        'field' => 'store_name.keyword',
                        'size' => 10,
                    ],
                ],
                'discount_ranges' => [
                    'range' => [
                        'field' => 'discount_percent',
                        'ranges' => [
                            ['key' => '0-25', 'to' => 25],
                            ['key' => '25-50', 'from' => 25, 'to' => 50],
                            ['key' => '50-75', 'from' => 50, 'to' => 75],
                            ['key' => '75-100', 'from' => 75],
                        ],
                    ],
                ],
            ],
        ];
        
        // Text search
        if (!empty($searchParams['q'])) {
            $query['query']['bool']['must'][] = [
                'multi_match' => [
                    'query' => $searchParams['q'],
                    'fields' => [
                        'code^3',
                        'description^2',
                        'store_name^2',
                        'tags^1.5',
                        'search_keywords^1.5',
                        'category.text^1',
                    ],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO',
                    'prefix_length' => 2,
                    'max_expansions' => 10,
                    'operator' => 'and',
                ],
            ];
        }
        
        // Active filter
        $query['query']['bool']['filter'][] = [
            'term' => ['is_active' => true],
        ];
        
        // Not expired filter
        $query['query']['bool']['filter'][] = [
            'range' => [
                'expires_at' => [
                    'gte' => now()->toISOString(),
                ],
            ],
        ];
        
        // Category filter
        if (!empty($searchParams['category'])) {
            $query['query']['bool']['filter'][] = [
                'term' => ['category' => $searchParams['category']],
            ];
        }
        
        // Store filter
        if (!empty($searchParams['store_id'])) {
            $query['query']['bool']['filter'][] = [
                'term' => ['store_id' => $searchParams['store_id']],
            ];
        }
        
        // Discount range filter
        if (!empty($searchParams['min_discount'])) {
            $query['query']['bool']['filter'][] = [
                'range' => [
                    'discount_percent' => [
                        'gte' => $searchParams['min_discount'],
                    ],
                ],
            ];
        }
        
        if (!empty($searchParams['max_discount'])) {
            $query['query']['bool']['filter'][] = [
                'range' => [
                    'discount_percent' => [
                        'lte' => $searchParams['max_discount'],
                    ],
                ],
            ];
        }
        
        // Verified filter
        if (isset($searchParams['verified']) && $searchParams['verified']) {
            $query['query']['bool']['filter'][] = [
                'term' => ['is_verified' => true],
            ];
        }
        
        // App only filter
        if (isset($searchParams['app_only']) && $searchParams['app_only']) {
            $query['query']['bool']['filter'][] = [
                'term' => ['app_only' => true],
            ];
        }
        
        // Sorting
        $sortField = $searchParams['sort'] ?? 'popularity_score';
        $sortOrder = $searchParams['order'] ?? 'desc';
        
        switch ($sortField) {
            case 'discount':
                $query['sort'][] = ['discount_percent' => ['order' => $sortOrder]];
                break;
            case 'usage':
                $query['sort'][] = ['usage_count' => ['order' => $sortOrder]];
                break;
            case 'expiry':
                $query['sort'][] = ['expires_at' => ['order' => 'asc']];
                break;
            case 'created':
                $query['sort'][] = ['created_at' => ['order' => $sortOrder]];
                break;
            case 'popularity':
            default:
                $query['sort'][] = ['popularity_score' => ['order' => $sortOrder]];
                $query['sort'][] = ['_score' => ['order' => 'desc']];
                break;
        }
        
        // Pagination
        if (!empty($searchParams['size'])) {
            $query['size'] = min($searchParams['size'], 100);
        }
        
        if (!empty($searchParams['from'])) {
            $query['from'] = max(0, $searchParams['from']);
        }
        
        return $query;
    }

    /**
     * Prepare coupon document for indexing
     */
    protected function prepareCouponDocument(array $couponData): array
    {
        $document = [
            'id' => $couponData['id'],
            'code' => $couponData['code'],
            'description' => $couponData['description'] ?? '',
            'store_name' => $couponData['store_name'] ?? '',
            'category' => $couponData['category'] ?? 'other',
            'discount_percent' => (int) ($couponData['discount_percent'] ?? 0),
            'usage_count' => (int) ($couponData['usage_count'] ?? 0),
            'is_verified' => (bool) ($couponData['is_verified'] ?? false),
            'is_active' => (bool) ($couponData['is_active'] ?? true),
            'expires_at' => $couponData['expires_at'],
            'created_at' => $couponData['created_at'],
            'updated_at' => $couponData['updated_at'],
            'tags' => $couponData['tags'] ?? [],
            'affiliate_link' => $couponData['affiliate_link'] ?? '',
            'cashback' => (float) ($couponData['cashback'] ?? 0),
            'min_purchase' => (float) ($couponData['min_purchase'] ?? 0),
            'app_only' => (bool) ($couponData['app_only'] ?? false),
            'store_id' => $couponData['store_id'] ?? '',
            'popularity_score' => $this->calculatePopularityScore($couponData),
            'search_keywords' => $this->generateSearchKeywords($couponData),
        ];
        
        return $document;
    }

    /**
     * Calculate popularity score for sorting
     */
    protected function calculatePopularityScore(array $couponData): float
    {
        $score = 0.0;
        
        // Usage count (40% weight)
        $score += ($couponData['usage_count'] ?? 0) * 0.4;
        
        // Discount percent (25% weight)
        $score += ($couponData['discount_percent'] ?? 0) * 0.25;
        
        // Verified bonus (20% weight)
        if ($couponData['is_verified'] ?? false) {
            $score += 20;
        }
        
        // Recent activity (15% weight)
        $daysSinceCreation = Carbon::parse($couponData['created_at'] ?? now())->diffInDays();
        $recentActivityScore = max(0, 15 - ($daysSinceCreation * 0.5));
        $score += $recentActivityScore;
        
        return $score;
    }

    /**
     * Generate search keywords for better matching
     */
    protected function generateSearchKeywords(array $couponData): array
    {
        $keywords = [];
        
        // Add code variations
        if (!empty($couponData['code'])) {
            $keywords[] = $couponData['code'];
            $keywords[] = strtolower($couponData['code']);
            $keywords[] = strtoupper($couponData['code']);
        }
        
        // Add description keywords
        if (!empty($couponData['description'])) {
            $descriptionKeywords = $this->extractKeywords($couponData['description']);
            $keywords = array_merge($keywords, $descriptionKeywords);
        }
        
        // Add store name keywords
        if (!empty($couponData['store_name'])) {
            $keywords[] = $couponData['store_name'];
        }
        
        // Add category keywords
        if (!empty($couponData['category'])) {
            $keywords[] = $couponData['category'];
        }
        
        // Add tags
        if (!empty($couponData['tags'])) {
            $keywords = array_merge($keywords, $couponData['tags']);
        }
        
        return array_unique($keywords);
    }

    /**
     * Extract keywords from Arabic text
     */
    protected function extractKeywords(string $text): array
    {
        // Simple keyword extraction - in production, use proper Arabic NLP
        $keywords = [];
        
        // Remove common Arabic stop words
        $stopWords = ['في', 'من', 'إلى', 'على', 'مع', 'هذا', 'هذه', 'ذلك', 'تلك'];
        
        // Split text into words
        $words = preg_split('/\s+/', $text);
        
        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) > 2 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }
        
        return $keywords;
    }

    /**
     * Get search suggestions
     */
    public function getSearchSuggestions(string $query, int $limit = 10): array
    {
        try {
            $params = [
                'index' => $this->searchConfig['index'],
                'body' => [
                    'suggest' => [
                        'coupon_suggest' => [
                            'prefix' => $query,
                            'completion' => [
                                'field' => 'search_keywords',
                                'size' => $limit,
                                'skip_duplicates' => true,
                            ],
                        ],
                    ],
                ],
            ];
            
            $response = $this->elasticsearch->search($params);
            
            $suggestions = [];
            
            if (isset($response['suggest']['coupon_suggest'][0]['options'])) {
                foreach ($response['suggest']['coupon_suggest'][0]['options'] as $option) {
                    $suggestions[] = [
                        'text' => $option['text'],
                        'score' => $option['_score'],
                        'source' => $option['_source'],
                    ];
                }
            }
            
            return $suggestions;
            
        } catch (\Exception $e) {
            Log::error('Failed to get search suggestions', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get popular searches
     */
    public function getPopularSearches(int $limit = 10): array
    {
        try {
            // This would typically come from analytics data
            // For now, return hardcoded popular searches
            return Cache::remember('popular_searches', 3600, function () use ($limit) {
                return [
                    ['query' => 'خصم', 'count' => 1250],
                    ['query' => 'نون', 'count' => 980],
                    ['query' => 'شي إن', 'count' => 856],
                    ['query' => 'أمازون', 'count' => 743],
                    ['query' => 'تخفيض', 'count' => 689],
                    ['query' => 'تسوق', 'count' => 567],
                    ['query' => 'إلكترونيات', 'count' => 456],
                    ['query' => 'طعام', 'count' => 389],
                    ['query' => 'سفر', 'count' => 334],
                    ['query' => 'جمال', 'count' => 278],
                ];
            });
        } catch (\Exception $e) {
            Log::error('Failed to get popular searches', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Log search analytics
     */
    protected function logSearchAnalytics(array $searchParams, array $results): void
    {
        try {
            $analyticsData = [
                'query' => $searchParams['q'] ?? '',
                'filters' => $searchParams,
                'results_count' => $results['total'],
                'search_time' => $results['took'],
                'timestamp' => now()->toISOString(),
                'user_id' => auth()->id(),
            ];
            
            // Store in analytics collection
            // This would typically go to a separate analytics database
            Log::info('Search performed', $analyticsData);
            
            // Update popular searches cache
            if (!empty($searchParams['q'])) {
                $this->updatePopularSearches($searchParams['q']);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to log search analytics', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update popular searches
     */
    protected function updatePopularSearches(string $query): void
    {
        try {
            $popularSearches = Cache::get('popular_searches', []);
            
            // Find existing query
            $found = false;
            foreach ($popularSearches as &$search) {
                if ($search['query'] === $query) {
                    $search['count']++;
                    $found = true;
                    break;
                }
            }
            
            // Add new query if not found
            if (!$found) {
                $popularSearches[] = ['query' => $query, 'count' => 1];
            }
            
            // Sort by count and keep top 10
            usort($popularSearches, function ($a, $b) {
                return $b['count'] <=> $a['count'];
            });
            
            $popularSearches = array_slice($popularSearches, 0, 10);
            
            Cache::put('popular_searches', $popularSearches, 3600);
            
        } catch (\Exception $e) {
            Log::error('Failed to update popular searches', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get fallback results when Elasticsearch is unavailable
     */
    protected function getFallbackResults(array $searchParams): array
    {
        try {
            // Fallback to database search
            $query = \App\Models\Coupon::query()
                ->where('is_active', true)
                ->where('expires_at', '>=', now());
            
            // Apply filters
            if (!empty($searchParams['category'])) {
                $query->where('category', $searchParams['category']);
            }
            
            if (!empty($searchParams['store_id'])) {
                $query->where('store_id', $searchParams['store_id']);
            }
            
            if (!empty($searchParams['min_discount'])) {
                $query->where('discount_percent', '>=', $searchParams['min_discount']);
            }
            
            if (!empty($searchParams['max_discount'])) {
                $query->where('discount_percent', '<=', $searchParams['max_discount']);
            }
            
            if (isset($searchParams['verified']) && $searchParams['verified']) {
                $query->where('is_verified', true);
            }
            
            if (isset($searchParams['app_only']) && $searchParams['app_only']) {
                $query->where('app_only', true);
            }
            
            // Apply text search
            if (!empty($searchParams['q'])) {
                $query->where(function ($q) use ($searchParams) {
                    $q->where('code', 'LIKE', '%' . $searchParams['q'] . '%')
                      ->orWhere('description', 'LIKE', '%' . $searchParams['q'] . '%');
                });
            }
            
            // Apply sorting
            $sortField = $searchParams['sort'] ?? 'usage_count';
            $sortOrder = $searchParams['order'] ?? 'desc';
            
            switch ($sortField) {
                case 'discount':
                    $query->orderBy('discount_percent', $sortOrder);
                    break;
                case 'created':
                    $query->orderBy('created_at', $sortOrder);
                    break;
                case 'expiry':
                    $query->orderBy('expires_at', $sortOrder === 'desc' ? 'desc' : 'asc');
                    break;
                case 'usage':
                default:
                    $query->orderBy('usage_count', $sortOrder);
                    break;
            }
            
            // Apply pagination
            $size = min($searchParams['size'] ?? 20, 100);
            $from = max(0, $searchParams['from'] ?? 0);
            
            $coupons = $query->offset($from)->limit($size)->get();
            
            return [
                'coupons' => $coupons->toArray(),
                'total' => $coupons->count(),
                'max_score' => 0,
                'took' => 0,
                'aggregations' => [],
                'fallback' => true,
            ];
            
        } catch (\Exception $e) {
            Log::error('Fallback search failed', [
                'search_params' => $searchParams,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'coupons' => [],
                'total' => 0,
                'max_score' => 0,
                'took' => 0,
                'aggregations' => [],
                'fallback' => true,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get search statistics
     */
    public function getSearchStatistics(): array
    {
        try {
            $params = [
                'index' => $this->searchConfig['index'],
                'body' => [
                    'size' => 0,
                    'aggs' => [
                        'total_coupons' => [
                            'value_count' => [
                                'field' => 'id',
                            ],
                        ],
                        'active_coupons' => [
                            'filter' => [
                                'term' => ['is_active' => true],
                            ],
                        ],
                        'verified_coupons' => [
                            'filter' => [
                                'term' => ['is_verified' => true],
                            ],
                        ],
                        'categories' => [
                            'terms' => [
                                'field' => 'category',
                                'size' => 20,
                            ],
                        ],
                        'discount_distribution' => [
                            'histogram' => [
                                'field' => 'discount_percent',
                                'interval' => 10,
                            ],
                        ],
                    ],
                ],
            ];
            
            $response = $this->elasticsearch->search($params);
            
            $stats = [
                'total_coupons' => $response['aggregations']['total_coupons']['value'] ?? 0,
                'active_coupons' => $response['aggregations']['active_coupons']['doc_count'] ?? 0,
                'verified_coupons' => $response['aggregations']['verified_coupons']['doc_count'] ?? 0,
                'categories' => [],
                'discount_distribution' => [],
            ];
            
            // Process categories
            if (isset($response['aggregations']['categories']['buckets'])) {
                foreach ($response['aggregations']['categories']['buckets'] as $bucket) {
                    $stats['categories'][] = [
                        'category' => $bucket['key'],
                        'count' => $bucket['doc_count'],
                    ];
                }
            }
            
            // Process discount distribution
            if (isset($response['aggregations']['discount_distribution']['buckets'])) {
                foreach ($response['aggregations']['discount_distribution']['buckets'] as $bucket) {
                    $stats['discount_distribution'][] = [
                        'range' => $bucket['key'] . '-' . ($bucket['key'] + 10),
                        'count' => $bucket['doc_count'],
                    ];
                }
            }
            
            return $stats;
            
        } catch (\Exception $e) {
            Log::error('Failed to get search statistics', [
                'error' => $e->getMessage(),
            ]);
            
            return [
                'total_coupons' => 0,
                'active_coupons' => 0,
                'verified_coupons' => 0,
                'categories' => [],
                'discount_distribution' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Rebuild search index
     */
    public function rebuildIndex(): bool
    {
        try {
            // Delete existing index
            if ($this->elasticsearch->indices()->exists(['index' => $this->searchConfig['index']])) {
                $this->elasticsearch->indices()->delete(['index' => $this->searchConfig['index']]);
            }
            
            // Create new index
            $this->createIndex();
            
            // Re-index all coupons
            $coupons = \App\Models\Coupon::all();
            
            $results = $this->bulkIndexCoupons($coupons->toArray());
            
            Log::info('Search index rebuilt', $results);
            
            return $results['success'] > 0;
            
        } catch (\Exception $e) {
            Log::error('Failed to rebuild search index', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Optimize search index
     */
    public function optimizeIndex(): bool
    {
        try {
            $params = [
                'index' => $this->searchConfig['index'],
                'body' => [
                    'max_num_segments' => 1,
                ],
            ];
            
            $this->elasticsearch->indices()->forcemerge($params);
            
            Log::info('Search index optimized');
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to optimize search index', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
