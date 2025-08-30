<?php
/**
 * Debug CORS - Script para debugar problemas de CORS
 * 
 * Este script ajuda a identificar problemas de configuração CORS
 * 
 * @author Alex Lana
 * @version 1.0
 */

// Carregar configuração
require_once __DIR__ . '/app/config.php';

echo "🔍 DEBUG CORS - Universal LLM Backend\n";
echo "=====================================\n\n";

// Verificar configurações
echo "📋 CONFIGURAÇÕES ATUAIS:\n";
echo "------------------------\n";

$allowedOrigins = getConfig('security.allowed_origins');
echo "✅ Origens permitidas:\n";
foreach ($allowedOrigins as $origin) {
    echo "   - $origin\n";
}

echo "\n";

// Verificar variáveis do servidor
echo "🌐 VARIÁVEIS DO SERVIDOR:\n";
echo "-------------------------\n";

$origin = $_SERVER['HTTP_ORIGIN'] ?? 'NÃO DEFINIDA';
$method = $_SERVER['REQUEST_METHOD'] ?? 'NÃO DEFINIDO';
$host = $_SERVER['HTTP_HOST'] ?? 'NÃO DEFINIDO';
$referer = $_SERVER['HTTP_REFERER'] ?? 'NÃO DEFINIDO';

echo "Origin: $origin\n";
echo "Method: $method\n";
echo "Host: $host\n";
echo "Referer: $referer\n";

echo "\n";

// Testar verificação CORS
echo "🔍 TESTE DE VERIFICAÇÃO CORS:\n";
echo "-----------------------------\n";

if ($origin !== 'NÃO DEFINIDA') {
    $isAllowed = in_array($origin, $allowedOrigins);
    echo "Origin '$origin' está na lista de permitidos: " . ($isAllowed ? '✅ SIM' : '❌ NÃO') . "\n";
    
    if (!$isAllowed) {
        echo "\n❌ PROBLEMA IDENTIFICADO:\n";
        echo "A origem '$origin' não está na lista de origens permitidas.\n";
        echo "\n💡 SOLUÇÕES:\n";
        echo "1. Adicione '$origin' ao array 'allowed_origins' no config.php\n";
        echo "2. Ou use uma das origens já permitidas\n";
    } else {
        echo "✅ CORS deve funcionar corretamente para esta origem.\n";
    }
} else {
    echo "⚠️  Origin não está definida. Isso pode acontecer em:\n";
    echo "   - Requisições locais (file://)\n";
    echo "   - Alguns navegadores em modo de desenvolvimento\n";
    echo "   - Requisições sem Origin header\n";
}

echo "\n";

// Verificar se é uma requisição OPTIONS (preflight)
if ($method === 'OPTIONS') {
    echo "🔄 REQUISIÇÃO PREFLIGHT DETECTADA:\n";
    echo "---------------------------------\n";
    echo "Esta é uma requisição OPTIONS (preflight) do navegador.\n";
    echo "O backend deve responder com status 200 e headers CORS.\n";
}

echo "\n";

// Sugestões de correção
echo "🛠️  SUGESTÕES DE CORREÇÃO:\n";
echo "-------------------------\n";

echo "1. Verifique se o frontend está rodando em uma das origens permitidas:\n";
foreach ($allowedOrigins as $allowedOrigin) {
    echo "   - $allowedOrigin\n";
}

echo "\n2. Se necessário, adicione a origem do frontend ao config.php:\n";
echo "   'allowed_origins' => [\n";
echo "       'http://localhost:8080',  // Seu frontend\n";
echo "       // ... outras origens\n";
echo "   ]\n";

echo "\n3. Para desenvolvimento, você pode permitir todas as origens (NÃO RECOMENDADO para produção):\n";
echo "   'allowed_origins' => ['*']\n";

echo "\n4. Verifique se o container Docker está rodando:\n";
echo "   docker-compose ps\n";

echo "\n5. Teste a conectividade:\n";
echo "   curl -X GET http://localhost:8081/universal_llm_backend.php\n";

echo "\n";

// Teste de conectividade
echo "🧪 TESTE DE CONECTIVIDADE:\n";
echo "-------------------------\n";

$testUrl = 'http://localhost:8081/universal_llm_backend.php';
echo "Testando conectividade com: $testUrl\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $testUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ Erro de conectividade: $error\n";
    echo "   Verifique se o container está rodando: docker-compose up -d\n";
} else {
    echo "✅ Conectividade OK - HTTP Code: $httpCode\n";
    
    if ($httpCode === 200) {
        echo "   O backend está respondendo corretamente.\n";
    } else {
        echo "   O backend está respondendo, mas com código HTTP $httpCode\n";
    }
}

echo "\n🎯 PRÓXIMOS PASSOS:\n";
echo "==================\n";
echo "1. Verifique a origem do seu frontend\n";
echo "2. Adicione a origem ao config.php se necessário\n";
echo "3. Reinicie o container: docker-compose restart\n";
echo "4. Teste novamente a requisição\n";
