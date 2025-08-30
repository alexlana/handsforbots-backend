# 🔧 Sistema MCP Integrado - Universal LLM Backend

Este projeto implementa um sistema completo de **MCP (Model Context Protocol)** que permite que LLMs usem ferramentas específicas durante conversas, com foco especial na ferramenta `show_relevant_content` para navegação em páginas web.

## 🏗️ Arquitetura Completa

```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Frontend      │    │   Backend PHP    │    │   LLM Provider  │
│                 │    │                  │    │                 │
│ ShowRelevant    │───▶│ Universal LLM    │───▶│ OpenAI/Claude/  │
│ Content.js      │    │ Backend.php      │    │ Gemini/Ollama   │
│                 │    │                  │    │                 │
│ MCPHelper.js    │    │ MCP Processing   │    │                 │
│                 │    │ Tool Execution   │    │                 │
│ UniversalLLM.js │    │ Response Format  │    │                 │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

## 🚀 Como Funciona

### 1. **Registro de Ferramentas** (Frontend)
- `ShowRelevantContent.js` registra ferramentas MCP no `Bot.js`
- `MCPHelper.js` gerencia as ferramentas disponíveis
- `UniversalLLM.js` envia contexto MCP para o backend

### 2. **Processamento MCP** (Backend)
- Backend PHP recebe requisições com contexto MCP
- Adiciona instruções MCP ao prompt do LLM
- Processa tool calls na resposta do LLM
- Executa ferramentas e retorna resultados

### 3. **Execução de Ferramentas**
- Backend identifica tool calls no formato `<tool>...</tool>`
- Executa as ferramentas correspondentes
- Retorna resultados estruturados

## 📁 Estrutura de Arquivos

```
handsforbots-backend/php/
├── universal_llm_backend.php     # Backend principal com MCP
├── config.php                    # Configuração MCP
├── test_mcp_integration.php      # Testes do sistema MCP
├── exemplo_uso_frontend.html     # Exemplo completo frontend
└── docs/
    └── MCP_INTEGRATION.md        # Documentação detalhada
```

## 🛠️ Configuração Rápida

### 1. **Habilitar MCP no Backend**

```php
// config.php
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

### 2. **Configurar Frontend**

```javascript
// ShowRelevantContent.js
this.bot.mcp.availableTools.push({
    name: 'show_relevant_content',
    description: 'Show content relevant to the user question',
    parameters: {
        type: 'object',
        properties: {
            query: {
                type: 'string',
                description: 'The query to find the element',
                enum: ['docker', 'php', 'javascript', 'python']
            }
        },
        required: ['query']
    },
    execute: async (params) => {
        return await this.executeShowRelevantContent(params.query)
    }
})
```

## 🧪 Testando o Sistema

### 1. **Teste via PHP**

```bash
cd handsforbots-backend/php
php test_mcp_integration.php
```

### 2. **Teste via Frontend**

Abra `exemplo_uso_frontend.html` no navegador e teste o chat interativo.

### 3. **Teste via cURL**

```bash
# Consultar ferramentas disponíveis
curl -X GET http://localhost:8080/universal_llm_backend.php

# Enviar requisição com MCP
curl -X POST http://localhost:8080/universal_llm_backend.php \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "openai",
    "model": "gpt-3.5-turbo",
    "messages": [{"role": "user", "content": "Mostre a seção sobre Docker"}],
    "parameters": {"max_tokens": 1024, "temperature": 0.7},
    "context": {
      "mcp_context": {
        "tools": [{
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
        }]
      }
    }
  }'
```

## 🔧 Ferramentas Disponíveis

### 1. **show_relevant_content**
- **Função**: Rola para seções específicas do site
- **Parâmetros**: `query` (string)
- **Exemplo**: `{"name": "show_relevant_content", "parameters": {"query": "docker"}}`

### 2. **search_web**
- **Função**: Busca informações na web
- **Parâmetros**: `query` (string)
- **Exemplo**: `{"name": "search_web", "parameters": {"query": "Docker containers"}}`

### 3. **get_weather**
- **Função**: Obtém informações meteorológicas
- **Parâmetros**: `location` (string)
- **Exemplo**: `{"name": "get_weather", "parameters": {"location": "São Paulo"}}`

## 📊 Exemplo de Fluxo Completo

### 1. **Usuário envia mensagem**
```
"Mostre a seção sobre Docker no site"
```

### 2. **Backend processa com MCP**
- Adiciona instruções MCP ao prompt
- Envia para LLM com contexto de ferramentas

### 3. **LLM responde com tool call**
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

### 4. **Backend executa ferramenta**
- Extrai tool call da resposta
- Executa `show_relevant_content` com `query: "docker"`
- Retorna resultado estruturado

