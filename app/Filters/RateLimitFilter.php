<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Rate Limiting Filter
 *
 * Implements rate limiting per IP address to prevent DDoS and brute force attacks
 * Default: 60 requests per minute per IP
 *
 * Usage in Routes:
 * $routes->group('', ['filter' => 'rate_limit'], function($routes) {
 *     $routes->post('create', 'Document::create');
 * });
 */
class RateLimitFilter implements FilterInterface
{
    // Configuration
    protected $maxRequests = 60;           // Max requests per time window
    protected $timeWindow = 60;            // Time window in seconds (1 minute)
    protected $cacheKeyPrefix = 'rate_limit_';
    protected $cache;

    public function before(RequestInterface $request, $arguments = null)
    {
        $this->cache = \Config\Services::cache();

        // Get client IP address
        $clientIP = $request->getIPAddress();

        // Create cache key for this IP
        // Sanitize IP (IPv6 contains ':' which is a reserved cache character)
        $safeIP = md5($clientIP);
        $cacheKey = $this->cacheKeyPrefix . $safeIP;

        // Get current request count
        $requestCount = $this->cache->get($cacheKey) ?? 0;

        // Increment request count
        $requestCount++;

        // Save updated count to cache with TTL = time window
        $this->cache->save($cacheKey, $requestCount, $this->timeWindow);

        // Check if limit exceeded
        if ($requestCount > $this->maxRequests) {
            log_message('warning', "[RATE_LIMIT] Rate limit exceeded for IP: {$clientIP} ({$requestCount} requests)");

            return $this->response($request);
        }

        // Log high rate requests (warning threshold: 80% of limit)
        if ($requestCount > ($this->maxRequests * 0.8)) {
            log_message('warning', "[RATE_LIMIT] High request rate from IP: {$clientIP} ({$requestCount}/{$this->maxRequests} requests)");
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing to do after
    }

    /**
     * Return rate limit exceeded response
     */
    private function response(RequestInterface $request): ResponseInterface
    {
        $response = service('response');

        $response
            ->setStatusCode(429)
            ->setHeader('Retry-After', $this->timeWindow)
            ->setHeader('X-RateLimit-Limit', $this->maxRequests)
            ->setHeader('X-RateLimit-Window', $this->timeWindow)
            ->setJSON([
                'status' => 429,
                'error' => 'Too Many Requests',
                'message' => 'Terlalu banyak permintaan. Silakan coba lagi dalam beberapa detik.',
                'retry_after' => $this->timeWindow
            ]);

        return $response;
    }
}
