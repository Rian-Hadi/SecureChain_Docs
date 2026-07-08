<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Security Headers Filter
 * 
 * Adds important security headers to prevent common web vulnerabilities:
 * - X-Frame-Options: Prevents clickjacking attacks
 * - X-Content-Type-Options: Prevents MIME type sniffing
 * - X-XSS-Protection: Legacy XSS protection header
 * - Strict-Transport-Security: Force HTTPS
 * - Content-Security-Policy: Prevent XSS and injection attacks
 * - Referrer-Policy: Control referrer information
 */
class SecurityHeadersFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Nothing to do before
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // ========== X-Frame-Options: Prevent Clickjacking ==========
        // DENY: Page cannot be displayed in a frame, regardless of site
        $response->setHeader('X-Frame-Options', 'DENY');

        // ========== X-Content-Type-Options: Prevent MIME Sniffing ==========
        // nosniff: Browser will not try to guess MIME type
        $response->setHeader('X-Content-Type-Options', 'nosniff');

        // ========== X-XSS-Protection: Legacy XSS Protection ==========
        // 1; mode=block: Enable XSS protection, block page if XSS detected
        $response->setHeader('X-XSS-Protection', '1; mode=block');

        // ========== Referrer-Policy: Control Referrer Information ==========
        // strict-origin-when-cross-origin: Send referrer only for same-origin, origin for cross-origin
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // ========== Strict-Transport-Security (HSTS): Force HTTPS ==========
        // max-age=31536000 (1 year); includeSubDomains
        if ($request->isSecure()) {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // ========== Content-Security-Policy (CSP): XSS & Injection Prevention ==========
        // Strict CSP policy
        $csp = [
            "default-src 'self'",                          // Default: only same origin
            "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net",  // Scripts from self and CDNs
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com", // Styles from self
            "font-src 'self' https://fonts.gstatic.com",   // Fonts from GCS
            "img-src 'self' data: https:",                 // Images from self and HTTPS
            "connect-src 'self' https:",                   // AJAX/Fetch from self
            "frame-ancestors 'none'",                      // Prevent framing
            "base-uri 'self'",                             // Restrict base URL
            "form-action 'self'",                          // Restrict form submissions
            "upgrade-insecure-requests",                    // Upgrade HTTP to HTTPS
        ];
        $response->setHeader('Content-Security-Policy', implode('; ', $csp));

        // ========== Permissions-Policy: Control Browser Features ==========
        // Disable dangerous features
        $permissions = [
            'accelerometer=()',
            'ambient-light-sensor=()',
            'autoplay=()',
            'camera=()',
            'cross-origin-isolated=()',
            'document-domain=()',
            'encrypted-media=()',
            'fullscreen=()',
            'geolocation=()',
            'gyroscope=()',
            'magnetometer=()',
            'microphone=()',
            'midi=()',
            'payment=()',
            'picture-in-picture=()',
            'publickey-credentials-get=()',
            'speaker=()',
            'usb=()',
            'screen-wake-lock=()',
            'xr-spatial-tracking=()',
        ];
        $response->setHeader('Permissions-Policy', implode(', ', $permissions));

        // ========== Remove sensitive headers that expose server info ==========
        $response->removeHeader('Server');
        $response->removeHeader('X-Powered-By');

        return $response;
    }
}
