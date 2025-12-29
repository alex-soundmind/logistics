<?php
require_once 'config.php';
session_start();

$is_logged_in = isset($_SESSION['user']);
$action = $_GET['action'] ?? 'list';
// По умолчанию открываем таблицу Клиенты
$table = $_GET['table'] ?? 'Clients';
$id = $_GET['id'] ?? null;

// Список таблиц базы данных логистики
$tables = [
    'Clients' => 'Клиенты',
    'Orders' => 'Заказы',
    'Cargo' => 'Грузы',
    'Trips' => 'Рейсы',
    'Vehicles' => 'Транспорт',
    'Employees' => 'Сотрудники',
    'Drivers_on_Trips' => 'Водители на рейсах'
];

if (!isset($tables[$table])) {
    die('<p class="error">Неверная таблица</p>');
}

try {
    $stmt = $pdo->query("SELECT * FROM $table LIMIT 0");
    $columns = [];
    for ($i = 0; $i < $stmt->columnCount(); $i++) {
        $meta = $stmt->getColumnMeta($i);
        $columns[] = $meta['name'];
    }
    $pk = $columns[0] ?? 'id';
} catch (PDOException $e) {
    die('<p class="error">Ошибка получения структуры таблицы.</p>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_logged_in) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json; charset=utf-8');
    }

    $data = [];
    $errors = [];

    foreach ($columns as $col) {
        if ($col === $pk) continue;

        $value = $_POST[$col] ?? '';

        // 1. Проверка числовых полей
        // Добавляем поля веса, объема, стоимости, пробега и внешних ключей
        $numericFields = [
            'weight_kg', 'volume_m3', 'value', 'manufacture_year', 'mileage', 
            'cargo_weight_kg', 'client_id', 'cargo_id', 'vehicle_id', 'order_id', 'trip_id', 'driver_id'
        ];
        
        if (in_array($col, $numericFields) && $value !== '') {
            // Для дробных чисел заменяем запятую на точку перед проверкой
            $valCheck = str_replace(',', '.', $value);
            if (!is_numeric($valCheck)) {
                $errors[] = "Поле '" . translate($col) . "' должно быть числом.";
                continue;
            }
        }

        // 2. Email валидация
        if (str_contains($col, 'email') && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Неверный формат E-mail.";
            continue;
        }

        // 3. Телефон валидация (разрешаем цифры, +, -, скобки)
        if (str_contains($col, 'phone') && $value !== '' && !preg_match('/^[\d\+\-\(\)\s]+$/', $value)) {
            $errors[] = "Неверный формат номера телефона.";
            continue;
        }

        // 4. Даты
        // Проверяем поля, которые заканчиваются на _date (но не departure_date, если она datetime)
        // В вашей схеме departure_date в Trips - это DATETIME, а в Orders - DATE.
        // Упростим проверку: если поле HTML type="date", браузер сам шлет YYYY-MM-DD.
        if (str_contains($col, 'date') && !str_contains($col, 'time') && $value !== '') {
             // Простая проверка формата, если строка похожа на дату
             if (strlen($value) === 10 && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                 // Это для полей типа DATE
                 // Если это DATETIME (как в Trips), там будет 'T' или пробел, пропускаем строгую проверку здесь
             }
        }

        // Подготовка данных
        $data[$col] = $value === '' ? null : $value;
    }

    if (empty($errors)) {
        try {
            if ($action === 'create') {
                $cols = implode(', ', array_keys($data));
                $placeholders = implode(', ', array_fill(0, count($data), '?'));
                $stmt = $pdo->prepare("INSERT INTO $table ($cols) VALUES ($placeholders)");
                $stmt->execute(array_values($data));
            } elseif ($action === 'edit' && $id) {
                $set_clauses = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
                $stmt = $pdo->prepare("UPDATE $table SET $set_clauses WHERE $pk = ?");
                $stmt->execute([...array_values($data), $id]);
            }

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                echo json_encode(['success' => true]);
                exit;
            }

            header("Location: index.php?table=$table");
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Ошибка БД: ' . $e->getMessage();
        }
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
}

