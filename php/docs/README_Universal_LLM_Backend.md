# Universal LLM Backend - Padrão Universal para APIs de LLMs

## Visão Geral

O **Universal LLM Backend** é uma solução que padroniza a comunicação com diferentes APIs de LLMs (Large Language Models) através de um formato universal. Isso permite que o frontend JavaScript se comunique com qualquer provedor de IA (OpenAI, Anthropic, Google, Ollama, etc.) usando uma única interface.

## Arquitetura

```
Frontend JavaScript (UniversalLLMBackend.js)
           ↓
    Formato Universal
           ↓
Backend PHP (universal_llm_backend.php)
           ↓
    Mapeamento para APIs específicas
           ↓
    OpenAI | Anthropic | Google | Ollama | etc.
```

## Formato Universal de Requisição

### Estrutura da Requisição

```json
{
  "request_id": "req_1234567890_abc123",
  "session_id": "session_1234567890_xyz789",
  "timestamp": "2024-01-15T10:30:00.000Z",
  
  "provider": "auto",
  "model": "auto",
  "api_key": "optional-api-key",
  
  "messages": [
    {
      "role": "user",
      "content": "Olá, como você está?",
      "timestamp": "2024-01-15T10:30:00.000Z"
    }
  ],
  
  "parameters": {
    "max_tokens": 1024,
    "temperature": 0.7,
    "top_p": 0.9,
    "frequency_penalty": 0.0,
    "presence_penalty": 0.0,
    "stream": false
  },
  
  "context": {
    "system_prompt": "Você é um assistente útil...",
    "conversation_history": [],
    "mcp_context": {
      "tools": [],
      "available_models": [],
      "available_functions": []
    }
  },
  
  "options": {
    "include_usage": true,
    "include_metadata": true,
    "response_format": "text"
  }
}
```

### Campos Obrigatórios

- **provider**: Provedor da IA (`auto`, `openai`, `anthropic`, `google`, `ollama`)
- **model**: Modelo específico (`auto` para detecção automática)
- **messages**: Array de mensagens da conversa
- **parameters**: Parâmetros de geração

### Campos Opcionais

- **api_key**: Chave da API (pode ser configurada no backend)
- **context**: Contexto adicional (system prompt, histórico, MCP)
- **options**: Opções de resposta

## Formato Universal de Resposta

### Estrutura da Resposta

```json
{
  "response": "Olá! Estou bem, obrigado por perguntar. Como posso ajudá-lo hoje?",
  "metadata": {
    "provider": "openai",
    "model": "gpt-3.5-turbo",
    "usage": {
      "prompt_tokens": 10,
      "completion_tokens": 20,
      "total_tokens": 30
    },
    "finish_reason": "stop"
  }
}
```

### Campos da Resposta

- **response**: Texto da resposta da IA
- **metadata**: Metadados específicos do provedor

## Configuração do Backend PHP

### Variáveis de Ambiente

```bash
# OpenAI
OPENAI_API_KEY=sk-your-openai-key

# Anthropic
ANTHROPIC_API_KEY=sk-ant-your-anthropic-key

# Google
GOOGLE_API_KEY=your-google-api-key

# Ollama (opcional)
OLLAMA_ENDPOINT=http://localhost:11434
```

### Instalação

1. Coloque o arquivo `universal_llm_backend.php` no seu servidor web
2. Configure as variáveis de ambiente com suas chaves de API
3. Certifique-se de que o PHP tem a extensão cURL habilitada

## Configuração do Frontend JavaScript

### Exemplo de Uso

```javascript
// Configuração do bot com o backend universal
const bot = new Bot({
    engine: 'universal-llm-backend',
    endpoint: 'https://seu-dominio.com/backend/universal_llm_backend.php',
    provider: 'auto', // ou 'openai', 'anthropic', 'google', 'ollama'
    model: 'auto',    // ou modelo específico
    apiKey: null,     // opcional, pode ser configurado no backend
    sessionId: null   // opcional, será gerado automaticamente
});
```

### Configurações Específicas por Provedor

```javascript
// OpenAI
const botOpenAI = new Bot({
    engine: 'universal-llm-backend',
    endpoint: 'https://seu-dominio.com/backend/universal_llm_backend.php',
    provider: 'openai',
    model: 'gpt-4'
});

// Anthropic
const botAnthropic = new Bot({
    engine: 'universal-llm-backend',
    endpoint: 'https://seu-dominio.com/backend/universal_llm_backend.php',
    provider: 'anthropic',
    model: 'claude-3-sonnet-20240229'
});

// Google Gemini
const botGoogle = new Bot({
    engine: 'universal-llm-backend',
    endpoint: 'https://seu-dominio.com/backend/universal_llm_backend.php',
    provider: 'google',
    model: 'gemini-1.5-flash'
});

// Ollama
const botOllama = new Bot({
    engine: 'universal-llm-backend',
    endpoint: 'https://seu-dominio.com/backend/universal_llm_backend.php',
    provider: 'ollama',
    model: 'llama2'
});
```

## Mapeamento de Parâmetros

### Parâmetros Universais → OpenAI

