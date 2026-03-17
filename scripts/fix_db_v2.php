<?php
$host = 'localhost';
$port = '3307';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS qrkartvizit_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "Veritabanı OK.";
    
    $pdo->exec("USE qrkartvizit_db;");
    $sql = file_get_contents('database.sql');
    $pdo->exec($sql);
    echo " Tablolar OK.";
} catch (PDOException $e) {
    echo "HATA: " . $e->getMessage();
}
?>
