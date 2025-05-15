<?php
require 'db.php';

// Создаем таблицу для администраторов, если ее нет
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            login VARCHAR(50) UNIQUE NOT NULL,
            pass_hash VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB
    ");

    // Добавляем администратора по умолчанию, если таблица пуста
    $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
    if ($stmt->fetchColumn() == 0) {
        $pass_hash = password_hash('123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO admins (login, pass_hash) VALUES (?, ?)")
            ->execute(['admin', $pass_hash]);
    }
} catch (PDOException $e) {
    die("Ошибка инициализации БД: " . $e->getMessage());
}

// HTTP-аутентификация
if (empty($_SERVER['PHP_AUTH_USER'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Panel"');
    die('Требуется авторизация');
}

// Проверка логина и пароля
try {
    $stmt = $pdo->prepare("SELECT pass_hash FROM admins WHERE login = ?");
    $stmt->execute([$_SERVER['PHP_AUTH_USER']]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($_SERVER['PHP_AUTH_PW'], $admin['pass_hash'])) {
        header('HTTP/1.1 401 Unauthorized');
        header('WWW-Authenticate: Basic realm="Admin Panel"');
        die('Неверные логин или пароль');
    }
} catch (PDOException $e) {
    die("Ошибка аутентификации: " . $e->getMessage());
}

// Обработка действий администратора
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? 0;

try {
    // Удаление записи
    if ($action === 'delete' && $id) {
        $pdo->prepare("DELETE FROM applications WHERE id = ?")->execute([$id]);
        header("Location: index.php");
        exit();
    }

    // Обновление записи
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
        $stmt = $pdo->prepare("UPDATE applications SET
            name = ?, phone = ?, email = ?, birthdate = ?,
            gender = ?, bio = ?, agreement = ?
            WHERE id = ?");

        $stmt->execute([
            $_POST['name'],
            $_POST['phone'],
            $_POST['email'],
            $_POST['birthdate'],
            $_POST['gender'],
            $_POST['bio'],
            isset($_POST['agreement']) ? 1 : 0,
            $_POST['id']
        ]);

        // Обновляем языки
        $pdo->prepare("DELETE FROM application_languages WHERE application_id = ?")
            ->execute([$_POST['id']]);

        $lang_stmt = $pdo->prepare("INSERT INTO application_languages
            (application_id, language_id) SELECT ?, id FROM languages WHERE name = ?");

        foreach ($_POST['languages'] as $lang) {
            $lang_stmt->execute([$_POST['id'], $lang]);
        }

        header("Location: index.php");
        exit();
    }
} catch (PDOException $e) {
    die("Ошибка обработки действия: " . $e->getMessage());
}

// Получение данных для отображения
try {
    // Получаем все заявки
    $applications = $pdo->query("
        SELECT a.*, GROUP_CONCAT(l.name) as languages
        FROM applications a
        LEFT JOIN application_languages al ON a.id = al.application_id
        LEFT JOIN languages l ON al.language_id = l.id
        GROUP BY a.id
    ")->fetchAll();

    // Получаем статистику по языкам
    $stats = $pdo->query("
        SELECT l.name, COUNT(al.application_id) as count
        FROM languages l
        LEFT JOIN application_languages al ON l.id = al.language_id
        GROUP BY l.id
        ORDER BY count DESC
    ")->fetchAll();

    // Получаем список всех языков
    $all_languages = $pdo->query("SELECT name FROM languages")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die("Ошибка получения данных: " . $e->getMessage());
}

// Форма редактирования
$edit_data = null;
if ($action === 'edit' && $id) {
    foreach ($applications as $app) {
        if ($app['id'] == $id) {
            $edit_data = $app;
            $edit_data['languages'] = explode(',', $app['languages']);
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление заявками</title>
    <!-- Подключаем стили -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <h1 class="mb-4">Управление заявками</h1>

<!-- Статистика -->
<?php foreach ($stats as $stat): ?>
    <h3><?= htmlspecialchars($stat['name']) ?></h3>
    <p><?= $stat['count'] ?></p>
<?php endforeach; ?>

        <!-- Форма редактирования -->
        <?php if ($edit_data): ?>
            <div class="card mb-4">
                <div class="card-header">
                    Редактирование заявки #<?= $edit_data['id'] ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= $edit_data['id'] ?>">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">ФИО</label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($edit_data['name']) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Телефон</label>
                                    <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($edit_data['phone']) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($edit_data['email']) ?>" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="form-label">Дата рождения</label>
                                    <input type="date" name="birthdate" class="form-control" value="<?= htmlspecialchars($edit_data['birthdate']) ?>" required>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Пол</label>
                                    <select name="gender" class="form-select" required>
                                        <option value="male" <?= $edit_data['gender'] === 'male' ? 'selected' : '' ?>>Мужской</option>
                                        <option value="female" <?= $edit_data['gender'] === 'female' ? 'selected' : '' ?>>Женский</option>
                                        <option value="other" <?= $edit_data['gender'] === 'other' ? 'selected' : '' ?>>Другое</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Контракт принят</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="agreement" id="contract"
                                            <?= $edit_data['agreement'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="contract">Да</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Языки программирования</label>
                            <select name="languages[]" class="form-select" multiple size="5" required>
                                <?php foreach ($all_languages as $lang): ?>
                                    <option value="<?= htmlspecialchars($lang) ?>"
                                        <?= in_array($lang, $edit_data['languages']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($lang) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Биография</label>
                            <textarea name="bio" class="form-control" rows="4" required><?= htmlspecialchars($edit_data['bio']) ?></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                            <a href="index.php" class="btn btn-outline-secondary">Отмена</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Список заявок -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="mb-0">Список заявок</h2>
                <span class="badge bg-primary">Всего: <?= count($applications) ?></span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>ФИО</th>
                                <th>Email</th>
                                <th>Телефон</th>
                                <th>Языки</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($applications as $app): ?>
                                <tr>
                                    <td><?= $app['id'] ?></td>
                                    <td><?= htmlspecialchars($app['name']) ?></td>
                                    <td><?= htmlspecialchars($app['email']) ?></td>
                                    <td><?= htmlspecialchars($app['phone']) ?></td>
                                    <td>
                                        <?php
                                            $langs = explode(',', $app['languages']);
                                            foreach ($langs as $lang) {
                                                echo '<span class="badge bg-light text-dark me-1">'.htmlspecialchars($lang).'</span>';
                                            }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="index.php?action=edit&id=<?= $app['id'] ?>" class="btn btn-sm btn-warning">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="index.php?action=delete&id=<?= $app['id'] ?>" class="btn btn-sm btn-danger"
                                               onclick="return confirm('Вы уверены, что хотите удалить эту заявку?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>