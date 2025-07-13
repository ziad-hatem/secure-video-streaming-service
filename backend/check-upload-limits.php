<?php
/**
 * Upload Limits Diagnostic Tool
 * 
 * This script checks all the relevant settings for large file uploads
 * and provides recommendations for fixing any issues.
 */

echo "=== Upload Limits Diagnostic Tool ===\n\n";

// Function to convert PHP size values to bytes
function convertToBytes($value) {
    $value = trim($value);
    $last = strtolower($value[strlen($value)-1]);
    $value = (int) $value;
    
    switch($last) {
        case 'g':
            $value *= 1024;
        case 'm':
            $value *= 1024;
        case 'k':
            $value *= 1024;
    }
    
    return $value;
}

// Function to format bytes to human readable
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Check PHP settings
echo "=== PHP Configuration ===\n";
$phpSettings = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'max_execution_time' => ini_get('max_execution_time'),
    'max_input_time' => ini_get('max_input_time'),
    'max_file_uploads' => ini_get('max_file_uploads'),
    'file_uploads' => ini_get('file_uploads') ? 'On' : 'Off',
    'max_input_vars' => ini_get('max_input_vars'),
];

$targetSize = 1024 * 1024 * 1024; // 1GB in bytes
$issues = [];

foreach ($phpSettings as $setting => $value) {
    echo sprintf("%-20s: %s", $setting, $value);
    
    // Check for potential issues
    if (in_array($setting, ['upload_max_filesize', 'post_max_size', 'memory_limit'])) {
        $bytes = convertToBytes($value);
        if ($bytes < $targetSize) {
            echo " ❌ (Too small for 1GB uploads)";
            $issues[] = "$setting is too small: $value (need at least 1024M)";
        } else {
            echo " ✅";
        }
    } elseif ($setting === 'file_uploads' && $value === 'Off') {
        echo " ❌ (File uploads disabled)";
        $issues[] = "File uploads are disabled";
    } else {
        echo " ✅";
    }
    
    echo "\n";
}

echo "\n=== Server Information ===\n";
echo sprintf("%-20s: %s\n", "PHP Version", phpversion());
echo sprintf("%-20s: %s\n", "Server Software", $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown');
echo sprintf("%-20s: %s\n", "PHP SAPI", php_sapi_name());
echo sprintf("%-20s: %s\n", "Operating System", PHP_OS);

echo "\n=== Upload Test ===\n";
echo "Maximum theoretical upload size based on current settings:\n";

$uploadMax = convertToBytes(ini_get('upload_max_filesize'));
$postMax = convertToBytes(ini_get('post_max_size'));
$memoryMax = convertToBytes(ini_get('memory_limit'));

$effectiveMax = min($uploadMax, $postMax);
if ($memoryMax > 0) { // memory_limit = -1 means unlimited
    $effectiveMax = min($effectiveMax, $memoryMax);
}

echo "- upload_max_filesize: " . formatBytes($uploadMax) . "\n";
echo "- post_max_size: " . formatBytes($postMax) . "\n";
echo "- memory_limit: " . ($memoryMax > 0 ? formatBytes($memoryMax) : 'Unlimited') . "\n";
echo "- Effective maximum: " . formatBytes($effectiveMax) . "\n";

if ($effectiveMax >= $targetSize) {
    echo "✅ Configuration supports 1GB uploads\n";
} else {
    echo "❌ Configuration does NOT support 1GB uploads\n";
    $issues[] = "Effective maximum upload size is only " . formatBytes($effectiveMax);
}

echo "\n=== Web Server Check ===\n";
if (function_exists('apache_get_modules')) {
    echo "Apache modules loaded:\n";
    $modules = apache_get_modules();
    $relevantModules = ['mod_php', 'mod_rewrite', 'mod_core'];
    foreach ($relevantModules as $module) {
        $loaded = in_array($module, $modules) ? '✅' : '❌';
        echo "- $module: $loaded\n";
    }
} else {
    echo "Cannot detect Apache modules (not running under Apache or mod_php)\n";
}

echo "\n=== Recommendations ===\n";
if (empty($issues)) {
    echo "✅ All settings look good for 1GB uploads!\n";
    echo "\nIf you're still getting 'POST data is too large' errors, check:\n";
    echo "1. Web server configuration (nginx client_max_body_size, Apache LimitRequestBody)\n";
    echo "2. Reverse proxy settings (if using one)\n";
    echo "3. Load balancer settings (if using one)\n";
    echo "4. Firewall or security software\n";
} else {
    echo "❌ Issues found:\n";
    foreach ($issues as $issue) {
        echo "- $issue\n";
    }
    
    echo "\nTo fix these issues:\n";
    echo "1. Update your php.ini file with the correct values\n";
    echo "2. Restart your web server\n";
    echo "3. Use the custom start-server.sh script for development\n";
    echo "4. Check .htaccess file for Apache configurations\n";
}

echo "\n=== Quick Fix Commands ===\n";
echo "For development server:\n";
echo "  ./start-server.sh\n\n";

echo "For production (add to php.ini):\n";
echo "  upload_max_filesize = 1024M\n";
echo "  post_max_size = 1024M\n";
echo "  memory_limit = 1024M\n";
echo "  max_execution_time = 300\n";
echo "  max_input_time = 300\n\n";

echo "For Apache (add to .htaccess or virtual host):\n";
echo "  LimitRequestBody 1073741824\n\n";

echo "For Nginx (add to server block):\n";
echo "  client_max_body_size 1024M;\n\n";

echo "=== Test Upload URL ===\n";
echo "You can test uploads at: http://localhost:8000/api/videos/upload\n";
echo "Make sure to include authentication headers.\n\n";

echo "=== End of Diagnostic ===\n";
