<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
        // Menampilkan halaman selamat datang default CodeIgniter.
        return view('welcome_message');
    }

    public function accessDenied()
    {
        // Menampilkan halaman "Akses Ditolak" untuk IP yang tidak ada di whitelist.
        return view('errors/access_denied');
    }
}
