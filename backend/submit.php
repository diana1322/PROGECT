<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
ensureSchema();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['message' => 'Разрешён только POST-запрос.'], 405);
}

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verifyCsrfToken($csrf)) {
    jsonResponse(['message' => 'Ошибка безопасности: неверный CSRF-токен.'], 403);
}

$data = getJsonPayload();
$errors = validateApplication($data);

if ($errors) {
    jsonResponse([
        'message' => 'Форма заполнена с ошибками.',
        'errors' => $errors,
    ], 422);
}

$stmt = db()->prepare(
    'INSERT INTO applications (fullname, email, phone, company, message, project_name, region_name, city_name, package_name, extras_text, estimate, ip_address)
     VALUES (:fullname, :email, :phone, :company, :message, :project_name, :region_name, :city_name, :package_name, :extras_text, :estimate, :ip_address)'
);

$extras = $data['extras'] ?? [];
if (is_array($extras)) {
    $extrasText = implode(', ', array_map('strval', $extras));
} else {
    $extrasText = normalizeString($extras);
}

$stmt->execute([
    ':fullname' => normalizeString($data['fullname'] ?? ''),
    ':email' => normalizeString($data['email'] ?? ''),
    ':phone' => normalizeString($data['phone'] ?? ''),
    ':company' => normalizeString($data['company'] ?? ''),
    ':message' => normalizeString($data['message'] ?? ''),
    ':project_name' => normalizeString($data['project'] ?? ''),
    ':region_name' => normalizeString($data['region'] ?? ''),
    ':city_name' => normalizeString($data['city'] ?? ''),
    ':package_name' => normalizeString($data['package'] ?? ''),
    ':extras_text' => $extrasText,
    ':estimate' => normalizeString($data['estimate'] ?? ''),
    ':ip_address' => normalizeString($_SERVER['REMOTE_ADDR'] ?? ''),
]);

jsonResponse([
    'message' => 'Заявка успешно сохранена в базе данных учебного сервера. Менеджер свяжется с вами в ближайшее время.'
]);
