<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductModel extends Model
{
    protected $table = 'products';
    protected $primary_key = 'id';

    public function __construct()
    {
        parent::__construct();
        $this->call->database();
    }

    public function search($q = '', $category = '')
    {
        $sql    = 'SELECT * FROM products WHERE 1=1';
        $params = [];

        if ($q !== '') {
            $sql .= ' AND (product_name LIKE ? OR description LIKE ? OR category LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }
        if ($category !== '' && strtolower($category) !== 'all') {
            $sql .= ' AND category = ?';
            $params[] = $category;
        }
        $sql .= ' ORDER BY id DESC';

        return $this->db->raw($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function categories()
    {
        return $this->db->raw('SELECT DISTINCT category FROM products ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
    }

    public function find_product($id)
    {
        return $this->db->raw('SELECT * FROM products WHERE id = ? LIMIT 1', [$id])->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create_product(array $d)
    {
        $this->db->raw(
            'INSERT INTO products (product_name, description, price, quantity, category, image_url) VALUES (?, ?, ?, ?, ?, ?)',
            [$d['product_name'], $d['description'], $d['price'], $d['quantity'], $d['category'], $d['image_url']]
        );
        return (int) $this->db->last_id();
    }

    public function update_product($id, array $d)
    {
        $allowed = ['product_name', 'description', 'price', 'quantity', 'category', 'image_url'];
        $sets = [];
        $params = [];
        foreach ($d as $col => $val) {
            if (!in_array($col, $allowed, true)) continue;
            $sets[] = "`{$col}` = ?";
            $params[] = $val;
        }
        if (!$sets) return;
        $params[] = $id;
        $this->db->raw('UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }

    public function delete_product($id)
    {
        $this->db->raw('DELETE FROM products WHERE id = ?', [$id]);
    }
}
