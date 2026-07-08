<?php

namespace App\Libraries;

use Config\Telegram as TelegramConfig;
use CodeIgniter\HTTP\CURLRequest;
use Psr\Log\LoggerInterface;

/**
 * Telegram Service
 * 
 * Service untuk mengirim notifikasi ke Telegram private channel
 * Menyediakan real-time alerts untuk blockchain recovery events
 */
class TelegramService
{
    protected TelegramConfig $config;
    protected CURLRequest $client;
    protected LoggerInterface $logger;
    protected bool $available = false;
    protected int $retryCount = 0;

    public function __construct()
    {
        $this->config = new TelegramConfig();
        $this->client = service('curlrequest');
        $this->logger = service('logger');

        // Check connectivity on initialization
        $this->checkAvailability();
    }

    /**
     * Check if Telegram Bot is accessible
     * 
     * @return bool
     */
    public function checkAvailability(): bool
    {
        if (!$this->config->enabled) {
            $this->logger->info('Telegram notifications disabled in config');
            $this->available = false;
            return false;
        }

        if (empty($this->config->botToken) || empty($this->config->channelId)) {
            $this->logger->warning('Telegram Bot Token or Channel ID not configured');
            $this->available = false;
            return false;
        }

        try {
            $response = $this->client->request('GET', $this->config->apiUrl . $this->config->botToken . '/getMe', [
                'timeout' => 5,
                'verify' => false
            ]);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody(), true);
                
                if ($data['ok'] === true) {
                    $this->logger->info('Telegram bot connected: ' . $data['result']['username']);
                    $this->available = true;
                    return true;
                }
            }

