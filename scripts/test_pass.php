<?php
$passwords = ['', 'root', 'admin', '1234', '123456'];
foreach ($passwords as $p) {
    try {
        $pdo = new PDO("mysql:host=localhost", 'root', $p);
        die("BASARILI: Sifre ['$p']");
    } catch (PDOException $e) {
        echo "Sifre ['$p'] denendi: Hata.\n";
    }
}
?>
