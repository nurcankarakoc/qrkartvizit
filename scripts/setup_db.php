<?php
$host = 'localhost';
$user = 'root';
$pass = 'Kastedor567?';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS qrkartvizit_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "Veritabanı oluşturuldu veya zaten var.\n";
    
    $pdo->exec("USE qrkartvizit_db;");
    $sql = file_get_contents('database.sql');
    $pdo->exec($sql);
    echo "Tablolar başarıyla oluşturuldu.\n";
} catch (PDOException $e) {
    die("HATA: " . $e->getMessage());
}
?>
