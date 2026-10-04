<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

if (!function_exists('format_product')) {
    /** Cast DB strings to proper JSON types. */
    function format_product($r)
    {
        return [
            'id'           => (int) $r['id'],
            'product_name' => $r['product_name'],
            'description'  => $r['description'],
            'price'        => (float) $r['price'],
            'quantity'     => (int) $r['quantity'],
            'category'     => $r['category'],
            'image_url'    => $r['image_url'],
            'created_at'   => $r['created_at'],
        ];
    }
}
