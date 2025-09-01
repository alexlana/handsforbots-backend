<?php
/**
 * MCP (Model Context Protocol) Handler
 * 
 * Gerencia todas as funcionalidades MCP incluindo ferramentas, processamento
 * de tool calls e execução de ações específicas.
 * 
 * @author Alex Lana
 * @version 2.0
 */


if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    die('This file should not be accessed directly');
}
// Carregar configuração e classes base
try {
    require_once __DIR__ . '/MCPToolInterface.php';
    require_once __DIR__ . '/AbstractMCPTool.php';
    require_once __DIR__ . '/mcp_config.php';
} catch (Exception $e) {
    error_log("MCP Handler Error: Failed to load required files: " . $e->getMessage());
    throw $e;
}

// ============================================================================
// MCP (Model Context Protocol) FUNCTIONS
// ============================================================================

/**
 * Process MCP context and tools
 * @param array $context Context data including MCP information
 * @return array Processed MCP context
 */
function processMCPContext($context) {
    try {
        $mcpContext = $context['mcp_context'] ?? [];
        
        // Get available tools from MCP context (frontend tools)
        $frontendTools = $mcpContext['tools'] ?? [];
        
        // Initialize backend tools array
        $backendTools = [];
        
        // Add default tools if MCP is enabled
        if (function_exists('getConfig') && getConfig('mcp.enabled')) {
            $defaultTools = getDefaultMCPTools();
            $backendTools = array_merge($backendTools, $defaultTools);
        }
        
        // Add configured MCP tools from backend
        $mcpToolInstances = getAllMCPToolInstances();
        foreach ($mcpToolInstances as $toolKey => $toolInstance) {
            if ($toolInstance->isAvailable()) {
                $backendTools[] = $toolInstance->getConfiguration();
            }
        }
        
        // Combine frontend and backend tools
        $allTools = array_merge($frontendTools, $backendTools);
        
        // Log for debugging
        if (function_exists('logSecurityEvent')) {
            logSecurityEvent('mcp_context_processed', [
                'frontend_tools_count' => count($frontendTools),
                'backend_tools_count' => count($backendTools),
                'total_tools_count' => count($allTools),
                'frontend_tools' => array_column($frontendTools, 'name'),
                'backend_tools' => array_column($backendTools, 'name')
            ]);
        }
        
        return [
            'tools' => $allTools,
            'available_models' => $mcpContext['available_models'] ?? [],
            'available_functions' => $mcpContext['available_functions'] ?? []
        ];
    } catch (Exception $e) {
        error_log("MCP Error in processMCPContext: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Extract tool calls from model response
 * @param string $responseText Model response text
 * @return array Array of tool calls
 */
function extractToolCalls($responseText) {
    $toolCalls = [];
    $toolRegex = '/<tool>\s*(\{[\s\S]*?\})\s*<\/tool>/s';
    
    if (preg_match_all($toolRegex, $responseText, $matches)) {
        foreach ($matches[1] as $match) {
            try {
                $toolCall = json_decode($match, true);
                if ($toolCall && isset($toolCall['name'])) {
                    $toolCalls[] = $toolCall;
                }
            } catch (Exception $e) {
                logLLMError('tool_call_parse_error', [
                    'error' => $e->getMessage(),
                    'raw_match' => $match
                ]);
            }
        }
    }
    
    return $toolCalls;
}

/**
 * Execute tool calls
 * @param array $toolCalls Array of tool calls
 * @param array $availableTools Available tools configuration
 * @return array Array of tool execution results
 */
function executeToolCalls($toolCalls, $availableTools) {
    $backendResults = [];
    $frontendTools = [];
    
    // Separate backend and frontend tools
    foreach ($toolCalls as $toolCall) {
        $toolName = $toolCall['name'] ?? '';
        $parameters = $toolCall['parameters'] ?? [];
        
        // Check if tool is configured in backend (mcp_config.php)
        $toolConfig = getMCPToolConfigurationByName($toolName);
        
        if ($toolConfig) {
            // Backend tool - execute it
            try {
                $result = executeMCPToolByName($toolName, $parameters);
                $backendResults[] = [
                    'tool' => $toolName,
                    'parameters' => $parameters,
                    'result' => $result,
                    'success' => $result['success'] !== false,
                    'execution_type' => 'backend'
                ];
            } catch (Exception $e) {
                $backendResults[] = [
                    'tool' => $toolName,
                    'parameters' => $parameters,
                    'result' => [
                        'success' => false,
                        'error' => $e->getMessage()
                    ],
                    'success' => false,
                    'execution_type' => 'backend'
                ];
            }
        } else {
            // Frontend tool - add to frontend tools list
            $frontendTools[] = [
                'tool' => $toolName,
                'parameters' => $parameters,
                'execution_type' => 'frontend'
            ];
        }
    }
    
    // Process frontend tools
    foreach ($frontendTools as $frontendTool) {
        $toolName = $frontendTool['tool'];
        $parameters = $frontendTool['parameters'];
        
        $backendResults[] = [
            'tool' => $toolName,
            'parameters' => $parameters,
            'result' => [
                'success' => true,
                'message' => "Frontend tool execution requested: $toolName",
                'action' => 'execute_tool', // Specific action for show_relevant_content
                'tool_name' => $toolName,
                'parameters' => $parameters,
                'execution_type' => 'frontend',
                'query' => $parameters['query'] ?? null, // Extract query for frontend
                'title' => $parameters['title'] ?? null, // Extract title for frontend
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'success' => true,
            'execution_type' => 'frontend'
        ];
    }
    
    return $backendResults;
}





/**
 * Generate MCP instructions for the prompt
 * @param array $availableTools Available tools
 * @return string Formatted instructions
 */
function generateMCPInstructions($availableTools) {
    if (empty($availableTools)) {
        return '';
    }
    
    $instructions = "INSTRUÇÕES IMPORTANTES:\n\n";
    $instructions .= "Você tem acesso às seguintes ferramentas que pode usar quando necessário:\n\n";
    
    foreach ($availableTools as $tool) {
        $name = $tool['name'] ?? '';
        $description = $tool['description'] ?? '';
        
        $instructions .= "🔧 " . strtoupper($name) . ": $description\n";
        
        if (isset($tool['parameters']['properties'])) {
            $instructions .= "   Parâmetros:\n";
            foreach ($tool['parameters']['properties'] as $paramName => $paramConfig) {
                $paramDesc = $paramConfig['description'] ?? '';
                $instructions .= "   - $paramName: $paramDesc";
                
                if (isset($paramConfig['enum'])) {
                    $instructions .= " (opções: " . implode(', ', $paramConfig['enum']) . ")";
                }
                $instructions .= "\n";
            }
        }
        $instructions .= "\n";
    }
    
    $instructions .= "FORMATO DE RESPOSTA:\n\n";
    $instructions .= "Quando você quiser usar uma ferramenta, responda EXATAMENTE neste formato:\n\n";
    $instructions .= "<tool>\n";
    $instructions .= "{\n";
    $instructions .= '  "name": "nome_da_ferramenta",' . "\n";
    $instructions .= '  "parameters": {' . "\n";
    $instructions .= '    "parametro1": "valor1",' . "\n";
    $instructions .= '    "parametro2": "valor2"' . "\n";
    $instructions .= "  }\n";
    $instructions .= "}\n";
    $instructions .= "</tool>\n\n";
    $instructions .= "Depois de usar a ferramenta, continue sua resposta normalmente.\n\n";
    $instructions .= "Se não precisar usar ferramentas, responda diretamente ao usuário.\n\n";
    $instructions .= "EXEMPLO:\n";
    $instructions .= 'Usuário: "Mostre a seção sobre Docker"' . "\n";
    $instructions .= 'Assistente: <tool>' . "\n";
    $instructions .= "{\n";
    $instructions .= '  "name": "show_relevant_content",' . "\n";
    $instructions .= '  "parameters": {' . "\n";
    $instructions .= '    "query": "docker"' . "\n";
    $instructions .= "  }\n";
    $instructions .= "}\n";
    $instructions .= "</tool>\n\n";
    $instructions .= "Agora vou mostrar a seção sobre Docker para você...";
    
    return $instructions;
}

/**
 * Process response with MCP tool calls
 * @param string $responseText Original response text
 * @param array $toolCalls Extracted tool calls
 * @param array $toolResults Tool execution results
 * @return array Processed response
 */
function processMCPResponse($responseText, $toolCalls, $toolResults) {
    // Remove tool tags from response
    $cleanResponse = preg_replace('/<tool>[\s\S]*?<\/tool>/s', '', $responseText);
    $cleanResponse = trim($cleanResponse);
    
    $response = [];
    $backendContent = [];
    
    // Process tool execution results
    foreach ($toolResults as $toolResult) {
        $toolName = $toolResult['tool'];
        $result = $toolResult['result'];
        $executionType = $toolResult['execution_type'] ?? 'unknown';
        
        if ($result['success']) {
            if ($executionType === 'frontend') {
                // Frontend tool execution - send instruction to frontend
                $response[] = [
                    'recipient_id' => 'user',
                    'text' => '', // Empty text for tool executions
                    'tool_result' => [
                        'action' => $result['action'],
                        'query' => $result['query'],
                        'title' => $result['title'],
                        'tool_name' => $result['tool_name'],
                        'parameters' => $result['parameters']
                    ],
                    'type' => 'tool_execution',
                    'execution_type' => 'frontend'
                ];
            } else {
                // Backend tool execution - collect content to include in response
                if (isset($result['content']) && !empty($result['content'])) {
                    $backendContent[] = $result['content'];
                }
                
                // Log successful backend tool execution (optional)
                if (function_exists('logSecurityEvent')) {
                    logSecurityEvent('backend_tool_executed', [
                        'tool_name' => $toolName,
                        'content_length' => strlen($result['content'] ?? ''),
                        'section' => $result['section'] ?? 'unknown'
                    ]);
                }
            }
        } else {
            $response[] = [
                'recipient_id' => 'user',
                'text' => "❌ Erro ao executar $toolName: " . $result['error'],
                'type' => 'tool_error',
                'execution_type' => $executionType
            ];
        }
    }

    // Combine original response with backend tool content
    $finalResponse = $cleanResponse;

    if (!empty($backendContent)) {
        if (!empty($finalResponse)) {
            $finalResponse .= "\n\n";
        }
        $finalResponse .= implode("\n\n", $backendContent);
    }

    // Add final response if available
    if (!empty($finalResponse)) {
        $response[] = [
            'recipient_id' => 'user',
            'text' => $finalResponse,
            'type' => 'text'
        ];
    }
    
    return $response;
}


/**
 * Get enabled tools configuration
 * @return array Only enabled tools
 */
function getEnabledMCPTools(): array
{
    $allTools = getMCPToolsConfiguration();
    $enabledTools = [];
    
    foreach ($allTools as $toolKey => $toolConfig) {
        if ($toolConfig['enabled']) {
            $enabledTools[$toolKey] = $toolConfig;
        }
    }
    
    return $enabledTools;
}

/**
 * Load tool class file
 * @param string $toolKey Tool key
 * @return bool True if loaded successfully
 */
function loadMCPTool(string $toolKey): bool
{
    $toolsConfig = getMCPToolsConfiguration();
    
    if (!isset($toolsConfig[$toolKey])) {
        return false;
    }
    
    $toolConfig = $toolsConfig[$toolKey];
    $filePath = __DIR__ . '/' . $toolConfig['file'];
    
    if (!file_exists($filePath)) {
        return false;
    }
    
    require_once $filePath;
    return true;
}

/**
 * Create tool instance
 * @param string $toolKey Tool key
 * @return MCPToolInterface|null Tool instance or null if failed
 */
function createMCPToolInstance(string $toolKey): ?MCPToolInterface
{
    try {
        $toolsConfig = getMCPToolsConfiguration();
        
        if (!isset($toolsConfig[$toolKey])) {
            return null;
        }
        
        $toolConfig = $toolsConfig[$toolKey];
        $className = $toolConfig['class'];
        
        // Load the tool file
        if (!loadMCPTool($toolKey)) {
            return null;
        }
        
        // Check if class exists
        if (!class_exists($className)) {
            return null;
        }
        
        // Create instance
        try {
            return new $className();
        } catch (Exception $e) {
            error_log("MCP Config Error: Failed to create instance of $className: " . $e->getMessage());
            return null;
        }
        
    } catch (Exception $e) {
        error_log("MCP Config Error in createMCPToolInstance for $toolKey: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all available tool instances
 * @return array Array of tool instances
 */
function getAllMCPToolInstances(): array
{
    try {
        $enabledTools = getEnabledMCPTools();
        $instances = [];
        
        foreach ($enabledTools as $toolKey => $toolConfig) {
            try {
                $instance = createMCPToolInstance($toolKey);
                if ($instance !== null && $instance->isAvailable()) {
                    $instances[$toolKey] = $instance;
                }
            } catch (Exception $e) {
                error_log("MCP Config Error: Failed to create instance for $toolKey: " . $e->getMessage());
                // Continue with other tools instead of failing completely
            }
        }
        
        return $instances;
        
    } catch (Exception $e) {
        error_log("MCP Config Error in getAllMCPToolInstances: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Execute tool by name
 * @param string $toolName Tool name
 * @param array $parameters Tool parameters
 * @return array Execution result
 */
function executeMCPToolByName(string $toolName, array $parameters): array
{
    $instances = getAllMCPToolInstances();
    
    foreach ($instances as $toolKey => $instance) {
        if ($instance->getName() === $toolName) {
            return $instance->execute($parameters);
        }
    }
    
    return [
        'success' => false,
        'error' => "Tool '$toolName' not found or not available"
    ];
}




