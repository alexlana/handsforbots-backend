#!/bin/bash

# Script para testar conectividade entre repositórios
# Testa se o backend consegue se comunicar com o frontend

echo "🔍 Testando conectividade entre repositórios..."

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Função para imprimir com cores
print_status() {
    local status=$1
    local message=$2
    
    case $status in
        "success")
            echo -e "${GREEN}✅ $message${NC}"
            ;;
        "error")
            echo -e "${RED}❌ $message${NC}"
            ;;
        "warning")
            echo -e "${YELLOW}⚠️  $message${NC}"
            ;;
        "info")
            echo -e "${BLUE}ℹ️  $message${NC}"
            ;;
    esac
}

# Verificar se Docker está rodando
if ! docker info > /dev/null 2>&1; then
    print_status "error" "Docker não está rodando"
    exit 1
fi

print_status "success" "Docker está rodando"

# Verificar se a rede teste-docker_alxbotnet existe
if ! docker network ls | grep -q "teste-docker_alxbotnet"; then
    print_status "warning" "Rede 'teste-docker_alxbotnet' não encontrada"
    print_status "info" "Criando rede 'teste-docker_alxbotnet'..."
    docker network create teste-docker_alxbotnet
    print_status "success" "Rede 'teste-docker_alxbotnet' criada"
else
    print_status "success" "Rede 'teste-docker_alxbotnet' encontrada"
fi

# Verificar containers em execução
echo ""
print_status "info" "Verificando containers em execução..."

# Verificar se o container do frontend está rodando
if docker ps | grep -q "alx-chatbot"; then
    print_status "success" "Container do frontend (alx-chatbot) está rodando"
    FRONTEND_RUNNING=true
else
    print_status "warning" "Container do frontend (alx-chatbot) não está rodando"
    FRONTEND_RUNNING=false
fi

# Verificar se o container do backend está rodando
if docker ps | grep -q "alx-chatbot-backend"; then
    print_status "success" "Container do backend (alx-chatbot-backend) está rodando"
    BACKEND_RUNNING=true
else
    print_status "warning" "Container do backend (alx-chatbot-backend) não está rodando"
    BACKEND_RUNNING=false
fi

# Testar conectividade de rede
echo ""
print_status "info" "Testando conectividade de rede..."

# Testar se o frontend pode acessar o backend
if [ "$FRONTEND_RUNNING" = true ] && [ "$BACKEND_RUNNING" = true ]; then
    print_status "info" "Testando conectividade entre containers..."
    
    # Testar se o frontend consegue acessar o backend
    if docker exec alx-chatbot curl -s http://alx-chatbot-backend/health > /dev/null 2>&1; then
        print_status "success" "Frontend consegue acessar o backend via rede Docker"
    else
        print_status "error" "Frontend não consegue acessar o backend via rede Docker"
    fi
    
    # Testar se o backend consegue acessar o frontend
    if docker exec alx-chatbot-backend curl -s http://alx-chatbot/ > /dev/null 2>&1; then
        print_status "success" "Backend consegue acessar o frontend via rede Docker"
    else
        print_status "warning" "Backend não consegue acessar o frontend via rede Docker"
    fi
fi

# Testar endpoints externos
echo ""
print_status "info" "Testando endpoints externos..."

