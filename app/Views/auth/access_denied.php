<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses Ditolak - Blockchain Document System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-white min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full text-center">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 mb-6">
            <svg class="w-10 h-10 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
        </div>

        <h1 class="text-3xl font-semibold text-gray-900 mb-3">Akses Ditolak</h1>

        <p class="text-gray-600 mb-8">
            Anda tidak memiliki izin untuk mengakses halaman ini.
        </p>

        <div class="flex flex-col gap-3">
            <?php if (session()->has('isLoggedIn')): ?>
                <a href="<?= base_url(session()->get('role') === 'admin' ? '/admin/dashboard' : '/') ?>"
                    class="inline-flex items-center justify-center px-6 py-3 bg-gray-900 hover:bg-gray-800 text-white font-medium rounded-lg transition-colors">
                    Kembali ke Dashboard
                </a>
            <?php else: ?>
                <a href="<?= base_url('/auth/login') ?>"
                    class="inline-flex items-center justify-center px-6 py-3 bg-gray-900 hover:bg-gray-800 text-white font-medium rounded-lg transition-colors">
                    Login
                </a>
            <?php endif; ?>

            <a href="javascript:history.back()"
                class="inline-flex items-center justify-center px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium rounded-lg transition-colors">
                Kembali
            </a>
        </div>
    </div>

</body>

</html>