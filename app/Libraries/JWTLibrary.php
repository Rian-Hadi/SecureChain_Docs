<?php

namespace App\Libraries;

use Exception;

class JWTLibrary
{
    private $secretKey;
    private $algorithm = 'HS256';
    private $expirationTime = 86400; // 24 hours in seconds

    public function __construct()
    {
        // Get secret key from environment or config
        $this->secretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-change-this-in-production';
    }

    /**
     * Generate JWT token
     * 
     * @param array $data User data to encode
     * @return string JWT token
     */
    public function generateToken(array $data)
    {
        $issuedAt = time();
        $expire = $issuedAt + $this->expirationTime;

        $payload = [
            'iat'  => $issuedAt,         // Issued at
            'exp'  => $expire,           // Expiration time
            'data' => $data              // User data
        ];

        return $this->encode($payload);
    }

    /**
     * Verify and decode JWT token
     * 
     * @param string $token JWT token
     * @return array|false Decoded data or false if invalid
     */
    public function verifyToken($token)
    {
        try {
            $decoded = $this->decode($token);
            
            // Check if token is expired
            if (isset($decoded['exp']) && $decoded['exp'] < time()) {
                return false;
            }

            return $decoded['data'] ?? false;
        } catch (Exception $e) {
            log_message('error', '[JWT] Token verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Encode data to JWT
     * 
     * @param array $payload
     * @return string
     */
    private function encode(array $payload)
    {
        $header = [
            'typ' => 'JWT',
            'alg' => $this->algorithm
        ];

        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($payload));

        $signature = hash_hmac(
            'sha256',
            $headerEncoded . '.' . $payloadEncoded,
            $this->secretKey,
            true
        );
        $signatureEncoded = $this->base64UrlEncode($signature);

        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }

    /**
     * Decode JWT token
     * 
     * @param string $token
     * @return array
     * @throws Exception
     */
    private function decode($token)
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new Exception('Invalid token structure');
        }

        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;

        // Verify signature
        $signature = $this->base64UrlDecode($signatureEncoded);
        $expectedSignature = hash_hmac(
            'sha256',
            $headerEncoded . '.' . $payloadEncoded,
            $this->secretKey,
            true
        );

        if (!hash_equals($signature, $expectedSignature)) {
            throw new Exception('Invalid token signature');
        }

        // Decode payload
        $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON in payload');
        }

        return $payload;
    }

    /**
     * Base64 URL encode
     */
    private function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private function base64UrlDecode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /**
     * Set expiration time in seconds
     */
    public function setExpirationTime($seconds)
    {
        $this->expirationTime = $seconds;
        return $this;
    }

    /**
     * Get expiration time
     */
    public function getExpirationTime()
    {
        return $this->expirationTime;
    }
}
