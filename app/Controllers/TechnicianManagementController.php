<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Services/OperatorService.php';

/**
 * Technician Management Controller (Operator Only)
 */
class TechnicianManagementController extends BaseController
{
    private $operatorService;

    public function __construct()
    {
        parent::__construct();
        $roleGuard = new RoleMiddleware(['operator']);
        $roleGuard->handle();

        $this->operatorService = new OperatorService();
    }

    public function index()
    {
        $search = $_GET['search'] ?? '';
        $technicians = $this->operatorService->getTechniciansList($search);

        $data = [
            'title'       => 'Kelola Siswa Teknisi TKJ - TKJ Service Hub',
            'activeMenu'  => 'operator_technicians',
            'technicians' => $technicians,
            'search'      => $search
        ];

        $this->renderLayout('operator/technicians/index', $data);
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASEURL . '/operator/technicians');
            exit;
        }

        $result = $this->operatorService->storeTechnician($_POST);

        if (!$result['success']) {
            Flasher::setMessage($result['message'], 'Gagal', 'danger');
        } else {
            Flasher::setMessage($result['message'], 'Sukses', 'success');
        }

        header('Location: ' . BASEURL . '/operator/technicians');
        exit;
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASEURL . '/operator/technicians');
            exit;
        }

        $result = $this->operatorService->updateTechnician($id, $_POST);

        if (!$result['success']) {
            Flasher::setMessage($result['message'], 'Gagal', 'danger');
        } else {
            Flasher::setMessage($result['message'], 'Sukses', 'success');
        }

        header('Location: ' . BASEURL . '/operator/technicians');
        exit;
    }
}
