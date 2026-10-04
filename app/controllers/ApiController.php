<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once APP_DIR . 'helpers/api_helper.php';

/**
 * Base controller shared by every API endpoint:
 * loads the LavaLust API library and offers JSON input + validation helpers.
 */
class ApiController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
    }

    /** Raw JSON body (not HTML-escaped; React escapes on output). */
    protected function input()
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '[]', true);
        if (is_array($data)) return $data;
        if (!empty($_POST)) return $_POST;
        return [];
    }

    protected function fail_validation(array $errors)
    {
        $errors = array_filter($errors, fn($v) => $v !== null);
        $this->api->respond(['error' => 'Validation failed', 'errors' => $errors, 'status' => 422], 422);
    }

    /** API index */
    public function index()
    {
        $this->api->respond([
            'name'    => 'Velora Parts API',
            'status'  => 'ok',
            'version' => '1.0.0',
        ]);
    }
}
