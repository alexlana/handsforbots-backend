<?php
/**
 * Universal LLM Backend
 * 
 * This PHP backend receives requests in a universal format and maps them
 * to different LLM APIs (OpenAI, Anthropic, Google, Ollama, etc.)
 * 
 * @author Alex Lana
 * @version 0.1
 */

// Enable error reporting and logging
ini_set('display_errors', 0); // Don't display errors to browser
ini_set('log_errors', 1); // Log errors
// ini_set('error_log', '/tmp/php_errors.log'); // Set error log file
error_reporting(E_ALL);


// Load configuration
require_once __DIR__ . '/config.php';

// Load system prompt
$systemPrompt = loadSystemPrompt();

// Load MCP Handler
require_once __DIR__ . '/mcp/mcp_handler.php';

// Security headers
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// CORS configuration - Use config values
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, getConfig('security.allowed_origins'))) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Log unauthorized origin attempt
    logSecurityEvent('unauthorized_origin', ['origin' => $origin]);
    http_response_code(403);
    exit(json_encode(['error' => 'Origin not allowed']));
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Request-ID, X-Session-ID, X-API-Key');
header('Access-Control-Max-Age: 86400'); // 24 hours

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Handle different HTTP methods
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if this is a session ID request
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($requestUri, PHP_URL_PATH);
    
    if (str_ends_with($path, '/session')) {
        // Generate and return a new session ID
        $sessionId = generateSessionId();
        $response = [
            'session_id' => $sessionId,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        logSecurityEvent('session_id_generated', [
            'session_id' => $sessionId
        ]);
        
        echo json_encode($response);
        exit();
    }
    
    // GET request for MCP tools info
    $response = [
        'mcp' => [
            'enabled' => getConfig('mcp.enabled'),
            'tools' => getDefaultMCPTools()
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    exit();
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Validate request size
    validateRequestSize();
    
    // Get client ID for rate limiting
    $clientId = getClientId();

    // Check rate limit
    checkRateLimit($clientId);

    // Validate API key if required
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? null;
    if (getConfig('security.require_api_key') && !validateApiKey($apiKey)) {
        logSecurityEvent('invalid_api_key', ['provided_key' => substr($apiKey, 0, 10) . '...']);
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key']);
        exit();
    }

    // Get and validate request data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate and sanitize input
    $input = validateAndSanitizeInput($input);
    
    // Extract headers
    $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
    $sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? null;
    
    // Log request (without sensitive data)
    logSecurityEvent('request_received', [
        'request_id' => $requestId,
        'session_id' => $sessionId,
        'provider' => $input['provider'],
        'model' => $input['model']
    ]);
    
    // Process the universal request
    try {
        $response = processUniversalRequest($input, $requestId, $sessionId);
        
        // Log successful response
        logSecurityEvent('request_processed', [
            'request_id' => $requestId,
            'provider' => $input['provider']
        ]);
    } catch (Exception $e) {
        // Log LLM-specific error
        logLLMError('request_processing_failed', [
            'request_id' => $requestId,
            'session_id' => $sessionId,
            'provider' => $input['provider'],
            'model' => $input['model'],
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
    
    // Return response
    echo json_encode($response);
    
} catch (Exception $e) {
    // Log error
    logSecurityEvent('error', [
        'message' => $e->getMessage(),
        'request_id' => $requestId ?? null
    ]);
    
    // Don't expose internal errors in production
    $errorMessage = 'Internal server error';
    if (getConfig('general.debug_mode')) {
        $errorMessage = $e->getMessage();
    }
    
    http_response_code(500);
    echo json_encode([
        'error' => $errorMessage,
        'request_id' => $requestId ?? null
    ]);
}

// ============================================================================
// SECURITY FUNCTIONS
// ============================================================================

/**
 * Validate and sanitize input data
 */
function validateAndSanitizeInput($data) {
    if (!is_array($data)) {
        throw new Exception('Invalid input format');
    }
    
    // Check for required fields
    $requiredFields = ['provider', 'model', 'messages', 'parameters'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Validate provider
    $allowedProviders = getConfig('general.allowed_providers');
    if (!in_array(strtolower($data['provider']), $allowedProviders)) {
        throw new Exception('Invalid provider specified');
    }
    
    // Validate messages structure
    if (!is_array($data['messages']) || empty($data['messages'])) {
        throw new Exception('Messages must be a non-empty array');
    }
    
    // Sanitize messages
    foreach ($data['messages'] as &$message) {
        if (!isset($message['role']) || !isset($message['content'])) {
            throw new Exception('Invalid message format');
        }
        
        // Validate role
        $allowedRoles = ['user', 'assistant', 'system'];
        if (!in_array($message['role'], $allowedRoles)) {
            throw new Exception('Invalid message role');
        }
        
        // Sanitize content
        $message['content'] = sanitizeString($message['content']);
        
        // Limit content length
        $maxContentLength = getConfig('general.max_content_length');
        if (strlen($message['content']) > $maxContentLength) {
            throw new Exception("Message content too long (max {$maxContentLength} bytes)");
        }
    }
    
    // Validate parameters
    if (!is_array($data['parameters'])) {
        throw new Exception('Parameters must be an array');
    }
    
    // Sanitize and validate parameters
    $allowedParams = ['max_tokens', 'temperature', 'top_p', 'frequency_penalty', 'presence_penalty', 'stream'];
    foreach ($data['parameters'] as $key => $value) {
        if (!in_array($key, $allowedParams)) {
            unset($data['parameters'][$key]);
            continue;
        }
        
        // Validate numeric parameters
        if (in_array($key, ['max_tokens', 'temperature', 'top_p', 'frequency_penalty', 'presence_penalty'])) {
            if (!is_numeric($value)) {
                throw new Exception("Parameter $key must be numeric");
            }
            
            // Set reasonable limits from config
            $limits = getConfig('general.parameter_limits');
            switch ($key) {
                case 'max_tokens':
                    $data['parameters'][$key] = min(max((int)$value, $limits['max_tokens']['min']), $limits['max_tokens']['max']);
                    break;
                case 'temperature':
                    $data['parameters'][$key] = min(max((float)$value, $limits['temperature']['min']), $limits['temperature']['max']);
                    break;
                case 'top_p':
                    $data['parameters'][$key] = min(max((float)$value, $limits['top_p']['min']), $limits['top_p']['max']);
                    break;
                case 'frequency_penalty':
                case 'presence_penalty':
                    $data['parameters'][$key] = min(max((float)$value, $limits['penalties']['min']), $limits['penalties']['max']);
                    break;
            }
        }
        
        // Validate boolean parameters
        if ($key === 'stream') {
            $data['parameters'][$key] = (bool)$value;
        }
    }
    
    return $data;
}

/**
 * Sanitize string input
 */
function sanitizeString($input) {
    // Remove null bytes
    $input = str_replace("\0", '', $input);
    
    // Remove control characters except newlines and tabs
    $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
    
    // Limit length
    $maxLength = getConfig('general.max_content_length');
    if (strlen($input) > $maxLength) {
        $input = substr($input, 0, $maxLength);
    }
    
    return $input;
}

/**
 * Rate limiting implementation
 */
function checkRateLimit($clientId) {
    $rateLimitConfig = getConfig('rate_limit');
    $rateLimitFile = sys_get_temp_dir() . '/llm_rate_limit.json';
    $currentTime = time();
    $window = $rateLimitConfig['window'];
    $maxRequests = $rateLimitConfig['requests_per_window'];
    
    // Load existing rate limit data
    $rateData = [];
    if (file_exists($rateLimitFile)) {
        $rateData = json_decode(file_get_contents($rateLimitFile), true) ?: [];
    }
    
    // Clean old entries
    $rateData = array_filter($rateData, function($timestamp) use ($currentTime, $window) {
        // Ensure timestamp is an integer
        $timestamp = is_numeric($timestamp) ? (int)$timestamp : 0;
        return ($currentTime - $timestamp) < $window;
    });
    
    // Check if client has exceeded limit
    $clientRequests = array_count_values($rateData)[$clientId] ?? 0;
    if ($clientRequests >= $maxRequests) {
        logSecurityEvent('rate_limit_exceeded', ['client_id' => $clientId]);
        throw new Exception('Rate limit exceeded. Please try again later.');
    }
    
    // Add current request
    $rateData[] = $clientId;
    
    // Save updated rate limit data
    file_put_contents($rateLimitFile, json_encode($rateData));
    
    return true;
}

/**
 * Validate API key
 */
function validateApiKey($apiKey) {
    if (empty($apiKey)) {
        return false;
    }
    
    // Check against allowed API keys from config
    $allowedKeys = getConfig('security.allowed_api_keys');
    return in_array($apiKey, $allowedKeys);
}

/**
 * Get client identifier for rate limiting
 */
function getClientId() {
    // Use IP address as client identifier
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 
          $_SERVER['HTTP_X_REAL_IP'] ?? 
          $_SERVER['REMOTE_ADDR'] ?? 
          'unknown';
    
    return hash('sha256', $ip);
}

/**
 * Log security events
 */
function logSecurityEvent($event, $details = []) {
    if (!getConfig('general.enable_logging')) {
        return;
    }
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'event' => $event,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'details' => $details
    ];
    
    $logLevel = getConfig('general.log_level');
    $logMessage = 'SECURITY: ' . json_encode($logEntry);
    
    switch ($logLevel) {
        case 'debug':
            error_log($logMessage);
            break;
        case 'info':
            if (!in_array($event, ['request_received', 'request_processed'])) {
                error_log($logMessage);
            }
            break;
        case 'warning':
            if (in_array($event, ['unauthorized_origin', 'invalid_api_key', 'rate_limit_exceeded'])) {
                error_log($logMessage);
            }
            break;
        case 'error':
            if (in_array($event, ['error', 'security_violation'])) {
                error_log($logMessage);
            }
            break;
    }
}

/**
 * Log LLM-specific errors with detailed information
 */
function logLLMError($errorType, $details = []) {
    if (!getConfig('general.enable_logging')) {
        return;
    }
    
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => 'llm_error',
        'error_type' => $errorType,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'request_id' => $_SERVER['HTTP_X_REQUEST_ID'] ?? null,
        'session_id' => $_SERVER['HTTP_X_SESSION_ID'] ?? null,
        'details' => $details
    ];
    
    $logLevel = getConfig('general.log_level');
    $logMessage = 'LLM_ERROR: ' . json_encode($logEntry);
    
    // Always log LLM errors regardless of log level
    error_log($logMessage);
    
    // Also log to a specific LLM error file if configured
    $logFile = getConfig('general.llm_error_log_file');
    if ($logFile) {
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Validate request size
 */
function validateRequestSize() {
    $maxSize = getConfig('general.max_request_size');
    $contentLength = $_SERVER['CONTENT_LENGTH'] ?? 0;
    
    if ($contentLength > $maxSize) {
        throw new Exception('Request too large');
    }
}

/**
 * Generate a unique session ID for conversation tracking
 * 
 * @return string Session ID
 */
function generateSessionId() {
    // Generate a secure session ID using multiple sources of entropy
    $timestamp = microtime(true);
    $random = bin2hex(random_bytes(16));
    $serverEntropy = hash('sha256', $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_TIME_FLOAT']);
    
    // Combine all sources and create a hash
    $sessionData = $timestamp . '_' . $random . '_' . substr($serverEntropy, 0, 8);
    $sessionId = 'session_' . hash('sha256', $sessionData);
    
    return substr($sessionId, 0, 32); // Limit to 32 characters for readability
}

/**
 * Load system prompt from file
 * 
 * @return string System prompt content or empty string if file not found
 */
function loadSystemPrompt() {
    $systemPromptFile = __DIR__ . '/system-prompt.txt';
    
    if (!file_exists($systemPromptFile)) {
        logSecurityEvent('system_prompt_file_not_found', [
            'file_path' => $systemPromptFile
        ]);
        return '';
    }
    
    $content = file_get_contents($systemPromptFile);
    
    if ($content === false) {
        logSecurityEvent('system_prompt_file_read_error', [
            'file_path' => $systemPromptFile
        ]);
        return '';
    }
    
    // Trim whitespace and return
    $content = trim($content);
    
    if (!empty($content)) {
        logSecurityEvent('system_prompt_loaded', [
            'file_path' => $systemPromptFile,
            'content_length' => strlen($content)
        ]);
    }
    
    return $content;
}

// ============================================================================
// REQUEST PROCESSING FUNCTIONS
// ============================================================================

/**
 * Process universal request and map to appropriate LLM API
 * 
 * @param array $request Universal request format
 * @param string $requestId Request ID for tracking
 * @param string $sessionId Session ID for conversation tracking
 * @return array Response in universal format
 */
function processUniversalRequest($request, $requestId, $sessionId) {
    global $systemPrompt;
    
    // Validate required fields
    $requiredFields = ['provider', 'model', 'messages', 'parameters'];
    foreach ($requiredFields as $field) {
        if (!isset($request[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Determine which provider to use
    $provider = $request['provider'];
    $model = $request['model'];
    $messages = $request['messages'];
    $parameters = $request['parameters'];
    $context = $request['context'] ?? [];
    $options = $request['options'] ?? [];
    
    // Process MCP context and tools
    $mcpContext = processMCPContext($context);
    $availableTools = $mcpContext['tools'] ?? [];
    
    // Add global system prompt if available and not already provided in context
    if (!empty($systemPrompt) && !isset($context['system_prompt'])) {
        $context['system_prompt'] = $systemPrompt;
        logSecurityEvent('global_system_prompt_applied', [
            'provider' => $provider,
            'model' => $model,
            'system_prompt_length' => strlen($systemPrompt)
        ]);
    }
    
    // Add MCP instructions to system prompt if tools are available
    if (!empty($availableTools)) {
        $mcpInstructions = generateMCPInstructions($availableTools);
        if (!empty($mcpInstructions)) {
            $context['system_prompt'] = ($context['system_prompt'] ?? '') . "\n\n" . $mcpInstructions;
            
            logSecurityEvent('mcp_instructions_added', [
                'provider' => $provider,
                'model' => $model,
                'tools_count' => count($availableTools),
                'instructions_length' => strlen($mcpInstructions)
            ]);
        }
    }
    
    // Map to specific provider
    $llmResponse = null;
    switch (strtolower($provider)) {
        case 'openai':
        case 'gpt':
            $llmResponse = callOpenAI($model, $messages, $parameters, $context, $options);
            break;
            
        case 'anthropic':
        case 'claude':
            $llmResponse = callAnthropic($model, $messages, $parameters, $context, $options);
            break;
            
        case 'google':
        case 'gemini':
            $llmResponse = callGoogle($model, $messages, $parameters, $context, $options);
            break;
            
        case 'ollama':
            $llmResponse = callOllama($model, $messages, $parameters, $context, $options);
            break;
            
        case 'auto':
            // Auto-detect based on model name or try multiple providers
            $llmResponse = autoDetectProvider($model, $messages, $parameters, $context, $options);
            break;
            
        default:
            logLLMError('unsupported_provider', [
                'provider' => $provider,
                'model' => $model,
                'error' => "Unsupported provider: $provider"
            ]);
            throw new Exception("Unsupported provider: $provider");
    }
    
    // Process MCP tool calls if available
    if (!empty($availableTools) && isset($llmResponse['response'])) {
        $responseText = $llmResponse['response'];
        $toolCalls = extractToolCalls($responseText);

        if (!empty($toolCalls)) {
            // Execute tool calls
            $toolResults = executeToolCalls($toolCalls, $availableTools);

            // Process response with tool results
            $processedResponse = processMCPResponse($responseText, $toolCalls, $toolResults);
            // Return processed response with tool results
            return $processedResponse;
        }
    }

    return [[
        'recipient_id' => 'user',
        'text' => $llmResponse['response'],
        'metadata' => $llmResponse['metadata'] ?? []
    ]];
}

/**
 * Call OpenAI API
 */
function callOpenAI($model, $messages, $parameters, $context, $options) {
    $apiKey = getenv('OPENAI_API_KEY');
    if (!$apiKey) {
        logLLMError('openai_api_key_missing', [
            'provider' => 'openai',
            'model' => $model,
            'error' => 'OpenAI API key not configured'
        ]);
        throw new Exception('OpenAI API key not configured');
    }
    
    // Map universal parameters to OpenAI format
    $openaiParams = [
        'model' => $model === 'auto' ? 'gpt-3.5-turbo' : $model,
        'messages' => mapMessagesToOpenAI($messages),
        'max_tokens' => $parameters['max_tokens'] ?? 1024,
        'temperature' => $parameters['temperature'] ?? 0.7,
        'top_p' => $parameters['top_p'] ?? 0.9,
        'frequency_penalty' => $parameters['frequency_penalty'] ?? 0.0,
        'presence_penalty' => $parameters['presence_penalty'] ?? 0.0,
        'stream' => $parameters['stream'] ?? false
    ];
    
    // Add system prompt if available
    if (isset($context['system_prompt']) && $context['system_prompt']) {
        array_unshift($openaiParams['messages'], [
            'role' => 'system',
            'content' => $context['system_prompt']
        ]);
    }
    
    // Add conversation history if available
    if (isset($context['conversation_history']) && !empty($context['conversation_history'])) {
        $openaiParams['messages'] = array_merge($context['conversation_history'], $openaiParams['messages']);
        logSecurityEvent('openai_conversation_history_added', [
            'provider' => 'openai',
            'model' => $model,
            'history_count' => count($context['conversation_history']),
            'session_id' => $_SERVER['HTTP_X_SESSION_ID'] ?? null
        ]);
    }
    
    // Make API call
    $response = makeHttpRequest('https://api.openai.com/v1/chat/completions', [
        'method' => 'POST',
        'headers' => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ],
        'body' => json_encode($openaiParams)
    ]);
    
    // Map OpenAI response to universal format
    return mapOpenAIResponseToUniversal($response);
}

/**
 * Call Anthropic API
 */
function callAnthropic($model, $messages, $parameters, $context, $options) {
    $apiKey = getenv('ANTHROPIC_API_KEY');
    if (!$apiKey) {
        logLLMError('anthropic_api_key_missing', [
            'provider' => 'anthropic',
            'model' => $model,
            'error' => 'Anthropic API key not configured'
        ]);
        throw new Exception('Anthropic API key not configured');
    }
    
    // Map universal parameters to Anthropic format
    $anthropicParams = [
        'model' => $model === 'auto' ? 'claude-3-sonnet-20240229' : $model,
        'max_tokens' => $parameters['max_tokens'] ?? 1024,
        'temperature' => $parameters['temperature'] ?? 0.7,
        'top_p' => $parameters['top_p'] ?? 0.9,
        'stream' => $parameters['stream'] ?? false
    ];
    
    // Build prompt for Anthropic
    $prompt = buildPromptForProvider($messages, $context, 'anthropic');
    $anthropicParams['prompt'] = $prompt;
    
    // Make API call
    $response = makeHttpRequest('https://api.anthropic.com/v1/complete', [
        'method' => 'POST',
        'headers' => [
            'x-api-key: ' . $apiKey,
            'Content-Type: application/json',
            'anthropic-version: 2023-06-01'
        ],
        'body' => json_encode($anthropicParams)
    ]);
    
    // Map Anthropic response to universal format
    return mapAnthropicResponseToUniversal($response);
}

/**
 * Call Google Gemini API
 */
function callGoogle($model, $messages, $parameters, $context, $options) {
    $apiKey = getenv('GOOGLE_API_KEY');
    if (!$apiKey) {
        logLLMError('google_api_key_missing', [
            'provider' => 'google',
            'model' => $model,
            'error' => 'Google API key not configured'
        ]);
        throw new Exception('Google API key not configured');
    }
    
    // Prepare all messages including conversation history
    $allMessages = [];
    
    // Add conversation history if available
    if (isset($context['conversation_history']) && !empty($context['conversation_history'])) {
        $allMessages = array_merge($allMessages, $context['conversation_history']);
        logSecurityEvent('google_conversation_history_added', [
            'provider' => 'google',
            'model' => $model,
            'history_count' => count($context['conversation_history']),
            'session_id' => $_SERVER['HTTP_X_SESSION_ID'] ?? null
        ]);
    }
    
    // Add current messages
    $allMessages = array_merge($allMessages, $messages);
    
    // Map universal parameters to Google format
    $googleParams = [
        'contents' => mapMessagesToGoogle($allMessages),
        'generationConfig' => [
            'maxOutputTokens' => $parameters['max_tokens'] ?? 1024,
            'temperature' => $parameters['temperature'] ?? 0.7,
            'topP' => $parameters['top_p'] ?? 0.9
        ]
    ];
    
    // Add system instruction if available
    if (isset($context['system_prompt']) && $context['system_prompt']) {
        $googleParams['systemInstruction'] = [
            'parts' => [['text' => $context['system_prompt']]]
        ];
    }
    
    $modelName = $model === 'auto' ? 'gemini-1.5-flash' : $model;
    
    // Make API call
    $response = makeHttpRequest("https://generativelanguage.googleapis.com/v1beta/models/$modelName:generateContent?key=$apiKey", [
        'method' => 'POST',
        'headers' => [
            'Content-Type: application/json'
        ],
        'body' => json_encode($googleParams)
    ]);
    
    // Map Google response to universal format
    return mapGoogleResponseToUniversal($response);
}

/**
 * Call Ollama API
 */
function callOllama($model, $messages, $parameters, $context, $options) {
    $ollamaEndpoint = getenv('OLLAMA_ENDPOINT') ?: 'http://localhost:11434';
    
    // Map universal parameters to Ollama format
    $ollamaParams = [
        'model' => $model === 'auto' ? 'llama2' : $model,
        'prompt' => buildPromptForProvider($messages, $context, 'ollama'),
        'stream' => $parameters['stream'] ?? false,
        'options' => [
            'num_ctx' => 2048,
            'num_predict' => $parameters['max_tokens'] ?? 1024,
            'temperature' => $parameters['temperature'] ?? 0.7,
            'top_p' => $parameters['top_p'] ?? 0.9
        ]
    ];
    
    // Make API call
    $response = makeHttpRequest("$ollamaEndpoint/api/generate", [
        'method' => 'POST',
        'headers' => [
            'Content-Type: application/json'
        ],
        'body' => json_encode($ollamaParams)
    ]);
    
    // Map Ollama response to universal format
    return mapOllamaResponseToUniversal($response);
}

/**
 * Auto-detect provider based on model name
 */
function autoDetectProvider($model, $messages, $parameters, $context, $options) {
    $modelLower = strtolower($model);
    
    if (strpos($modelLower, 'gpt') !== false || strpos($modelLower, 'openai') !== false) {
        return callOpenAI($model, $messages, $parameters, $context, $options);
    } elseif (strpos($modelLower, 'claude') !== false || strpos($modelLower, 'anthropic') !== false) {
        return callAnthropic($model, $messages, $parameters, $context, $options);
    } elseif (strpos($modelLower, 'gemini') !== false || strpos($modelLower, 'google') !== false) {
        return callGoogle($model, $messages, $parameters, $context, $options);
    } elseif (strpos($modelLower, 'llama') !== false || strpos($modelLower, 'ollama') !== false) {
        return callOllama($model, $messages, $parameters, $context, $options);
    } else {
        // Default to OpenAI
        return callOpenAI($model, $messages, $parameters, $context, $options);
    }
}

/**
 * Map messages to OpenAI format
 */
function mapMessagesToOpenAI($messages) {
    $openaiMessages = [];
    foreach ($messages as $message) {
        $openaiMessages[] = [
            'role' => $message['role'],
            'content' => $message['content']
        ];
    }
    return $openaiMessages;
}

/**
 * Map messages to Google format
 */
function mapMessagesToGoogle($messages) {
    $googleContents = [];
    foreach ($messages as $message) {
        $googleContents[] = [
            'role' => $message['role'] === 'user' ? 'user' : 'model',
            'parts' => [['text' => $message['content']]]
        ];
    }
    return $googleContents;
}

/**
 * Build prompt for text-based providers (Anthropic, Ollama)
 */
function buildPromptForProvider($messages, $context, $provider) {
    $formats = [
        'anthropic' => [
            'system_prefix' => "\n\nHuman: ",
            'system_suffix' => "\n\nAssistant: I understand. I'll follow these instructions.",
            'message_format' => "\n\n%s: %s",
            'final_suffix' => "\n\nAssistant:"
        ],
        'ollama' => [
            'system_prefix' => "System: ",
            'system_suffix' => "\n\n",
            'message_format' => "%s: %s\n",
            'final_suffix' => ""
        ]
    ];
    
    $format = $formats[$provider] ?? $formats['ollama']; // Default fallback
    $prompt = "";
    
    // Add system prompt if available
    if (isset($context['system_prompt']) && $context['system_prompt']) {
        $prompt .= $format['system_prefix'] . $context['system_prompt'] . $format['system_suffix'];
    }
    
    // Add conversation history
    if (isset($context['conversation_history']) && !empty($context['conversation_history'])) {
        foreach ($context['conversation_history'] as $msg) {
            $prompt .= sprintf($format['message_format'], ucfirst($msg['role']), $msg['content']);
        }
        logConversationHistory($provider, $context['conversation_history']);
    }
    
    // Add current messages
    foreach ($messages as $message) {
        $prompt .= sprintf($format['message_format'], ucfirst($message['role']), $message['content']);
    }
    
    return $prompt . $format['final_suffix'];
}

/**
 * Log conversation history for providers
 */
function logConversationHistory($provider, $history) {
    logSecurityEvent($provider . '_conversation_history_added', [
        'provider' => $provider,
        'history_count' => count($history),
        'session_id' => $_SERVER['HTTP_X_SESSION_ID'] ?? null
    ]);
}



/**
 * Make secure HTTP request
 */
function makeHttpRequest($url, $options) {
    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        logLLMError('invalid_url', [
            'url' => $url,
            'error' => 'Invalid URL format'
        ]);
        throw new Exception('Invalid URL');
    }
    
    // Check for allowed domains
    $allowedDomains = getConfig('security.allowed_domains');
    $parsedUrl = parse_url($url);
    if (!in_array($parsedUrl['host'], $allowedDomains)) {
        logLLMError('domain_not_allowed', [
            'url' => $url,
            'host' => $parsedUrl['host'],
            'allowed_domains' => $allowedDomains
        ]);
        throw new Exception('Domain not allowed');
    }
    
    $ch = curl_init();
    
    $timeout = getConfig('general.request_timeout');
    $connectTimeout = getConfig('general.connect_timeout');

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_HTTPHEADER => $options['headers'],
        CURLOPT_POST => $options['method'] === 'POST',
        CURLOPT_POSTFIELDS => $options['body'] ?? null,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'Universal-LLM-Backend/2.0',
        CURLOPT_FOLLOWLOCATION => false, // Prevent redirect attacks
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
        CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS
    ]);
    
    $startTime = microtime(true);
    $response = curl_exec($ch);
    $endTime = microtime(true);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $errorNumber = curl_errno($ch);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    $connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
    $nameLookupTime = curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME);
    $pretransferTime = curl_getinfo($ch, CURLINFO_PRETRANSFER_TIME);
    $starttransferTime = curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME);
    $redirectTime = curl_getinfo($ch, CURLINFO_REDIRECT_TIME);
    $redirectCount = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    $sizeDownload = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
    $speedDownload = curl_getinfo($ch, CURLINFO_SPEED_DOWNLOAD);
    
    curl_close($ch);
    
    // Log detailed connection information
    $connectionInfo = [
        'url' => $url,
        'effective_url' => $effectiveUrl,
        'http_code' => $httpCode,
        'total_time' => round($totalTime, 3),
        'connect_time' => round($connectTime, 3),
        'name_lookup_time' => round($nameLookupTime, 3),
        'pretransfer_time' => round($pretransferTime, 3),
        'starttransfer_time' => round($starttransferTime, 3),
        'redirect_time' => round($redirectTime, 3),
        'redirect_count' => $redirectCount,
        'content_type' => $contentType,
        'content_length' => $contentLength,
        'size_download' => $sizeDownload,
        'speed_download' => $speedDownload,
        'custom_execution_time' => round(($endTime - $startTime) * 1000, 2) // in milliseconds
    ];
    
    if ($error) {
        logLLMError('curl_error', [
            'url' => $url,
            'error' => $error,
            'error_number' => $errorNumber,
            'connection_info' => $connectionInfo
        ]);
        throw new Exception("cURL error: $error");
    }
    
    if ($httpCode >= 400) {
        logLLMError('http_error', [
            'url' => $url,
            'http_code' => $httpCode,
            'response' => $response,
            'connection_info' => $connectionInfo
        ]);
        throw new Exception("HTTP error $httpCode: $response");
    }
    
    // Log successful requests for debugging
    if (getConfig('general.debug_mode')) {
        logLLMError('request_success', [
            'url' => $url,
            'http_code' => $httpCode,
            'connection_info' => $connectionInfo
        ]);
    }
    
    $decodedResponse = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logLLMError('json_decode_error', [
            'url' => $url,
            'response' => $response,
            'json_error' => json_last_error_msg(),
            'connection_info' => $connectionInfo
        ]);
        throw new Exception("Invalid JSON response: " . json_last_error_msg());
    }
    
    return $decodedResponse;
}

/**
 * Get nested value from array using dot notation
 */
function getNestedValue($array, $path) {
    $keys = explode('.', $path);
    $value = $array;
    foreach ($keys as $key) {
        if (!isset($value[$key])) return null;
        $value = $value[$key];
    }
    return $value;
}

/**
 * Map provider response to universal format
 */
function mapResponseToUniversal($response, $provider) {
    $mappings = [
        'openai' => [
            'response_path' => 'choices.0.message.content',
            'metadata' => [
                'model' => 'model',
                'usage' => 'usage',
                'finish_reason' => 'choices.0.finish_reason'
            ]
        ],
        'anthropic' => [
            'response_path' => 'completion',
            'metadata' => [
                'model' => 'model',
                'stop_reason' => 'stop_reason'
            ]
        ],
        'google' => [
            'response_path' => 'candidates.0.content.parts.0.text',
            'metadata' => [
                'finish_reason' => 'candidates.0.finishReason',
                'usage_metadata' => 'usageMetadata'
            ]
        ],
        'ollama' => [
            'response_path' => 'response',
            'metadata' => [
                'model' => 'model',
                'done' => 'done'
            ]
        ]
    ];
    
    $mapping = $mappings[$provider];
    $responseText = getNestedValue($response, $mapping['response_path']);
    
    if (!$responseText) {
        logLLMError($provider . '_invalid_response_format', [
            'provider' => $provider,
            'response' => $response,
            'error' => "Invalid $provider response format - missing " . $mapping['response_path']
        ]);
        throw new Exception("Invalid $provider response format");
    }
    
    $metadata = ['provider' => $provider];
    foreach ($mapping['metadata'] as $key => $path) {
        $metadata[$key] = getNestedValue($response, $path);
    }
    
    return [
        'response' => $responseText,
        'metadata' => $metadata
    ];
}

/**
 * Map OpenAI response to universal format
 */
function mapOpenAIResponseToUniversal($response) {
    return mapResponseToUniversal($response, 'openai');
}

/**
 * Map Anthropic response to universal format
 */
function mapAnthropicResponseToUniversal($response) {
    return mapResponseToUniversal($response, 'anthropic');
}

/**
 * Map Google response to universal format
 */
function mapGoogleResponseToUniversal($response) {
    return mapResponseToUniversal($response, 'google');
}

/**
 * Map Ollama response to universal format
 */
function mapOllamaResponseToUniversal($response) {
    return mapResponseToUniversal($response, 'ollama');
}

