<?php
/**
 * Health Check Endpoint
 * 
 * Endpoint para verificar se o backend está funcionando corretamente
 * 
 * @author Alex Lana
 * @version 1.0
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Verificar se o arquivo de configuração existe
    $configExists = file_exists(__DIR__ . '/config.php');
    
    // Verificar se as extensões PHP necessárias estão carregadas
    $requiredExtensions = ['curl', 'json', 'openssl'];
    $loadedExtensions = [];
    $missingExtensions = [];
    
    foreach ($requiredExtensions as $ext) {
        if (extension_loaded($ext)) {
            $loadedExtensions[] = $ext;
        } else {
            $missingExtensions[] = $ext;
        }
    }
    
    // Verificar conectividade com APIs externas (opcional)
    $apiConnectivity = [];
    $testUrls = [
        'openai' => 'https://api.openai.com/v1/models',
        'anthropic' => 'https://api.anthropic.com/v1/models',
        'google' => 'https://generativelanguage.googleapis.com/v1beta/models'
    ];
    
    foreach ($testUrls as $provider => $url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_NOBODY => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $apiConnectivity[$provider] = [
            'reachable' => $response !== false && $httpCode > 0,
            'http_code' => $httpCode,
            'error' => $error ?: null
        ];
    }
    
    // Verificar informações do sistema
    $systemInfo = [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size')
    ];
    
    // Verificar se há erros no log
    $errorLog = [];
    $logFile = ini_get('error_log');
    if ($logFile && file_exists($logFile)) {
        $recentErrors = [];
        $lines = file($logFile);
        $recentLines = array_slice($lines, -10); // Últimas 10 linhas
        
        foreach ($recentLines as $line) {
            if (strpos($line, 'ERROR') !== false || strpos($line, 'FATAL') !== false) {
                $recentErrors[] = trim($line);
            }
        }
        
        $errorLog = [
            'log_file' => $logFile,
            'recent_errors' => $recentErrors,
            'error_count' => count($recentErrors)
        ];
    }
    
    // Preparar resposta
    $response = [
        'status' => 'healthy',
        'timestamp' => date('c'),
        'version' => '2.0',
        'checks' => [
            'config_file' => [
                'status' => $configExists ? 'ok' : 'missing',
                'exists' => $configExists
            ],
            'php_extensions' => [
                'status' => empty($missingExtensions) ? 'ok' : 'warning',
                'loaded' => $loadedExtensions,
                'missing' => $missingExtensions
            ],
            'api_connectivity' => [
                'status' => 'info',
                'providers' => $apiConnectivity
            ],
            'system_info' => [
                'status' => 'ok',
                'info' => $systemInfo
            ],
            'error_log' => [
                'status' => empty($errorLog['recent_errors']) ? 'ok' : 'warning',
                'info' => $errorLog
            ]
        ]
    ];
    
    // Determinar status geral
    $hasWarnings = false;
    $hasErrors = false;
    
    foreach ($response['checks'] as $check) {
        if ($check['status'] === 'error') {
            $hasErrors = true;
        } elseif ($check['status'] === 'warning') {
            $hasWarnings = true;
        }
    }
    
    if ($hasErrors) {
        $response['status'] = 'unhealthy';
        http_response_code(503);
    } elseif ($hasWarnings) {
        $response['status'] = 'degraded';
        http_response_code(200);
    } else {
        $response['status'] = 'healthy';
        http_response_code(200);
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'timestamp' => date('c'),
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>
