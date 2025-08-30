<?php
/**
 * MCP Tool Interface
 * 
 * Interface padronizada para todas as ferramentas MCP.
 * Define os métodos obrigatórios que cada tool deve implementar.
 * 
 * @author Alex Lana
 * @version 1.0
 */

interface MCPToolInterface
{
    /**
     * Get tool configuration
     * @return array Tool configuration with name, description and parameters
     */
    public function getConfiguration(): array;
    
    /**
     * Execute tool with given parameters
     * @param array $parameters Tool parameters
     * @return array Execution result
     */
    public function execute(array $parameters): array;
    
    /**
     * Get tool name
     * @return string Tool name
     */
    public function getName(): string;
    
    /**
     * Check if tool is available/enabled
     * @return bool True if tool is available
     */
    public function isAvailable(): bool;
}
