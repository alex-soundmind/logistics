<?php
$host = 'dpg-d4v9dk3e5dus73a9raig-a.singapore-postgres.render.com';
$db   = 'logistics';
$user = 'trams_db_user';
$pass = 'Gbj0c9Akmi32On4MsJWjH4dLkUCnp31t';
$dsn  = "pgsql:host=$host;dbname=$db";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}

function translate($column) {
    static $map = [
'id' => 'ID',
        
        // Таблица Clients (Клиенты)
        'name' => 'ФИО / Название',
        'contact_phone' => 'Контактный телефон',
        'contact_email' => 'Контактный E-mail',
        
        // Таблица Cargo (Грузы)
        'description' => 'Описание груза',
        'weight_kg' => 'Вес (кг)',
        'volume_m3' => 'Объем (м³)',
        'value' => 'Стоимость (руб)',
        
        // Таблица Employees (Сотрудники)
        'position' => 'Должность',
        'hire_date' => 'Дата найма',
        'phone' => 'Телефон',
        'email' => 'E-mail',
        
        // Таблица Vehicles (Транспорт)
        'brand' => 'Марка',
        'model' => 'Модель',
        'license_plate' => 'Госномер',
        'manufacture_year' => 'Год выпуска',
        'mileage' => 'Пробег (км)',
        'vehicle_type' => 'Тип ТС',
        
        // Таблица Orders (Заказы)
        'client_id' => 'ID Клиента',
        'order_date' => 'Дата заказа',
        'delivery_date' => 'Дата доставки',
        'cargo_id' => 'ID Груза',
        'order_status' => 'Статус заказа',
        
        // Таблица Trips (Рейсы)
        'vehicle_id' => 'ID Транспорта',
        'departure_date' => 'Дата отправления',
        'arrival_date' => 'Дата прибытия',
        'trip_status' => 'Статус рейса',
        'cargo_description' => 'Описание груза (в рейсе)',
        'cargo_weight_kg' => 'Вес груза (в рейсе)',
        'order_id' => 'ID Заказа',
        
        // Таблица Drivers_on_Trips (Связь водителей и рейсов)
        'trip_id' => 'ID Рейса',
        'driver_id' => 'ID Водителя',
        
        // Для таблицы пользователей (если осталась)
        'password' => 'Пароль'
    ];
    return $map[$column] ?? ucfirst(str_replace('_', ' ', $column));
}
?>