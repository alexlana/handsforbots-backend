#!/bin/bash

# Script para inicializar o diretório de logs das LLMs
# Este script deve ser executado antes de iniciar o container Docker

echo "🔧 Inicializando diretório de logs das LLMs..."

# Criar diretório de logs se não existir
if [ ! -d "llm_logs" ]; then
    echo "📁 Criando diretório llm_logs..."
    mkdir -p llm_logs
    echo "✅ Diretório llm_logs criado com sucesso"
else
    echo "✅ Diretório llm_logs já existe"
fi

# Definir permissões adequadas
echo "🔐 Configurando permissões..."
chmod 755 llm_logs

# Criar arquivo de log inicial se não existir
if [ ! -f "llm_logs/llm_errors.log" ]; then
    echo "📄 Criando arquivo de log inicial..."
    touch llm_logs/llm_errors.log
    chmod 644 llm_logs/llm_errors.log
    echo "✅ Arquivo llm_errors.log criado com sucesso"
else
    echo "✅ Arquivo llm_errors.log já existe"
fi

# Criar arquivo .gitkeep para manter o diretório no git
if [ ! -f "llm_logs/.gitkeep" ]; then
    echo "📝 Criando arquivo .gitkeep..."
    touch llm_logs/.gitkeep
    echo "✅ Arquivo .gitkeep criado"
fi

# Criar arquivo .gitignore para logs se não existir
if [ ! -f "llm_logs/.gitignore" ]; then
    echo "🚫 Criando .gitignore para logs..."
    cat > llm_logs/.gitignore << EOF
# Ignorar todos os arquivos de log exceto .gitkeep
*.log
!*.log.example

# Manter apenas o .gitkeep
!.gitkeep
EOF
    echo "✅ Arquivo .gitignore criado"
fi

echo ""
echo "🎉 Inicialização concluída!"
echo ""
echo "📋 Estrutura criada:"
echo "   llm_logs/"
echo "   ├── .gitkeep"
echo "   ├── .gitignore"
echo "   └── llm_errors.log"
echo ""
echo "🚀 Próximos passos:"
echo "   1. Execute: docker-compose up -d"
echo "   2. Monitore os logs: tail -f llm_logs/llm_errors.log"
echo "   3. Use o visualizador: php view_llm_logs.php"
echo ""