# Testar health check do backend
if curl -s http://localhost:8081/health > /dev/null 2>&1; then
    print_status "success" "Health check do backend acessível em http://localhost:8081/health"
    
    # Fazer uma requisição real para ver a resposta
    HEALTH_RESPONSE=$(curl -s http://localhost:8081/health)
    if echo "$HEALTH_RESPONSE" | grep -q '"status":"healthy"'; then
        print_status "success" "Backend está saudável"
    elif echo "$HEALTH_RESPONSE" | grep -q '"status":"degraded"'; then
        print_status "warning" "Backend está com problemas menores"
    else
        print_status "error" "Backend não está saudável"
        echo "Resposta do health check:"
        echo "$HEALTH_RESPONSE" | jq '.' 2>/dev/null || echo "$HEALTH_RESPONSE"
    fi
else
    print_status "error" "Health check do backend não acessível em http://localhost:8081/health"
fi

# Testar frontend
if curl -s http://localhost:8080/ > /dev/null 2>&1; then
    print_status "success" "Frontend acessível em http://localhost:8080/"
else
    print_status "warning" "Frontend não acessível em http://localhost:8080/"
fi

# Testar comunicação entre frontend e backend
echo ""
print_status "info" "Testando comunicação entre frontend e backend..."

# Criar um arquivo de teste temporário
cat > /tmp/test-connection.html << 'EOF'
<!DOCTYPE html>
<html>
<head>
    <title>Teste de Conexão</title>
</head>
<body>
    <h1>Teste de Conexão Frontend-Backend</h1>
    <div id="result"></div>
    
    <script>
        async function testConnection() {
            const resultDiv = document.getElementById('result');
            
            try {
                // Testar health check
                const healthResponse = await fetch('http://localhost:8081/health');
                const healthData = await healthResponse.json();
                
                if (healthData.status === 'healthy') {
                    resultDiv.innerHTML += '<p style="color: green;">✅ Health check: OK</p>';
                } else {
                    resultDiv.innerHTML += '<p style="color: orange;">⚠️ Health check: ' + healthData.status + '</p>';
                }
                
                // Testar API do backend
                const apiResponse = await fetch('http://localhost:8081/universal_llm_backend.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        provider: 'openai',
                        model: 'gpt-3.5-turbo',
                        messages: [{
                            role: 'user',
                            content: 'Teste de conexão'
                        }],
                        parameters: {
                            max_tokens: 10
                        }
                    })
                });
                
                if (apiResponse.ok) {
                    resultDiv.innerHTML += '<p style="color: green;">✅ API do backend: OK</p>';
                } else {
                    resultDiv.innerHTML += '<p style="color: red;">❌ API do backend: Erro ' + apiResponse.status + '</p>';
                }
                
            } catch (error) {
                resultDiv.innerHTML += '<p style="color: red;">❌ Erro de conexão: ' + error.message + '</p>';
            }
        }
        
        // Executar teste quando a página carregar
        window.onload = testConnection;
    </script>
</body>
</html>
EOF

print_status "info" "Arquivo de teste criado em /tmp/test-connection.html"
print_status "info" "Abra http://localhost:8080/test-connection.html para testar a comunicação"

# Mostrar informações de rede
echo ""
print_status "info" "Informações de rede:"

echo "Rede Docker 'teste-docker_alxbotnet':"
docker network inspect teste-docker_alxbotnet --format='{{range .Containers}}{{.Name}}: {{.IPv4Address}}{{"\n"}}{{end}}' 2>/dev/null || echo "Nenhum container conectado"

echo ""
echo "Containers em execução:"
docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}" | grep -E "(alx-chatbot|alx-chatbot-backend)" || echo "Nenhum container relevante encontrado"

# Resumo final
echo ""
print_status "info" "Resumo da conectividade:"

if [ "$FRONTEND_RUNNING" = true ] && [ "$BACKEND_RUNNING" = true ]; then
    print_status "success" "Ambos os containers estão rodando"
    
    if curl -s http://localhost:8081/health > /dev/null 2>&1 && curl -s http://localhost:8080/ > /dev/null 2>&1; then
        print_status "success" "Conectividade externa: OK"
        print_status "success" "Sistema pronto para uso!"
    else
        print_status "warning" "Conectividade externa: Problemas detectados"
    fi
else
    print_status "warning" "Alguns containers não estão rodando"
    print_status "info" "Execute './start.sh' no diretório apropriado para iniciar os containers"
fi

echo ""
print_status "info" "Para mais detalhes, consulte os logs:"
echo "  - Backend: docker-compose logs -f"
echo "  - Frontend: docker logs alx-chatbot"
