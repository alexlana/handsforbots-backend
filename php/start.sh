#!/bin/bash

# Script de inicialização do Backend PHP
# Universal LLM Backend

echo "🚀 Iniciando Backend PHP - Universal LLM Backend"

# Verificar se o Docker está rodando
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker não está rodando. Inicie o Docker primeiro."
    exit 1
fi

# Verificar se a rede teste-docker_alxbotnet existe
if ! docker network ls | grep -q "teste-docker_alxbotnet"; then
    echo "⚠️  Rede 'teste-docker_alxbotnet' não encontrada."
    echo "📋 Criando rede 'teste-docker_alxbotnet'..."
    docker network create teste-docker_alxbotnet
fi

# Verificar se o arquivo .env existe
if [ ! -f .env ]; then
    echo "⚠️  Arquivo .env não encontrado."
    echo "📋 Copiando env.example para .env..."
    cp env.example .env
    echo "🔧 Configure suas chaves de API no arquivo .env antes de continuar."
    echo "   Edite o arquivo .env e configure suas chaves de API."
    exit 1
fi

# Carregar variáveis de ambiente
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
fi

# Parar container se estiver rodando
echo "🛑 Parando container se estiver rodando..."
docker-compose down

# Construir e iniciar
echo "🔨 Iniciando container..."
docker-compose up -d

# Verificar se o container está rodando
if docker ps | grep -q "alx-chatbot-backend"; then
    echo "✅ Backend iniciado com sucesso!"
    echo "🌐 URL: http://localhost:8081"
    echo "📊 Status: $(docker ps --format 'table {{.Names}}\t{{.Status}}' | grep alx-chatbot-backend)"
else
    echo "❌ Erro ao iniciar o backend."
    echo "📋 Logs do container:"
    docker-compose logs
    exit 1
fi

echo ""
echo "📚 Recursos disponíveis:"
echo "   - Backend API: http://localhost:8081"
echo "   - Logs: docker-compose logs -f"
echo "   - Parar: docker-compose down"
echo "   - Reiniciar: docker-compose restart"
echo ""
echo "🔧 Para configurar o backend, edite o arquivo .env"
echo "🧪 Para testar: curl http://localhost:8081/health"
