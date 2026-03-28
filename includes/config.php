<?php
$host = 'localhost';          // Usually localhost on Hostinger
$dbname = 'u629648508_factshield_db';
$username = 'u629648508_FSnjb';
$password = 'FSnjb1234';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>