| Universal | OpenAI | Descrição |
|-----------|--------|-----------|
| max_tokens | max_tokens | Máximo de tokens na resposta |
| temperature | temperature | Criatividade (0.0-2.0) |
| top_p | top_p | Nucleus sampling |
| frequency_penalty | frequency_penalty | Penalidade por repetição |
| presence_penalty | presence_penalty | Penalidade por presença |

### Parâmetros Universais → Anthropic

| Universal | Anthropic | Descrição |
|-----------|-----------|-----------|
| max_tokens | max_tokens | Máximo de tokens na resposta |
| temperature | temperature | Criatividade (0.0-1.0) |
| top_p | top_p | Nucleus sampling |

### Parâmetros Universais → Google

| Universal | Google | Descrição |
|-----------|--------|-----------|
| max_tokens | maxOutputTokens | Máximo de tokens na resposta |
| temperature | temperature | Criatividade (0.0-2.0) |
| top_p | topP | Nucleus sampling |

### Parâmetros Universais → Ollama

| Universal | Ollama | Descrição |
|-----------|--------|-----------|
| max_tokens | num_predict | Máximo de tokens na resposta |
| temperature | temperature | Criatividade (0.0-2.0) |
| top_p | top_p | Nucleus sampling |

## Suporte a MCP (Model Context Protocol)

O backend universal suporta MCP através do campo `mcp_context`:

```json
{
  "context": {
    "mcp_context": {
      "tools": [
        {
          "name": "search_web",
          "description": "Search the web for information",
          "parameters": {
            "type": "object",
            "properties": {
              "query": {
                "type": "string",
                "description": "Search query"
              }
            }
          }
        }
      ],
      "available_models": ["gpt-4", "claude-3", "gemini-1.5"],
      "available_functions": ["function1", "function2"]
    }
  }
}
```

## Tratamento de Erros

### Códigos de Erro

- **400**: Dados de entrada inválidos
- **401**: Chave de API inválida ou ausente
- **403**: Acesso negado
- **404**: Provedor não encontrado
- **429**: Rate limit excedido
- **500**: Erro interno do servidor

### Formato de Erro

```json
{
  "error": "Internal server error",
  "message": "Detalhes específicos do erro",
  "request_id": "req_1234567890_abc123"
}
```

## Logs e Monitoramento

O backend registra logs para:

- Requisições recebidas (Request ID, Session ID)
- Erros de API
- Tempo de resposta
- Uso de tokens

### Exemplo de Log

```
[2024-01-15 10:30:00] Request ID: req_1234567890_abc123, Session ID: session_1234567890_xyz789
[2024-01-15 10:30:01] Provider: openai, Model: gpt-3.5-turbo, Tokens: 30
```

## Segurança

### Recomendações

1. **HTTPS**: Sempre use HTTPS em produção
2. **Rate Limiting**: Implemente rate limiting no servidor web
3. **API Keys**: Configure as chaves de API no backend, não no frontend
4. **Validação**: Valide todas as entradas do usuário
5. **Logs**: Monitore logs para detectar uso anômalo

### Headers de Segurança

```php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
```

## Extensibilidade

### Adicionando Novos Provedores

Para adicionar um novo provedor:

1. Crie uma função `callNewProvider()` no PHP
2. Adicione o case no switch do `processUniversalRequest()`
3. Crie funções de mapeamento específicas
4. Atualize a documentação

### Exemplo: Adicionando Cohere

```php
case 'cohere':
    return callCohere($model, $messages, $parameters, $context, $options);

function callCohere($model, $messages, $parameters, $context, $options) {
    // Implementação específica do Cohere
}

function mapCohereResponseToUniversal($response) {
    // Mapeamento da resposta do Cohere
}
```

## Performance

### Otimizações

1. **Connection Pooling**: Reutilize conexões HTTP
2. **Caching**: Cache respostas frequentes
3. **Async**: Use requisições assíncronas quando possível
4. **Compression**: Comprima respostas grandes

### Métricas

- Tempo de resposta médio
- Taxa de sucesso
- Uso de tokens por requisição
- Latência por provedor

## Exemplos de Uso

### Chat Simples

```javascript
const response = await bot.backend.send(false, "Olá, como você está?");
console.log(response[0].text);
```

### Chat com Contexto

```javascript
const response = await bot.backend.send(false, {
    role: 'user',
    content: 'Explique sobre inteligência artificial',
    context: {
        system_prompt: 'Você é um especialista em IA'
    }
});
```

### Chat com MCP

```javascript
// O MCP é automaticamente incluído se configurado no bot
const response = await bot.backend.send(false, "Busque informações sobre PHP");
// O backend processará automaticamente as ferramentas MCP
```

## Troubleshooting

### Problemas Comuns

1. **Erro 401**: Verifique se as chaves de API estão configuradas
2. **Erro 404**: Verifique se o endpoint está correto
3. **Timeout**: Aumente o timeout do cURL se necessário
4. **Rate Limit**: Implemente retry com backoff exponencial

### Debug

Ative logs detalhados no PHP:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Contribuição

Para contribuir com melhorias:

1. Fork o repositório
2. Crie uma branch para sua feature
3. Implemente as mudanças
4. Adicione testes
5. Documente as mudanças
6. Faça um pull request

## Licença

Este projeto está sob a licença MIT. Veja o arquivo LICENSE para detalhes.
