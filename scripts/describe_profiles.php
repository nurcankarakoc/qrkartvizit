<?php
require_once __DIR__ . '/../core/db.php';
echo "=== profiles ===\n";
$s = $pdo->query('DESCRIBE profiles');
foreach ($s->fetchAll() as $r) echo $r['Field'].' | '.$r['Type'].' | default: '.$r['Default']."\n";

echo "\n=== Sample profile ===\n";
$s2 = $pdo->query('SELECT * FROM profiles LIMIT 1');
$row = $s2->fetch(PDO::FETCH_ASSOC);
if ($row) foreach ($row as $k=>$v) echo "$k => $v\n";