### 5. **Frontend recebe resposta**
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
                "query": "docker"
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
        "mcp_processed": true,
        "tool_calls": [...],
        "tool_results": [...]
    }
}
```

### 6. **Frontend executa ação**
- Rola para seção "docker"
- Destaca a seção temporariamente
- Exibe mensagens do assistente

## 🔒 Segurança

- ✅ Validação de parâmetros
- ✅ Rate limiting
- ✅ CORS configurado
- ✅ Sanitização de entrada
- ✅ Logs de segurança
- ✅ API keys opcionais

## 🔧 Correções Recentes

### Frontend (UniversalLLM.js)
- ✅ **Detecção de arrays de resposta**: Adicionada verificação `Array.isArray(bot_dt.response)`
- ✅ **Processamento de tool executions**: Suporte a `type: 'tool_execution'` e `type: 'text'`
- ✅ **Execução automática de scroll**: Função `executeScrollAction()` para ações de rolagem
- ✅ **Validação de tipos**: Função `decodeUTF8()` agora valida se o input é string

### Frontend (Bot.js)
- ✅ **Validação de propriedades**: Função `extractActions()` agora verifica se `obj.text` existe e é string
- ✅ **Compatibilidade com MCP**: Suporte a objetos de resposta sem propriedade `text`

### Backend (universal_llm_backend.php)
- ✅ **Formato de resposta estruturado**: Retorna arrays de mensagens com tipos específicos
- ✅ **Metadados MCP**: Inclui informações sobre tool calls e resultados
- ✅ **Processamento de ferramentas**: Execução e retorno de resultados estruturados

### System Prompt Otimizado
- ✅ **Respostas concisas**: Foco em orientação, não explicação detalhada
- ✅ **Navegação eficiente**: Uso de ferramentas + orientação breve
- ✅ **Experiência melhorada**: 2-3 frases vs 20+ frases
- ✅ **Estrutura ideal**: Tool call + confirmação + orientação

## 🐛 Troubleshooting

### Problema: Tool calls não são processados
**Solução**: Verifique se MCP está habilitado em `config.php`

### Problema: Ferramentas não são encontradas
**Solução**: Confirme se as ferramentas estão registradas corretamente

### Problema: Erro de CORS
**Solução**: Verifique se a origem está em `allowed_origins`

### Problema: "text.trim is not a function" no frontend
**Solução**: 
1. Verifique se o `UniversalLLM.js` está processando corretamente arrays de resposta
2. Confirme se a função `decodeUTF8` está validando tipos de entrada
3. Verifique se a função `extractActions` no `Bot.js` está validando propriedades `text`

### Problema: Resposta MCP não é processada pelo frontend
**Solução**:
1. Verifique se o `UniversalLLM.js` detecta arrays de resposta (`Array.isArray(bot_dt.response)`)
2. Confirme se cada item da resposta tem as propriedades corretas (`type`, `text`, `recipient_id`)
3. Verifique se o `ShowRelevantContent` plugin está registrado corretamente

## 📈 Próximos Passos

1. **Adicionar mais ferramentas**: Calendário, email, APIs externas
2. **Melhorar feedback loop**: Enviar resultados de volta para LLM
3. **Cache de ferramentas**: Cachear resultados de ferramentas
4. **Streaming**: Suporte a respostas em streaming
5. **Autenticação**: Sistema de autenticação mais robusto
6. **Otimização de prompts**: Refinar system prompts para diferentes contextos
7. **Análise de uso**: Métricas sobre eficácia das ferramentas MCP

## 🎯 Otimização de Respostas

### Problema Identificado
- Respostas muito verbosas (20+ frases)
- Repetição de conteúdo da página
- Experiência de usuário prejudicada

### Solução Implementada
- **System Prompt Otimizado**: Foco em navegação e orientação
- **Respostas Concisas**: Máximo 2-3 frases
- **Estrutura Ideal**: Tool call + confirmação + orientação

### Exemplo de Melhoria

**Antes** (Verboso):
```
Com certeza! Para te ajudar a encontrar a seção sobre Cloud SQL no artigo, vou usar a ferramenta SHOW_RELEVANT_CONTENT.

Aqui está a seção sobre como criar uma instância do Cloud SQL:

Na GCP, primeiro prepare os serviços:
Vamos iniciar ativando e configurando os serviços necessários na GCP.
Acesse console.cloud.google.com e faça login na sua conta...
[20+ frases de conteúdo repetido]
```

**Depois** (Otimizado):
```
<tool>
{
  "name": "show_relevant_content",
  "parameters": {
    "query": "criar-instancia-cloud-sql"
  }
}
</tool>

Pronto! Rolei até a seção sobre criação de instância do Cloud SQL. Lá você encontrará o passo a passo completo para configurar o banco de dados na GCP.
```

### Benefícios
- ✅ **Tempo de resposta reduzido**
- ✅ **Menos scroll no chat**
- ✅ **Usuários encontram conteúdo mais rapidamente**
- ✅ **Experiência mais fluida e natural**

## 🤝 Contribuição

Para adicionar novas ferramentas:

1. Implemente a função no backend PHP
2. Registre a ferramenta no `config.php`
3. Adicione ao frontend se necessário
4. Teste com `test_mcp_integration.php`
5. Documente no `MCP_INTEGRATION.md`

---

**🎉 Sistema MCP Integrado funcionando!** 

Agora você tem um backend PHP que processa ferramentas MCP e se integra perfeitamente com o frontend JavaScript, permitindo que LLMs usem ferramentas específicas como rolagem de página, busca na web e muito mais.
