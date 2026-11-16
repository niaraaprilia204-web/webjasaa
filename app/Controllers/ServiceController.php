<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Services/CatalogService.php';

/**
 * Services Master Data Controller (Operator Only)
 */
class ServiceController extends BaseController
{
    private $catalogService;

    public function __construct()
    {
        parent::__construct();
        // Guarded for Operator role only
        $roleGuard = new RoleMiddleware(['operator']);
        $roleGuard->handle();

        $this->catalogService = new CatalogService();
    }

    public function index()
    {
        $search = $_GET['search'] ?? '';
        $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;
        $status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

        $filtered = $this->catalogService->getFilteredServices($search, $categoryId, $status, $page, 8);
        $categories = $this->catalogService->getAllCategories();

        $data = [
            'title'        => 'Kelola Katalog Layanan - TKJ Service Hub',
            'activeMenu'   => 'services_manage',
            'services'     => $filtered['services'],
            'pagination'   => [
                'total' => $filtered['total'],
                'pages' => $filtered['pages'],
                'page'  => $filtered['page']
            ],
            'categories'   => $categories,
            'filters'      => [
                'search'      => $search,
                'category_id' => $categoryId,
                'status'      => $status
            ]
        ];

        $this->renderLayout('operator/services/index', $data);
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASEURL . '/operator/services');
            exit;
        }

        $result = $this->catalogService->storeService($_POST);

        if (!$result['success']) {
            Flasher::setMessage($result['message'], 'Gagal', 'danger');
        } else {
            Flasher::setMessage($result['message'], 'Sukses', 'success');
        }

        header('Location: ' . BASEURL . '/operator/services');
        exit;
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASEURL . '/operator/services');
            exit;
        }

        $result = $this->catalogService->updateService($id, $_POST);

        if (!$result['success']) {
            Flasher::setMessage($result['message'], 'Gagal', 'danger');
        } else {
            Flasher::setMessage($result['message'], 'Sukses', 'success');
        }

        header('Location: ' . BASEURL . '/operator/services');
        exit;
    }

    public function toggle($id)
    {
        $result = $this->catalogService->toggleServiceStatus($id);

        if (!$result['success']) {
            Flasher::setMessage($result['message'], 'Gagal', 'danger');
        } else {
            Flasher::setMessage($result['message'], 'Sukses', 'info');
        }

        header('Location: ' . BASEURL . '/operator/services');
        exit;
    }

    public function delete($id)
    {
        $result = $this->catalogService->deleteService($id);

        if (!$result['success']) {
            Flasher::setMessage($result['message'], 'Gagal', 'danger');
        } else {
            Flasher::setMessage($result['message'], 'Sukses', 'success');
        }

        header('Location: ' . BASEURL . '/operator/services');
        exit;
    }
}
