# MCP (Model Context Protocol) Integration

Este documento explica como usar o sistema MCP integrado com o Universal LLM Backend PHP.

## Visão Geral

O sistema MCP permite que o LLM use ferramentas específicas durante a conversa. O backend PHP processa as chamadas de ferramentas e executa as ações correspondentes.

## Arquitetura

```
Frontend (Bot.js) → UniversalLLM.js → Backend PHP → LLM Provider
     ↓                    ↓              ↓
ShowRelevantContent → MCPHelper → MCP Processing
```

### Componentes

1. **ShowRelevantContent.js**: Plugin que registra ferramentas MCP
2. **MCPHelper.js**: Gerencia ferramentas MCP no frontend
3. **UniversalLLM.js**: Envia contexto MCP para o backend
4. **universal_llm_backend.php**: Processa e executa ferramentas MCP

## Configuração

### 1. Habilitar MCP no Backend

No arquivo `config.php`, certifique-se de que MCP está habilitado:

```php
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
        ]
    ]
]
```

### 2. Configurar Ferramentas no Frontend

No `ShowRelevantContent.js`, as ferramentas são registradas automaticamente:

```javascript
this.bot.mcp.availableTools.push({
    name: 'show_relevant_content',
    description: `Show content relevant to the user's question. Available options: ${this.elements.join(', ')}`,
    parameters: {
        type: 'object',
        properties: {
            query: {
                type: 'string',
                description: `The query to use to find the element. Available options: ${this.elements.join(', ')}`,
                enum: this.elements
            }
        },
        required: ['query']
    },
    execute: async (params) => {
        return await this.executeShowRelevantContent(params.query)
    }
})
```

## Uso

### 1. Consultar Ferramentas Disponíveis

```bash
curl -X GET http://localhost:8080/universal_llm_backend.php
```

Resposta:
```json
{
    "mcp": {
        "enabled": true,
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
                    },
                    "required": ["query"]
                }
            }
        ],
        "show_relevant_content_tool": {
            "name": "show_relevant_content",
            "description": "Show content relevant to the user's question by scrolling to specific sections on the page",
            "parameters": {
                "type": "object",
                "properties": {
                    "query": {
                        "type": "string",
                        "description": "The query to use to find the element to scroll to"
                    }
                },
                "required": ["query"]
            }
        }
    },
    "timestamp": "2024-01-15 10:30:00"
}
```

### 2. Enviar Requisição com MCP

```bash
curl -X POST http://localhost:8080/universal_llm_backend.php \
  -H "Content-Type: application/json" \
  -H "X-Request-ID: req_123" \
  -H "X-Session-ID: session_456" \
  -d '{
    "provider": "openai",
    "model": "gpt-3.5-turbo",
    "messages": [
      {
        "role": "user",
        "content": "Mostre a seção sobre Docker no site"
      }
    ],
    "parameters": {
      "max_tokens": 1024,
      "temperature": 0.7
    },
    "context": {
      "mcp_context": {
        "tools": [
          {
            "name": "show_relevant_content",
            "description": "Show content relevant to the user question",
            "parameters": {
              "type": "object",
              "properties": {
                "query": {
                  "type": "string",
                  "description": "The query to find the element",
                  "enum": ["docker", "php", "javascript", "python"]
                }
              },
              "required": ["query"]
            }
          }
        ]
      }
    }
  }'
```

### 3. Resposta com Tool Calls

O LLM pode responder com tool calls:

```
<tool>
{
  "name": "show_relevant_content",
  "parameters": {
    "query": "docker"
  }
}
</tool>

