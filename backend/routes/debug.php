<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Debug Routes
|--------------------------------------------------------------------------
|
| These routes are for debugging upload issues and should be removed
| in production.
|
*/

// Simple upload test endpoint
Route::post('/test-upload', function (Request $request) {
    $response = [
        'message' => 'Upload test endpoint',
        'timestamp' => now()->toISOString(),
        'php_limits' => [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
        ],
        'request_info' => [
            'content_length' => $request->header('Content-Length'),
            'content_type' => $request->header('Content-Type'),
            'method' => $request->method(),
            'has_file' => $request->hasFile('file'),
            'files_count' => count($request->allFiles()),
        ],
    ];

    if ($request->hasFile('file')) {
        $file = $request->file('file');
        $response['file_info'] = [
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'size_mb' => round($file->getSize() / (1024 * 1024), 2),
            'mime_type' => $file->getMimeType(),
            'extension' => $file->getClientOriginalExtension(),
            'error' => $file->getError(),
            'error_message' => $file->getErrorMessage(),
            'is_valid' => $file->isValid(),
        ];
    }

    return response()->json($response);
});

// PHP info endpoint
Route::get('/php-info', function () {
    ob_start();
    phpinfo();
    $phpinfo = ob_get_clean();
    
    return response($phpinfo)->header('Content-Type', 'text/html');
});

// Upload limits check endpoint
Route::get('/upload-limits', function () {
    $convertToBytes = function($value) {
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
    };

    $formatBytes = function($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    };

    $uploadMax = $convertToBytes(ini_get('upload_max_filesize'));
    $postMax = $convertToBytes(ini_get('post_max_size'));
    $memoryMax = $convertToBytes(ini_get('memory_limit'));
    
    $effectiveMax = min($uploadMax, $postMax);
    if ($memoryMax > 0) {
        $effectiveMax = min($effectiveMax, $memoryMax);
    }

    $targetSize = 1024 * 1024 * 1024; // 1GB

    return response()->json([
        'php_settings' => [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
            'file_uploads' => ini_get('file_uploads') ? 'On' : 'Off',
        ],
        'calculated_limits' => [
            'upload_max_bytes' => $uploadMax,
            'post_max_bytes' => $postMax,
            'memory_max_bytes' => $memoryMax,
            'effective_max_bytes' => $effectiveMax,
            'effective_max_formatted' => $formatBytes($effectiveMax),
        ],
        'support_1gb' => $effectiveMax >= $targetSize,
        'server_info' => [
            'php_version' => phpversion(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'php_sapi' => php_sapi_name(),
        ],
    ]);
});
