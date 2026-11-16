<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../Services/SystemHealthService.php';

class Home extends BaseController
{
    private $healthService;

    public function __construct()
    {
        parent::__construct();
        $this->healthService = new SystemHealthService();
    }

    public function index()
    {
        $healthData = $this->healthService->getSystemHealthOverview();

        $data = [
            'title'        => 'Beranda - TKJ Service Hub',
            'activeMenu'   => 'home',
            'healthData'   => $healthData['data']
        ];

        $this->renderLayout('home/index', $data);
    }

    public function about()
    {
        $data = [
            'title'      => 'Tentang Kami - TKJ Service Hub',
            'activeMenu' => 'about'
        ];

        $this->renderLayout('home/about', $data);
    }

    public function services()
    {
        require_once __DIR__ . '/../Services/CatalogService.php';
        $catalogService = new CatalogService();

        $search = $_GET['search'] ?? '';
        $categoryId = isset($_GET['category']) && $_GET['category'] !== '' ? (int)$_GET['category'] : null;

        $services = $catalogService->getActiveServices($search, $categoryId);
        $categories = $catalogService->getAllCategories();

        $data = [
            'title'        => 'Katalog Layanan Teknis - TKJ Service Hub',
            'activeMenu'   => 'services',
            'services'     => $services,
            'categories'   => $categories,
            'currSearch'   => $search,
            'currCategory' => $categoryId
        ];

        $this->renderLayout('home/services', $data);
    }

    public function serviceDetail($id = null)
    {
        if (empty($id)) {
            header('Location: ' . BASEURL . '/services');
            exit;
        }

        require_once __DIR__ . '/../Services/CatalogService.php';
        $catalogService = new CatalogService();

        $db = new Database();
        $db->query("SELECT s.*, sc.name as category_name, sc.icon as category_icon, sc.description as category_desc FROM services s JOIN service_categories sc ON s.category_id = sc.id WHERE s.id = :id AND s.is_active = 1");
        $db->bind(':id', $id);
        $service = $db->single();

        if (!$service) {
            header('Location: ' . BASEURL . '/services');
            exit;
        }

        $data = [
            'title'      => $service['title'] . ' - TKJ Service Hub',
            'activeMenu' => 'services',
            'service'    => $service
        ];

        $this->renderLayout('home/service_detail', $data);
    }

    public function contact()
    {
        $data = [
            'title'      => 'Kontak - TKJ Service Hub',
            'activeMenu' => 'contact'
        ];

        $this->renderLayout('home/contact', $data);
    }
}
