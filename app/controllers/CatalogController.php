<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once __DIR__ . '/ApiController.php';

/** Public, read-only storefront endpoints (no login needed). */
class CatalogController extends ApiController
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model('ProductModel');
    }

    public function index()
    {
        $this->api->require_method('GET');
        $this->api->require_jwt();
        $this->api->rate_limit('catalog_' . ($_SERVER['REMOTE_ADDR'] ?? 'x'), 120, 60);
        $q = trim((string) ($_GET['q'] ?? ''));
        $category = trim((string) ($_GET['category'] ?? ''));
        $rows = $this->ProductModel->search($q, $category);
        $this->api->respond(['data' => array_map('format_product', $rows), 'count' => count($rows)]);
    }

    public function categories()
    {
        $this->api->require_method('GET');
        $this->api->require_jwt();
        $this->api->respond(['data' => $this->ProductModel->categories()]);
    }
}
