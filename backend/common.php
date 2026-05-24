<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header_remove('X-Powered-By');
ini_set('display_errors', '0');
ini_set('log_errors', '1');

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function ensureSchema(): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $pdo = db();

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS applications (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            fullname VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            company VARCHAR(150) NOT NULL DEFAULT '',
            message TEXT NOT NULL,
            project_name VARCHAR(150) NOT NULL DEFAULT '',
            region_name VARCHAR(120) NOT NULL DEFAULT '',
            city_name VARCHAR(120) NOT NULL DEFAULT '',
            package_name VARCHAR(80) NOT NULL DEFAULT '',
            extras_text TEXT NOT NULL,
            estimate VARCHAR(80) NOT NULL DEFAULT '',
            ip_address VARCHAR(45) NOT NULL DEFAULT '',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            login VARCHAR(64) NOT NULL UNIQUE,
            pass_hash CHAR(32) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $check = $pdo->prepare('SELECT id FROM admin_users WHERE login = ? LIMIT 1');
    $check->execute([ADMIN_BOOTSTRAP_LOGIN]);

    if (!$check->fetchColumn()) {
        $insert = $pdo->prepare('INSERT INTO admin_users (login, pass_hash) VALUES (?, ?)');
        $insert->execute([ADMIN_BOOTSTRAP_LOGIN, ADMIN_BOOTSTRAP_PASSWORD_MD5]);
    }

    $initialized = true;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool
{
    return is_string($token) && $token !== '' && hash_equals(csrfToken(), $token);
}

function getJsonPayload(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);

    return is_array($data) ? $data : [];
}

function normalizeString(mixed $value): string
{
    return trim((string) $value);
}

function validateApplication(array $data): array
{
    $errors = [];

    $fullname = normalizeString($data['fullname'] ?? '');
    $email = normalizeString($data['email'] ?? '');
    $phone = normalizeString($data['phone'] ?? '');
    $consent = $data['consent'] ?? null;

    if ($fullname === '' || !preg_match('/^[А-Яа-яЁёA-Za-z\s-]{5,150}$/u', $fullname)) {
        $errors['fullname'] = 'ФИО заполнено некорректно.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email заполнен некорректно.';
    }

    if ($phone === '' || !preg_match('/^\+?[\d\s()\-]{10,20}$/', $phone)) {
        $errors['phone'] = 'Телефон заполнен некорректно.';
    }

    if (empty($consent)) {
        $errors['consent'] = 'Нужно подтвердить согласие на обработку данных.';
    }

    return $errors;
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function authAdmin(): void
{
    ensureSchema();

    $login = $_SERVER['PHP_AUTH_USER'] ?? '';
    $password = $_SERVER['PHP_AUTH_PW'] ?? '';

    if ($login === '' || $password === '') {
        header('HTTP/1.1 401 Unauthorized');
        header('WWW-Authenticate: Basic realm="HanJip Admin"');
        header('Content-Type: text/html; charset=UTF-8');
        echo '<h1>401 Требуется авторизация</h1>';
        exit;
    }

    $stmt = db()->prepare('SELECT pass_hash FROM admin_users WHERE login = ? LIMIT 1');
    $stmt->execute([$login]);
    $row = $stmt->fetch();

    if (!$row || md5($password) !== (string) $row['pass_hash']) {
        header('HTTP/1.1 401 Unauthorized');
        header('WWW-Authenticate: Basic realm="HanJip Admin"');
        header('Content-Type: text/html; charset=UTF-8');
        echo '<h1>401 Неверный логин или пароль</h1>';
        exit;
    }
}
