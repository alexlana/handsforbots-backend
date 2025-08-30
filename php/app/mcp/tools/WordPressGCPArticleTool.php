<?php
/**
 * WordPress GCP Article MCP Tool
 * 
 * Ferramenta MCP para acessar o artigo "WordPress na Google Cloud Platform"
 * de forma eficiente, evitando o envio completo do artigo a cada requisição.
 * 
 * @author Alex Lana
 * @version 2.0
 */

require_once __DIR__ . '/../AbstractMCPTool.php';

class WordPressGCPArticleTool extends AbstractMCPTool
{
    /**
     * Article file path
     * @var string
     */
    private string $articleFile;
    
    /**
     * Reference file path
     * @var string
     */
    private string $referenceFile;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->articleFile = __DIR__ . '/artigo-wordpress-gcp.md';
        $this->referenceFile = __DIR__ . '/artigo-referencia.json';
        
        parent::__construct(
            'get_wordpress_gcp_article',
            'Acessa seções específicas do artigo "WordPress na Google Cloud Platform (GCP) com Docker, Cloud Run e Cloud Storage" de Alex Lana. Use esta ferramenta quando o usuário fizer perguntas sobre o artigo ou precisar de informações específicas sobre a implantação do WordPress na GCP.',
            [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'description' => 'Ação a ser executada',
                        'enum' => [
                            'get_section',
                            'search_content',
                            'get_overview',
                            'get_troubleshooting',
                            'get_configuration'
                        ]
                    ],
                    'section' => [
                        'type' => 'string',
                        'description' => 'Seção específica do artigo (usado com action=get_section)',
                        'enum' => [
                            'introducao',
                            'requisitos',
                            'projeto-github',
                            'rodar-localmente',
                            'preparar-gcp',
                            'configurar-cloud-run',
                            'carregar-dados',
                            'configurar-wordpress',
                            'servicos-adicionais',
                            'gitops',
                            'escolhas',
                            'troubleshooting'
                        ]
                    ],
                    'query' => [
                        'type' => 'string',
                        'description' => 'Termo de busca no artigo (usado com action=search_content)',
                        'maxLength' => 100
                    ],
                    'topic' => [
                        'type' => 'string',
                        'description' => 'Tópico específico para configuração (usado com action=get_configuration)',
                        'enum' => [
                            'cloud-storage',
                            'cloud-sql',
                            'cloud-run',
                            'load-balancing',
                            'cdn',
                            'vpc',
                            'wp-stateless',
                            'gitops',
                            'custos',
                            'seguranca'
                        ]
                    ]
                ],
                'required' => ['action']
            ],
            file_exists($this->articleFile) && file_exists($this->referenceFile)
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
        
        $action = $parameters['action'] ?? '';
        
        switch ($action) {
            case 'get_section':
                return $this->getArticleSection($parameters['section'] ?? '');
                
            case 'search_content':
                return $this->searchArticleContent($parameters['query'] ?? '');
                
            case 'get_overview':
                return $this->getArticleOverview();
                
            case 'get_troubleshooting':
                return $this->getTroubleshootingInfo();
                
            case 'get_configuration':
                return $this->getConfigurationInfo($parameters['topic'] ?? '');
                
            default:
                return $this->createErrorResponse("Ação desconhecida: $action");
        }
    }
    
    /**
     * Get specific section from article
     * @param string $section Section name
     * @return array Section content
     */
    private function getArticleSection(string $section): array
    {
        if (!file_exists($this->articleFile)) {
            return $this->createErrorResponse('Arquivo do artigo não encontrado');
        }
        
        $content = file_get_contents($this->articleFile);
        
        // Map section names to content patterns
        $sectionPatterns = [
            'introducao' => '/^# WordPress na Google Cloud Platform.*?(?=## )/ms',
            'requisitos' => '/## Solução.*?### Requisitos:(.*?)(?=### )/ms',
            'projeto-github' => '/### Use o projeto do GitHub:(.*?)(?=### )/ms',
            'rodar-localmente' => '/### Para rodar localmente:(.*?)(?=### )/ms',
            'preparar-gcp' => '/### Na GCP, primeiro prepare os serviços:(.*?)(?=### )/ms',
            'configurar-cloud-run' => '/### Configure o Cloud Run:(.*?)(?=### )/ms',
            'carregar-dados' => '/### Carregando os dados(.*?)(?=## )/ms',
            'configurar-wordpress' => '/## Faça as últimas configurações no WordPress(.*?)(?=## )/ms',
            'servicos-adicionais' => '/## Serviços para melhorar a performance(.*?)(?=## )/ms',
            'gitops' => '/## Sobre GitOps(.*?)(?=## )/ms',
            'escolhas' => '/## Sobre as escolhas feitas(.*?)(?=## )/ms',
            'troubleshooting' => '/## Troubleshooting(.*?)(?=## )/ms'
        ];
        
        if (!isset($sectionPatterns[$section])) {
            return $this->createErrorResponse("Seção não encontrada: $section");
        }
        
        if (preg_match($sectionPatterns[$section], $content, $matches)) {
            $sectionContent = trim($matches[1] ?? $matches[0]);
            
            return $this->createSuccessResponse([
                'section' => $section,
                'content' => $sectionContent,
                'content_length' => strlen($sectionContent)
            ]);
        }
        
        return $this->createErrorResponse("Conteúdo da seção '$section' não encontrado");
    }
    
    /**
     * Search content in article
     * @param string $query Search query
     * @return array Search results
     */
    private function searchArticleContent(string $query): array
    {
        if (!file_exists($this->articleFile)) {
            return $this->createErrorResponse('Arquivo do artigo não encontrado');
        }
        
        $content = file_get_contents($this->articleFile);
        $query = strtolower(trim($query));
        
        // Split content into paragraphs
        $paragraphs = preg_split('/\n\s*\n/', $content);
        $results = [];
        
        foreach ($paragraphs as $index => $paragraph) {
            if (stripos($paragraph, $query) !== false) {
                $results[] = [
                    'paragraph' => $index + 1,
                    'content' => trim($paragraph),
                    'relevance' => 'high'
                ];
            }
        }
        
        return $this->createSuccessResponse([
            'query' => $query,
            'results_count' => count($results),
            'results' => array_slice($results, 0, 5) // Limit to 5 results
        ]);
    }
    
    /**
     * Get article overview
     * @return array Article overview
     */
    private function getArticleOverview(): array
    {
        if (!file_exists($this->referenceFile)) {
            return $this->createErrorResponse('Arquivo de referência não encontrado');
        }
        
        $reference = json_decode(file_get_contents($this->referenceFile), true);
        
        return $this->createSuccessResponse([
            'overview' => [
                'titulo' => $reference['titulo'],
                'autor' => $reference['autor'],
                'secoes' => $reference['secoes'],
                'links_importantes' => $reference['links_importantes'],
                'configuracoes_padrao' => $reference['configuracoes_padrao'],
                'custos_importantes' => $reference['custos_importantes'],
                'seguranca' => $reference['seguranca']
            ]
        ]);
    }
    
    /**
     * Get troubleshooting information
     * @return array Troubleshooting info
     */
    private function getTroubleshootingInfo(): array
    {
        $troubleshooting = $this->getArticleSection('troubleshooting');
        
        if (!$troubleshooting['success']) {
            return $troubleshooting;
        }
        
        // Extract specific troubleshooting topics
        $content = $troubleshooting['content'];
        $topics = [];
        
        // Extract problem-solution pairs
        if (preg_match_all('/### (.*?)\n(.*?)(?=### |$)/s', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $topics[] = [
                    'problem' => trim($match[1]),
                    'solution' => trim($match[2])
                ];
            }
        }
        
        return $this->createSuccessResponse([
            'troubleshooting_topics' => $topics,
            'content' => $troubleshooting['content']
        ]);
    }
    
    /**
     * Get configuration information for specific topic
     * @param string $topic Configuration topic
     * @return array Configuration info
     */
    private function getConfigurationInfo(string $topic): array
    {
        $configurations = [
            'cloud-storage' => [
                'title' => 'Configuração do Cloud Storage',
                'steps' => [
                    'Criar bucket com nome "alx-wp-gcp"',
                    'Local: us (várias regições dos Estados Unidos)',
                    'Classe de armazenamento: Coldline',
                    'Controle de acesso: Detalhado',
                    'Configurar acesso de leitura para todos os usuários',
                    'Criar conta de serviço com papel "Administrador do Storage"',
                    'Gerar chave de acesso JSON'
                ],
                'section' => 'preparar-gcp'
            ],
            'cloud-sql' => [
                'title' => 'Configuração do Cloud SQL',
                'steps' => [
                    'Criar instância MySQL com ID "alx-wp-gcp"',
                    'Tipo: Development',
                    'Tipo de máquina: Núcleo compartilhado',
                    'Armazenamento: 10 GB',
                    'Configurar IP público',
                    'Criar banco de dados "alx-wp-gcp"',
                    'Criar usuário para acesso (evitar root)'
                ],
                'section' => 'preparar-gcp'
            ],
            'cloud-run' => [
                'title' => 'Configuração do Cloud Run',
                'steps' => [
                    'Selecionar repositório GitHub com fork',
                    'Ramificação: ^dev$',
                    'Build type: Dockerfile',
                    'Número máximo de instâncias: 1',
                    'Autenticação: Permitir invocações não autenticadas',
                    'Capacidade: 128MiB',
                    'Configurar variáveis de ambiente do banco'
                ],
                'section' => 'configurar-cloud-run'
            ],
            'load-balancing' => [
                'title' => 'Configuração do Load Balancing',
                'steps' => [
                    'Criar balanceador de carga HTTP(S) global',
                    'Configurar front-end com HTTPS',
                    'Criar endereço IP',
                    'Configurar certificado SSL',
                    'Ativar redirecionamento HTTP para HTTPS',
                    'Configurar back-end com Cloud Run',
                    'Ativar Cloud CDN'
                ],
                'section' => 'servicos-adicionais'
            ],
            'cdn' => [
                'title' => 'Configuração da CDN',
                'steps' => [
                    'Ativar Cloud CDN no Load Balancer',
                    'Configurar cache adequado',
                    'Atenção para dados pessoais de usuários',
                    'Testar cache para evitar vazamento de dados'
                ],
                'section' => 'servicos-adicionais'
            ],
            'vpc' => [
                'title' => 'Configuração da Rede VPC',
                'steps' => [
                    'Criar rede VPC',
                    'Modo de criação de sub-rede: Automática',
                    'Criar conector para rede VPC',
                    'Configurar Cloud Run para usar VPC',
                    'Configurar Cloud SQL para IP Particular',
                    'Remover IP Público do Cloud SQL'
                ],
                'section' => 'servicos-adicionais'
            ],
            'wp-stateless' => [
                'title' => 'Configuração do WP-Stateless',
                'steps' => [
                    'Ativar plugin WP-Stateless',
                    'General: Stateless',
                    'File URL Replacement: Enable editor & meta',
                    'Bucket: alx-wp-gcp',
                    'Service Account JSON: conteúdo do arquivo JSON',
                    'Testar upload de imagem'
                ],
                'section' => 'configurar-wordpress'
            ],
            'gitops' => [
                'title' => 'Estratégia GitOps',
                'steps' => [
                    'Desenvolvimento local com Docker',
                    'Branchs separados por desenvolvedor',
                    'Pull request para staging',
                    'Backup do banco antes de atualizar',
                    'Teste em staging antes da produção',
                    'Gerenciamento manual de revisões no Cloud Run'
                ],
                'section' => 'gitops'
            ],
            'custos' => [
                'title' => 'Controle de Custos',
                'points' => [
                    'Cloud SQL é o maior custo',
                    'Load Balancing e CDN têm custo extra',
                    'VPC Connector tem custo extra',
                    'Configurar orçamentos e alertas',
                    'Usar crédito inicial da GCP para testes',
                    'Excluir projeto para interromper custos'
                ]
            ],
            'seguranca' => [
                'title' => 'Medidas de Segurança',
                'points' => [
                    'Repositório Git deve ser privado',
                    'Trocar credenciais padrão do WordPress',
                    'Configurar IPs específicos para Cloud SQL',
                    'Usar Secret Manager para senhas',
                    'Evitar variáveis de ambiente para dados sensíveis'
                ]
            ]
        ];
        
        if (!isset($configurations[$topic])) {
            return $this->createErrorResponse("Tópico de configuração não encontrado: $topic");
        }
        
        $config = $configurations[$topic];
        
        // If there's a specific section, get its content
        if (isset($config['section'])) {
            $sectionContent = $this->getArticleSection($config['section']);
            if ($sectionContent['success']) {
                $config['section_content'] = $sectionContent['content'];
            }
        }
        
        return $this->createSuccessResponse([
            'configuration' => $config
        ]);
    }
}
