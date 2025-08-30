<?php
/**
 * Exemplo de configuração para o Universal LLM Backend
 * 
 * Copie este arquivo para config.php e configure suas chaves de API
 */

// Configurações de API Keys
// Configure suas chaves de API aqui ou use variáveis de ambiente
$config = [
    // OpenAI
    'openai' => [
        'api_key' => getenv('OPENAI_API_KEY') ?: 'sk-your-openai-key-here',
        'default_model' => 'gpt-3.5-turbo',
        'available_models' => [
            'gpt-3.5-turbo',
            'gpt-3.5-turbo-16k',
            'gpt-4',
            'gpt-4-turbo',
            'gpt-4-turbo-preview'
        ]
    ],
    
    // Anthropic
    'anthropic' => [
        'api_key' => getenv('ANTHROPIC_API_KEY') ?: 'sk-ant-your-anthropic-key-here',
        'default_model' => 'claude-3-sonnet-20240229',
        'available_models' => [
            'claude-3-haiku-20240307',
            'claude-3-sonnet-20240229',
            'claude-3-opus-20240229'
        ]
    ],
    
    // Google
    'google' => [
        'api_key' => getenv('GOOGLE_API_KEY') ?: 'your-google-api-key-here',
        'default_model' => 'gemini-1.5-flash',
        'available_models' => [
            'gemini-1.5-flash',
            'gemini-1.5-pro',
            'gemini-pro'
        ]
    ],
    
    // Ollama
    'ollama' => [
        'endpoint' => getenv('OLLAMA_ENDPOINT') ?: 'http://localhost:11434',
        'default_model' => 'llama2',
        'available_models' => [
            'llama2',
            'llama2:13b',
            'llama2:70b',
            'mistral',
            'codellama',
            'neural-chat'
        ]
    ],
    
    // Configurações gerais
    'general' => [
        'default_provider' => 'auto',
        'default_model' => 'auto',
        'debug_mode' => false,
        'enable_logging' => true,
        'log_level' => 'info', // debug, info, warning, error
        'request_timeout' => 30,
        'connect_timeout' => 10,
        'max_retries' => 3,
        'max_request_size' => 1024 * 1024, // 1MB
        'max_content_length' => 10000, // 10KB per message
        'allowed_providers' => ['openai', 'anthropic', 'google', 'ollama', 'auto', 'gpt', 'claude', 'gemini'],
        'parameter_limits' => [
            'max_tokens' => ['min' => 1, 'max' => 4000],
            'temperature' => ['min' => 0.0, 'max' => 2.0],
            'top_p' => ['min' => 0.0, 'max' => 1.0],
            'penalties' => ['min' => -2.0, 'max' => 2.0]
        ]
    ],
    
    // Configurações de segurança
    'security' => [
        'allowed_origins' => [
            'http://localhost:3000',
            'http://localhost:8080',
            'https://seu-dominio.com',
            'https://www.seu-dominio.com'
        ],
        'allowed_domains' => [
            'api.openai.com',
            'api.anthropic.com',
            'generativelanguage.googleapis.com',
            'localhost',
            '127.0.0.1'
        ],
        'allowed_api_keys' => [
            'your-secure-api-key-here',
            // Add more keys as needed
        ],
        'require_api_key' => false, // Se true, todas as requisições precisam de api_key
        'validate_input' => true,
        'sanitize_output' => true
    ],
    
    // Configurações de rate limiting
    'rate_limit' => [
        'enabled' => true,
        'window' => 60, // seconds
        'requests_per_window' => 30, // requests per window
        'storage' => 'file' // file, redis, memcached
    ],
    
    // Configurações de cache
    'cache' => [
        'enabled' => false,
        'ttl' => 300, // 5 minutos
        'storage' => 'file', // file, redis, memcached
        'path' => '/tmp/llm_cache'
    ],
    
    // Configurações de MCP
    'mcp' => [
        'enabled' => true,
        'default_tools' => [
            'search_web' => [
                'name' => 'search_web',
                'description' => 'Search the web for information',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Search query'
                        ]
                    ],
                    'required' => ['query']
                ]
            ],
            'get_weather' => [
                'name' => 'get_weather',
                'description' => 'Get current weather information',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'location' => [
                            'type' => 'string',
                            'description' => 'City name or coordinates'
                        ]
                    ],
                    'required' => ['location']
                ]
            ]
        ]
    ]
];

// Função para obter configuração
function getConfig($key = null) {
    global $config;
    
    if ($key === null) {
        return $config;
    }
    
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            return null;
        }
    }
    
    return $value;
}

// Função para validar configuração
function validateConfig() {
    $errors = [];
    
    // Verificar se pelo menos um provedor está configurado
    $providers = ['openai', 'anthropic', 'google', 'ollama'];
    $hasProvider = false;
    
    foreach ($providers as $provider) {
        if (getConfig("$provider.api_key") && getConfig("$provider.api_key") !== "sk-your-$provider-key-here") {
            $hasProvider = true;
            break;
        }
    }
    
    if (!$hasProvider) {
        $errors[] = "Pelo menos um provedor de API deve estar configurado";
    }
    
    // Verificar configurações de segurança
    if (getConfig('security.allowed_origins') === ['*']) {
        $errors[] = "Configuração de segurança muito permissiva. Configure allowed_origins específicos em produção.";
    }
    
    return $errors;
}

// Função para obter provedores disponíveis
function getAvailableProviders() {
    $providers = [];
    $config = getConfig();
    
    foreach (['openai', 'anthropic', 'google', 'ollama'] as $provider) {
        if (isset($config[$provider]['api_key']) && 
            $config[$provider]['api_key'] && 
            $config[$provider]['api_key'] !== "sk-your-$provider-key-here") {
            $providers[] = $provider;
        }
    }
    
    return $providers;
}

