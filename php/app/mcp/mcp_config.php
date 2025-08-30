<?php
/**
 * MCP Configuration
 * 
 * Arquivo de configuração para gerenciar quais ferramentas MCP
 * estão disponíveis e suas configurações.
 * 
 * @author Alex Lana
 * @version 1.0
 */

/**
 * MCP Tools Configuration
 * @return array Configuration for all available tools
 */
function getMCPToolsConfiguration(): array
{
    return [
        // // WordPress GCP Article Tool
        // 'wordpress_gcp_article' => [
        //     'class' => 'WordPressGCPArticleTool',
        //     'file' => 'tools/WordPressGCPArticleTool.php',
        //     'enabled' => true,
        //     'description' => 'Acessa seções específicas do artigo "WordPress na Google Cloud Platform"'
        // ],
        
        // // Web Search Tool
        // 'search_web' => [
        //     'class' => 'SearchWebTool',
        //     'file' => 'tools/SearchWebTool.php',
        //     'enabled' => false, // Disabled by default
        //     'description' => 'Realiza buscas na web'
        // ],
        
        // // Weather Tool
        // 'get_weather' => [
        //     'class' => 'GetWeatherTool',
        //     'file' => 'tools/GetWeatherTool.php',
        //     'enabled' => false, // Disabled by default
        //     'description' => 'Obtém informações meteorológicas'
        // ]
    ];
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
 * Get tool configuration by name
 * @param string $toolName Tool name
 * @return array|null Tool configuration or null if not found
 */
function getMCPToolConfigurationByName(string $toolName): ?array
{
    $instances = getAllMCPToolInstances();
    
    foreach ($instances as $toolKey => $instance) {
        if ($instance->getName() === $toolName) {
            return $instance->getConfiguration();
        }
    }
    
    return null;
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
