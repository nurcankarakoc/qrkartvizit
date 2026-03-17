<?php
// core/db.php
// Veritabanı yapılandırması ve Güvenli PDO Bağlantısı

$host = 'localhost'; // Sunucu ip veya host
$port = '3307';      // XAMPP'ın kullandığı port
$db   = 'qrkartvizit_db'; 
$user = 'root'; 
$pass = ''; 
$charset = 'utf8mb4';

// Data Source Name - Port bilgisi eklendi
$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

// Güvenlik ve performans için PDO ayarları
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Hataları Exception olarak fırlat
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Varsayılan olarak sütun isimlerine göre array dönsün
    PDO::ATTR_EMULATE_PREPARES   => false,                  // SQL Injection saldırılarına karşı gerçek prepared statements kullan
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Canlı ortamda hatayı ekrana basmak yerine loglamak önemlidir.
    // Şimdilik test için ekrana yazdırıyoruz ancak yayına almadan önce burayı loglama ile değiştirebilirsiniz.
    error_log($e->getMessage());
    exit('Veritabanı bağlantı hatası. Lütfen daha sonra tekrar deneyiniz.');
}
?>