// Função para obter modelos disponíveis por provedor
function getAvailableModels($provider) {
    $config = getConfig();
    
    if (isset($config[$provider]['available_models'])) {
        return $config[$provider]['available_models'];
    }
    
    return [];
}

// Função para obter modelo padrão por provedor
function getDefaultModel($provider) {
    $config = getConfig();
    
    if (isset($config[$provider]['default_model'])) {
        return $config[$provider]['default_model'];
    }
    
    return 'auto';
}

// Função para obter API key por provedor
function getApiKey($provider) {
    $config = getConfig();
    
    if (isset($config[$provider]['api_key'])) {
        return $config[$provider]['api_key'];
    }
    
    return null;
}

// Função para verificar se um provedor está disponível
function isProviderAvailable($provider) {
    $apiKey = getApiKey($provider);
    return $apiKey && $apiKey !== "sk-your-$provider-key-here";
}

// Função para obter configuração de timeout
function getTimeout() {
    return getConfig('general.timeout') ?: 30;
}

// Função para obter configuração de retry
function getMaxRetries() {
    return getConfig('general.max_retries') ?: 3;
}

// Função para verificar se logging está habilitado
function isLoggingEnabled() {
    return getConfig('general.enable_logging') ?: true;
}

// Função para obter nível de log
function getLogLevel() {
    return getConfig('general.log_level') ?: 'info';
}

// Função para verificar se cache está habilitado
function isCacheEnabled() {
    return getConfig('cache.enabled') ?: false;
}

// Função para obter TTL do cache
function getCacheTTL() {
    return getConfig('cache.ttl') ?: 300;
}

// Função para verificar se MCP está habilitado
function isMCPEnabled() {
    return getConfig('mcp.enabled') ?: true;
}

// Função para obter ferramentas MCP padrão
function getDefaultMCPTools() {
    return getConfig('mcp.default_tools') ?: [];
}

// Função para validar origem da requisição
function validateOrigin($origin) {
    $allowedOrigins = getConfig('security.allowed_origins') ?: ['*'];
    
    if (in_array('*', $allowedOrigins)) {
        return true;
    }
    
    return in_array($origin, $allowedOrigins);
}

// Função para verificar se API key é obrigatória
function isApiKeyRequired() {
    return getConfig('security.require_api_key') ?: false;
}

// Função para verificar se validação de entrada está habilitada
function isInputValidationEnabled() {
    return getConfig('security.validate_input') ?: true;
}

// Função para verificar se sanitização de saída está habilitada
function isOutputSanitizationEnabled() {
    return getConfig('security.sanitize_output') ?: true;
}

// Função para verificar se rate limiting está habilitado
function isRateLimitEnabled() {
    return getConfig('rate_limit.enabled') ?: true;
}

// Função para obter configuração de rate limiting
function getRateLimitConfig() {
    return getConfig('rate_limit') ?: [
        'enabled' => true,
        'window' => 60,
        'requests_per_window' => 30,
        'storage' => 'file'
    ];
}

// Função para obter domínios permitidos
function getAllowedDomains() {
    return getConfig('security.allowed_domains') ?: [
        'api.openai.com',
        'api.anthropic.com',
        'generativelanguage.googleapis.com',
        'localhost',
        '127.0.0.1'
    ];
}

// Função para obter chaves de API permitidas
function getAllowedApiKeys() {
    return getConfig('security.allowed_api_keys') ?: [];
}

// Função para obter limites de parâmetros
function getParameterLimits() {
    return getConfig('general.parameter_limits') ?: [
        'max_tokens' => ['min' => 1, 'max' => 4000],
        'temperature' => ['min' => 0.0, 'max' => 2.0],
        'top_p' => ['min' => 0.0, 'max' => 1.0],
        'penalties' => ['min' => -2.0, 'max' => 2.0]
    ];
}

// Função para obter provedores permitidos
function getAllowedProviders() {
    return getConfig('general.allowed_providers') ?: ['openai', 'anthropic', 'google', 'ollama', 'auto'];
}

// Função para obter configuração de timeout
function getRequestTimeout() {
    return getConfig('general.request_timeout') ?: 30;
}

// Função para obter configuração de timeout de conexão
function getConnectTimeout() {
    return getConfig('general.connect_timeout') ?: 10;
}

// Função para obter tamanho máximo de requisição
function getMaxRequestSize() {
    return getConfig('general.max_request_size') ?: 1024 * 1024;
}

// Função para obter tamanho máximo de conteúdo
function getMaxContentLength() {
    return getConfig('general.max_content_length') ?: 10000;
}

// Função para verificar se debug está habilitado
function isDebugMode() {
    return getConfig('general.debug_mode') ?: false;
}

// Exemplo de uso:
/*
// Incluir este arquivo no seu backend
require_once 'config.php';

// Validar configuração
$errors = validateConfig();
if (!empty($errors)) {
    foreach ($errors as $error) {
        error_log("Config error: $error");
    }
}

// Obter provedores disponíveis
$providers = getAvailableProviders();
echo "Provedores disponíveis: " . implode(', ', $providers);

// Verificar se um provedor está disponível
if (isProviderAvailable('openai')) {
    echo "OpenAI está disponível";
}

// Obter API key
$apiKey = getApiKey('openai');
echo "API Key: $apiKey";

// Verificar configurações de segurança
if (isRateLimitEnabled()) {
    echo "Rate limiting está habilitado";
}

$timeout = getRequestTimeout();
echo "Timeout de requisição: $timeout segundos";
*/
