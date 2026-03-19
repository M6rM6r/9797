<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Carbon\Carbon;

class AdvancedSecurityMiddleware
{
    protected array $rateLimits = [
        'default' => ['requests' => 100, 'window' => 60], // 100 requests per minute
        'search' => ['requests' => 30, 'window' => 60],   // 30 searches per minute
        'upload' => ['requests' => 5, 'window' => 300],   // 5 uploads per 5 minutes
        'auth' => ['requests' => 10, 'window' => 300],   // 10 auth attempts per 5 minutes
    ];

    protected array $suspiciousPatterns = [
        'sql_injection' => ['/union\s+select/', '/drop\s+table/', '/insert\s+into/', '/delete\s+from/'],
        'xss' => ['<script', 'javascript:', 'onerror=', 'onload='],
        'path_traversal' => ['\.\./', '\.\.\\', '%2e%2e%2f'],
        'command_injection' => [';ls', ';cat', ';rm', '|whoami'],
    ];

    protected array $allowedOrigins = [
        'https://yourdomain.com',
        'https://www.yourdomain.com',
        'https://admin.yourdomain.com',
    ];

    public function handle(Request $request, Closure $next)
    {
        // Security headers
        $this->addSecurityHeaders($request);
        
        // Rate limiting
        if (!$this->checkRateLimit($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many requests. Please try again later.',
                'error_code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $this->getRetryAfter($request),
            ], 429);
        }

