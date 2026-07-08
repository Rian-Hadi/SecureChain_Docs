<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\WhitelistModel;

class IPWhitelistFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $requestIP = $request->getIPAddress();
        
        // Cek IP di database whitelist
        $whitelistModel = model(WhitelistModel::class);
        
        if (!$whitelistModel->isIPWhitelisted($requestIP)) {
            // Log akses yang ditolak
            log_message('warning', "[IP_WHITELIST] Akses ditolak dari IP: {$requestIP} ke URL: " . $request->getUri());
            
            // Redirect ke halaman access denied
            $session = \Config\Services::session();
            $session->setFlashdata('error', "Akses ditolak! IP Anda ({$requestIP}) tidak terdaftar dalam whitelist. Silakan hubungi administrator.");
            
            return redirect()->to('/access-denied');
        }
        
        // Log akses yang berhasil
        log_message('info', "[IP_WHITELIST] Akses diterima dari IP: {$requestIP} ke URL: " . $request->getUri());
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Tidak perlu melakukan apa-apa setelah request
    }
}