Agora vou mostrar a seção sobre Docker para você...
```

### 4. Resposta Processada

O backend processa as tool calls e retorna:

```json
{
    "response": [
        {
            "recipient_id": "user",
            "text": "Executando show_relevant_content...",
            "tool_result": {
                "success": true,
                "message": "Scrolled to content: docker",
                "action": "scroll_to_content",
                "query": "docker",
                "timestamp": "2024-01-15 10:30:00"
            },
            "type": "tool_execution"
        },
        {
            "recipient_id": "user",
            "text": "Agora vou mostrar a seção sobre Docker para você...",
            "type": "text"
        }
    ],
    "metadata": {
        "provider": "openai",
        "model": "gpt-3.5-turbo",
        "mcp_processed": true,
        "tool_calls": [
            {
                "name": "show_relevant_content",
                "parameters": {
                    "query": "docker"
                }
            }
        ],
        "tool_results": [
            {
                "tool": "show_relevant_content",
                "parameters": {
                    "query": "docker"
                },
                "result": {
                    "success": true,
                    "message": "Scrolled to content: docker",
                    "action": "scroll_to_content",
                    "query": "docker",
                    "timestamp": "2024-01-15 10:30:00"
                },
                "success": true
            }
        ]
    }
}
```

## Ferramentas Disponíveis

### 1. show_relevant_content

Permite rolar para seções específicas do site.

**Parâmetros:**
- `query` (string, obrigatório): Query para encontrar o elemento

**Exemplo:**
```json
{
    "name": "show_relevant_content",
    "parameters": {
        "query": "docker"
    }
}
```

### 2. search_web

Busca informações na web.

**Parâmetros:**
- `query` (string, obrigatório): Query de busca

**Exemplo:**
```json
{
    "name": "search_web",
    "parameters": {
        "query": "Docker containers"
    }
}
```

### 3. get_weather

Obtém informações meteorológicas.

**Parâmetros:**
- `location` (string, obrigatório): Cidade ou coordenadas

**Exemplo:**
```json
{
    "name": "get_weather",
    "parameters": {
        "location": "São Paulo"
    }
}
```

## Adicionando Novas Ferramentas

### 1. No Backend PHP

Adicione a ferramenta no arquivo `config.php`:

```php
'mcp' => [
    'enabled' => true,
    'default_tools' => [
        'minha_ferramenta' => [
            'name' => 'minha_ferramenta',
            'description' => 'Descrição da minha ferramenta',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'parametro1' => [
                        'type' => 'string',
                        'description' => 'Descrição do parâmetro'
                    ]
                ],
                'required' => ['parametro1']
            ]
        ]
    ]
]
```

### 2. Implementar Execução

No arquivo `universal_llm_backend.php`, adicione a função de execução:

```php
function executeTool($toolName, $parameters) {
    switch ($toolName) {
        case 'minha_ferramenta':
            return executeMinhaFerramenta($parameters);
            
        // ... outras ferramentas
        
        default:
            throw new Exception("Unknown tool: $toolName");
    }
}

function executeMinhaFerramenta($parameters) {
    $parametro1 = $parameters['parametro1'] ?? '';
    
    if (empty($parametro1)) {
        return [
            'success' => false,
            'error' => 'Parâmetro obrigatório não fornecido'
        ];
    }
    
    // Implementar lógica da ferramenta
    return [
        'success' => true,
        'message' => "Ferramenta executada com sucesso: $parametro1",
        'result' => 'Resultado da execução'
    ];
}
```

### 3. No Frontend

Registre a ferramenta no plugin JavaScript:

```javascript
this.bot.mcp.availableTools.push({
    name: 'minha_ferramenta',
    description: 'Descrição da minha ferramenta',
    parameters: {
        type: 'object',
        properties: {
            parametro1: {
                type: 'string',
                description: 'Descrição do parâmetro'
            }
        },
        required: ['parametro1']
    },
    execute: async (params) => {
        return await this.executeMinhaFerramenta(params.parametro1)
    }
})
```

## Testando

Use o arquivo `test_mcp_integration.php` para testar a integração:

```bash
php test_mcp_integration.php
```

## Logs e Debug

O sistema registra logs detalhados para debugging:

- `mcp_instructions_added`: Quando instruções MCP são adicionadas
- `tool_call_parse_error`: Erros ao parsear tool calls
- `mcp_processed`: Quando MCP é processado com sucesso

## Considerações de Segurança

1. **Validação de Parâmetros**: Todos os parâmetros são validados
2. **Rate Limiting**: Aplicado a todas as requisições
3. **CORS**: Configurado para origens permitidas
4. **API Keys**: Opcional mas recomendado para produção

## Troubleshooting

### Problema: Tool calls não são processados

**Solução:**
1. Verifique se MCP está habilitado em `config.php`
2. Confirme se as ferramentas estão sendo enviadas no contexto
3. Verifique os logs para erros de parsing

### Problema: Ferramentas não são encontradas

**Solução:**
1. Verifique se a ferramenta está registrada corretamente
2. Confirme se o nome da ferramenta está correto
3. Verifique se os parâmetros estão corretos

### Problema: Erro de CORS

**Solução:**
1. Verifique se a origem está em `allowed_origins`
2. Confirme se os headers CORS estão configurados
3. Verifique se o método HTTP está permitido
