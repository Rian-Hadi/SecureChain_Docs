<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\JWTLibrary;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Load cookie helper untuk delete_cookie()
        helper('cookie');
        
        $session = session();

        // Check if user is logged in
        if (!$session->has('isLoggedIn')) {
            // Try to validate JWT from cookie
            $jwtToken = $request->getCookie('jwt_token');
            
            if ($jwtToken) {
                $jwt = new JWTLibrary();
                $userData = $jwt->verifyToken($jwtToken);
                
                if ($userData) {
                    // Restore session from JWT
                    $sessionData = [
                        'isLoggedIn' => true,
                        'user_id'    => $userData['user_id'],
                        'username'   => $userData['username'],
                        'email'      => $userData['email'],
                        'role'       => $userData['role'],
                        'jwt_token'  => $jwtToken,
                        'login_time' => time()
                    ];
                    $session->set($sessionData);
                    
                    log_message('info', "[AUTH] Session restored from JWT for user: {$userData['username']}");
                    return;
                }
            }

            // Not authenticated, redirect to login
            log_message('warning', "[AUTH] Unauthenticated access attempt to: " . $request->getUri());
            
            $session->setFlashdata('error', 'Anda harus login terlebih dahulu!');
            return redirect()->to('/auth/login');
        }

        // Check if role is specified in arguments
        if (!empty($arguments)) {
            $userRole = $session->get('role');
            
            if (!in_array($userRole, $arguments)) {
                log_message('warning', "[AUTH] Unauthorized access attempt by {$session->get('username')} (role: {$userRole}) to: " . $request->getUri());
                
                $session->setFlashdata('error', 'Anda tidak memiliki akses ke halaman ini!');
                return redirect()->to('/auth/access-denied');
            }
        }

        // Validate JWT token if exists
        $jwtToken = $session->get('jwt_token');
        if ($jwtToken) {
            $jwt = new JWTLibrary();
            $userData = $jwt->verifyToken($jwtToken);
            
            if (!$userData) {
                // Token expired or invalid
                log_message('warning', "[AUTH] Invalid or expired JWT token for user: {$session->get('username')}");
                
                $session->destroy();
                delete_cookie('jwt_token');
                
                $session->setFlashdata('error', 'Sesi Anda telah berakhir. Silakan login kembali!');
                return redirect()->to('/auth/login');
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing to do after
    }
}
