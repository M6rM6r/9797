<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Support\Str;

class IntelligentCacheService
{
    protected Redis $redis;
    protected array $cacheConfig;
    protected array $compressionAlgorithms = ['gzip', 'bzip2', 'lz4'];
    protected string $currentCompression = 'gzip';
    
    public function __construct()
    {
        $this->redis = Redis::connection();
        $this->cacheConfig = [
            'default_ttl' => config('cache.default_ttl', 3600), // 1 hour
            'compression_enabled' => config('cache.compression_enabled', true),
            'clustering_enabled' => config('cache.clustering_enabled', false),
            'cache_warmup_enabled' => config('cache.cache_warmup_enabled', true),
            'smart_eviction' => config('cache.smart_eviction', true),
        ];
    }

    /**
     * Intelligent cache get with multi-layer strategy
     */
    public function get(string $key, $default = null)
    {
        try {
            // Layer 1: Check local cache (fastest)
            $localValue = $this->getFromLocalCache($key);
            if ($localValue !== null) {
                $this->recordCacheHit('local', $key);
                return $this->uncompressData($localValue);
            }

            // Layer 2: Check Redis cluster
            $redisValue = $this->getFromRedisCluster($key);
            if ($redisValue !== null) {
                $this->recordCacheHit('redis', $key);
                // Promote to local cache
                $this->setLocalCache($key, $redisValue);
                return $this->uncompressData($redisValue);
            }

            // Layer 3: Check database with caching
            $dbValue = $this->getFromDatabase($key);
            if ($dbValue !== null) {
                $this->recordCacheHit('database', $key);
                // Cache in all layers
                $this->setMultiLayer($key, $dbValue);
                return $dbValue;
            }

            $this->recordCacheMiss($key);
            return $default;

        } catch (\Exception $e) {
            Log::error('Cache get error', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return $default;
        }
    }

    /**
     * Intelligent cache set with compression and distribution
     */
    public function set(string $key, $value, int $ttl = null): bool
    {
        try {
            $ttl = $ttl ?? $this->cacheConfig['default_ttl'];
            $compressedValue = $this->compressData($value);
            
            // Set in all cache layers
            $success = true;
            
            // Local cache
            $success &= $this->setLocalCache($key, $compressedValue, $ttl);
            
            // Redis cluster with intelligent distribution
            $success &= $this->setRedisCluster($key, $compressedValue, $ttl);
            
            if ($success) {
                $this->recordCacheSet($key, $ttl);
                $this->updateCacheStatistics($key, 'set');
            }
            
            return $success;

        } catch (\Exception $e) {
            Log::error('Cache set error', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Smart cache invalidation with dependency tracking
     */
    public function invalidate(string $pattern = null, array $tags = []): bool
    {
        try {
            $invalidated = 0;
            
            if ($pattern) {
                // Pattern-based invalidation
                $keys = $this->redis->keys($pattern);
                foreach ($keys as $key) {
                    if ($this->redis->del($key)) {
                        $invalidated++;
                        $this->recordCacheInvalidation($key, 'pattern');
                    }
                }
            }
            
            if (!empty($tags)) {
                // Tag-based invalidation
                foreach ($tags as $tag) {
                    $taggedKeys = $this->getKeysByTag($tag);
                    foreach ($taggedKeys as $key) {
                        if ($this->redis->del($key)) {
                            $invalidated++;
                            $this->recordCacheInvalidation($key, 'tag');
                        }
                    }
                }
            }
            
            // Clear local cache
            $this->clearLocalCache();
            
            Log::info('Cache invalidation completed', [
                'pattern' => $pattern,
                'tags' => $tags,
                'invalidated_keys' => $invalidated,
            ]);
            
            return $invalidated > 0;

        } catch (\Exception $e) {
            Log::error('Cache invalidation error', [
                'pattern' => $pattern,
                'tags' => $tags,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Intelligent cache warmup based on usage patterns
     */
    public function warmupCache(): array
    {
        if (!$this->cacheConfig['cache_warmup_enabled']) {
            return ['status' => 'disabled'];
        }

        try {
            $warmupStats = [
                'started_at' => now()->toISOString(),
                'keys_warmed' => 0,
                'bytes_warmed' => 0,
                'errors' => 0,
            ];

            // Get frequently accessed keys
            $frequentKeys = $this->getFrequentKeys(100);
            
            foreach ($frequentKeys as $keyData) {
                $key = $keyData['key'];
                $frequency = $keyData['frequency'];
                
                // Calculate optimal TTL based on frequency
                $optimalTtl = $this->calculateOptimalTTL($frequency);
                
                // Get fresh data
                $value = $this->getFromDatabase($key);
                if ($value !== null) {
                    $compressedValue = $this->compressData($value);
                    
                    // Set in cache layers
                    $this->setLocalCache($key, $compressedValue, $optimalTtl);
                    $this->setRedisCluster($key, $compressedValue, $optimalTtl);
                    
                    $warmupStats['keys_warmed']++;
                    $warmupStats['bytes_warmed'] += strlen($compressedValue);
                }
            }

            // Warmup popular queries
            $this->warmupPopularQueries();
            
            $warmupStats['completed_at'] = now()->toISOString();
            $warmupStats['duration_seconds'] = now()->diffInSeconds(
                Carbon::parse($warmupStats['started_at'])
            );

            Log::info('Cache warmup completed', $warmupStats);
            return $warmupStats;

        } catch (\Exception $e) {
            Log::error('Cache warmup error', [
                'error' => $e->getMessage(),
            ]);
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Get cache with intelligent fallback
     */
    public function remember(string $key, \Closure $callback, int $ttl = null)
    {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }

        // Execute callback and cache result
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }

    /**
     * Multi-get for batch operations
     */
    public function mget(array $keys): array
    {
        try {
            $results = [];
            $uncachedKeys = [];
            
            // Try local cache first
            foreach ($keys as $key) {
                $localValue = $this->getFromLocalCache($key);
                if ($localValue !== null) {
                    $results[$key] = $this->uncompressData($localValue);
                } else {
                    $uncachedKeys[] = $key;
                }
            }
            
            // Get remaining from Redis
            if (!empty($uncachedKeys)) {
                $redisResults = $this->mgetRedisCluster($uncachedKeys);
                foreach ($redisResults as $key => $value) {
                    if ($value !== null) {
                        $results[$key] = $this->uncompressData($value);
                        // Promote to local cache
                        $this->setLocalCache($key, $value);
                    }
                }
            }
            
            return $results;

        } catch (\Exception $e) {
            Log::error('Cache mget error', [
                'keys' => $keys,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Multi-set for batch operations
     */
    public function mset(array $keyValuePairs, int $ttl = null): bool
    {
        try {
            $success = true;
            $ttl = $ttl ?? $this->cacheConfig['default_ttl'];
            
            foreach ($keyValuePairs as $key => $value) {
                $compressedValue = $this->compressData($value);
                $success &= $this->setLocalCache($key, $compressedValue, $ttl);
                $success &= $this->setRedisCluster($key, $compressedValue, $ttl);
            }
            
            return $success;

        } catch (\Exception $e) {
            Log::error('Cache mset error', [
                'keys' => array_keys($keyValuePairs),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get cache statistics and performance metrics
     */
    public function getCacheStatistics(): array
    {
        try {
            $redisInfo = $this->redis->info();
            
            return [
                'redis_memory_usage' => $redisInfo['used_memory_human'] ?? 'unknown',
                'redis_memory_peak' => $redisInfo['used_memory_peak_human'] ?? 'unknown',
                'redis_connected_clients' => $redisInfo['connected_clients'] ?? 0,
                'redis_total_commands' => $redisInfo['total_commands_processed'] ?? 0,
                'redis_keyspace_hits' => $redisInfo['keyspace_hits'] ?? 0,
                'redis_keyspace_misses' => $redisInfo['keyspace_misses'] ?? 0,
                'hit_rate' => $this->calculateHitRate($redisInfo),
                'local_cache_size' => $this->getLocalCacheSize(),
                'compression_ratio' => $this->getCompressionRatio(),
                'cache_efficiency' => $this->calculateCacheEfficiency(),
                'top_keys' => $this->getTopKeys(10),
                'eviction_policy' => $this->getEvictionPolicy(),
                'memory_fragmentation' => $redisInfo['mem_fragmentation_ratio'] ?? 0,
                'last_warmup' => $this->getLastWarmupTime(),
            ];

        } catch (\Exception $e) {
            Log::error('Error getting cache statistics', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Optimize cache performance
     */
    public function optimizeCache(): array
    {
        try {
            $optimizationResults = [
                'started_at' => now()->toISOString(),
                'actions_taken' => [],
                'memory_freed' => 0,
                'errors' => 0,
            ];

            // Clean up expired keys
            $expiredKeys = $this->getExpiredKeys();
            foreach ($expiredKeys as $key) {
                if ($this->redis->del($key)) {
                    $optimizationResults['actions_taken'][] = "Deleted expired key: $key";
                    $optimizationResults['memory_freed'] += $this->getKeySize($key);
                }
            }

            // Compress large values
            $largeKeys = $this->getLargeKeys();
            foreach ($largeKeys as $key) {
                $value = $this->redis->get($key);
                if ($value && strlen($value) > 1024) { // > 1KB
                    $compressed = $this->compressData($value);
                    if (strlen($compressed) < strlen($value)) {
                        $this->redis->setex($key, $this->getKeyTTL($key), $compressed);
                        $optimizationResults['actions_taken'][] = "Compressed large key: $key";
                        $optimizationResults['memory_freed'] += strlen($value) - strlen($compressed);
                    }
                }
            }

            // Reorganize cache distribution
            if ($this->cacheConfig['clustering_enabled']) {
                $this->rebalanceCacheDistribution();
                $optimizationResults['actions_taken'][] = 'Rebalanced cache distribution';
            }

            // Clear local cache if memory pressure
            if ($this->getMemoryPressure() > 0.8) {
                $this->clearLocalCache();
                $optimizationResults['actions_taken'][] = 'Cleared local cache due to memory pressure';
            }

            $optimizationResults['completed_at'] = now()->toISOString();
            $optimizationResults['duration_seconds'] = now()->diffInSeconds(
                Carbon::parse($optimizationResults['started_at'])
            );

            Log::info('Cache optimization completed', $optimizationResults);
            return $optimizationResults;

        } catch (\Exception $e) {
            Log::error('Cache optimization error', [
                'error' => $e->getMessage(),
            ]);
            return ['status' => 'error', 'error' => $e->getMessage()];
        }
    }

    /**
     * Protected helper methods
     */
    protected function getFromLocalCache(string $key): ?string
    {
        return Cache::get("local_{$key}");
    }

    protected function setLocalCache(string $key, string $value, int $ttl = null): bool
    {
        return Cache::put("local_{$key}", $value, $ttl ?? $this->cacheConfig['default_ttl']);
    }

    protected function clearLocalCache(): bool
    {
        $localKeys = array_filter(Cache::getKeys(), fn($key) => Str::startsWith($key, 'local_'));
        foreach ($localKeys as $key) {
            Cache::forget($key);
        }
        return true;
    }

    protected function getFromRedisCluster(string $key): ?string
    {
        if ($this->cacheConfig['clustering_enabled']) {
            $node = $this->getRedisNode($key);
            return $this->redis->connection($node)->get($key);
        }
        
        return $this->redis->get($key);
    }

    protected function setRedisCluster(string $key, string $value, int $ttl): bool
    {
        if ($this->cacheConfig['clustering_enabled']) {
            $node = $this->getRedisNode($key);
            return $this->redis->connection($node)->setex($key, $ttl, $value);
        }
        
        return $this->redis->setex($key, $ttl, $value);
    }

    protected function mgetRedisCluster(array $keys): array
    {
        if ($this->cacheConfig['clustering_enabled']) {
            $results = [];
            $nodeKeys = [];
            
            // Group keys by node
            foreach ($keys as $key) {
                $node = $this->getRedisNode($key);
                $nodeKeys[$node][] = $key;
            }
            
            // Batch get from each node
            foreach ($nodeKeys as $node => $nodeKeyGroup) {
                $nodeResults = $this->redis->connection($node)->mget($nodeKeyGroup);
                $results = array_merge($results, $nodeResults);
            }
            
            return $results;
        }
        
        return $this->redis->mget($keys);
    }

    protected function getRedisNode(string $key): string
    {
        // Consistent hashing for distribution
        $hash = crc32($key);
        $nodeCount = config('cache.redis_nodes', 3);
        $nodeIndex = abs($hash) % $nodeCount;
        
        return "redis_node_{$nodeIndex}";
    }

    protected function compressData($data): string
    {
        if (!$this->cacheConfig['compression_enabled']) {
            return is_string($data) ? $data : serialize($data);
        }

        $serialized = is_string($data) ? $data : serialize($data);
        
        switch ($this->currentCompression) {
            case 'gzip':
                return gzcompress($serialized, 9);
            case 'bzip2':
                return bzcompress($serialized, 9);
            case 'lz4':
                // Would require lz4 extension
                return $serialized;
            default:
                return $serialized;
        }
    }

    protected function uncompressData(string $data)
    {
        if (!$this->cacheConfig['compression_enabled']) {
            return unserialize($data);
        }

        switch ($this->currentCompression) {
            case 'gzip':
                $uncompressed = @gzuncompress($data);
                break;
            case 'bzip2':
                $uncompressed = @bzdecompress($data);
                break;
            case 'lz4':
                $uncompressed = $data; // Would require lz4 extension
                break;
            default:
                $uncompressed = $data;
        }

        return $uncompressed !== false ? unserialize($uncompressed) : null;
    }

    protected function setMultiLayer(string $key, $value): void
    {
        $ttl = $this->calculateOptimalTTL($this->getKeyFrequency($key));
        $compressedValue = $this->compressData($value);
        
        $this->setLocalCache($key, $compressedValue, $ttl);
        $this->setRedisCluster($key, $compressedValue, $ttl);
    }

    protected function getFromDatabase(string $key)
    {
        // This would fetch from your database
        // Implementation depends on your database structure
        return null;
    }

    protected function getFrequentKeys(int $limit = 100): array
    {
        // Get frequently accessed keys from Redis
        $keys = $this->redis->keys('*');
        $frequencies = [];
        
        foreach ($keys as $key) {
            $frequency = $this->redis->get("freq:{$key}") ?? 0;
            $frequencies[] = [
                'key' => $key,
                'frequency' => $frequency,
            ];
        }
        
        // Sort by frequency and limit
        usort($frequencies, fn($a, $b) => $b['frequency'] <=> $a['frequency']);
        
        return array_slice($frequencies, 0, $limit);
    }

    protected function calculateOptimalTTL(int $frequency): int
    {
        // Higher frequency = longer TTL
        $baseTTL = $this->cacheConfig['default_ttl'];
        $multiplier = min(5.0, 1.0 + ($frequency / 10.0));
        
        return (int)($baseTTL * $multiplier);
    }

    protected function getKeyFrequency(string $key): int
    {
        return (int)($this->redis->get("freq:{$key}") ?? 0);
    }

    protected function recordCacheHit(string $layer, string $key): void
    {
        $this->redis->incr("hits:{$layer}");
        $this->redis->incr("freq:{$key}");
        $this->redis->expire("freq:{$key}", 86400); // 24 hours
    }

    protected function recordCacheMiss(string $key): void
    {
        $this->redis->incr("misses");
    }

    protected function recordCacheSet(string $key, int $ttl): void
    {
        $this->redis->incr("sets");
        $this->redis->hset("key_meta:{$key}", 'ttl', $ttl);
        $this->redis->hset("key_meta:{$key}", 'created_at', now()->timestamp);
    }

    protected function recordCacheInvalidation(string $key, string $reason): void
    {
        $this->redis->incr("invalidations");
        $this->redis->hset("key_meta:{$key}", 'invalidated_at', now()->timestamp);
        $this->redis->hset("key_meta:{$key}", 'invalidation_reason', $reason);
    }

    protected function calculateHitRate(array $redisInfo): float
    {
        $hits = $redisInfo['keyspace_hits'] ?? 0;
        $misses = $redisInfo['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;
    }

    protected function getLocalCacheSize(): int
    {
        $localKeys = array_filter(Cache::getKeys(), fn($key) => Str::startsWith($key, 'local_'));
        $size = 0;
        
        foreach ($localKeys as $key) {
            $value = Cache::get($key);
            if ($value) {
                $size += strlen(serialize($value));
            }
        }
        
        return $size;
    }

    protected function getCompressionRatio(): float
    {
        // Calculate compression ratio from cached data
        $totalOriginal = 0;
        $totalCompressed = 0;
        
        $sampleKeys = $this->redis->randomkey(100);
        
        foreach ($sampleKeys as $key) {
            $value = $this->redis->get($key);
            if ($value) {
                $original = strlen(unserialize($value));
                $compressed = strlen($value);
                $totalOriginal += $original;
                $totalCompressed += $compressed;
            }
        }
        
        return $totalOriginal > 0 ? round(($totalCompressed / $totalOriginal) * 100, 2) : 100.0;
    }

    protected function calculateCacheEfficiency(): array
    {
        $stats = $this->getCacheStatistics();
        
        return [
            'hit_rate' => $stats['hit_rate'] ?? 0,
            'memory_efficiency' => $this->calculateMemoryEfficiency(),
            'response_time_avg' => $this->getAverageResponseTime(),
            'eviction_rate' => $this->getEvictionRate(),
        ];
    }

    protected function getTopKeys(int $limit): array
    {
        // Get top keys by access frequency
        $keys = $this->redis->keys('*');
        $keyStats = [];
        
        foreach ($keys as $key) {
            $frequency = $this->redis->get("freq:{$key}") ?? 0;
            $size = $this->redis->memory("usage", $key) ?? 0;
            
            $keyStats[] = [
                'key' => $key,
                'frequency' => $frequency,
                'size_bytes' => $size,
                'ttl' => $this->getKeyTTL($key),
            ];
        }
        
        usort($keyStats, fn($a, $b) => $b['frequency'] <=> $a['frequency']);
        
        return array_slice($keyStats, 0, $limit);
    }

    protected function getKeyTTL(string $key): int
    {
        return $this->redis->ttl($key);
    }

    protected function getKeySize(string $key): int
    {
        return $this->redis->memory("usage", $key) ?? 0;
    }

    protected function getExpiredKeys(): array
    {
        // This would require scanning all keys and checking TTL
        // Implementation depends on Redis version and configuration
        return [];
    }

    protected function getLargeKeys(): array
    {
        // Get keys larger than threshold
        $threshold = 1024; // 1KB
        $keys = $this->redis->keys('*');
        $largeKeys = [];
        
        foreach ($keys as $key) {
            $size = $this->redis->memory("usage", $key) ?? 0;
            if ($size > $threshold) {
                $largeKeys[] = [
                    'key' => $key,
                    'size' => $size,
                ];
            }
        }
        
        return $largeKeys;
    }

    protected function rebalanceCacheDistribution(): void
    {
        // Implement cache rebalancing logic
        // This would move keys between nodes based on load
    }

    protected function getMemoryPressure(): float
    {
        $memoryInfo = $this->redis->info('memory');
        $usedMemory = $memoryInfo['used_memory'] ?? 0;
        $maxMemory = $memoryInfo['maxmemory'] ?? 0;
        
        return $maxMemory > 0 ? $usedMemory / $maxMemory : 0.0;
    }

    protected function warmupPopularQueries(): void
    {
        // Warmup cache with popular query results
        $popularQueries = [
            'coupons:popular',
            'coupons:fashion',
            'coupons:electronics',
            'stores:active',
            'categories:all',
        ];
        
        foreach ($popularQueries as $query) {
            $result = $this->getFromDatabase($query);
            if ($result !== null) {
                $this->set($query, $result, 3600); // 1 hour
            }
        }
    }

    protected function getLastWarmupTime(): ?string
    {
        return $this->redis->get('last_warmup_time');
    }

    protected function getEvictionPolicy(): string
    {
        $info = $this->redis->info();
        return $info['maxmemory_policy'] ?? 'noeviction';
    }

    protected function getMemoryEfficiency(): float
    {
        $info = $this->redis->info('memory');
        $usedMemory = $info['used_memory'] ?? 0;
        $rssMemory = $info['used_memory_rss'] ?? 0;
        
        return $usedMemory > 0 ? round(($usedMemory / $rssMemory) * 100, 2) : 100.0;
    }

    protected function getAverageResponseTime(): float
    {
        // This would track response times
        return 0.0; // Placeholder
    }

    protected function getEvictionRate(): float
    {
        $info = $this->redis->info('stats');
        $evictedKeys = $info['evicted_keys'] ?? 0;
        $totalKeys = $info['keyspace_hits'] + $info['keyspace_misses'];
        
        return $totalKeys > 0 ? round(($evictedKeys / $totalKeys) * 100, 2) : 0.0;
    }

    protected function getKeysByTag(string $tag): array
    {
        // Implement tag-based key retrieval
        $pattern = "tag:{$tag}:*";
        return $this->redis->keys($pattern);
    }
}
