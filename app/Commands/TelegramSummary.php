<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\ActivityLogModel;
use App\Libraries\TelegramService;

class TelegramSummary extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Telegram';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'telegram:summary';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Kirim rangkuman total manipulasi dan recovery ke Telegram berdasarkan periode (daily, weekly, monthly).';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'telegram:summary [periode]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [
        'periode' => 'Periode rangkuman: daily, weekly, atau monthly (default: daily)'
    ];

    /**
     * Actually execute a command.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        $period = $params[0] ?? 'daily';
        $validPeriods = ['daily', 'weekly', 'monthly'];

        if (!in_array(strtolower($period), $validPeriods)) {
            CLI::error("Periode tidak valid. Gunakan: daily, weekly, atau monthly.");
            return;
        }

        $period = strtolower($period);
        CLI::write("Mengambil data rangkuman untuk periode: {$period}...", 'yellow');

        $activityLog = model(ActivityLogModel::class);
        $summary = $activityLog->getSummaryByPeriod($period);

        $telegramService = new TelegramService();

        if (!$telegramService->isAvailable()) {
            CLI::error("Telegram tidak tersedia. Pastikan botToken dan channelId sudah dikonfigurasi di .env");
            return;
        }

        $message = $this->formatSummaryMessage($period, $summary);
        
        CLI::write("Mengirim pesan ke Telegram...", 'yellow');
        
        $result = $telegramService->sendMessage($message);

        if ($result['success']) {
            CLI::write("Pesan rangkuman berhasil dikirim ke Telegram!", 'green');
        } else {
            CLI::error("Gagal mengirim pesan: " . $result['error']);
        }
    }

    private function formatSummaryMessage(string $period, array $summary): string
    {
        $periodNames = [
            'daily' => 'Harian',
            'weekly' => 'Mingguan',
            'monthly' => 'Bulanan'
        ];

        $periodName = $periodNames[$period] ?? 'Harian';
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
}
