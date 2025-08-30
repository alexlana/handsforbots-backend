<?php
/**
 * Get Weather MCP Tool
 * 
 * Ferramenta MCP para obter informações meteorológicas.
 * Desabilitada por padrão - requer configuração de API.
 * 
 * @author Alex Lana
 * @version 1.0
 */

require_once __DIR__ . '/../AbstractMCPTool.php';

class GetWeatherTool extends AbstractMCPTool
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(
            'get_weather',
            'Obtém informações meteorológicas para uma localização específica',
            [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'Localização (cidade, país ou coordenadas)',
                        'maxLength' => 100
                    ],
                    'units' => [
                        'type' => 'string',
                        'description' => 'Unidades de temperatura',
                        'enum' => ['celsius', 'fahrenheit'],
                        'default' => 'celsius'
                    ],
                    'forecast_days' => [
                        'type' => 'integer',
                        'description' => 'Número de dias para previsão',
                        'minimum' => 1,
                        'maximum' => 7,
                        'default' => 1
                    ]
                ],
                'required' => ['location']
            ],
            false // Disabled by default
        );
    }
    
    /**
     * Execute tool
     * @param array $parameters Tool parameters
     * @return array Execution result
     */
    public function execute(array $parameters): array
    {
        // Validate parameters
        $validation = $this->validateParameters($parameters);
        if (!$validation['success']) {
            return $this->createErrorResponse('Parameter validation failed: ' . implode(', ', $validation['errors']));
        }
        
        $location = $parameters['location'] ?? '';
        $units = $parameters['units'] ?? 'celsius';
        $forecastDays = $parameters['forecast_days'] ?? 1;
        
        if (empty($location)) {
            return $this->createErrorResponse('Location parameter is required');
        }
        
        // This is a placeholder - in a real implementation, you'd call a weather API
        return $this->createSuccessResponse([
            'message' => "Weather information for: $location",
            'location' => $location,
            'units' => $units,
            'forecast_days' => $forecastDays,
            'weather' => [
                'location' => $location,
                'temperature' => '22°C',
                'condition' => 'Sunny',
                'humidity' => '65%',
                'wind_speed' => '10 km/h',
                'pressure' => '1013 hPa'
            ],
            'note' => 'This tool is currently disabled. Configure weather API credentials to enable it.'
        ]);
    }
}
