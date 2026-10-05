<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

require_once __DIR__ . '/ApiController.php';

class AuthController extends ApiController
{
    public function __construct()
    {
        parent::__construct();
        $this->call->database();
    }

    private function public_user($u)
    {
        return ['id' => (int) $u['id'], 'username' => $u['username'], 'email' => $u['email'], 'role' => $u['role']];
    }

    public function login()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit('login_' . ($_SERVER['REMOTE_ADDR'] ?? 'x'), 20, 60);

        $in = $this->input();
        $email = trim((string) ($in['email'] ?? ''));
        $username = trim((string) ($in['username'] ?? ''));
        $identifier = $email !== '' ? $email : $username;
        $identifier_column = $email !== '' ? 'email' : 'username';
        $password = (string) ($in['password'] ?? '');

        if ($identifier === '' || $password === '') {
            $this->fail_validation([
                'email'    => $identifier === '' ? 'Email or username is required' : null,
                'password' => $password === '' ? 'Password is required' : null,
            ]);
        }

        $user = $this->db->raw(
            "SELECT * FROM users WHERE {$identifier_column} = ? AND is_active = 1 LIMIT 1",
            [$identifier]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            $this->api->respond_error('Invalid email or password', 401);
        }

        $tokens = $this->api->issue_tokens([
            'id'     => $user['id'],
            'role'   => $user['role'],
            'scopes' => $user['role'] === 'admin' ? ['read', 'write', 'delete'] : ($user['role'] === 'moderator' ? ['read', 'write'] : ['read']),
        ]);

        $this->api->respond(['message' => 'Login successful', 'user' => $this->public_user($user), 'tokens' => $tokens]);
    }

    public function register()
    {
        $this->api->require_method('POST');
        $this->api->rate_limit('register_' . ($_SERVER['REMOTE_ADDR'] ?? 'x'), 5, 300);

        $in = $this->input();
        $username = is_string($in['username'] ?? null) ? trim($in['username']) : '';
        $email = is_string($in['email'] ?? null) ? strtolower(trim($in['email'])) : '';
        $password = is_string($in['password'] ?? null) ? $in['password'] : '';
        $errors = [];

        if ($username === '') {
            $errors['username'] = 'Username is required';
        } elseif (mb_strlen($username) > 100) {
            $errors['username'] = 'Username must be 100 characters or fewer';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email address is required';
        } elseif (mb_strlen($email) > 255) {
            $errors['email'] = 'Email must be 255 characters or fewer';
        }

        if (mb_strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        } elseif (mb_strlen($password) > 255) {
            $errors['password'] = 'Password must be 255 characters or fewer';
        }

        if ($errors) {
            $this->fail_validation($errors);
        }

        $existing = $this->db->raw(
            'SELECT username, email FROM users WHERE LOWER(email) = ? OR username = ? LIMIT 1',
            [$email, $username]
        )->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $this->api->respond_error(
                strcasecmp($existing['email'], $email) === 0
                    ? 'An account with this email already exists'
                    : 'This username is already taken',
                409
            );
        }

        $this->db->raw(
            'INSERT INTO users (username, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)',
            [$username, $email, password_hash($password, PASSWORD_DEFAULT), 'user']
        );
        $user = [
            'id'       => $this->db->last_id(),
            'username' => $username,
            'email'    => $email,
            'role'     => 'user',
        ];
        $tokens = $this->api->issue_tokens([
            'id'     => $user['id'],
            'role'   => $user['role'],
            'scopes' => ['read'],
        ]);

        $this->api->respond([
            'message' => 'Account created',
            'user'    => $this->public_user($user),
            'tokens'  => $tokens,
        ], 201);
    }

    public function refresh()
    {
        $this->api->require_method('POST');
        $in = $this->input();
        $token = (string) ($in['refresh_token'] ?? '');
        if ($token === '') {
            $this->api->respond_error('refresh_token is required', 400);
        }
        $this->api->refresh_access_token($token);
    }

    public function me()
    {
        $this->api->require_method('GET');
        $payload = $this->api->require_jwt();
        $user = $this->db->raw('SELECT id, username, email, role FROM users WHERE id = ? LIMIT 1', [$payload['sub']])->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $this->api->respond_error('Unauthorized', 401);
        }
        $this->api->respond(['user' => $this->public_user($user)]);
    }

    public function logout()
    {
        $this->api->require_method('POST');
        $in = $this->input();
        if (!empty($in['refresh_token'])) {
            $this->api->revoke_refresh_token((string) $in['refresh_token']);
        }
        $this->api->respond(['message' => 'Logged out']);
    }
}
