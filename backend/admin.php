<?php

declare(strict_types=1);

require_once __DIR__ . '/common.php';
ensureSchema();
authAdmin();

$token = csrfToken();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Ошибка CSRF: неверный токен безопасности.');
    }

    $stmt = $pdo->prepare('DELETE FROM applications WHERE id = ?');
    $stmt->execute([(int) $_POST['delete_id']]);
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Ошибка CSRF: неверный токен безопасности.');
    }

    $stmt = $pdo->prepare('UPDATE applications SET fullname = ?, email = ?, phone = ?, company = ?, message = ? WHERE id = ?');
    $stmt->execute([
        normalizeString($_POST['fullname'] ?? ''),
        normalizeString($_POST['email'] ?? ''),
        normalizeString($_POST['phone'] ?? ''),
        normalizeString($_POST['company'] ?? ''),
        normalizeString($_POST['message'] ?? ''),
        (int) $_POST['edit_id'],
    ]);

    header('Location: admin.php');
    exit;
}

$statsProjects = $pdo->query('SELECT project_name, COUNT(*) AS total FROM applications GROUP BY project_name ORDER BY total DESC')->fetchAll();
$statsRegions = $pdo->query('SELECT region_name, COUNT(*) AS total FROM applications GROUP BY region_name ORDER BY total DESC')->fetchAll();
$applications = $pdo->query('SELECT * FROM applications ORDER BY created_at DESC, id DESC')->fetchAll();

$editing = null;
if (isset($_GET['edit'])) {
    $editStmt = $pdo->prepare('SELECT * FROM applications WHERE id = ? LIMIT 1');
    $editStmt->execute([(int) $_GET['edit']]);
    $editing = $editStmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Админ-панель 한집</title>
  <style>
    body{margin:0;font-family:Arial,sans-serif;background:#0b1720;color:#eaf0f6;padding:24px}
    .container{max-width:1240px;margin:0 auto}
    .box{background:#122330;border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:20px;margin-bottom:20px}
    h1,h2,h3{margin-top:0}
    p,small{color:#b7c5d3}
    .grid{display:grid;gap:16px}
    .stats{grid-template-columns:repeat(auto-fit,minmax(260px,1fr))}
    table{width:100%;border-collapse:collapse;margin-top:16px}
    th,td{padding:12px;border:1px solid rgba(255,255,255,.08);vertical-align:top;text-align:left}
    th{background:#173042}
    input,textarea{width:100%;padding:10px 12px;border-radius:12px;border:1px solid rgba(255,255,255,.12);background:#0d1a24;color:#fff;box-sizing:border-box}
    textarea{min-height:120px;resize:vertical}
    .actions{display:flex;gap:8px;flex-wrap:wrap}
    .btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 14px;border-radius:12px;border:none;cursor:pointer;font-weight:700;text-decoration:none}
    .btn-primary{background:#d0a96f;color:#1a1610}
    .btn-danger{background:#a84545;color:#fff}
    .btn-secondary{background:#1d3444;color:#fff}
    code{color:#ffe3bb}
  </style>
</head>
<body>
  <div class="container">
    <div class="box">
      <h1>Админ-панель проекта «한집»</h1>
      <p>Доступ к панели защищён через HTTP Basic Auth. Логин и пароль администратора сохранены в БД и дополнительно продублированы в файле <code>backend/ADMIN_ACCESS.txt</code>.</p>
      <p><a href="../index.html" class="btn btn-secondary">Вернуться на сайт</a></p>
    </div>

    <div class="grid stats">
      <div class="box">
        <h2>Статистика по проектам</h2>
        <table>
          <tr><th>Проект</th><th>Количество заявок</th></tr>
          <?php foreach ($statsProjects as $row): ?>
            <tr>
              <td><?= e((string) ($row['project_name'] ?: 'Не указан')) ?></td>
              <td><?= (int) $row['total'] ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>

      <div class="box">
        <h2>Статистика по регионам</h2>
        <table>
          <tr><th>Регион</th><th>Количество заявок</th></tr>
          <?php foreach ($statsRegions as $row): ?>
            <tr>
              <td><?= e((string) ($row['region_name'] ?: 'Не указан')) ?></td>
              <td><?= (int) $row['total'] ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>

    <?php if ($editing): ?>
      <div class="box">
        <h2>Редактирование заявки №<?= (int) $editing['id'] ?></h2>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
          <input type="hidden" name="edit_id" value="<?= (int) $editing['id'] ?>">
          <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(240px,1fr));margin-bottom:12px;">
            <label>ФИО <input type="text" name="fullname" value="<?= e((string) $editing['fullname']) ?>" required></label>
            <label>Email <input type="email" name="email" value="<?= e((string) $editing['email']) ?>" required></label>
            <label>Телефон <input type="text" name="phone" value="<?= e((string) $editing['phone']) ?>" required></label>
            <label>Организация <input type="text" name="company" value="<?= e((string) $editing['company']) ?>"></label>
          </div>
          <label>Сообщение <textarea name="message"><?= e((string) $editing['message']) ?></textarea></label>
          <div class="actions" style="margin-top:12px;">
            <button class="btn btn-primary" type="submit">Сохранить изменения</button>
            <a class="btn btn-secondary" href="admin.php">Отмена</a>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <div class="box">
      <h2>Все заявки</h2>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Дата</th>
            <th>Клиент</th>
            <th>Контакты</th>
            <th>Проект</th>
            <th>Локация</th>
            <th>Пакет / доп. опции</th>
            <th>Смета</th>
            <th>Сообщение</th>
            <th>Действия</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($applications as $application): ?>
            <tr>
              <td><?= (int) $application['id'] ?></td>
              <td><?= e((string) $application['created_at']) ?></td>
              <td>
                <strong><?= e((string) $application['fullname']) ?></strong><br>
                <small><?= e((string) $application['company']) ?></small>
              </td>
              <td>
                <?= e((string) $application['email']) ?><br>
                <?= e((string) $application['phone']) ?>
              </td>
              <td><?= e((string) $application['project_name']) ?></td>
              <td><?= e(trim((string) $application['region_name'] . ' / ' . $application['city_name'], ' /')) ?></td>
              <td>
                <?= e((string) $application['package_name']) ?><br>
                <small><?= e((string) $application['extras_text']) ?></small>
              </td>
              <td><?= e((string) $application['estimate']) ?></td>
              <td><?= nl2br(e((string) $application['message'])) ?></td>
              <td>
                <div class="actions">
                  <a class="btn btn-secondary" href="admin.php?edit=<?= (int) $application['id'] ?>">Редактировать</a>
                  <form method="post" onsubmit="return confirm('Удалить заявку?');">
                    <input type="hidden" name="csrf_token" value="<?= e($token) ?>">
                    <input type="hidden" name="delete_id" value="<?= (int) $application['id'] ?>">
                    <button class="btn btn-danger" type="submit">Удалить</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
