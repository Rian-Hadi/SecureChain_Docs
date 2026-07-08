<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document System</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800">


    <div class="ml-5 mr-5">
        <header class="bg-white border-b border-slate-200 sticky top-0 z-40 relative">
            <div class="px-8 py-6">
                <div class="absolute right-8 top-4 text-right">
                    <p class="text-sm text-slate-600" id="currentDate"></p>
                    <p class="text-xs text-slate-500" id="currentTime"></p>
                </div>
                <div class="pt-10"></div>
            </div>
        </header>

        <main class=" min-h-screen">
            <?= $this->renderSection('content') ?>
        </main>

        <footer class="bg-white border-t border-slate-200 py-6">
            <div class="px-8">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-slate-600">© <?= date('Y') ?>. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </div>

    <script>
    function updateClock() {
        const now = new Date();
        const dateElement = document.getElementById('currentDate');
        const timeElement = document.getElementById('currentTime');

        if (!dateElement || !timeElement) {
            return;
        }

        const dateOptions = {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            timeZone: 'Asia/Jakarta',
        };

        const timeOptions = {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
            timeZone: 'Asia/Jakarta',
        };

        dateElement.textContent = new Intl.DateTimeFormat('en-US', dateOptions).format(now);
        timeElement.textContent = `${new Intl.DateTimeFormat('en-US', timeOptions).format(now)} WIB`;
    }
    
    updateClock();
    setInterval(updateClock, 1000);
    </script>

</body>
</html>