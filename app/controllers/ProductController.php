<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once __DIR__ . '/ApiController.php';

/** Product CRUD — every action requires a valid access token. */
class ProductController extends ApiController
{
    public function __construct()
    {
        parent::__construct();
        $this->call->model('ProductModel');
    }

    private function authorize($scope = 'read')
    {
        $payload = $this->api->require_jwt();
        if (!in_array($scope, $payload['scopes'] ?? [], true)) {
            $this->api->respond_error('Forbidden: your account cannot ' . $scope . ' products', 403);
        }
        return $payload;
    }

    /** Validate input; $partial=TRUE for PATCH (only validate supplied keys). */
    private function clean(array $in, $partial = FALSE)
    {
        $e = [];
        $out = [];

        if (!$partial || array_key_exists('product_name', $in)) {
            $v = trim((string) ($in['product_name'] ?? ''));
            if ($v === '') $e['product_name'] = 'Product name is required';
            elseif (mb_strlen($v) > 100) $e['product_name'] = 'Product name must be 100 characters or fewer';
            else $out['product_name'] = $v;
        }
        if (!$partial || array_key_exists('description', $in)) {
            $out['description'] = trim((string) ($in['description'] ?? ''));
        }
        if (!$partial || array_key_exists('price', $in)) {
            $v = $in['price'] ?? '';
            if ($v === '' || !is_numeric($v)) $e['price'] = 'Price must be a number';
            elseif ((float) $v < 0) $e['price'] = 'Price cannot be negative';
            elseif ((float) $v > 99999999.99) $e['price'] = 'Price is too large';
            else $out['price'] = number_format((float) $v, 2, '.', '');
        }
        if (!$partial || array_key_exists('quantity', $in)) {
            $v = $in['quantity'] ?? '';
            if ($v === '' || !is_numeric($v) || floor((float) $v) != (float) $v) $e['quantity'] = 'Quantity must be a whole number';
            elseif ((int) $v < 0) $e['quantity'] = 'Quantity cannot be negative';
            else $out['quantity'] = (int) $v;
        }
        if (!$partial || array_key_exists('category', $in)) {
            $v = trim((string) ($in['category'] ?? ''));
            if ($v === '') $v = 'General';
            if (mb_strlen($v) > 60) $e['category'] = 'Category must be 60 characters or fewer';
            else $out['category'] = $v;
        }
        if (!$partial || array_key_exists('image_url', $in)) {
            $v = trim((string) ($in['image_url'] ?? ''));
            if ($v === '') $out['image_url'] = null;
            elseif (mb_strlen($v) > 255) $e['image_url'] = 'Image URL must be 255 characters or fewer';
            elseif (!preg_match('#^(https?://|/)[^\s<>"\']+$#i', $v)) $e['image_url'] = 'Image must be a link starting with http(s):// or /';
            else $out['image_url'] = $v;
        }

        if ($e) $this->fail_validation($e);
        return $out;
    }

    // GET /api/products
    public function index()
    {
        $this->api->require_method('GET');
        $this->authorize('read');
        $rows = $this->ProductModel->search(trim((string) ($_GET['q'] ?? '')), trim((string) ($_GET['category'] ?? '')));
        $this->api->respond(['data' => array_map('format_product', $rows), 'count' => count($rows)]);
    }

    // GET /api/products/{id}
    public function show($id)
    {
        $this->api->require_method('GET');
        $this->authorize('read');
        $row = $this->ProductModel->find_product((int) $id);
        if (!$row) $this->api->respond_error('Product not found', 404);
        $this->api->respond(['data' => format_product($row)]);
    }

    // POST /api/products
    public function store()
    {
        $this->api->require_method('POST');
        $this->authorize('write');
        $data = $this->clean($this->input());
        $id = $this->ProductModel->create_product($data);
        $this->api->respond(['message' => 'Product created', 'data' => format_product($this->ProductModel->find_product($id))], 201);
    }

    // PUT|PATCH /api/products/{id}
    public function update($id)
    {
        $this->authorize('write');
        $id = (int) $id;
        if (!$this->ProductModel->find_product($id)) $this->api->respond_error('Product not found', 404);
        $partial = ($_SERVER['REQUEST_METHOD'] ?? '') === 'PATCH';
        $data = $this->clean($this->input(), $partial);
        $this->ProductModel->update_product($id, $data);
        $this->api->respond(['message' => 'Product updated', 'data' => format_product($this->ProductModel->find_product($id))]);
    }

    // DELETE /api/products/{id}
    public function destroy($id)
    {
        $this->api->require_method('DELETE');
        $this->authorize('delete');
        $id = (int) $id;
        if (!$this->ProductModel->find_product($id)) $this->api->respond_error('Product not found', 404);
        $this->ProductModel->delete_product($id);
        $this->api->respond(['message' => 'Product deleted']);
    }
}
