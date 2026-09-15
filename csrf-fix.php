#!/usr/bin/env php
<?php
/**
 * Скрипт для диагностики и исправления CSRF проблем
 * Запуск: php csrf-fix.php
 */

echo "=== CoreCMS CSRF Diagnostic Tool ===\n\n";

$basePath = __DIR__;
$bootstrapFile = $basePath . '/app/bootstrap.php';

if (!file_exists($bootstrapFile)) {
    die("Error: bootstrap.php not found at $bootstrapFile\n");
}

require_once $bootstrapFile;

use NetFree\Session;
use NetFree\Csrf;

// Запускаем сессию
Session::start();

echo "1. Checking Session...\n";
echo "   Status: " . (session_status() === PHP_SESSION_ACTIVE ? "✓ Active" : "✗ Inactive") . "\n";
echo "   ID: " . session_id() . "\n";
echo "   Save Path: " . session_save_path() . "\n\n";

echo "2. Checking CSRF Token...\n";
$token = Csrf::token();
echo "   Token: " . substr($token, 0, 20) . "...\n";
echo "   Length: " . strlen($token) . " chars\n\n";

echo "3. Testing CSRF Verification...\n";
$testToken = $token;
$isValid = Csrf::verify($testToken);
echo "   Verification: " . ($isValid ? "✓ PASS" : "✗ FAIL") . "\n\n";

if (!$isValid) {
    echo "⚠️  CSRF verification failed!\n";
    echo "   Trying to regenerate token...\n";
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $newToken = Csrf::token();
    $isValid2 = Csrf::verify($newToken);
    echo "   New verification: " . ($isValid2 ? "✓ PASS" : "✗ FAIL") . "\n\n";
}

echo "4. Checking Session Storage...\n";
$savePath = session_save_path();
if (empty($savePath)) {
    $savePath = sys_get_temp_dir();
}
echo "   Path: $savePath\n";
echo "   Writable: " . (is_writable($savePath) ? "✓ Yes" : "✗ No") . "\n\n";

echo "5. Session Configuration...\n";
echo "   Cookie Name: " . session_name() . "\n";
echo "   Cookie Lifetime: " . ini_get('session.cookie_lifetime') . " sec\n";
echo "   GC Max Lifetime: " . ini_get('session.gc_maxlifetime') . " sec\n\n";

// Проверка прав на storage
$storageDir = $basePath . '/storage';
echo "6. Checking Storage Directory...\n";
echo "   Path: $storageDir\n";
if (is_dir($storageDir)) {
    echo "   Exists: ✓ Yes\n";
    echo "   Writable: " . (is_writable($storageDir) ? "✓ Yes" : "✗ No") . "\n";

    if (!is_writable($storageDir)) {
        echo "\n⚠️  Storage directory is not writable!\n";
        echo "   Run: chmod 755 $storageDir\n";
    }
} else {
    echo "   Exists: ✗ No\n";
    echo "   Creating...\n";
    mkdir($storageDir, 0755, true);
    echo "   Created: ✓\n";
}

echo "\n=== Summary ===\n";
if ($isValid || ($isValid2 ?? false)) {
    echo "✓ CSRF is working correctly!\n";
    echo "\nIf you still see errors in browser:\n";
    echo "1. Clear browser cookies\n";
    echo "2. Clear browser cache\n";
    echo "3. Try incognito/private mode\n";
} else {
    echo "✗ CSRF has issues!\n";
    echo "\nTry these fixes:\n";
    echo "1. Check session save path permissions\n";
    echo "2. Ensure storage/ directory is writable\n";
    echo "3. Check PHP session configuration\n";
    echo "4. Review error logs\n";
}

echo "\n";
