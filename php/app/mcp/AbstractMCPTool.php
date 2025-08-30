<?php
/**
 * Abstract MCP Tool
 * 
 * Classe base abstrata que implementa funcionalidades comuns
 * para todas as ferramentas MCP.
 * 
 * @author Alex Lana
 * @version 1.0
 */

require_once __DIR__ . '/MCPToolInterface.php';

abstract class AbstractMCPTool implements MCPToolInterface
{
    /**
     * Tool name
     * @var string
     */
    protected string $name;
    
    /**
     * Tool description
     * @var string
     */
    protected string $description;
    
    /**
     * Tool parameters schema
     * @var array
     */
    protected array $parameters;
    
    /**
     * Whether tool is available
     * @var bool
     */
    protected bool $available = true;
    
    /**
     * Constructor
     * @param string $name Tool name
     * @param string $description Tool description
     * @param array $parameters Tool parameters schema
     * @param bool $available Whether tool is available
     */
    public function __construct(
        string $name,
        string $description,
        array $parameters = [],
        bool $available = true
    ) {
        $this->name = $name;
        $this->description = $description;
        $this->parameters = $parameters;
        $this->available = $available;
    }
    
    /**
     * Get tool configuration
     * @return array Tool configuration
     */
    public function getConfiguration(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->parameters
        ];
    }
    
    /**
     * Get tool name
     * @return string Tool name
     */
    public function getName(): string
    {
        return $this->name;
    }
    
    /**
     * Check if tool is available
     * @return bool True if tool is available
     */
    public function isAvailable(): bool
    {
        return $this->available;
    }
    
    /**
     * Validate parameters against schema
     * @param array $parameters Parameters to validate
     * @return array Validation result with success and errors
     */
    protected function validateParameters(array $parameters): array
    {
        $errors = [];
        
        // Check required parameters
        $required = $this->parameters['required'] ?? [];
        foreach ($required as $requiredParam) {
            if (!isset($parameters[$requiredParam])) {
                $errors[] = "Required parameter '$requiredParam' is missing";
            }
        }
        
        // Check parameter types and values
        $properties = $this->parameters['properties'] ?? [];
        foreach ($parameters as $paramName => $paramValue) {
            if (isset($properties[$paramName])) {
                $property = $properties[$paramName];
                
                // Check type
                if (isset($property['type'])) {
                    $valid = $this->validateParameterType($paramValue, $property['type']);
                    if (!$valid) {
                        $errors[] = "Parameter '$paramName' must be of type '{$property['type']}'";
                    }
                }
                
                // Check enum values
                if (isset($property['enum']) && !in_array($paramValue, $property['enum'])) {
                    $errors[] = "Parameter '$paramName' must be one of: " . implode(', ', $property['enum']);
                }
                
                // Check max length
                if (isset($property['maxLength']) && strlen($paramValue) > $property['maxLength']) {
                    $errors[] = "Parameter '$paramName' must not exceed {$property['maxLength']} characters";
                }
            }
        }
        
        return [
            'success' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate parameter type
     * @param mixed $value Parameter value
     * @param string $type Expected type
     * @return bool True if valid
     */
    private function validateParameterType($value, string $type): bool
    {
        switch ($type) {
            case 'string':
                return is_string($value);
            case 'integer':
                return is_int($value);
            case 'number':
                return is_numeric($value);
            case 'boolean':
                return is_bool($value);
            case 'array':
                return is_array($value);
            case 'object':
                return is_array($value) || is_object($value);
            default:
                return true;
        }
    }
    
    /**
     * Create error response
     * @param string $message Error message
     * @return array Error response
     */
    protected function createErrorResponse(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Create success response
     * @param array $data Response data
     * @return array Success response
     */
    protected function createSuccessResponse(array $data = []): array
    {
        return array_merge([
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s')
        ], $data);
    }
}