            $this->logger->warning('Telegram Bot returned non-ok status');
            $this->available = false;
            return false;
        } catch (\Exception $e) {
            $this->logger->warning('Telegram Bot unavailable: ' . $e->getMessage());
            $this->available = false;
            return false;
        }
    }

    /**
     * Check apakah Telegram tersedia untuk digunakan
     * 
     * @return bool
     */
    public function isAvailable(): bool
    {
        return $this->available;
    }

    /**
     * Send message ke Telegram channel
     * 
     * @param string $message Text message (supports Markdown)
     * @param array $options ['parse_mode' => 'Markdown', 'disable_web_page_preview' => true]
     * @return array ['success' => bool, 'messageId' => int, 'error' => string]
     */
    public function sendMessage(string $message, array $options = []): array
    {
        if (!$this->isAvailable()) {
            $this->logger->warning('Telegram not available, skipping send message');
            return [
                'success' => false,
                'error' => 'Telegram bot not available',
                'messageId' => null
            ];
        }

        try {
            $payload = [
                'chat_id' => $this->config->channelId,
                'text' => $message,
                'parse_mode' => $options['parse_mode'] ?? 'Markdown',
                'disable_web_page_preview' => $options['disable_web_page_preview'] ?? true,
                'disable_notification' => $options['disable_notification'] ?? false
            ];

            $response = $this->client->request('POST', $this->config->apiUrl . $this->config->botToken . '/sendMessage', [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($payload),
                'timeout' => $this->config->connectTimeout,
                'verify' => false
            ]);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody(), true);

                if ($data['ok'] === true) {
                    $messageId = $data['result']['message_id'] ?? null;
                    
                    $this->logger->info('Telegram message sent', [
                        'messageId' => $messageId,
                        'length' => strlen($message)
                    ]);

                    return [
                        'success' => true,
                        'messageId' => $messageId,
                        'error' => null
                    ];
                } else {
                    $error = $data['description'] ?? 'Unknown error';
                    $this->logger->error('Telegram API error: ' . $error);
                    
                    return [
                        'success' => false,
                        'error' => $error,
                        'messageId' => null
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'HTTP ' . $response->getStatusCode(),
                'messageId' => null
            ];
        } catch (\Exception $e) {
            $this->logger->error('Telegram message send failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'messageId' => null
            ];
        }
    }

    /**
     * Send message dengan retry logic
     * 
     * @param string $message
     * @param array $options
     * @return array
     */
    public function sendMessageWithRetry(string $message, array $options = []): array
    {
        $this->retryCount = 0;

        while ($this->retryCount < $this->config->maxRetries) {
            $result = $this->sendMessage($message, $options);

            if ($result['success']) {
                return $result;
            }

            $this->retryCount++;
            
            if ($this->retryCount < $this->config->maxRetries) {
                sleep($this->config->retryDelay);
            }
        }

        $this->logger->error('Telegram send failed after ' . $this->config->maxRetries . ' retries');
        
        return [
            'success' => false,
            'error' => 'Failed after ' . $this->config->maxRetries . ' retries',
            'messageId' => null
        ];
    }

    /**
     * Edit existing message
     * 
     * @param int $messageId
     * @param string $newText
     * @return array ['success' => bool, 'error' => string]
     */
    public function editMessage(int $messageId, string $newText): array
    {
        if (!$this->isAvailable()) {
            return ['success' => false, 'error' => 'Telegram bot not available'];
        }

        try {
            $payload = [
                'chat_id' => $this->config->channelId,
                'message_id' => $messageId,
                'text' => $newText,
                'parse_mode' => 'Markdown'
            ];

            $response = $this->client->request('POST', $this->config->apiUrl . $this->config->botToken . '/editMessageText', [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($payload),
                'timeout' => $this->config->connectTimeout,
                'verify' => false
            ]);

            $data = json_decode($response->getBody(), true);

            return [
                'success' => $data['ok'] ?? false,
                'error' => !$data['ok'] ? ($data['description'] ?? 'Unknown error') : null
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Quick test untuk verify bot configuration
     * 
     * @return array ['status' => 'ok'|'error', 'message' => string]
     */
    public function test(): array
    {
        if (!$this->config->enabled) {
            return ['status' => 'error', 'message' => 'Telegram disabled in config'];
        }

        if (empty($this->config->botToken)) {
            return ['status' => 'error', 'message' => 'Bot token not configured'];
        }

        if (empty($this->config->channelId)) {
            return ['status' => 'error', 'message' => 'Channel ID not configured'];
        }

        try {
            $response = $this->client->request('GET', $this->config->apiUrl . $this->config->botToken . '/getMe', [
                'timeout' => 5,
                'verify' => false
            ]);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody(), true);

                if ($data['ok'] === true) {
                    return [
                        'status' => 'ok',
                        'message' => 'Bot connected: ' . $data['result']['username'],
                        'bot_username' => $data['result']['username'],
                        'channel_id' => $this->config->channelId
                    ];
                }
            }

            return ['status' => 'error', 'message' => 'Telegram API returned error'];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Get service configuration (untuk debugging)
     * 
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'enabled' => $this->config->enabled,
            'botToken' => substr($this->config->botToken, 0, 10) . '****', // Masked
            'channelId' => $this->config->channelId,
            'available' => $this->available,
            'maxRetries' => $this->config->maxRetries,
            'retryDelay' => $this->config->retryDelay
        ];
    }

    /**
     * Get recent updates from Telegram Bot API
     * 
     * @param int $offset
     * @return array
     */
    public function getUpdates(int $offset = 0): array
    {
        if (!$this->isAvailable()) {
            return ['ok' => false, 'error' => 'Telegram bot not available'];
        }

        try {
            $url = $this->config->apiUrl . $this->config->botToken . '/getUpdates?timeout=10&allowed_updates=["message","channel_post"]';
            if ($offset > 0) {
                $url .= '&offset=' . $offset;
            }

            $response = $this->client->request('GET', $url, [
                'timeout' => 15,
                'verify' => false
            ]);

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody(), true);
            }

            return ['ok' => false, 'error' => 'HTTP ' . $response->getStatusCode()];
        } catch (\Exception $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send message ke spesifik Chat ID (untuk balas pesan)
     * 
     * @param string $chatId
     * @param string $message
     * @param array $options
     * @return array
     */
    public function sendMessageToChat(string $chatId, string $message, array $options = []): array
    {
        if (!$this->isAvailable()) {
            return ['success' => false, 'error' => 'Telegram bot not available'];
        }

        try {
            $payload = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $options['parse_mode'] ?? 'Markdown',
                'disable_web_page_preview' => $options['disable_web_page_preview'] ?? true
            ];

            $response = $this->client->request('POST', $this->config->apiUrl . $this->config->botToken . '/sendMessage', [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($payload),
                'timeout' => $this->config->connectTimeout,
                'verify' => false
            ]);

            $data = json_decode($response->getBody(), true);
            return [
                'success' => $data['ok'] ?? false,
                'messageId' => $data['result']['message_id'] ?? null,
                'error' => !$data['ok'] ? ($data['description'] ?? 'Unknown error') : null
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
