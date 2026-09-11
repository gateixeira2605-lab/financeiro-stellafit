<?php
declare(strict_types=1);

final class AuthController extends BaseController
{
    public function login(): void
    {
        if (!empty($_SESSION['user_id'])) redirect('dashboard');
        $error = null;
        if (is_post()) {
            verify_csrf();
            $stmt = db()->prepare('SELECT id, name, username, password FROM users WHERE username = ? AND active = 1 LIMIT 1');
            $stmt->execute([trim((string) ($_POST['username'] ?? ''))]);
            $user = $stmt->fetch();
            $password = (string) ($_POST['password'] ?? '');
            $legacyOk = $user && str_starts_with($user['password'], '{SHA256}') && hash_equals(substr($user['password'], 8), hash('sha256', $password));
            if ($user && ($legacyOk || password_verify($password, $user['password']))) {
                if ($legacyOk) db()->prepare('UPDATE users SET password=? WHERE id=?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['user_name'] = $user['name'];
                redirect('dashboard');
            }
            $error = 'Usuário ou senha inválidos.';
        }
        $this->renderPublic('auth/login', compact('error'));
    }

    public function logout(): void
    {
        if (is_post()) verify_csrf();
        $_SESSION = [];
        session_destroy();
        header('Location: ' . url('login'));
        exit;
    }
}
