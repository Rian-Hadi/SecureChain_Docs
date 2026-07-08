<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Internal | E-Archive DJKI</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .bg-djki-blue { background-color: #004a87; }
        .text-djki-gold { color: #d4af37; }
        .border-djki-blue { border-color: #004a87; }
        .focus\:ring-djki-blue:focus { --tw-ring-color: #004a87; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4 sm:p-6">

    <div class="max-w-5xl w-full bg-white rounded-[2rem] shadow-2xl overflow-hidden flex flex-col md:flex-row min-h-[600px]">

        <div class="md:w-5/12 bg-djki-blue p-10 text-white flex flex-col justify-between relative overflow-hidden">
            <div class="absolute -top-20 -left-20 w-64 h-64 bg-blue-800 rounded-full opacity-50"></div>
            <div class="absolute bottom-10 -right-10 w-48 h-48 bg-djki-gold rounded-full opacity-10 blur-3xl"></div>

            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-8">
                    <div class="bg-white p-2.5 rounded-xl shadow-lg">
                        <i data-lucide="server" class="w-7 h-7 text-djki-blue"></i>
                    </div>
                    <h2 class="text-xl font-bold tracking-tight">PORTAL <span class="text-djki-gold">INTERNAL</span></h2>
                </div>

                <h1 class="text-4xl font-extrabold leading-tight mb-4">Sistem Manajemen <br><span class="text-djki-gold">Arsip Digital.</span></h1>
                <p class="text-blue-100 text-sm leading-relaxed mb-8 opacity-90">
                    Gunakan kredensial korporat Anda untuk mengakses database terpusat Merek, Paten, Hak Cipta, dan Desain Industri DJKI.
                </p>
            </div>

            <div class="relative z-10 mt-10">
                <p class="text-[11px] text-blue-300 font-medium">© <?= date('Y') ?> Direktorat Jenderal Kekayaan Intelektual</p>
            </div>
        </div>

        <div class="md:w-7/12 p-8 md:p-16 flex flex-col justify-center bg-white relative">

            <div class="mb-10 text-center md:text-left">
                <h3 class="text-3xl font-bold text-gray-800 mb-2">Login</h3>
                <p class="text-gray-500 text-sm">Silakan masukkan username dan password Anda.</p>
            </div>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded-r-xl text-sm shadow-sm flex items-center">
                    <i data-lucide="alert-circle" class="w-4 h-4 mr-2 flex-shrink-0"></i>
                    <?= session()->getFlashdata('error') ?>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('success')): ?>
                <div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-r-xl text-sm shadow-sm flex items-center">
                    <i data-lucide="check-circle-2" class="w-4 h-4 mr-2 flex-shrink-0"></i>
                    <?= session()->getFlashdata('success') ?>
                </div>
            <?php endif; ?>

            <form action="<?= base_url('/auth/process-login') ?>" method="POST" class="space-y-6">
                <?= csrf_field() ?>

                <div>
                    <label for="username" class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-2 ml-1">
                        Username
                    </label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 group-focus-within:text-djki-blue transition-colors">
                            <i data-lucide="user" class="w-5 h-5"></i>
                        </div>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            value="<?= old('username') ?>"
                            class="w-full pl-12 pr-4 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:bg-white focus:ring-4 focus:ring-blue-100 focus:border-djki-blue outline-none transition-all placeholder:text-gray-400"
                            placeholder="Masukkan username"
                            required
                            autofocus>
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-2 ml-1">
                        Password
                    </label>
                    <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-gray-400 group-focus-within:text-djki-blue transition-colors">
                            <i data-lucide="lock-keyhole" class="w-5 h-5"></i>
                        </div>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="w-full pl-12 pr-4 py-4 bg-gray-50 border border-gray-200 rounded-2xl focus:bg-white focus:ring-4 focus:ring-blue-100 focus:border-djki-blue outline-none transition-all placeholder:text-gray-400"
                            placeholder="••••••••"
                            required>
                    </div>
                </div>

                <div class="flex items-center justify-between px-1">
                    <label for="remember" class="flex items-center cursor-pointer group">
                        <input
                            type="checkbox"
                            id="remember"
                            name="remember"
                            value="1"
                            class="w-5 h-5 rounded-md border-gray-300 text-djki-blue focus:ring-djki-blue cursor-pointer transition-all">
                        <span class="ml-3 text-sm font-medium text-gray-600 group-hover:text-djki-blue transition-colors">Ingat saya</span>
                    </label>
                </div>

                <button type="submit" class="w-full bg-djki-blue hover:bg-blue-900 text-white font-bold py-4 rounded-2xl shadow-lg shadow-blue-900/20 transform hover:-translate-y-1 active:scale-95 transition-all flex items-center justify-center gap-3">
                    <i data-lucide="log-in" class="w-5 h-5"></i>
                    <span>LOGIN</span>
                </button>
            </form>

            <div class="mt-10 pt-6 border-t border-gray-100">
                <div class="flex items-start gap-3 bg-blue-50/50 p-4 rounded-xl border border-blue-100">
                    <i data-lucide="info" class="w-5 h-5 text-djki-blue mt-0.5 flex-shrink-0"></i>
                    <div>
                        <p class="text-xs font-semibold text-gray-800 mb-1">Kendala Akses Portal?</p>
                        <p class="text-xs text-gray-500 leading-relaxed">Jika Anda lupa password atau username tidak terdaftar, silakan hubungi tim Helpdesk IT di ekstensi <strong>112</strong> atau email ke <strong>it.support@dgip.go.id</strong>.</p>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        // Inisialisasi Ikon
        lucide.createIcons();
    </script>
</body>
</html>
