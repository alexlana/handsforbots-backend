<?php
/**
 * Configuração de Produção para Universal LLM Backend
 * 
 * Este arquivo contém configurações otimizadas para ambiente de produção
 * com medidas de segurança mais rigorosas
 * 
 * @author Alex Lana
 * @version 1.0
 */

// Configurações de API Keys
$config = [
    // OpenAI
    'openai' => [
        'api_key' => getenv('OPENAI_API_KEY'),
        'default_model' => 'gpt-3.5-turbo',
        'available_models' => [
            'gpt-3.5-turbo',
            'gpt-3.5-turbo-16k',
            'gpt-4',
            'gpt-4-turbo'
        ]
    ],
    
    // Anthropic
    'anthropic' => [
        'api_key' => getenv('ANTHROPIC_API_KEY'),
        'default_model' => 'claude-3-sonnet-20240229',
        'available_models' => [
            'claude-3-haiku-20240307',
            'claude-3-sonnet-20240229',
            'claude-3-opus-20240229'
        ]
    ],
    
    // Google
    'google' => [
        'api_key' => getenv('GOOGLE_API_KEY'),
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
            'codellama'
        ]
    ],
    
    // Configurações gerais - PRODUÇÃO
    'general' => [
        'default_provider' => 'auto',
        'default_model' => 'auto',
        'debug_mode' => false, // SEMPRE false em produção
        'enable_logging' => true,
        'log_level' => 'warning', // Apenas warnings e errors em produção
        'request_timeout' => 30,
        'connect_timeout' => 10,
        'max_retries' => 2, // Menos retries em produção
        'max_request_size' => 512 * 1024, // 512KB - mais restritivo
        'max_content_length' => 5000, // 5KB - mais restritivo
        'allowed_providers' => ['openai', 'anthropic', 'google', 'ollama', 'auto'],
        'parameter_limits' => [
            'max_tokens' => ['min' => 1, 'max' => 2000], // Limite menor em produção
            'temperature' => ['min' => 0.0, 'max' => 1.5], // Range menor
            'top_p' => ['min' => 0.0, 'max' => 1.0],
            'penalties' => ['min' => -1.0, 'max' => 1.0] // Range menor
        ]
    ],
    
    // Configurações de segurança - PRODUÇÃO
    'security' => [
        'allowed_origins' => [
            // Configure apenas os domínios que realmente precisam acessar
            'https://seu-dominio.com',
            'https://www.seu-dominio.com',
            'https://app.seu-dominio.com'
        ],
        'allowed_domains' => [
            'api.openai.com',
            'api.anthropic.com',
            'generativelanguage.googleapis.com'
            // Remova localhost e 127.0.0.1 em produção
        ],
        'allowed_api_keys' => [
            // Configure apenas as chaves que realmente precisam
            getenv('FRONTEND_API_KEY'),
            getenv('MOBILE_API_KEY')
        ],
        'require_api_key' => true, // SEMPRE true em produção
        'validate_input' => true,
        'sanitize_output' => true
    ],
    
    // Configurações de rate limiting - PRODUÇÃO
    'rate_limit' => [
        'enabled' => true,
        'window' => 60, // 1 minuto
        'requests_per_window' => 20, // Menos requisições em produção
        'storage' => 'file'
    ],
    
    // Configurações de cache - PRODUÇÃO
    'cache' => [
        'enabled' => true, // Habilitar cache em produção
        'ttl' => 300, // 5 minutos
        'storage' => 'file',
        'path' => '/var/cache/llm_backend'
    ],
    
    // Configurações de MCP - PRODUÇÃO
    'mcp' => [
        'enabled' => true,
        'default_tools' => [
            // Configure apenas as ferramentas necessárias
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

// Função para validar configuração de produção
function validateProductionConfig() {
    $errors = [];
    
    // Verificar se debug está desabilitado
    if (getConfig('general.debug_mode')) {
        $errors[] = "Debug mode deve estar desabilitado em produção";
    }
    
    // Verificar se API key é obrigatória
    if (!getConfig('security.require_api_key')) {
        $errors[] = "API key deve ser obrigatória em produção";
    }
    
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
    
    // Verificar se origens permitidas estão configuradas
    $allowedOrigins = getConfig('security.allowed_origins');
    if (empty($allowedOrigins) || in_array('*', $allowedOrigins)) {
        $errors[] = "Configure origens permitidas específicas em produção";
    }
    
    // Verificar se domínios locais foram removidos
    $allowedDomains = getConfig('security.allowed_domains');
    if (in_array('localhost', $allowedDomains) || in_array('127.0.0.1', $allowedDomains)) {
        $errors[] = "Remova domínios locais das origens permitidas em produção";
    }
    
    // Verificar se chaves de API estão configuradas
    $allowedApiKeys = getConfig('security.allowed_api_keys');
    if (empty($allowedApiKeys)) {
        $errors[] = "Configure chaves de API permitidas em produção";
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
    return getConfig('general.request_timeout') ?: 30;
}

// Função para obter configuração de retry
function getMaxRetries() {
    return getConfig('general.max_retries') ?: 2;
}

// Função para verificar se logging está habilitado
function isLoggingEnabled() {
    return getConfig('general.enable_logging') ?: true;
}

// Função para obter nível de log
function getLogLevel() {
    return getConfig('general.log_level') ?: 'warning';
}

// Função para verificar se cache está habilitado
function isCacheEnabled() {
    return getConfig('cache.enabled') ?: true;
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
    $allowedOrigins = getConfig('security.allowed_origins') ?: [];
    return in_array($origin, $allowedOrigins);
}

// Função para verificar se API key é obrigatória
function isApiKeyRequired() {
    return getConfig('security.require_api_key') ?: true;
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
        'requests_per_window' => 20,
        'storage' => 'file'
    ];
}

// Função para obter domínios permitidos
function getAllowedDomains() {
    return getConfig('security.allowed_domains') ?: [
        'api.openai.com',
        'api.anthropic.com',
        'generativelanguage.googleapis.com'
    ];
}

// Função para obter chaves de API permitidas
function getAllowedApiKeys() {
    return getConfig('security.allowed_api_keys') ?: [];
}

// Função para obter limites de parâmetros
function getParameterLimits() {
    return getConfig('general.parameter_limits') ?: [
        'max_tokens' => ['min' => 1, 'max' => 2000],
        'temperature' => ['min' => 0.0, 'max' => 1.5],
        'top_p' => ['min' => 0.0, 'max' => 1.0],
        'penalties' => ['min' => -1.0, 'max' => 1.0]
    ];
}

// Função para obter provedores permitidos
function getAllowedProviders() {
    return getConfig('general.allowed_providers') ?: ['openai', 'anthropic', 'google', 'ollama'];
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
    return getConfig('general.max_request_size') ?: 512 * 1024;
}

// Função para obter tamanho máximo de conteúdo
function getMaxContentLength() {
    return getConfig('general.max_content_length') ?: 5000;
}

// Função para verificar se debug está habilitado
function isDebugMode() {
    return getConfig('general.debug_mode') ?: false;
}

// Validação automática em produção
$productionErrors = validateProductionConfig();
if (!empty($productionErrors)) {
    error_log("ERRO DE CONFIGURAÇÃO DE PRODUÇÃO:");
    foreach ($productionErrors as $error) {
        error_log("  - $error");
    }
    // Em produção, você pode querer parar a execução aqui
    // exit(1);
}

?>
