<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once __DIR__ . '/ApiController.php';

/** Admin-only user management. */
class UserController extends ApiController
{
    public function __construct()
    {
        parent::__construct();
        $this->call->database();
    }

    private function authorize_admin()
    {
        $payload = $this->api->require_jwt();
        if (($payload['role'] ?? '') !== 'admin') {
            $this->api->respond_error('Forbidden: administrator access required', 403);
        }
        return $payload;
    }

    private function public_user(array $user)
    {
        return [
            'id'         => (int) $user['id'],
            'username'   => $user['username'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'is_active'  => (int) $user['is_active'],
            'created_at' => $user['created_at'],
        ];
    }

    private function find_user($id)
    {
        return $this->db->raw(
            'SELECT id, username, email, role, is_active, created_at FROM users WHERE id = ? LIMIT 1',
            [$id]
        )->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function clean(array $in, $partial = FALSE)
    {
        $errors = [];
        $data = [];

        if (!$partial || array_key_exists('username', $in)) {
            $value = $in['username'] ?? null;
            if (!is_string($value) || trim($value) === '') {
                $errors['username'] = 'Username is required';
            } elseif (mb_strlen(trim($value)) > 100) {
                $errors['username'] = 'Username must be 100 characters or fewer';
            } else {
                $data['username'] = trim($value);
            }
        }

        if (!$partial || array_key_exists('email', $in)) {
            $value = $in['email'] ?? null;
            if (!is_string($value) || trim($value) === '' || !filter_var(trim($value), FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'A valid email address is required';
            } elseif (mb_strlen(trim($value)) > 255) {
                $errors['email'] = 'Email must be 255 characters or fewer';
            } else {
                $data['email'] = trim($value);
            }
        }

        if (!$partial || array_key_exists('password', $in)) {
            $value = $in['password'] ?? null;
            if (!is_string($value) || mb_strlen($value) < 8) {
                $errors['password'] = 'Password must be at least 8 characters';
            } elseif (mb_strlen($value) > 255) {
                $errors['password'] = 'Password must be 255 characters or fewer';
            } else {
                $data['password'] = password_hash($value, PASSWORD_DEFAULT);
            }
        }

        if (!$partial || array_key_exists('role', $in)) {
            $value = $in['role'] ?? 'user';
            if (!is_string($value) || !in_array($value, ['admin', 'moderator', 'user'], true)) {
                $errors['role'] = 'Role must be admin, moderator, or user';
            } else {
                $data['role'] = $value;
            }
        }

        if (!$partial || array_key_exists('is_active', $in)) {
            $value = $in['is_active'] ?? 1;
            if (!in_array($value, [0, 1, '0', '1', false, true], true)) {
                $errors['is_active'] = 'Active status must be true or false';
            } else {
                $data['is_active'] = (int) (bool) $value;
            }
        }

        if ($partial && !$data && !$errors) {
            $errors['user'] = 'At least one user field must be provided';
        }

        if ($errors) {
            $this->fail_validation($errors);
        }

        return $data;
    }

    private function assert_unique_account($username, $email, $except_id = null)
    {
        if ($except_id === null) {
            $existing = $this->db->raw(
                'SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1',
                [$username, $email]
            )->fetch(PDO::FETCH_ASSOC);
        } else {
            $existing = $this->db->raw(
                'SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1',
                [$username, $email, $except_id]
            )->fetch(PDO::FETCH_ASSOC);
        }

        if ($existing) {
            $this->api->respond_error('A user with that username or email already exists', 409);
        }
    }

    private function protect_last_active_admin(array $current, array $changes = [])
    {
        $will_be_active_admin = ($changes['role'] ?? $current['role']) === 'admin'
            && (int) ($changes['is_active'] ?? $current['is_active']) === 1;

        if ($current['role'] !== 'admin' || (int) $current['is_active'] !== 1 || $will_be_active_admin) {
            return FALSE;
        }

        $this->db->transaction();
        $active_admin_ids = $this->db->raw(
            "SELECT id FROM users WHERE role = 'admin' AND is_active = 1 FOR UPDATE"
        )->fetchAll(PDO::FETCH_COLUMN);
        $remaining = count(array_filter($active_admin_ids, function ($id) use ($current) {
            return (int) $id !== (int) $current['id'];
        }));

        if ($remaining === 0) {
            $this->api->respond_error('The last active administrator cannot be deactivated, demoted, or deleted', 409);
        }
        return TRUE;
    }

    public function index()
    {
        $this->api->require_method('GET');
        $this->authorize_admin();
        $rows = $this->db->raw(
            'SELECT id, username, email, role, is_active, created_at FROM users ORDER BY id DESC'
        )->fetchAll(PDO::FETCH_ASSOC);

        $this->api->respond(['data' => array_map([$this, 'public_user'], $rows), 'count' => count($rows)]);
    }

    public function show($id)
    {
        $this->api->require_method('GET');
        $this->authorize_admin();
        $user = $this->find_user((int) $id);
        if (!$user) {
            $this->api->respond_error('User not found', 404);
        }
        $this->api->respond(['data' => $this->public_user($user)]);
    }

    public function store()
    {
        $this->api->require_method('POST');
        $this->authorize_admin();
        $data = $this->clean($this->input());
        $this->assert_unique_account($data['username'], $data['email']);

        $this->db->raw(
            'INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, ?, ?)',
            [$data['username'], $data['email'], $data['password'], $data['role'], $data['is_active']]
        );
        $user = $this->find_user((int) $this->db->last_id());

        $this->api->respond(['message' => 'User created', 'data' => $this->public_user($user)], 201);
    }

    public function update($id)
    {
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['PUT', 'PATCH'], true)) {
            $this->api->respond_error('Method Not Allowed', 405);
        }
        $admin = $this->authorize_admin();
        $id = (int) $id;
        $current = $this->find_user($id);
        if (!$current) {
            $this->api->respond_error('User not found', 404);
        }

        $data = $this->clean($this->input(), TRUE);
        $username = $data['username'] ?? $current['username'];
        $email = $data['email'] ?? $current['email'];
        $this->assert_unique_account($username, $email, $id);
        if ((int) $admin['sub'] === $id
            && (($data['role'] ?? $current['role']) !== 'admin' || (int) ($data['is_active'] ?? 1) !== 1)) {
            $this->api->respond_error('You cannot demote or deactivate your own administrator account', 409);
        }
        $admin_lock = $this->protect_last_active_admin($current, $data);

        $sets = [];
        $values = [];
        foreach ($data as $column => $value) {
            $sets[] = "`{$column}` = ?";
            $values[] = $value;
        }
        $sets[] = 'updated_at = CURRENT_TIMESTAMP';
        $values[] = $id;
        $this->db->raw('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?', $values);

        if (isset($data['password']) || (isset($data['is_active']) && $data['is_active'] === 0)) {
            $this->db->raw('DELETE FROM refresh_tokens WHERE user_id = ?', [$id]);
        }
        if ($admin_lock) {
            $this->db->commit();
        }

        $this->api->respond(['message' => 'User updated', 'data' => $this->public_user($this->find_user($id))]);
    }

    public function destroy($id)
    {
        $this->api->require_method('DELETE');
        $admin = $this->authorize_admin();
        $id = (int) $id;
        $current = $this->find_user($id);
        if (!$current) {
            $this->api->respond_error('User not found', 404);
        }
        if ((int) $admin['sub'] === $id) {
            $this->api->respond_error('You cannot delete your own administrator account', 409);
        }
        $admin_lock = $this->protect_last_active_admin($current);

        $this->db->raw('DELETE FROM refresh_tokens WHERE user_id = ?', [$id]);
        $this->db->raw('DELETE FROM users WHERE id = ?', [$id]);
        if ($admin_lock) {
            $this->db->commit();
        }
        $this->api->respond(['message' => 'User deleted']);
    }
}
