<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Libraries\TelegramService;
use App\Models\ActivityLogModel;

class TelegramDaemon extends BaseCommand
{
    protected $group = 'Telegram';
    protected $name = 'telegram:daemon';
    protected $description = 'Jalankan daemon interaktif untuk menerima dan membalas perintah Telegram secara realtime';
    protected $usage = 'telegram:daemon';

    private bool $running = true;

    public function run(array $params)
    {
        CLI::write('╔══════════════════════════════════════════════════════════╗', 'cyan');
        CLI::write('║           TELEGRAM BOT DAEMON (Interactive Mode)         ║', 'cyan');
        CLI::write('╚══════════════════════════════════════════════════════════╝', 'cyan');
        
        $telegramService = new TelegramService();

        if (!$telegramService->isAvailable()) {
            CLI::error("Telegram tidak tersedia. Pastikan botToken sudah dikonfigurasi di .env");
            return;
        }

        CLI::write("Menjalankan listener Telegram... Tekan Ctrl+C untuk berhenti.", 'yellow');

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT,  [$this, 'handleSignal']);
        }

        $updateIdFile = WRITEPATH . 'telegram_last_update_id.txt';
        $lastUpdateId = 0;

        if (file_exists($updateIdFile)) {
            $lastUpdateId = (int) file_get_contents($updateIdFile);
        }

        $activityLog = model(ActivityLogModel::class);

        while ($this->running) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            if (!$this->running) break;

            $updates = $telegramService->getUpdates($lastUpdateId + 1);

            if (isset($updates['ok']) && $updates['ok'] === true && !empty($updates['result'])) {
                foreach ($updates['result'] as $update) {
                    $updateId = $update['update_id'];
                    $lastUpdateId = max($lastUpdateId, $updateId);

                    $messageData = $update['message'] ?? $update['channel_post'] ?? null;

                    if ($messageData && isset($messageData['text'])) {
                        $chatId = $messageData['chat']['id'];
                        $text = trim($messageData['text']);
                        $username = $messageData['from']['username'] ?? $messageData['from']['first_name'] ?? $messageData['chat']['title'] ?? 'User';

                        CLI::write("[Menerima pesan] dari {$username} ({$chatId}): {$text}", 'green');

                        $this->processCommand($text, $chatId, $telegramService, $activityLog);
                    }
                }
                
                // Simpan update_id terakhir
                file_put_contents($updateIdFile, $lastUpdateId);
            }

            // Hindari CPU spike (Long polling menggunakan timeout pada request, tapi jika error/kosong kita sleep sebentar)
            sleep(2);
        }

        CLI::write("\nTelegram Daemon dihentikan.", 'yellow');
    }

    private function processCommand(string $text, string $chatId, TelegramService $telegramService, ActivityLogModel $activityLog)
    {
        $command = strtolower($text);
        
        // Membersihkan jika ada tag username bot (misal: /daily@MyBot)
        if (strpos($command, '@') !== false) {
            $command = explode('@', $command)[0];
        }

        $validPeriods = ['daily', 'weekly', 'monthly'];
        $periodNames = ['daily' => 'Harian', 'weekly' => 'Mingguan', 'monthly' => 'Bulanan'];

        if (in_array(str_replace('/', '', $command), $validPeriods)) {
            $period = str_replace('/', '', $command);
            CLI::write("Memproses rangkuman {$period}...", 'cyan');

            $summary = $activityLog->getSummaryByPeriod($period);
            $message = $this->formatSummaryMessage($period, $periodNames[$period], $summary);

            $telegramService->sendMessageToChat($chatId, $message);
            CLI::write("Balasan {$period} dikirim ke {$chatId}.", 'green');
        } elseif ($command === '/help' || $command === '/start') {
            $message = "Halo! Saya adalah Bot Pemantau Blockchain Anda. 🤖\n\n";
            $message .= "Berikut perintah yang bisa Anda gunakan:\n";
            $message .= "`/daily` - Lihat rangkuman manipulasi hari ini\n";
            $message .= "`/weekly` - Lihat rangkuman 7 hari terakhir\n";
            $message .= "`/monthly` - Lihat rangkuman bulan ini\n";
            $message .= "`/help` - Menampilkan pesan ini\n\n";
            $message .= "Catat **Chat ID** Anda jika ingin menjadikannya channel utama: `{$chatId}`";

            $telegramService->sendMessageToChat($chatId, $message);
        } else {
            // Abaikan jika tidak dikenal (supaya tidak spam jika bot berada di grup)
            // $telegramService->sendMessageToChat($chatId, "Perintah tidak dikenali. Ketik /help untuk bantuan.");
        }
    }

    private function formatSummaryMessage(string $period, string $periodName, array $summary): string
    {
        $dateStr = date('d M Y');

        if ($period === 'weekly') {
            $dateStr = date('d M Y', strtotime('-7 days')) . ' - ' . date('d M Y');
        } elseif ($period === 'monthly') {
            $dateStr = date('F Y');
        }

        $message = "📊 *Rangkuman {$periodName} Aktivitas Blockchain*\n";
        $message .= "🗓 *Periode:* {$dateStr}\n\n";
        
        $message .= "⚠️ *Kasus Manipulasi Terdeteksi:* {$summary['total_manipulations']}\n";
        $message .= "✅ *Total Pemulihan (Recovery):* {$summary['total_recoveries']}\n\n";
        
        if ($summary['total_manipulations'] > 0) {
            if ($summary['total_recoveries'] >= $summary['total_manipulations']) {
                $message .= "💡 _Sistem berjalan dengan baik. Semua manipulasi berhasil dipulihkan._";
            } else {
                $message .= "🚨 _Perhatian! Ada manipulasi yang mungkin belum dipulihkan atau membutuhkan tinjauan manual._";
            }
        } else {
            $message .= "💡 _Sistem aman. Tidak ada manipulasi yang terdeteksi._";
        }

        return $message;
    }

    public function handleSignal(int $signal): void
    {
        $this->running = false;
    }
}
