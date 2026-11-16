<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Services/TechnicianService.php';

class TechnicianController extends BaseController
{
    private $technicianService;

    public function __construct()
    {
        parent::__construct();
        // Guarded strictly for 'technician' role
        $roleGuard = new RoleMiddleware(['technician']);
        $roleGuard->handle();

        $this->technicianService = new TechnicianService();
    }

    public function dashboard()
    {
        $userId = (int) $_SESSION['user']['id'];
        $profile = $this->technicianService->getProfileByUserId($userId);
        $assignedOrders = [];
        $activeOrders = [];
        $history = [];
        $stats = ['total' => 0, 'active' => 0, 'done' => 0];

        if (!empty($profile)) {
            $assignedOrders = $this->technicianService->getWorkOrders((int) $profile['id']);
            $stats = $this->technicianService->getStats((int) $profile['id']);
            foreach ($assignedOrders as $workOrder) {
                if ($workOrder['status'] === 'done') {
                    $history[] = $workOrder;
                } else {
                    $activeOrders[] = $workOrder;
                }
            }
        }

        $data = [
            'title'          => 'Dashboard Teknisi - TKJ Service Hub',
            'activeMenu'     => 'dashboard_technician',
            'user'           => $_SESSION['user'],
            'profile'        => $profile,
            'assignedOrders' => $assignedOrders,
            'activeOrders'   => $activeOrders,
            'history'        => $history,
            'stats'          => $stats,
            'csrfToken'      => Csrf::token('technician'),
        ];

        $this->renderLayout('technician/dashboard', $data);
    }

    public function jobDetail($id = null)
    {
        $profile = $this->requireProfile();
        $result = $this->technicianService->getWorkOrderDetail((int) $id, (int) $profile['id']);
        if (!$result['success']) {
            Flasher::setMessage($result['message'], 'Akses Ditolak', 'danger');
            header('Location: ' . BASEURL . '/technician/dashboard');
            exit;
        }

        $data = [
            'title'      => 'Detail Pekerjaan - TKJ Service Hub',
            'activeMenu' => 'dashboard_technician',
            'user'       => $_SESSION['user'],
            'profile'    => $profile,
            'workOrder'  => $result['data']['workOrder'],
            'files'      => $result['data']['files'],
            'csrfToken'  => Csrf::token('technician'),
        ];

        $this->renderLayout('technician/job_detail', $data);
    }

    public function jobUpdate()
    {
        $profile = $this->requireProfile();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate('technician')) {
            Flasher::setMessage('Sesi keamanan tidak valid.', 'Gagal', 'danger');
            header('Location: ' . BASEURL . '/technician/dashboard');
            exit;
        }

        $workOrderId = (int) ($_POST['work_order_id'] ?? 0);
        $result = $this->technicianService->updateJob($workOrderId, (int) $profile['id'], $_POST);
        Flasher::setMessage($result['message'], $result['success'] ? 'Berhasil' : 'Gagal', $result['success'] ? 'success' : 'danger');

        $target = $workOrderId > 0 ? '/technician/jobs/detail/' . $workOrderId : '/technician/dashboard';
        header('Location: ' . BASEURL . $target);
        exit;
    }

    public function jobUpload()
    {
        $profile = $this->requireProfile();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Csrf::validate('technician')) {
            Flasher::setMessage('Sesi keamanan tidak valid.', 'Gagal', 'danger');
            header('Location: ' . BASEURL . '/technician/dashboard');
            exit;
        }

        $workOrderId = (int) ($_POST['work_order_id'] ?? 0);
        $type = (string) ($_POST['file_type'] ?? 'before');
        $caption = (string) ($_POST['caption'] ?? '');
        $result = $this->technicianService->uploadDocumentation(
            $workOrderId,
            (int) $profile['id'],
            $_FILES['documentation'] ?? null,
            $type,
            $caption
        );

        Flasher::setMessage($result['message'], $result['success'] ? 'Berhasil' : 'Gagal', $result['success'] ? 'success' : 'danger');
        $target = $workOrderId > 0 ? '/technician/jobs/detail/' . $workOrderId : '/technician/dashboard';
        header('Location: ' . BASEURL . $target);
        exit;
    }

    private function requireProfile()
    {
        $profile = $this->technicianService->getProfileByUserId((int) $_SESSION['user']['id']);
        if (empty($profile)) {
            Flasher::setMessage('Profil teknisi tidak ditemukan. Hubungi operator/administrator.', 'Gagal', 'danger');
            header('Location: ' . BASEURL . '/technician/dashboard');
            exit;
        }
        return $profile;
    }
}
