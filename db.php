<?php
/**
 * Настройки подключения к базе данных
 */
$host = 'localhost';
$dbname = 'u68675';
$username = 'u68675';
$password = '5180478';

// Создаем подключение
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    // Создаем таблицы
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            login VARCHAR(50) UNIQUE NOT NULL,
            pass_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS applications (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            login VARCHAR(50) UNIQUE,
            pass_hash VARCHAR(255),
            name VARCHAR(150) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(100) NOT NULL,
            birthdate DATE NOT NULL,
            gender ENUM('male','female','other') NOT NULL,
            bio TEXT,
            contract_accepted BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS languages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            popularity INT DEFAULT 0
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS application_languages (
            application_id INT UNSIGNED NOT NULL,
            language_id INT UNSIGNED NOT NULL,
            proficiency ENUM('beginner','intermediate','advanced','expert') DEFAULT 'beginner',
            PRIMARY KEY (application_id, language_id),
            FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
            FOREIGN KEY (language_id) REFERENCES languages(id) ON UPDATE CASCADE
        ) ENGINE=InnoDB
    ");

    // Заполняем языки программирования
    $stmt = $pdo->query("SELECT COUNT(*) FROM languages");
    if ($stmt->fetchColumn() == 0) {
        $languages = [
            ['name' => 'Pascal', 'popularity' => 5],
            ['name' => 'C', 'popularity' => 90],
            // ... остальные языки
        ];
        
        $stmt = $pdo->prepare("INSERT INTO languages (name, popularity) VALUES (?, ?)");
        foreach ($languages as $lang) {
            $stmt->execute([$lang['name'], $lang['popularity']]);
        }
    }

} catch (PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

// Возвращаем объект PDO для использования в других файлах
return $pdo;
?>