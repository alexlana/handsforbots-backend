# 🔒 Guia de Segurança - Universal LLM Backend

## 📋 Resumo das Medidas de Segurança Implementadas

### ✅ **Medidas Já Implementadas**

#### 1. **Headers de Segurança**
```php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
```

#### 2. **CORS Restritivo**
- Lista de origens permitidas configurável
- Validação de origem antes de processar requisições
- Log de tentativas de acesso não autorizado

#### 3. **Validação de Entrada**
- Sanitização de strings
- Validação de tipos de dados
- Limites de tamanho de conteúdo
- Validação de parâmetros numéricos

#### 4. **Rate Limiting**
- Limite de 30 requisições por minuto por IP
- Janela deslizante de 60 segundos
- Armazenamento em arquivo temporário

#### 5. **Autenticação por API Key**
- Validação de chaves de API
- Headers customizados para autenticação
- Log de tentativas de acesso inválidas

#### 6. **Validação de Tamanho de Requisição**
- Limite de 1MB por requisição
- Prevenção de ataques de negação de serviço

#### 7. **Logs de Segurança**
- Log de eventos de segurança
- Rastreamento de IPs e User Agents
- Log de tentativas de acesso não autorizado

### 🚀 **Medidas Adicionais Recomendadas**

#### 1. **Configuração de Servidor Web**

##### **Apache (.htaccess)**
```apache
# Desabilitar listagem de diretórios
Options -Indexes

# Forçar HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Headers de segurança adicionais
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"

# Limitar métodos HTTP
<LimitExcept POST OPTIONS>
    Deny from all
</LimitExcept>

# Proteger arquivos sensíveis
<Files "*.php">
    <RequireAll>
        Require all granted
        Require not ip 192.168.1.0/24
    </RequireAll>
</Files>
```

##### **Nginx (nginx.conf)**
```nginx
# Limitar tamanho de requisição
client_max_body_size 1M;

# Headers de segurança
add_header X-Content-Type-Options nosniff;
add_header X-Frame-Options DENY;
add_header X-XSS-Protection "1; mode=block";
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains";

# Rate limiting
limit_req_zone $binary_remote_addr zone=llm_api:10m rate=30r/m;
limit_req zone=llm_api burst=10 nodelay;

# Permitir apenas POST e OPTIONS
if ($request_method !~ ^(POST|OPTIONS)$) {
    return 405;
}

# Proteger arquivos sensíveis
location ~ \.(env|config|key)$ {
    deny all;
}
```

#### 2. **Configuração de PHP (php.ini)**
```ini
; Desabilitar funções perigosas
disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_multi_exec,parse_ini_file,show_source

; Configurações de segurança
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off
file_uploads = Off

; Limites de memória e tempo
memory_limit = 128M
max_execution_time = 30
max_input_time = 30

; Configurações de sessão
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
```

#### 3. **Firewall e Rede**

##### **Configuração de Firewall (iptables)**
```bash
# Permitir apenas tráfego necessário
iptables -A INPUT -p tcp --dport 80 -j ACCEPT
iptables -A INPUT -p tcp --dport 443 -j ACCEPT
iptables -A INPUT -p tcp --dport 22 -j ACCEPT
iptables -A INPUT -j DROP

# Rate limiting no firewall
iptables -A INPUT -p tcp --dport 80 -m limit --limit 30/minute --limit-burst 10 -j ACCEPT
```

##### **Fail2ban para Proteção Adicional**
```ini
# /etc/fail2ban/jail.local
[llm-api]
enabled = true
port = http,https
filter = llm-api
logpath = /var/log/apache2/access.log
maxretry = 5
bantime = 3600
findtime = 600
```

#### 4. **Monitoramento e Alertas**

##### **Script de Monitoramento**
```bash
#!/bin/bash
# monitor_security.sh

LOG_FILE="/var/log/llm_security.log"
ALERT_EMAIL="admin@seu-dominio.com"

# Verificar tentativas de acesso não autorizado
unauthorized_attempts=$(grep "Unauthorized origin attempt" $LOG_FILE | wc -l)

if [ $unauthorized_attempts -gt 10 ]; then
    echo "ALERT: Muitas tentativas de acesso não autorizado detectadas!" | mail -s "Alerta de Segurança" $ALERT_EMAIL
fi

# Verificar rate limit excedido
rate_limit_exceeded=$(grep "Rate limit exceeded" $LOG_FILE | wc -l)

if [ $rate_limit_exceeded -gt 5 ]; then
    echo "ALERT: Rate limit excedido múltiplas vezes!" | mail -s "Alerta de Rate Limit" $ALERT_EMAIL
fi
```

#### 5. **Backup e Recuperação**

##### **Script de Backup Automático**
```bash
#!/bin/bash
# backup_config.sh

BACKUP_DIR="/backup/llm_config"
DATE=$(date +%Y%m%d_%H%M%S)

# Criar backup dos arquivos de configuração
tar -czf $BACKUP_DIR/config_$DATE.tar.gz \
    /path/to/universal_llm_backend.php \
    /path/to/config.php \
    /etc/apache2/sites-available/llm-api.conf

# Manter apenas os últimos 7 backups
find $BACKUP_DIR -name "config_*.tar.gz" -mtime +7 -delete
```

