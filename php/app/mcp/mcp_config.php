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
        // WordPress GCP Article Tool
        'wordpress_gcp_article' => [
            'class' => 'WordPressGCPArticleTool',
            'file' => 'tools/WordPressGCPArticleTool.php',
            'enabled' => false,
            'description' => 'Acessa seções específicas do artigo "WordPress na Google Cloud Platform"'
        ],
        
        // Web Search Tool
        'search_web' => [
            'class' => 'SearchWebTool',
            'file' => 'tools/SearchWebTool.php',
            'enabled' => false, // Disabled by default
            'description' => 'Realiza buscas na web'
        ],
        
        // Weather Tool
        'get_weather' => [
            'class' => 'GetWeatherTool',
            'file' => 'tools/GetWeatherTool.php',
            'enabled' => false, // Disabled by default
            'description' => 'Obtém informações meteorológicas'
        ]
    ];
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
