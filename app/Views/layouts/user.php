<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'User Dashboard' ?></title>

    <script src="https://cdn.tailwindcss.com"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>

<body class="bg-gray-50 text-gray-800 h-screen overflow-hidden">

<div class="flex h-screen">
    <!-- Sidebar -->
    <?= $this->include('partials/user/sidebar') ?>

    <!-- Main Content -->
    <div class="flex-1 ml-64 flex flex-col h-screen">
        <header class="bg-white border-b border-gray-200 flex-shrink-0">
            <div class="px-8 py-6">
                <div class="text-left">
                    <p class="text-sm text-gray-600" id="currentDate"></p>
                    <p class="text-xs text-gray-500" id="currentTime"></p>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-8">
            <?= $this->renderSection('content') ?>
        </main>

        <footer class="bg-white border-t border-gray-200 py-4 flex-shrink-0">
            <div class="px-8">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-600">© <?= date('Y') ?>. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </div>
</div>

<script>
    // --- Script untuk Jam & Waktu ---
    function updateClock() {
        const dateElement = document.getElementById('currentDate');
        const timeElement = document.getElementById('currentTime');
        if (!dateElement || !timeElement) return;

        const now = new Date();
        dateElement.textContent = new Intl.DateTimeFormat('en-US', {
            weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'Asia/Jakarta',
        }).format(now);
        timeElement.textContent = new Intl.DateTimeFormat('en-US', {
            hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false, timeZone: 'Asia/Jakarta',
        }).format(now) + ' WIB';
    }

    updateClock();
    setInterval(updateClock, 1000);


</script>

</body>
</html>