if ($action === 'delete' && $id && $is_logged_in) {
    try {
        $stmt = $pdo->prepare("DELETE FROM $table WHERE $pk = ?");
        $stmt->execute([$id]);
    } catch (PDOException $e) {
        // Ловим ошибку внешнего ключа (нельзя удалить клиента, если у него есть заказы)
        die('<script>alert("Ошибка удаления! Возможно, эта запись используется в других таблицах."); window.history.back();</script>');
    }

    header("Location: index.php?table=$table");
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Грузовая компания<?= $tables[$table] ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header>
    <div class="header-inner">
        <!-- Добавил иконку самолета для красоты -->
        <h1>Грузовая компания</h1>
        <nav>
            <?php foreach ($tables as $tbl_name => $tbl_title): ?>
                <a href="?table=<?= $tbl_name ?>" class="<?= $table === $tbl_name ? 'active' : '' ?>"><?= $tbl_title ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</header>

<div class="container">
    <?php if ($action === 'list'): ?>
        <h2>Управление: <?= $tables[$table] ?></h2>
        <?php
        $stmt = $pdo->query("SELECT * FROM $table ORDER BY $pk");
        $rows = $stmt->fetchAll();

        if (!$rows): ?>
            <p style="text-align: center; color: var(--text-muted); padding: 2rem;">В этой таблице пока нет данных.</p>
        <?php else: ?>
            <!-- Обертка для скролла таблицы на мобильных -->
            <div class="table-wrapper">
                <table>
                    <thead>
                    <tr>
                        <?php foreach ($columns as $col):
                            if ($table === 'users' && $col === 'password' && !$is_logged_in) continue;
                        ?>
                            <th><?= translate($col) ?></th>
                        <?php endforeach; ?>
                        <?php if ($is_logged_in): ?><th>Действия</th><?php endif; ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <?php foreach ($row as $key => $val):
                                if ($table === 'users' && $key === 'password' && !$is_logged_in) continue;
                            ?>
                                <td><?= htmlspecialchars((string)$val, ENT_QUOTES) ?></td>
                            <?php endforeach; ?>

                            <?php if ($is_logged_in): ?>
                                <td class="actions">
                                    <a href="?table=<?= $table ?>&action=edit&id=<?= $row[$pk] ?>" class="edit" title="Редактировать">✏️</a>
                                    <a href="?table=<?= $table ?>&action=delete&id=<?= $row[$pk] ?>" class="delete" onclick="return confirm('Вы уверены, что хотите удалить эту запись?')" title="Удалить">🗑️</a>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <?php if ($is_logged_in): ?>
            <a href="?table=<?= $table ?>&action=create" class="btn-add"><button>+ Добавить запись</button></a>
        <?php endif; ?>

    <?php elseif ($action === 'create' || $action === 'edit'): ?>
        <?php
        if (!$is_logged_in) die('Доступ запрещен.');

        $values = [];
        if ($action === 'edit' && $id) {
            $stmt = $pdo->prepare("SELECT * FROM $table WHERE $pk = ?");
            $stmt->execute([$id]);
            $values = $stmt->fetch();
            if (!$values) die('Запись не найдена.');
        }
        ?>
        <h2><?= $action === 'create' ? 'Новая запись' : 'Редактирование' ?></h2>
        <form method="post" action="?table=<?= $table ?>&action=<?= $action ?><?= $id ? '&id='.$id : '' ?>">
            <?php foreach ($columns as $col):
                if ($col === $pk) continue;
                $val = $values[$col] ?? '';
                $label = translate($col);

                $type = 'text';
                if (str_contains($col, '_date')) $type = 'date';
                elseif (str_contains($col, '_time')) $type = 'time';
                elseif (in_array($col, ['capacity', 'manufacture_year'])) $type = 'number';
                elseif (str_contains($col, 'email')) $type = 'email';
                elseif (str_contains($col, 'password')) $type = 'password';

                ?>
                <div class="form-group">
                    <label for="<?= $col ?>"><?= $label ?></label>
                    <?php if (str_contains($col, 'description')): ?>
                        <textarea id="<?= $col ?>" name="<?= $col ?>"><?= htmlspecialchars($val) ?></textarea>
                    <?php else: ?>
                        <input type="<?= $type ?>" id="<?= $col ?>" name="<?= $col ?>" value="<?= htmlspecialchars($val) ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="form-actions">
                <input type="submit" value="Сохранить изменения">
                <a href="?table=<?= $table ?>"><button type="button" class="danger">Отмена</button></a>
            </div>
        </form>
    <?php endif; ?>
</div>

<footer>
    <div class="footer-content">
        <?php if (!$is_logged_in): ?>
            <a href="auth.php?mode=login">Войти в систему</a> | <a href="auth.php?mode=register">Регистрация</a>
        <?php else: ?>
            Пользователь: <b><?= htmlspecialchars($_SESSION['user']['name']) ?></b> — <a href="logout.php">Выйти</a>
        <?php endif; ?>
        <p style="margin-top: 0.5rem; opacity: 0.6;">&copy; <?= date('Y') ?> Грузовая компания</p>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form');
    if (!form) return;

    form.addEventListener('submit', async e => {
        e.preventDefault();
        const submitBtn = form.querySelector('input[type="submit"]');
        const originalText = submitBtn.value;
        submitBtn.value = 'Сохранение...';
        submitBtn.disabled = true;

        const formData = new FormData(form);

        try {
            const res = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await res.json();
            if (data.success) {
                window.location.href = `?table=<?= $table ?>`;
            } else if (data.errors) {
                alert(data.errors.join('\n'));
                submitBtn.value = originalText;
                submitBtn.disabled = false;
            } else {
                alert('Произошла ошибка при сохранении данных.');
                submitBtn.value = originalText;
                submitBtn.disabled = false;
            }
        } catch (error) {
            console.error(error);
            alert('Ошибка сети.');
            submitBtn.value = originalText;
            submitBtn.disabled = false;
        }
    });
});
</script>
</body>
</html>