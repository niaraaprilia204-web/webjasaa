<?php

require_once __DIR__ . '/BaseService.php';
require_once __DIR__ . '/../Models/ServiceCategory.php';
require_once __DIR__ . '/../Models/Service.php';

/**
 * Catalog & Master Data Management Service
 */
class CatalogService extends BaseService
{
    private $categoryModel;
    private $serviceModel;

    public function __construct()
    {
        $this->categoryModel = new ServiceCategory();
        $this->serviceModel = new Service();
    }

    // Helper: Create URL friendly slug
    private function createSlug($string)
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string)));
        return rtrim($slug, '-');
    }

    // ==========================================
    // SERVICE CATEGORIES METHODS
    // ==========================================

    public function getAllCategories()
    {
        return $this->categoryModel->all();
    }

    public function getCategoryById($id)
    {
        return $this->categoryModel->find($id);
    }

    public function storeCategory($data)
    {
        $name = trim(htmlspecialchars($data['name'] ?? ''));
        $description = trim(htmlspecialchars($data['description'] ?? ''));
        $icon = trim(htmlspecialchars($data['icon'] ?? 'bi-tools'));

        if (empty($name) || strlen($name) < 3) {
            return $this->response(false, 'Nama kategori minimal 3 karakter.');
        }

        $slug = $this->createSlug($name);
        $existing = $this->categoryModel->where('slug', $slug);
        if (!empty($existing)) {
            return $this->response(false, 'Kategori dengan nama serupa sudah ada.');
        }

        $success = $this->categoryModel->create([
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description,
            'icon'        => $icon
        ]);

        if (!$success) {
            return $this->response(false, 'Gagal menyimpan kategori baru.');
        }

        return $this->response(true, 'Kategori baru berhasil ditambahkan!');
    }

    public function updateCategory($id, $data)
    {
        $name = trim(htmlspecialchars($data['name'] ?? ''));
        $description = trim(htmlspecialchars($data['description'] ?? ''));
        $icon = trim(htmlspecialchars($data['icon'] ?? 'bi-tools'));

        if (empty($name) || strlen($name) < 3) {
            return $this->response(false, 'Nama kategori minimal 3 karakter.');
        }

        $slug = $this->createSlug($name);

        $success = $this->categoryModel->update($id, [
            'name'        => $name,
            'slug'        => $slug,
            'description' => $description,
            'icon'        => $icon
        ]);

        if (!$success) {
            return $this->response(false, 'Gagal memperbarui kategori.');
        }

        return $this->response(true, 'Kategori berhasil diperbarui!');
    }

    public function deleteCategory($id)
    {
        $category = $this->categoryModel->find($id);
        if (!$category) {
            return $this->response(false, 'Kategori tidak ditemukan.');
        }

        // Check if category has associated services
        $associatedServices = $this->categoryModel->services($id);
        if (!empty($associatedServices)) {
            return $this->response(false, 'Kategori tidak dapat dihapus karena masih memiliki layanan terkait.');
        }

        $success = $this->categoryModel->delete($id);
        if (!$success) {
            return $this->response(false, 'Gagal menghapus kategori.');
        }

        return $this->response(true, 'Kategori berhasil dihapus!');
    }

    // ==========================================
    // SERVICES MASTER DATA METHODS
    // ==========================================

    public function getActiveServices($search = '', $categoryId = null)
    {
        $db = new Database();
        if (!$db->isConnected()) return [];

        $sql = "SELECT s.*, sc.name as category_name, sc.icon as category_icon FROM services s JOIN service_categories sc ON s.category_id = sc.id WHERE s.is_active = 1";
        
        if (!empty($search)) {
            $sql .= " AND (s.title LIKE :search OR s.description LIKE :search)";
        }
        if (!empty($categoryId)) {
            $sql .= " AND s.category_id = :cid";
        }

        $sql .= " ORDER BY s.id DESC";

        $db->query($sql);
        if (!empty($search)) {
            $db->bind(':search', '%' . $search . '%');
        }
        if (!empty($categoryId)) {
            $db->bind(':cid', $categoryId);
        }

        return $db->resultSet();
    }

    public function getFilteredServices($search = '', $categoryId = null, $status = null, $page = 1, $perPage = 10)
    {
        $db = new Database();
        if (!$db->isConnected()) {
            return [
                'services' => [],
                'total'    => 0,
                'pages'    => 1,
                'page'     => 1
            ];
        }

        $whereClause = " WHERE 1=1";
        if (!empty($search)) {
            $whereClause .= " AND (s.title LIKE :search OR s.description LIKE :search)";
        }
        if (!empty($categoryId)) {
            $whereClause .= " AND s.category_id = :cid";
        }
        if ($status !== null && $status !== '') {
            $whereClause .= " AND s.is_active = :status";
        }

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM services s" . $whereClause;
        $db->query($countSql);
        if (!empty($search)) $db->bind(':search', '%' . $search . '%');
        if (!empty($categoryId)) $db->bind(':cid', $categoryId);
        if ($status !== null && $status !== '') $db->bind(':status', (int)$status);
        
        $totalRes = $db->single();
        $total = $totalRes ? (int)$totalRes['total'] : 0;
        $pages = ceil($total / $perPage);
        if ($pages < 1) $pages = 1;
        if ($page < 1) $page = 1;
        if ($page > $pages) $page = $pages;

        $offset = ($page - 1) * $perPage;

        // Fetch Paginated
        $sql = "SELECT s.*, sc.name as category_name, sc.icon as category_icon FROM services s JOIN service_categories sc ON s.category_id = sc.id" . $whereClause . " ORDER BY s.id DESC LIMIT {$perPage} OFFSET {$offset}";
        $db->query($sql);
        if (!empty($search)) $db->bind(':search', '%' . $search . '%');
        if (!empty($categoryId)) $db->bind(':cid', $categoryId);
        if ($status !== null && $status !== '') $db->bind(':status', (int)$status);

        $services = $db->resultSet();

        return [
            'services' => $services,
            'total'    => $total,
            'pages'    => $pages,
            'page'     => $page
        ];
    }

    public function storeService($data)
    {
        $title = trim(htmlspecialchars($data['title'] ?? ''));
        $categoryId = (int)($data['category_id'] ?? 0);
        $description = trim(htmlspecialchars($data['description'] ?? ''));
        $estimatedDuration = trim(htmlspecialchars($data['estimated_duration'] ?? ''));
        $priceRange = trim(htmlspecialchars($data['price_range'] ?? ''));
        $isActive = isset($data['is_active']) ? 1 : 0;

        if (empty($title) || strlen($title) < 3) {
            return $this->response(false, 'Nama layanan minimal 3 karakter.');
        }

        if (empty($categoryId)) {
            return $this->response(false, 'Pilih kategori layanan.');
        }

        $slug = $this->createSlug($title);
        $existing = $this->serviceModel->where('slug', $slug);
        if (!empty($existing)) {
            $slug .= '-' . time();
        }

        $success = $this->serviceModel->create([
            'category_id'        => $categoryId,
            'title'              => $title,
            'slug'               => $slug,
            'description'        => $description,
            'estimated_duration' => $estimatedDuration,
            'price_range'        => $priceRange,
            'is_active'          => $isActive
        ]);

        if (!$success) {
            return $this->response(false, 'Gagal menambahkan layanan baru.');
        }

        return $this->response(true, 'Layanan baru berhasil ditambahkan!');
    }

    public function updateService($id, $data)
    {
        $title = trim(htmlspecialchars($data['title'] ?? ''));
        $categoryId = (int)($data['category_id'] ?? 0);
        $description = trim(htmlspecialchars($data['description'] ?? ''));
        $estimatedDuration = trim(htmlspecialchars($data['estimated_duration'] ?? ''));
        $priceRange = trim(htmlspecialchars($data['price_range'] ?? ''));
        $isActive = isset($data['is_active']) ? 1 : 0;

        if (empty($title) || strlen($title) < 3) {
            return $this->response(false, 'Nama layanan minimal 3 karakter.');
        }

        if (empty($categoryId)) {
            return $this->response(false, 'Pilih kategori layanan.');
        }

        $slug = $this->createSlug($title);

        $success = $this->serviceModel->update($id, [
            'category_id'        => $categoryId,
            'title'              => $title,
            'slug'               => $slug,
            'description'        => $description,
            'estimated_duration' => $estimatedDuration,
            'price_range'        => $priceRange,
            'is_active'          => $isActive
        ]);

        if (!$success) {
            return $this->response(false, 'Gagal memperbarui data layanan.');
        }

        return $this->response(true, 'Data layanan berhasil diperbarui!');
    }

    public function toggleServiceStatus($id)
    {
        $service = $this->serviceModel->find($id);
        if (!$service) {
            return $this->response(false, 'Layanan tidak ditemukan.');
        }

        $newStatus = $service['is_active'] ? 0 : 1;
        $success = $this->serviceModel->update($id, ['is_active' => $newStatus]);

        if (!$success) {
            return $this->response(false, 'Gagal mengubah status layanan.');
        }

        $statusText = $newStatus ? 'diaktifkan' : 'dinonaktifkan';
        return $this->response(true, "Layanan '{$service['title']}' berhasil {$statusText}.");
    }

    public function deleteService($id)
    {
        $service = $this->serviceModel->find($id);
        if (!$service) {
            return $this->response(false, 'Layanan tidak ditemukan.');
        }

        $success = $this->serviceModel->delete($id);
        if (!$success) {
            return $this->response(false, 'Gagal menghapus layanan.');
        }

        return $this->response(true, 'Layanan berhasil dihapus dari sistem!');
    }
}
