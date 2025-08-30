# LLM Error Logging System

Este documento descreve o sistema de logs de erro implementado para capturar e diagnosticar problemas de conexão com LLMs (Large Language Models).

## Visão Geral

O sistema de logs de erro LLM foi implementado para resolver o problema de erros genéricos que não forneciam informações suficientes para diagnosticar problemas de conexão com as APIs de LLM.

## Funcionalidades

### 1. Logs Detalhados de Conexão
- **Tempo de conexão**: Mede o tempo total de conexão, lookup DNS, transferência, etc.
- **Códigos de erro cURL**: Captura códigos de erro específicos do cURL
- **Informações HTTP**: Códigos de status, headers, tamanho de resposta
- **URLs efetivas**: Mostra redirecionamentos e URLs finais

### 2. Tipos de Erro Capturados

#### Erros de Configuração
- `openai_api_key_missing`: Chave da API OpenAI não configurada
- `anthropic_api_key_missing`: Chave da API Anthropic não configurada
- `google_api_key_missing`: Chave da API Google não configurada

#### Erros de Conexão
- `curl_error`: Erros específicos do cURL (timeout, DNS, SSL, etc.)
- `http_error`: Erros HTTP (401, 403, 429, 500, etc.)
- `invalid_url`: URLs malformadas
- `domain_not_allowed`: Domínios não permitidos

#### Erros de Resposta
- `openai_invalid_response_format`: Formato de resposta OpenAI inválido
- `anthropic_invalid_response_format`: Formato de resposta Anthropic inválido
- `google_invalid_response_format`: Formato de resposta Google inválido
- `ollama_invalid_response_format`: Formato de resposta Ollama inválido
- `json_decode_error`: Erro ao decodificar JSON da resposta

#### Erros Gerais
- `request_processing_failed`: Falha geral no processamento da requisição
- `unsupported_provider`: Provedor não suportado

### 3. Informações Capturadas em Cada Log

```json
{
  "timestamp": "2024-01-15 10:30:45",
  "type": "llm_error",
  "error_type": "curl_error",
  "ip": "192.168.1.100",
  "user_agent": "Mozilla/5.0...",
  "request_id": "req-12345",
  "session_id": "sess-67890",
  "details": {
    "url": "https://api.openai.com/v1/chat/completions",
    "error": "Connection timed out",
    "error_number": 28,
    "connection_info": {
      "url": "https://api.openai.com/v1/chat/completions",
      "effective_url": "https://api.openai.com/v1/chat/completions",
      "http_code": 0,
      "total_time": 30.0,
      "connect_time": 10.0,
      "name_lookup_time": 0.1,
      "pretransfer_time": 10.1,
      "starttransfer_time": 0.0,
      "redirect_time": 0.0,
      "redirect_count": 0,
      "content_type": null,
      "content_length": null,
      "size_download": 0,
      "speed_download": 0,
      "custom_execution_time": 30000.0
    }
  }
}
```

## Configuração

### Arquivo de Log
Configure o arquivo de log no `config.php`:

```php
'general' => [
    'llm_error_log_file' => '/tmp/llm_errors.log',
    // ... outras configurações
]
```

### Níveis de Log
- `debug`: Todos os logs (incluindo sucessos)
- `info`: Logs de erro e eventos importantes
- `warning`: Apenas warnings e erros
- `error`: Apenas erros

## Uso

### 1. Testando o Sistema de Logs

Execute o script de teste:

```bash
cd handsforbots-backend/php
php test_llm_logging.php
```

### 2. Visualizando os Logs

Use o visualizador de logs:

```bash
cd handsforbots-backend/php
php view_llm_logs.php
```

### 3. Monitoramento em Tempo Real

Para monitorar logs em tempo real:

```bash
tail -f /tmp/llm_errors.log
```

## Diagnóstico de Problemas

### Problemas Comuns e Soluções

#### 1. API Key Missing
```
Error Type: openai_api_key_missing
```
**Solução**: Configure a variável de ambiente `OPENAI_API_KEY`

#### 2. Connection Timeout
```
Error Type: curl_error
Error: Connection timed out
```
**Soluções**:
- Verificar conectividade de rede
- Aumentar timeout no config.php
- Verificar firewall

#### 3. Rate Limit Exceeded
```
Error Type: http_error
HTTP Code: 429
```
**Soluções**:
- Implementar retry com backoff exponencial
- Verificar limites da API
- Usar API key com maior quota

#### 4. Invalid Response Format
```
Error Type: openai_invalid_response_format
```
**Soluções**:
- Verificar se a API mudou o formato
- Atualizar código de parsing
- Verificar se o modelo solicitado existe

## Exemplos de Análise

### Exemplo 1: Problema de Timeout
```bash
php view_llm_logs.php | grep -A 10 "curl_error"
```

### Exemplo 2: Problemas com OpenAI
```bash
php view_llm_logs.php | grep -A 10 "openai"
```

### Exemplo 3: Erros HTTP 401/403
```bash
php view_llm_logs.php | grep -A 10 "http_code.*401\|http_code.*403"
```

## Integração com Monitoramento

### Logrotate
Configure logrotate para gerenciar o arquivo de logs:

```bash
# /etc/logrotate.d/llm-errors
/tmp/llm_errors.log {
    daily
    rotate 7
    compress
    delaycompress
    missingok
    notifempty
    create 644 www-data www-data
}
```

### Alertas
Configure alertas baseados em padrões de erro:

```bash
# Exemplo: Alerta para muitos timeouts
tail -f /tmp/llm_errors.log | grep "curl_error" | grep "Connection timed out" | wc -l
```

## Manutenção

### Limpeza de Logs
```bash
# Limpar logs antigos (mais de 30 dias)
find /tmp -name "llm_errors.log*" -mtime +30 -delete
```

### Análise de Tendências
```bash
# Contar erros por tipo no último dia
grep "$(date +%Y-%m-%d)" /tmp/llm_errors.log | grep "LLM_ERROR" | jq -r '.error_type' | sort | uniq -c
```

## Troubleshooting

### Logs não aparecem
1. Verificar se `enable_logging` está `true` no config.php
2. Verificar permissões do diretório de logs
3. Verificar se o PHP tem permissão de escrita

### Logs muito verbosos
1. Ajustar `log_level` para `warning` ou `error`
2. Desabilitar `debug_mode` no config.php

### Performance
1. Usar log file separado para evitar impacto no log principal
2. Considerar rotação de logs
3. Monitorar tamanho do arquivo de log

## Contribuição

Para adicionar novos tipos de erro:

1. Adicionar o log na função apropriada
2. Documentar o novo tipo de erro aqui
3. Adicionar recomendações de solução no visualizador
4. Atualizar testes se necessário
