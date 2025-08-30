# MCP (Model Context Protocol) - Estrutura Organizada

Esta pasta contém a implementação organizada do sistema MCP (Model Context Protocol) para o backend PHP.

## Estrutura de Arquivos

```
mcp/
├── MCPToolInterface.php          # Interface padronizada para todos os tools
├── AbstractMCPTool.php           # Classe base abstrata com funcionalidades comuns
├── mcp_config.php                # Configuração centralizada dos tools
├── mcp_handler.php               # Handler principal do MCP (refatorado)
├── README.md                     # Esta documentação
└── tools/                        # Pasta com todos os tools implementados
    ├── WordPressGCPArticleTool.php    # Tool para acessar o artigo WordPress GCP
    ├── ShowRelevantContentTool.php    # Tool para mostrar conteúdo relevante
    ├── SearchWebTool.php              # Tool para busca na web (desabilitado)
    ├── GetWeatherTool.php             # Tool para informações meteorológicas (desabilitado)
    ├── artigo-wordpress-gcp.md        # Arquivo do artigo
    └── artigo-referencia.json         # Arquivo de referência do artigo
```

## Arquitetura

### 1. Interface (`MCPToolInterface.php`)
Define o contrato que todos os tools devem implementar:
- `getConfiguration()`: Retorna a configuração do tool
- `execute()`: Executa o tool com os parâmetros fornecidos
- `getName()`: Retorna o nome do tool
- `isAvailable()`: Verifica se o tool está disponível

### 2. Classe Base (`AbstractMCPTool.php`)
Implementa funcionalidades comuns para todos os tools:
- Validação de parâmetros
- Criação de respostas padronizadas
- Gerenciamento de configuração

### 3. Configuração (`mcp_config.php`)
Gerencia quais tools estão disponíveis e suas configurações:
- Lista de todos os tools disponíveis
- Controle de quais tools estão habilitados
- Carregamento dinâmico de classes
- Criação de instâncias dos tools

### 4. Handler (`mcp_handler.php`)
Gerencia o processamento MCP:
- Processamento de contexto MCP
- Extração de tool calls
- Execução de tools
- Geração de instruções para o prompt

## Como Criar um Novo Tool

### 1. Criar a Classe do Tool
```php
<?php
require_once __DIR__ . '/../AbstractMCPTool.php';

class MeuNovoTool extends AbstractMCPTool
{
    public function __construct()
    {
        parent::__construct(
            'meu_novo_tool',
            'Descrição do meu novo tool',
            [
                'type' => 'object',
                'properties' => [
                    'parametro1' => [
                        'type' => 'string',
                        'description' => 'Descrição do parâmetro'
                    ]
                ],
                'required' => ['parametro1']
            ]
        );
    }
    
    public function execute(array $parameters): array
    {
        // Validação automática de parâmetros
        $validation = $this->validateParameters($parameters);
        if (!$validation['success']) {
            return $this->createErrorResponse('Erro de validação');
        }
        
        // Lógica do tool
        $resultado = $this->minhaLogica($parameters);
        
        return $this->createSuccessResponse([
            'data' => $resultado
        ]);
    }
    
    private function minhaLogica($parameters)
    {
        // Implementar lógica específica do tool
        return 'resultado';
    }
}
```

### 2. Adicionar à Configuração
Editar `mcp_config.php` e adicionar na função `getMCPToolsConfiguration()`:

```php
'meu_novo_tool' => [
    'class' => 'MeuNovoTool',
    'file' => 'tools/MeuNovoTool.php',
    'enabled' => true,
    'description' => 'Descrição do meu novo tool'
],
```

### 3. Salvar o Arquivo
Salvar o arquivo em `tools/MeuNovoTool.php`

## Tools Disponíveis

### 1. WordPressGCPArticleTool
- **Nome**: `get_wordpress_gcp_article`
- **Descrição**: Acessa seções específicas do artigo "WordPress na Google Cloud Platform"
- **Ações**: `get_section`, `search_content`, `get_overview`, `get_troubleshooting`, `get_configuration`
- **Status**: Habilitado

### 2. ShowRelevantContentTool
- **Nome**: `show_relevant_content`
- **Descrição**: Mostra conteúdo relevante na página atual
- **Parâmetros**: `query`, `section`, `behavior`
- **Status**: Habilitado

### 3. SearchWebTool
- **Nome**: `search_web`
- **Descrição**: Realiza buscas na web
- **Status**: Desabilitado (requer configuração de API)

### 4. GetWeatherTool
- **Nome**: `get_weather`
- **Descrição**: Obtém informações meteorológicas
- **Status**: Desabilitado (requer configuração de API)

## Benefícios da Nova Estrutura

1. **Organização**: Cada tool é uma classe separada e bem definida
2. **Reutilização**: Funcionalidades comuns na classe base
3. **Manutenibilidade**: Fácil de adicionar, remover ou modificar tools
4. **Validação**: Validação automática de parâmetros
5. **Configuração**: Controle centralizado de quais tools estão ativos
6. **Extensibilidade**: Fácil de estender com novos tools
7. **Padronização**: Interface consistente para todos os tools

## Migração

A estrutura antiga foi mantida como fallback para garantir compatibilidade. O sistema tenta usar a nova estrutura primeiro e, se não encontrar o tool, usa a implementação legacy.

## Próximos Passos

1. Implementar tools adicionais conforme necessário
2. Adicionar testes unitários para os tools
3. Implementar cache para melhorar performance
4. Adicionar logging detalhado para debugging
5. Implementar rate limiting para tools que fazem chamadas externas
