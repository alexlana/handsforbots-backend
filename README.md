# 🤖 HandsForBots Backend

Backend PHP para o sistema HandsForBots, fornecendo uma API universal para múltiplos provedores de LLMs.

## 🚀 Início Rápido

### Pré-requisitos

- Docker e Docker Compose instalados
- Repositório principal (alexlana) rodando na rede `alxbotnet`

### Configuração Inicial

1. **Clone o repositório**:
   ```bash
   git clone <url-do-repositorio>
   cd handsforbots-backend/php
   ```

2. **Configure as variáveis de ambiente**:
   ```bash
   cp env.example .env
   nano .env  # Configure suas chaves de API
   ```

3. **Inicie o backend**:
   ```bash
   ./start.sh
   ```

4. **Teste a conexão**:
   ```bash
   curl http://localhost:8081/health
   ```

## 📁 Estrutura do Projeto

```
handsforbots-backend/
├── php/
│   ├── app/                    # Código da aplicação
│   │   ├── health.php         # Health check endpoint
│   │   ├── universal_llm_backend.php  # Backend principal
│   │   ├── config.php         # Configuração (não commitar)
│   │   └── ...
│   ├── docker-compose.yml     # Configuração Docker
│   ├── env.example            # Exemplo de variáveis de ambiente
│   ├── start.sh               # Script de inicialização
│   └── docs/                  # Documentação
└── README.md                  # Este arquivo
```

## 🔧 Configuração

### Variáveis de Ambiente

Edite o arquivo `.env` com suas configurações:

```bash
# Configurações do Backend
PHP_VERSION=8.4.12-zts-alpine3.21
CONTAINER_NAME=alx-chatbot-backend
PORT=8081
NETWORK_NAME=alxbotnet

# Chaves de API dos provedores
OPENAI_API_KEY=sk-your-openai-key-here
ANTHROPIC_API_KEY=sk-ant-your-anthropic-key-here
GOOGLE_API_KEY=your-google-api-key-here
OLLAMA_ENDPOINT=http://localhost:11434

# Chaves de API do frontend
FRONTEND_API_KEY=your-frontend-api-key-here
MOBILE_API_KEY=your-mobile-api-key-here

# Configurações de ambiente
DEBUG_MODE=false
LOG_LEVEL=warning
```

### Configuração do Backend

O backend usa um sistema de configuração centralizado. Copie o arquivo de exemplo:

```bash
cp app/config.example.php app/config.php
```

Configure as opções de segurança e provedores no arquivo `app/config.php`.

## 🌐 Endpoints

### Health Check
```
GET /health
```
Verifica o status do backend e conectividade com APIs externas.

### Universal LLM Backend
```
POST /universal_llm_backend.php
```
Endpoint principal para requisições de LLMs.

**Headers necessários:**
- `Content-Type: application/json`
- `X-Request-ID: <request-id>` (opcional)
- `X-Session-ID: <session-id>` (opcional)
- `X-API-Key: <api-key>` (se configurado)

**Exemplo de requisição:**
```json
{
  "provider": "openai",
  "model": "gpt-3.5-turbo",
  "messages": [
    {
      "role": "user",
      "content": "Olá, como você está?"
    }
  ],
  "parameters": {
    "max_tokens": 100,
    "temperature": 0.7
  }
}
```

## 🔐 Segurança

O backend implementa várias camadas de segurança:

- **Rate Limiting**: Limite de requisições por IP
- **CORS**: Controle de origens permitidas
- **Validação de Entrada**: Sanitização e validação rigorosa
- **Autenticação**: Validação de API keys
- **Logs de Segurança**: Monitoramento de eventos suspeitos

### Configurações de Segurança

```php
'security' => [
    'allowed_origins' => ['https://seu-dominio.com'],
    'allowed_domains' => ['api.openai.com', 'api.anthropic.com'],
    'allowed_api_keys' => ['your-api-key'],
    'require_api_key' => true,
    'validate_input' => true,
    'sanitize_output' => true
]
```

## 🐳 Docker

### Comandos Úteis

```bash
# Iniciar o backend
docker-compose up -d

# Ver logs
docker-compose logs -f

# Parar o backend
docker-compose down

# Reiniciar
docker-compose restart

# Ver status
docker-compose ps
```

### Rede Docker

O backend usa a rede `teste-docker_alxbotnet` do repositório principal. Se a rede não existir:

```bash
docker network create teste-docker_alxbotnet
```

## 🧪 Testes

### Teste de Saúde
```bash
curl http://localhost:8081/health
```

### Teste de Segurança
```bash
# Execute os testes de segurança
php app/security_test.php http://localhost:8081 your-api-key
```

### Teste de API
```bash
curl -X POST http://localhost:8081/universal_llm_backend.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-api-key" \
  -d '{
    "provider": "openai",
    "model": "gpt-3.5-turbo",
    "messages": [{"role": "user", "content": "Olá"}],
    "parameters": {"max_tokens": 50}
  }'
```

## 📊 Monitoramento

### Logs
```bash
# Logs em tempo real
docker-compose logs -f

# Logs de segurança
tail -f /var/log/apache2/error.log
```

### Métricas
- **Health Check**: `/health`
- **Rate Limiting**: Logs de eventos
- **Erros**: Logs de segurança
- **Performance**: Tempo de resposta das APIs

## 🔄 Integração com Frontend

### Configuração do Frontend

No frontend, configure o endpoint do backend:

```javascript
const bot = new Bot({
    engine: 'universal-llm-backend',
    endpoint: 'http://localhost:8081/universal_llm_backend.php',
    provider: 'auto',
    model: 'auto'
});
```

### CORS

Configure as origens permitidas no backend:

```php
'allowed_origins' => [
    'http://localhost:3000',  // Frontend local
    'https://seu-dominio.com' // Produção
]
```

## 🚨 Troubleshooting

### Problemas Comuns

1. **Porta já em uso**:
   ```bash
   # Mude a porta no docker-compose.yml
   ports:
     - "8082:80"  # Use outra porta
   ```

2. **Rede não encontrada**:
   ```bash
   docker network create alxbotnet
   ```

3. **Erro de CORS**:
   - Verifique `allowed_origins` na configuração
   - Adicione seu domínio à lista

4. **API key inválida**:
   - Configure `allowed_api_keys` no config.php
   - Verifique se `require_api_key` está correto

### Logs de Debug

```bash
# Habilitar logs detalhados
docker-compose logs -f

# Ver logs do Apache
docker exec alx-chatbot-backend tail -f /var/log/apache2/error.log
```

## 📚 Documentação

- [Guia de Configuração](php/docs/README_CONFIGURATION.md)
- [Guia de Segurança](php/docs/SECURITY_GUIDE.md)
- [Exemplo de Uso](php/docs/exemplo_uso_universal_backend.html)

## 🤝 Contribuição

1. Fork o repositório
2. Crie uma branch para sua feature
3. Commit suas mudanças
4. Push para a branch
5. Abra um Pull Request

## 📄 Licença

Este projeto está sob a licença MIT. Veja o arquivo LICENSE para detalhes.

## 🆘 Suporte

Para suporte e dúvidas:

- Abra uma issue no GitHub
- Consulte a documentação
- Verifique os logs de erro

---

**Desenvolvido por Alex Lana** 🚀