### 🔐 **Configuração de Ambiente de Produção**

#### 1. **Variáveis de Ambiente Seguras**
```bash
# .env (não commitar no git)
OPENAI_API_KEY=sk-your-secure-key-here
ANTHROPIC_API_KEY=sk-ant-your-secure-key-here
GOOGLE_API_KEY=your-secure-google-key-here

# Configurações de segurança
DEBUG_MODE=false
ALLOWED_ORIGINS=https://seu-dominio.com,https://www.seu-dominio.com
RATE_LIMIT_REQUESTS=30
RATE_LIMIT_WINDOW=60
MAX_REQUEST_SIZE=1048576
```

#### 2. **Configuração de SSL/TLS**
```bash
# Gerar certificado SSL
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
    -keyout /etc/ssl/private/llm-api.key \
    -out /etc/ssl/certs/llm-api.crt

# Configurar SSL no Apache
<VirtualHost *:443>
    ServerName api.seu-dominio.com
    DocumentRoot /var/www/llm-api
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/llm-api.crt
    SSLCertificateKeyFile /etc/ssl/private/llm-api.key
    
    # Configurações SSL seguras
    SSLProtocol all -SSLv2 -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512
    SSLHonorCipherOrder on
    SSLCompression off
</VirtualHost>
```

### 📊 **Métricas de Segurança**

#### 1. **Logs a Monitorar**
- Tentativas de acesso não autorizado
- Rate limit excedido
- Erros de validação de entrada
- Tentativas de SQL injection
- Requisições malformadas

#### 2. **Alertas Automáticos**
- Mais de 10 tentativas de acesso não autorizado em 1 hora
- Rate limit excedido mais de 5 vezes em 10 minutos
- Erros de validação em mais de 20% das requisições
- Tentativas de acesso a arquivos sensíveis

### 🧪 **Testes de Segurança**

#### 1. **Testes Automatizados**
```php
// security_test.php
function testSecurityMeasures() {
    // Teste de rate limiting
    for ($i = 0; $i < 35; $i++) {
        $response = makeRequest();
        if ($i >= 30 && $response['status'] !== 429) {
            throw new Exception('Rate limiting not working');
        }
    }
    
    // Teste de validação de entrada
    $maliciousInput = [
        'provider' => 'invalid',
        'model' => 'test',
        'messages' => [['role' => 'user', 'content' => '<script>alert("xss")</script>']],
        'parameters' => ['max_tokens' => 'invalid']
    ];
    
    $response = makeRequest($maliciousInput);
    if ($response['status'] !== 400) {
        throw new Exception('Input validation not working');
    }
}
```

#### 2. **Ferramentas de Teste**
- **OWASP ZAP**: Teste de vulnerabilidades web
- **Burp Suite**: Teste de segurança de APIs
- **Nmap**: Verificação de portas abertas
- **Nikto**: Scanner de vulnerabilidades

### 🚨 **Plano de Resposta a Incidentes**

#### 1. **Detecção de Incidente**
- Monitoramento contínuo de logs
- Alertas automáticos
- Análise de padrões anômalos

#### 2. **Resposta Imediata**
- Bloquear IPs suspeitos
- Aumentar rate limiting
- Notificar administradores
- Documentar incidente

#### 3. **Recuperação**
- Análise forense
- Correção de vulnerabilidades
- Atualização de configurações
- Relatório de incidente

### 📚 **Recursos Adicionais**

#### 1. **Documentação de Segurança**
- [OWASP API Security Top 10](https://owasp.org/www-project-api-security/)
- [OWASP Web Security Testing Guide](https://owasp.org/www-project-web-security-testing-guide/)
- [Mozilla Security Guidelines](https://infosec.mozilla.org/guidelines/)

#### 2. **Ferramentas de Segurança**
- **ModSecurity**: WAF para Apache/Nginx
- **Fail2ban**: Proteção contra ataques
- **Lynis**: Auditoria de segurança
- **ClamAV**: Antivírus para servidor

#### 3. **Boas Práticas**
- Manter sistemas atualizados
- Usar senhas fortes e 2FA
- Fazer backups regulares
- Monitorar logs constantemente
- Treinar equipe em segurança

### 🔄 **Atualizações de Segurança**

#### 1. **Checklist Mensal**
- [ ] Atualizar dependências
- [ ] Revisar logs de segurança
- [ ] Testar backups
- [ ] Verificar configurações
- [ ] Atualizar certificados SSL

#### 2. **Checklist Trimestral**
- [ ] Auditoria de segurança
- [ ] Revisão de permissões
- [ ] Atualização de políticas
- [ ] Treinamento da equipe
- [ ] Teste de recuperação

Este guia fornece uma base sólida para manter o backend seguro em produção. Lembre-se de adaptar as configurações às suas necessidades específicas e manter-se atualizado com as melhores práticas de segurança.
