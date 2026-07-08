<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Telegram extends BaseConfig
{
    public bool $enabled = false;
    public string $botToken = '';
    public string $channelId = '';
    public string $apiUrl = 'https://api.telegram.org/bot';
    public int $connectTimeout = 5;
    public int $maxRetries = 3;
    public int $retryDelay = 2;

    public function __construct()
    {
        parent::__construct();

        // Baca konfigurasi dari file .env
        $this->enabled   = (bool) env('telegram.enabled', $this->enabled);
        $this->botToken  = (string) env('telegram.botToken', $this->botToken);
        $this->channelId = (string) env('telegram.channelId', $this->channelId);
    }
}
