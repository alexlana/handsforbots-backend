<?php
/**
 * LLM Error Log Viewer
 * 
 * This script displays LLM error logs in a readable format
 */

// Load configuration
require_once __DIR__ . '/app/config.php';

// Get log file path
$logFile = getConfig('general.llm_error_log_file') ?: '/tmp/llm_errors.log';

echo "=== LLM Error Log Viewer ===\n";
echo "Log file: $logFile\n\n";

if (!file_exists($logFile)) {
    echo "❌ Log file does not exist: $logFile\n";
    echo "Run some tests first or check the configuration.\n";
    exit(1);
}

// Read and parse log entries
$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (empty($lines)) {
    echo "📭 Log file is empty.\n";
    exit(0);
}

echo "📊 Found " . count($lines) . " log entries\n\n";

// Parse and display entries
$errorTypes = [];
$providers = [];
$totalErrors = 0;

foreach ($lines as $line) {
    if (strpos($line, 'LLM_ERROR:') === 0) {
        $jsonPart = substr($line, 11); // Remove 'LLM_ERROR: ' prefix
        $entry = json_decode($jsonPart, true);
        
        if ($entry) {
            $totalErrors++;
            $errorType = $entry['error_type'] ?? 'unknown';
            $errorTypes[$errorType] = ($errorTypes[$errorType] ?? 0) + 1;
            
            // Extract provider from details if available
            $provider = $entry['details']['provider'] ?? 'unknown';
            $providers[$provider] = ($providers[$provider] ?? 0) + 1;
            
            // Display entry
            echo "🕐 " . $entry['timestamp'] . "\n";
            echo "🚨 Error Type: " . $errorType . "\n";
            echo "🌐 Provider: " . $provider . "\n";
            echo "📱 Request ID: " . ($entry['request_id'] ?? 'N/A') . "\n";
            echo "🔗 Session ID: " . ($entry['session_id'] ?? 'N/A') . "\n";
            echo "💻 IP: " . $entry['ip'] . "\n";
            
            // Display specific error details
            if (isset($entry['details']['error'])) {
                echo "❌ Error: " . $entry['details']['error'] . "\n";
            }
            
            if (isset($entry['details']['url'])) {
                echo "🔗 URL: " . $entry['details']['url'] . "\n";
            }
            
            if (isset($entry['details']['http_code'])) {
                echo "📡 HTTP Code: " . $entry['details']['http_code'] . "\n";
            }
            
            if (isset($entry['details']['connection_info'])) {
                $connInfo = $entry['details']['connection_info'];
                echo "⏱️  Connection Info:\n";
                echo "   - Total Time: " . ($connInfo['total_time'] ?? 'N/A') . "s\n";
                echo "   - Connect Time: " . ($connInfo['connect_time'] ?? 'N/A') . "s\n";
                echo "   - Name Lookup: " . ($connInfo['name_lookup_time'] ?? 'N/A') . "s\n";
                echo "   - Transfer Time: " . ($connInfo['pretransfer_time'] ?? 'N/A') . "s\n";
            }
            
            if (isset($entry['details']['response'])) {
                echo "📄 Response: " . substr(json_encode($entry['details']['response']), 0, 200) . "...\n";
            }
            
            echo "\n" . str_repeat("-", 80) . "\n\n";
        }
    }
}

// Display summary
echo "=== SUMMARY ===\n";
echo "Total LLM Errors: $totalErrors\n\n";

echo "Error Types:\n";
arsort($errorTypes);
foreach ($errorTypes as $type => $count) {
    echo "  - $type: $count\n";
}

echo "\nProviders:\n";
arsort($providers);
foreach ($providers as $provider => $count) {
    echo "  - $provider: $count\n";
}

echo "\n=== RECOMMENDATIONS ===\n";

// Provide recommendations based on error types
if (isset($errorTypes['openai_api_key_missing']) || isset($errorTypes['anthropic_api_key_missing']) || isset($errorTypes['google_api_key_missing'])) {
    echo "🔑 API Key Issues:\n";
    echo "   - Check if API keys are properly configured in environment variables\n";
    echo "   - Verify OPENAI_API_KEY, ANTHROPIC_API_KEY, GOOGLE_API_KEY\n";
    echo "   - Ensure API keys are valid and have sufficient credits\n\n";
}

if (isset($errorTypes['curl_error'])) {
    echo "🌐 Connection Issues:\n";
    echo "   - Check network connectivity\n";
    echo "   - Verify firewall settings\n";
    echo "   - Check if the LLM service endpoints are accessible\n";
    echo "   - Consider increasing timeout values in configuration\n\n";
}

if (isset($errorTypes['http_error'])) {
    echo "📡 HTTP Errors:\n";
    echo "   - Check API rate limits\n";
    echo "   - Verify API key permissions\n";
    echo "   - Check if the requested models are available\n";
    echo "   - Review API documentation for error codes\n\n";
}

if (isset($errorTypes['invalid_response_format'])) {
    echo "📄 Response Format Issues:\n";
    echo "   - The LLM provider may have changed their API format\n";
    echo "   - Check if the provider is experiencing issues\n";
    echo "   - Verify the response parsing logic\n\n";
}

echo "For more detailed analysis, check the raw log file: $logFile\n";
?>