        // IP-based security
        if (!$this->checkIPSecurity($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied from this IP.',
                'error_code' => 'IP_BLOCKED',
            ], 403);
        }

        // Request validation
        if (!$this->validateRequest($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request format.',
                'error_code' => 'INVALID_REQUEST',
            ], 400);
        }

        // JWT authentication (if required)
        if ($this->requiresAuthentication($request)) {
            $authResult = $this->authenticateJWT($request);
            if (!$authResult['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $authResult['message'],
                    'error_code' => 'AUTHENTICATION_FAILED',
                ], 401);
            }
            
            // Add user info to request
            $request->merge(['user_id' => $authResult['user_id']]);
        }

        // Log security event
        $this->logSecurityEvent($request, 'request_allowed');

        return $next($request);
    }

    /**
     * Add comprehensive security headers
     */
    protected function addSecurityHeaders(Request $request): void
    {
        $response = response();
        
        // CORS headers
        $origin = $request->header('Origin');
        if (in_array($origin, $this->allowedOrigins)) {
            $response->header('Access-Control-Allow-Origin', $origin);
        }
        
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-API-Key');
        $response->header('Access-Control-Allow-Credentials', 'true');
        $response->header('Access-Control-Max-Age', '86400');

        // Security headers
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'DENY');
        $response->header('X-XSS-Protection', '1; mode=block');
        $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        $response->header('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'; frame-ancestors 'none';");
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->header('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        
        // Remove server information
        $response->header('Server', 'ArabicCouponApp');
        $response->header('X-Powered-By', 'ArabicCouponApp');
        
        $response->send();
    }

    /**
     * Advanced rate limiting with Redis
     */
    protected function checkRateLimit(Request $request): bool
    {
        $clientIp = $request->ip();
        $endpoint = $this->getEndpointType($request);
        $rateLimit = $this->rateLimits[$endpoint] ?? $this->rateLimits['default'];
        
        // Check if IP is whitelisted
        if ($this->isWhitelistedIP($clientIp)) {
            return true;
        }

        // Sliding window rate limiting
        $now = Carbon::now();
        $windowStart = $now->copy()->subSeconds($rateLimit['window']);
        
        // Clean old entries
        $this->cleanOldRateLimitEntries($windowStart);
        
        // Get current count for this IP and endpoint
        $key = "rate_limit:{$endpoint}:{$clientIp}";
        $currentCount = Redis::get($key) ?? 0;
        
        if ($currentCount >= $rateLimit['requests']) {
            $this->logSecurityEvent($request, 'rate_limit_exceeded', [
                'endpoint' => $endpoint,
                'current_count' => $currentCount,
                'limit' => $rateLimit['requests'],
            ]);
            return false;
        }

        // Increment counter
        Redis::incr($key);
        Redis::expire($key, $rateLimit['window']);
        
        return true;
    }

    /**
     * IP-based security checks
     */
    protected function checkIPSecurity(Request $request): bool
    {
        $clientIp = $request->ip();
        
        // Check against blacklisted IPs
        if ($this->isBlacklistedIP($clientIp)) {
            $this->logSecurityEvent($request, 'blacklisted_ip', ['ip' => $clientIp]);
            return false;
        }

        // Check for suspicious IP patterns
        if ($this->isSuspiciousIP($clientIp)) {
            $this->logSecurityEvent($request, 'suspicious_ip', ['ip' => $clientIp]);
            return false;
        }

        // Check for proxy/VPN usage
        if ($this->isProxyIP($request)) {
            $this->logSecurityEvent($request, 'proxy_detected', [
                'ip' => $clientIp,
                'headers' => $request->headers->all(),
            ]);
        }

        return true;
    }

    /**
     * Comprehensive request validation
     */
    protected function validateRequest(Request $request): bool
    {
        // Check request size
        $contentLength = $request->header('Content-Length');
        if ($contentLength && $contentLength > 10 * 1024 * 1024) { // 10MB limit
            return false;
        }

        // Check for suspicious patterns in input
        $allInput = array_merge(
            $request->all(),
            $request->headers->all()
        );

        foreach ($allInput as $key => $value) {
            if (is_string($value) && $this->containsSuspiciousPatterns($value)) {
                Log::warning('Suspicious pattern detected', [
                    'input_key' => $key,
                    'input_value' => $value,
                    'ip' => $request->ip(),
                ]);
                return false;
            }
        }

        // Validate JSON structure for JSON requests
        if ($request->isJson()) {
            try {
                json_decode($request->getContent());
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return false;
                }
            } catch (\Exception $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * JWT authentication with advanced validation
     */
    protected function authenticateJWT(Request $request): array
    {
        $token = $this->extractToken($request);
        
        if (!$token) {
            return ['valid' => false, 'message' => 'No token provided'];
        }

        try {
            $decoded = JWT::decode($token, new Key(config('app.jwt_secret'), 'HS256'));
            
            // Validate token structure
            if (!$this->validateTokenStructure($decoded)) {
                return ['valid' => false, 'message' => 'Invalid token structure'];
            }

            // Check if token is expired
            if (isset($decoded->exp) && $decoded->exp < Carbon::now()->timestamp) {
                return ['valid' => false, 'message' => 'Token expired'];
            }

            // Check if token is blacklisted
            if ($this->isTokenBlacklisted($token)) {
                return ['valid' => false, 'message' => 'Token is blacklisted'];
            }

            return [
                'valid' => true,
                'user_id' => $decoded->sub ?? null,
                'permissions' => $decoded->permissions ?? [],
                'token_id' => $decoded->jti ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('JWT authentication error', [
                'error' => $e->getMessage(),
                'token' => substr($token, 0, 50) . '...',
            ]);
            
            return ['valid' => false, 'message' => 'Invalid token'];
        }
    }

    /**
     * Check if request requires authentication
     */
    protected function requiresAuthentication(Request $request): bool
    {
        $publicPaths = [
            '/api/health',
            '/api/public/coupons',
            '/api/public/stores',
            '/api/public/categories',
        ];

        $path = $request->path();
        
        return !in_array($path, $publicPaths);
    }

    /**
     * Extract JWT token from request
     */
    protected function extractToken(Request $request): ?string
    {
        // Check Authorization header
        $authHeader = $request->header('Authorization');
        if ($authHeader && preg_match('/Bearer\s+(.*)$/', $authHeader, $matches)) {
            return $matches[1];
        }

        // Check custom header
        $customHeader = $request->header('X-Auth-Token');
        if ($customHeader) {
            return $customHeader;
        }

        // Check query parameter (not recommended but supported)
        return $request->get('token');
    }

    /**
     * Validate JWT token structure
     */
    protected function validateTokenStructure($decoded): bool
    {
        $requiredClaims = ['sub', 'iat', 'exp', 'jti'];
        
        foreach ($requiredClaims as $claim) {
            if (!isset($decoded->$claim)) {
                return false;
            }
        }

        // Check token age
        if (isset($decoded->iat)) {
            $maxAge = config('app.jwt_max_age', 3600); // 1 hour
            if (Carbon::now()->timestamp - $decoded->iat > $maxAge) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check for suspicious patterns in input
     */
    protected function containsSuspiciousPatterns(string $input): bool
    {
        $input = strtolower($input);
        
        foreach ($this->suspiciousPatterns as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $input)) {
                    Log::warning('Suspicious pattern detected', [
                        'category' => $category,
                        'pattern' => $pattern,
                        'input' => substr($input, 0, 100),
                    ]);
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get endpoint type for rate limiting
     */
    protected function getEndpointType(Request $request): string
    {
        $path = $request->path();
        
        if (str_contains($path, '/search')) {
            return 'search';
        } elseif (str_contains($path, '/upload')) {
            return 'upload';
        } elseif (str_contains($path, '/auth')) {
            return 'auth';
        }
        
        return 'default';
    }

    /**
     * Get retry after time for rate limited requests
     */
    protected function getRetryAfter(Request $request): int
    {
        $endpoint = $this->getEndpointType($request);
        $rateLimit = $this->rateLimits[$endpoint] ?? $this->rateLimits['default'];
        
        return $rateLimit['window'];
    }

    /**
     * Clean old rate limit entries
     */
    protected function cleanOldRateLimitEntries(Carbon $cutoff): void
    {
        $pattern = "rate_limit:*";
        $keys = Redis::keys($pattern);
        
        foreach ($keys as $key) {
            $ttl = Redis::ttl($key);
            if ($ttl < 0) { // Expired or doesn't exist
                Redis::del($key);
            }
        }
    }

    /**
     * Check if IP is whitelisted
     */
    protected function isWhitelistedIP(string $ip): bool
    {
        $whitelistedIPs = config('security.whitelisted_ips', []);
        
        foreach ($whitelistedIPs as $whitelistedIP) {
            if ($this->ipMatches($ip, $whitelistedIP)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if IP is blacklisted
     */
    protected function isBlacklistedIP(string $ip): bool
    {
        $blacklistedIPs = config('security.blacklisted_ips', []);
        
        foreach ($blacklistedIPs as $blacklistedIP) {
            if ($this->ipMatches($ip, $blacklistedIP)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check for suspicious IP characteristics
     */
    protected function isSuspiciousIP(string $ip): bool
    {
        // Check for private IP ranges
        if ($this->isPrivateIP($ip)) {
            return true;
        }

        // Check for known malicious ranges
        $suspiciousRanges = config('security.suspicious_ip_ranges', []);
        foreach ($suspiciousRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if request is coming through proxy
     */
    protected function isProxyIP(Request $request): bool
    {
        $proxyHeaders = [
            'X-Forwarded-For',
            'X-Real-IP',
            'X-Forwarded',
            'X-Forwarded-Host',
            'Client-IP',
            'CF-Connecting-IP',
        ];

        foreach ($proxyHeaders as $header) {
            if ($request->header($header)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if token is blacklisted
     */
    protected function isTokenBlacklisted(string $token): bool
    {
        $blacklistKey = "blacklist:token:" . md5($token);
        return Redis::exists($blacklistKey);
    }

    /**
     * Add token to blacklist
     */
    public function blacklistToken(string $token, int $ttl = 3600): void
    {
        $blacklistKey = "blacklist:token:" . md5($token);
        Redis::setex($blacklistKey, $ttl, true);
    }

    /**
     * IP matching utility
     */
    protected function ipMatches(string $ip, string $pattern): bool
    {
        // Support both CIDR and exact matching
        if (str_contains($pattern, '/')) {
            return $this->ipInRange($ip, $pattern);
        }
        
        return $ip === $pattern;
    }

    /**
     * Check if IP is in range
     */
    protected function ipInRange(string $ip, string $range): bool
    {
        if (!str_contains($range, '/')) {
            return $ip === $range;
        }

        [$rangeIP, $mask] = explode('/', $range);
        $rangeDecimal = ip2long($rangeIP);
        $ipDecimal = ip2long($ip);
        $maskDecimal = -1 << (32 - (int)$mask);
        
        return ($rangeDecimal & $maskDecimal) === ($ipDecimal & $maskDecimal);
    }

    /**
     * Check if IP is private
     */
    protected function isPrivateIP(string $ip): bool
    {
        $privateRanges = [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '127.0.0.0/8',
        ];

        foreach ($privateRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Log security events
     */
    protected function logSecurityEvent(Request $request, string $event, array $context = []): void
    {
        $logData = [
            'event' => $event,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path' => $request->path(),
            'method' => $request->method(),
            'timestamp' => Carbon::now()->toISOString(),
            'context' => $context,
        ];

        // Add request ID for tracing
        $requestId = $request->header('X-Request-ID', uniqid());
        $logData['request_id'] = $requestId;

        Log::warning('Security event', $logData);

        // Store in Redis for real-time monitoring
        Redis::lpush('security_events', json_encode($logData));
        Redis::expire('security_events', 86400); // 24 hours
    }

    /**
     * Get security statistics
     */
    public function getSecurityStatistics(): array
    {
        $events = Redis::lrange('security_events', 0, -1);
        $stats = [
            'total_events' => count($events),
            'events_by_type' => [],
            'events_by_ip' => [],
            'recent_events' => [],
            'blocked_ips' => [],
            'rate_limit_hits' => 0,
        ];

        foreach ($events as $event) {
            $eventData = json_decode($event, true);
            
            $eventType = $eventData['event'] ?? 'unknown';
            $stats['events_by_type'][$eventType] = ($stats['events_by_type'][$eventType] ?? 0) + 1;
            
            $ip = $eventData['ip'] ?? 'unknown';
            $stats['events_by_ip'][$ip] = ($stats['events_by_ip'][$ip] ?? 0) + 1;

            if ($eventType === 'rate_limit_exceeded') {
                $stats['rate_limit_hits']++;
            }

            if (in_array($eventType, ['blacklisted_ip', 'suspicious_ip'])) {
                $stats['blocked_ips'][] = $ip;
            }
        }

        // Get recent events (last 24 hours)
        $cutoff = Carbon::now()->subHours(24)->timestamp;
        $stats['recent_events'] = array_filter($events, function($event) use ($cutoff) {
            $eventData = json_decode($event, true);
            return ($eventData['timestamp'] ?? 0) > $cutoff;
        });

        return $stats;
    }

    /**
     * Clear security logs
     */
    public function clearSecurityLogs(): bool
    {
        try {
            Redis::del('security_events');
            
            // Clean up old rate limit entries
            $keys = Redis::keys('rate_limit:*');
            foreach ($keys as $key) {
                Redis::del($key);
            }

            Log::info('Security logs cleared');
            return true;

        } catch (\Exception $e) {
            Log::error('Error clearing security logs', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
