<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Middleware/RoleMiddleware.php';
require_once __DIR__ . '/../Services/CatalogService.php';

/**
 * Service Categories Master Data Controller (Operator Only)
 */
class CategoryController extends BaseController
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
        $categories = $this->catalogService->getAllCategories();

        $data = [
            'title'      => 'Kelola Kategori Layanan - TKJ Service Hub',
            'activeMenu' => 'categories_manage',
            'categories' => $categories
        ];

        $this->renderLayout('operator/categories/index', $data);
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASEURL . '/operator/categories');
            exit;
        }

        $result = $this->catalogService->storeCategory($_POST);

        if (!$result['success']) {
            Flasher::setMessage($result['message'], 'Gagal', 'danger');
        } else {
            Flasher::setMessage($result['message'], 'Sukses', 'success');
        }

        header('Location: ' . BASEURL . '/operator/categories');
        exit;
    }

    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASEURL . '/operator/categories');
            exit;
        }

        $result = $this->catalogService->updateCategory($id, $_POST);

        if (!$result['success']) {
            Flasher::setMessage($result['message'], 'Gagal', 'danger');
        } else {
            Flasher::setMessage($result['message'], 'Sukses', 'success');
        }

        header('Location: ' . BASEURL . '/operator/categories');
        exit;
    }

    public function delete($id)
    {
        $result = $this->catalogService->deleteCategory($id);

        if (!$result['success']) {
            Flasher::setMessage($result['message'], 'Gagal', 'danger');
        } else {
            Flasher::setMessage($result['message'], 'Sukses', 'success');
        }

        header('Location: ' . BASEURL . '/operator/categories');
        exit;
    }
}
