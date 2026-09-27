<?php
// ============================================================
//  DATABASE CONNECTION  (config/db.php)
// ============================================================
//  Works on XAMPP (local) and Railway (hosted).
//  Railway may use either MYSQLDATABASE or MYSQL_DATABASE
//  depending on how the service was linked.
// ============================================================

$db_host = getenv('MYSQLHOST')     ?: getenv('MYSQL_HOST')     ?: '127.0.0.1';
$db_user = getenv('MYSQLUSER')     ?: getenv('MYSQL_USER')     ?: 'root';
$db_pass = getenv('MYSQLPASSWORD') ?: getenv('MYSQL_PASSWORD') ?: getenv('MYSQL_ROOT_PASSWORD') ?: '';
$db_name = getenv('MYSQLDATABASE') ?: getenv('MYSQL_DATABASE') ?: 'dental_clinic';
$db_port = getenv('MYSQLPORT')     ?: getenv('MYSQL_PORT')     ?: '3306';

try {
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
} catch (PDOException $e) {
    die("<b>Database connection failed:</b> " . $e->getMessage() .
        "<br><br><b>Host:</b> {$db_host}:{$db_port}" .
        "<br><b>Database:</b> {$db_name}" .
        "<br><b>User:</b> {$db_user}");
}
?>
