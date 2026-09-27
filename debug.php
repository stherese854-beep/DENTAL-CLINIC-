<?php
// TEMPORARY debug page - DELETE THIS AFTER FIXING THE ERROR
echo "<h2>PHP Info</h2>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Session save path: " . ini_get('session.save_path') . "<br>";
echo "Session writable: " . (is_writable(ini_get('session.save_path')) ? 'YES' : 'NO') . "<br>";
echo "<br>";

echo "<h2>Environment Variables</h2>";
$vars = ['MYSQLHOST','MYSQLUSER','MYSQLDATABASE','MYSQLPORT','MYSQL_DATABASE'];
foreach ($vars as $v) {
    $val = getenv($v);
    echo "$v = " . ($val ? substr($val, 0, 20) . "..." : "(not set)") . "<br>";
}

echo "<br><h2>Extensions</h2>";
$needed = ['pdo','pdo_mysql','mbstring','openssl','curl','gd','json','session'];
foreach ($needed as $ext) {
    echo "$ext: " . (extension_loaded($ext) ? '✅' : '❌ MISSING') . "<br>";
}

echo "<br><h2>Database Test</h2>";
try {
    require_once 'config/db.php';
    $r = $pdo->query("SELECT COUNT(*) as c FROM users")->fetch();
    echo "Connected! Users in database: " . $r['c'] . "<br>";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

echo "<br><h2>Session Test</h2>";
session_start();
$_SESSION['test'] = 'ok';
echo "Session works: " . (isset($_SESSION['test']) ? '✅' : '❌') . "<br>";
echo "<br><b>DELETE debug.php after you fix the issue!</b>";
