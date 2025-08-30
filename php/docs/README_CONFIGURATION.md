# 🔧 Guia de Configuração - Universal LLM Backend

## 📋 Visão Geral

O Universal LLM Backend agora usa um sistema de configuração centralizado que permite personalizar todos os aspectos do sistema de forma segura e flexível.

## 🚀 Configuração Rápida

### 1. **Configuração Básica (Desenvolvimento)**

```bash
# Copie o arquivo de exemplo
cp config.example.php config.php

# Edite as configurações
nano config.php
```

### 2. **Configuração de Produção**

```bash
# Use o arquivo de configuração de produção
cp config.production.php config.php

# Configure as variáveis de ambiente
export OPENAI_API_KEY="sk-your-key"
export ANTHROPIC_API_KEY="sk-ant-your-key"
export GOOGLE_API_KEY="your-google-key"
export FRONTEND_API_KEY="your-frontend-key"
```

## 📁 Arquivos de Configuração

### **config.example.php**
- Configuração de exemplo para desenvolvimento
- Configurações mais permissivas
- Inclui todos os parâmetros configuráveis

### **config.production.php**
- Configuração otimizada para produção
- Medidas de segurança mais rigorosas
- Validação automática de configuração

### **config.php**
- Arquivo de configuração ativo
- Não commitar no git (contém chaves secretas)
- Criado a partir dos arquivos de exemplo

## ⚙️ Parâmetros de Configuração

### **Configurações Gerais**

```php
'general' => [
    'debug_mode' => false,              // Modo debug (sempre false em produção)
    'enable_logging' => true,           // Habilitar logs
    'log_level' => 'info',              // debug, info, warning, error
    'request_timeout' => 30,            // Timeout de requisição (segundos)
    'connect_timeout' => 10,            // Timeout de conexão (segundos)
    'max_retries' => 3,                 // Máximo de tentativas
    'max_request_size' => 1024 * 1024,  // Tamanho máximo de requisição (1MB)
    'max_content_length' => 10000,      // Tamanho máximo de conteúdo (10KB)
    'allowed_providers' => [...],       // Provedores permitidos
    'parameter_limits' => [...]         // Limites de parâmetros
]
```

### **Configurações de Segurança**

```php
'security' => [
    'allowed_origins' => [...],         // Origens CORS permitidas
    'allowed_domains' => [...],         // Domínios permitidos para requisições
    'allowed_api_keys' => [...],        // Chaves de API permitidas
    'require_api_key' => false,         // API key obrigatória
    'validate_input' => true,           // Validar entrada
    'sanitize_output' => true           // Sanitizar saída
]
```

### **Configurações de Rate Limiting**

```php
'rate_limit' => [
    'enabled' => true,                  // Habilitar rate limiting
    'window' => 60,                     // Janela de tempo (segundos)
    'requests_per_window' => 30,        // Requisições por janela
    'storage' => 'file'                 // Armazenamento (file, redis, memcached)
]
```

### **Configurações de Cache**

```php
'cache' => [
    'enabled' => false,                 // Habilitar cache
    'ttl' => 300,                       // Tempo de vida (segundos)
    'storage' => 'file',                // Armazenamento
    'path' => '/tmp/llm_cache'          // Caminho do cache
]
```

## 🔐 Configuração de Segurança

### **Variáveis de Ambiente**

```bash
# Chaves de API dos provedores
OPENAI_API_KEY=sk-your-openai-key
ANTHROPIC_API_KEY=sk-ant-your-anthropic-key
GOOGLE_API_KEY=your-google-api-key
OLLAMA_ENDPOINT=http://localhost:11434

# Chaves de API do frontend
FRONTEND_API_KEY=your-frontend-api-key
MOBILE_API_KEY=your-mobile-api-key

# Configurações de ambiente
DEBUG_MODE=false
LOG_LEVEL=warning
```

### **Origens Permitidas (CORS)**

```php
'allowed_origins' => [
    'http://localhost:3000',           // Desenvolvimento
    'http://localhost:8080',           // Desenvolvimento
    'https://seu-dominio.com',         // Produção
    'https://www.seu-dominio.com'      // Produção
]
```

### **Domínios Permitidos**

```php
'allowed_domains' => [
    'api.openai.com',                  // OpenAI
    'api.anthropic.com',               // Anthropic
    'generativelanguage.googleapis.com', // Google
    'localhost',                       // Desenvolvimento
    '127.0.0.1'                       // Desenvolvimento
]
```

## 📊 Limites de Parâmetros

### **Limites Padrão**

```php
'parameter_limits' => [
    'max_tokens' => ['min' => 1, 'max' => 4000],
    'temperature' => ['min' => 0.0, 'max' => 2.0],
    'top_p' => ['min' => 0.0, 'max' => 1.0],
    'penalties' => ['min' => -2.0, 'max' => 2.0]
]
```

### **Limites de Produção (Mais Restritivos)**

```php
'parameter_limits' => [
    'max_tokens' => ['min' => 1, 'max' => 2000],
    'temperature' => ['min' => 0.0, 'max' => 1.5],
    'top_p' => ['min' => 0.0, 'max' => 1.0],
    'penalties' => ['min' => -1.0, 'max' => 1.0]
]
```

## 🔧 Funções de Configuração

### **Funções Principais**

