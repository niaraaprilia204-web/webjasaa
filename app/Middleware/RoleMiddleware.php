<?php

require_once __DIR__ . '/BaseMiddleware.php';
require_once __DIR__ . '/AuthMiddleware.php';

/**
 * Role-Based Access Control (RBAC) Middleware
 */
class RoleMiddleware extends BaseMiddleware
{
    private $allowedRoles;

    public function __construct(array $allowedRoles)
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function handle()
    {
        // First ensure user is logged in
        $auth = new AuthMiddleware();
        $auth->handle();

        $userRole = $_SESSION['user']['role'] ?? '';

        if (!in_array($userRole, $this->allowedRoles)) {
            Flasher::setMessage('Anda tidak memiliki hak akses untuk membuka halaman tersebut.', 'Akses Ditolak', 'danger');
            
            // Redirect to appropriate dashboard based on user's actual role
            switch ($userRole) {
                case 'customer':
                    header('Location: ' . BASEURL . '/customer/dashboard');
                    break;
                case 'technician':
                    header('Location: ' . BASEURL . '/technician/dashboard');
                    break;
                case 'operator':
                    header('Location: ' . BASEURL . '/operator/dashboard');
                    break;
                case 'principal':
                    header('Location: ' . BASEURL . '/principal/dashboard');
                    break;
                case 'admin':
                    header('Location: ' . BASEURL . '/admin/dashboard');
                    break;
                default:
                    header('Location: ' . BASEURL);
                    break;
            }
            exit;
        }
    }
}