```php
// Obter configuração
$value = getConfig('security.allowed_origins');

// Verificar se funcionalidade está habilitada
if (isRateLimitEnabled()) {
    // Rate limiting está ativo
}

// Obter configurações específicas
$timeout = getRequestTimeout();
$maxSize = getMaxRequestSize();
$providers = getAllowedProviders();
```

### **Funções de Validação**

```php
// Validar configuração
$errors = validateConfig();

// Validar configuração de produção
$errors = validateProductionConfig();

// Verificar provedores disponíveis
$providers = getAvailableProviders();
```

## 🚀 Exemplos de Configuração

### **Desenvolvimento Local**

```php
// config.php para desenvolvimento
$config = [
    'general' => [
        'debug_mode' => true,
        'log_level' => 'debug',
        'max_request_size' => 2 * 1024 * 1024, // 2MB
        'max_content_length' => 20000, // 20KB
    ],
    'security' => [
        'allowed_origins' => ['http://localhost:3000', 'http://localhost:8080'],
        'allowed_domains' => ['localhost', '127.0.0.1', 'api.openai.com'],
        'require_api_key' => false,
    ],
    'rate_limit' => [
        'enabled' => false, // Desabilitar em desenvolvimento
    ]
];
```

### **Produção**

```php
// config.php para produção
$config = [
    'general' => [
        'debug_mode' => false,
        'log_level' => 'warning',
        'max_request_size' => 512 * 1024, // 512KB
        'max_content_length' => 5000, // 5KB
    ],
    'security' => [
        'allowed_origins' => ['https://seu-dominio.com'],
        'allowed_domains' => ['api.openai.com', 'api.anthropic.com'],
        'require_api_key' => true,
        'allowed_api_keys' => [getenv('FRONTEND_API_KEY')],
    ],
    'rate_limit' => [
        'enabled' => true,
        'requests_per_window' => 20, // Mais restritivo
    ]
];
```

## 🔍 Monitoramento e Logs

### **Níveis de Log**

- **debug**: Todas as informações (desenvolvimento)
- **info**: Informações gerais (desenvolvimento)
- **warning**: Apenas warnings e errors (produção)
- **error**: Apenas errors (produção crítica)

### **Eventos Logados**

```php
// Eventos de segurança
logSecurityEvent('unauthorized_origin', ['origin' => $origin]);
logSecurityEvent('invalid_api_key', ['key' => substr($key, 0, 10)]);
logSecurityEvent('rate_limit_exceeded', ['client_id' => $clientId]);

// Eventos de requisição
logSecurityEvent('request_received', ['provider' => $provider]);
logSecurityEvent('request_processed', ['provider' => $provider]);
logSecurityEvent('error', ['message' => $error]);
```

## 🧪 Testes de Configuração

### **Executar Testes de Segurança**

```bash
# Testar configuração atual
php security_test.php http://localhost

# Testar com API key
php security_test.php http://localhost your-api-key
```

### **Validar Configuração**

```php
// Validar configuração básica
$errors = validateConfig();

// Validar configuração de produção
$errors = validateProductionConfig();

if (!empty($errors)) {
    foreach ($errors as $error) {
        error_log("Config error: $error");
    }
}
```

## 🔄 Migração de Configurações

### **Da Versão Anterior**

Se você estava usando a versão anterior sem configuração centralizada:

1. **Copie o arquivo de exemplo**:
   ```bash
   cp config.example.php config.php
   ```

2. **Configure suas chaves de API**:
   ```php
   'openai' => [
       'api_key' => 'sua-chave-openai-aqui',
   ],
   ```

3. **Ajuste as configurações de segurança**:
   ```php
   'security' => [
       'allowed_origins' => ['http://localhost:3000'],
       'require_api_key' => false, // Para desenvolvimento
   ],
   ```

### **Para Produção**

1. **Use o arquivo de produção**:
   ```bash
   cp config.production.php config.php
   ```

2. **Configure variáveis de ambiente**:
   ```bash
   export OPENAI_API_KEY="sk-your-key"
   export FRONTEND_API_KEY="your-frontend-key"
   ```

3. **Ajuste origens permitidas**:
   ```php
   'allowed_origins' => ['https://seu-dominio.com'],
   ```

## 📚 Recursos Adicionais

- [Guia de Segurança](SECURITY_GUIDE.md)
- [Exemplo de Uso](exemplo_uso_universal_backend.html)
- [Testes de Segurança](security_test.php)

## 🆘 Troubleshooting

### **Problemas Comuns**

1. **Erro de configuração**:
   - Verifique se `config.php` existe
   - Execute `validateConfig()` para verificar erros

2. **CORS errors**:
   - Verifique `allowed_origins` em `security`
   - Adicione seu domínio à lista

3. **Rate limiting**:
   - Ajuste `requests_per_window` em `rate_limit`
   - Desabilite com `'enabled' => false` para desenvolvimento

4. **API key errors**:
   - Configure `allowed_api_keys` em `security`
   - Defina `require_api_key` como `false` para desenvolvimento

### **Logs de Debug**

```php
// Habilitar logs detalhados
'general' => [
    'debug_mode' => true,
    'log_level' => 'debug',
    'enable_logging' => true,
]
```

Este sistema de configuração centralizado torna o backend muito mais flexível e seguro, permitindo adaptar facilmente para diferentes ambientes e necessidades.
