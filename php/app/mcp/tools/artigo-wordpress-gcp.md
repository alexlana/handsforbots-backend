# WordPress na Google Cloud Platform (GCP) com Docker, Cloud Run e Cloud Storage

A arquitetura do WordPress não favorece o uso em serviços na nuvem, mas usando volumes no Docker e um plugin para persistência de arquivos de mídia em um bucket é possível aproveitar bem os recursos da Google Cloud Platform (GCP) e GitOps. Acredito que tudo aqui possa ser replicado na AWS e outros provedores.

Os diagramas abaixo resumem a proposta ([e podem ser editados no Excalidraw](/static/content/wordpress-na-google-cloud-platform-GCP-com-docker-cloud-run-e-cloud-storage/alx-wp-gcp.excalidraw)):

![Proposta para WordPress na GCP](/static/content/wordpress-na-google-cloud-platform-GCP-com-docker-cloud-run-e-cloud-storage/0.proposta.svg)
![Infra para WordPress na GCP](/static/content/wordpress-na-google-cloud-platform-GCP-com-docker-cloud-run-e-cloud-storage/1.infra.svg)
![Importação de BD e /wp-content/uploads na GCP](/static/content/wordpress-na-google-cloud-platform-GCP-com-docker-cloud-run-e-cloud-storage/2.importar.dados.svg)
![Deploy do WordPress na Cloud Run (GCP)](/static/content/wordpress-na-google-cloud-platform-GCP-com-docker-cloud-run-e-cloud-storage/3.deploy.svg)

> **Atenção!** Os procedimentos desse artigo vão gerar custos na Google Cloud Platform. Para novos assinantes da GCP existe um crédito que pode ser usado para testes durante um período determinado. Passando disso, tudo será cobrado e pode não ser barato, tenha atenção com esses custos!

- [Para encerrar o projeto e interromper custos futuros, clique nos 3 pontos à direta do seu projeto e selecione 'Excluir' (tudo no projeto será removido permantentemente)](https://console.cloud.google.com/cloud-resource-manager)
- [Acesse 'Orçamentos e alertas', crie um orçamento e defina alertas para minimizar as surpresas](https://console.cloud.google.com/billing/budgets)
- [Calculadora de custos](https://cloud.google.com/products/calculator)

## Solução (nem tão) rápida para ter o projeto online

O processo é um pouco longo, mas tentei deixar bem passo a passo para facilitar a execução.

### Requisitos:

Prepare suas contas e instalações necessárias para seguir.

- Conta na [GCP](https://console.cloud.google.com)
- Conta no [GitHub](https://github.com)
- [Docker](https://www.docker.com) rodando localmente
- [Git CLI](https://git-scm.com/book/pt-br/v2/Começando-Instalando-o-Git) rodando localmente. Alternativamente, você pode usar o [GitHub Desktop](https://desktop.github.com)
- Se você for usar um domínio próprio/customizado:
  - **Se você for adquirir um domínio na GCP**: [Contrate o domínio aqui.](https://domains.google/intl/pt-BR/)
  - **Se você vai usar um domínio ou subdomínio externo**: Verifique como configurar o DNS em sua hospedagem.

### Use o projeto do GitHub:

**Se você usa o Git CLI:**
1. Dê um fork [no projeto](https://github.com/alexlana/alx-wp-gcp) do GitHub
2. Crie um diretório para o projeto em seu computador
3. De dentro do diretório, no terminal, use:
   ```bash
   git init .
   git remote add origin https://github.com/SEUUSUARIO/alx-wp-gcp
   git pull origin main
   ```
   * Lembre-se de alterar 'SEUUSUARIO' por seu nome de usuário no GitHub.

**Se você usa o GitHub Desktop:**
1. Dê um fork [no projeto](https://github.com/alexlana/alx-wp-gcp) do GitHub
2. Na página de seu fork, clique no botão verde 'code' e em 'Open with GitHub Desktop'
3. Escolha o endereço local para salvar o projeto onde você vai trabalhar

### Para rodar localmente:

1. Ative o Docker
2. Abra o terminal e, de dentro da pasta do projeto, digite `docker-compose up -d`
3. Em seu navegador, acesse [https://localhost](https://localhost)
4. Configure o site, plugins, tema... você pode deixar o site pronto localmente antes de subir para a nuvem

> **Dados de acesso à administração do WordPress da nossa instalação original:**
> - Usuário: alxwpgcp
> - Senha: XsqX71Q)Vd*w$*O$lk
> 
> **Obrigatoriamente, troque os dados de acesso!**

### Na GCP, primeiro prepare os serviços:

Vamos iniciar ativando e configurando os serviços necessários na GCP.

1. Acesse [console.cloud.google.com](https://console.cloud.google.com) e faça login na sua conta. Se você não tiver uma conta, crie, até aqui não há custos
2. Na barra superior azul, clique ao lado da logo para selecionar/alternar projetos e, no modal que se abrirá, clique em 'Novo Projeto', informe o nome e salve
3. Você vai precisar [ativar o faturamento](https://console.cloud.google.com/billing/projects) clicando nos 3 pontos à direita do nome do projeto e em 'Alterar faturamento'. [Veja como encerrar os custos futuros](#remover-projeto)
4. Confirme se você está no projeto correto (na barra superior azul) e ative o [Cloud Storage](https://console.cloud.google.com/storage/browser)
   - [Crie um 'bucket'](https://console.cloud.google.com/storage/create-bucket), para o teste use o nome 'alx-wp-gcp', como local selecione 'us (várias regições dos Estados Unidos)', classe de armazenamento deve ser 'Coldline', e para 'Controle de acesso' selecione 'Detalhado'. Não altere os outros itens. Verifique as estimativas de custo no canto superior direito
   - Configure acesso de leitura para todos os usuários
   - Crie uma conta de serviço com papel 'Administrador do Storage'. Para os testes, use o nome 'alx-wp-gcp-stateless'
   - Gere uma chave de acesso e salve o arquivo JSON para configurar o WordPress depois
5. [Ative o Cloud Build](https://console.cloud.google.com/marketplace/product/google/cloudbuild.googleapis.com)
6. [Ative o Compute Engine](https://console.cloud.google.com/marketplace/product/google/compute.googleapis.com)
7. Adicione uma [rede VPC](https://console.cloud.google.com/networking/networks/add) (se necessário, ative a API)
   - Infome um nome
   - Em 'Modo de criação de sub-rede' selecione 'Automática'
   - Clique em 'Criar'
8. Ative o Cloud SQL e [crie uma instância MySQL](https://console.cloud.google.com/sql) (esse é um dos recursos que deve gerar mais custos). Configure observando os itens abaixo:
   - Para testes, use o ID 'alx-wp-gcp' e uma senha aleatória
   - Mais abaixo, escolha 'Development'
   - Vá até 'Personalizar sua instância'
   - Em 'Tipo de máquina' escolha 'Núcleo compartilhado'
   - Em 'Conexões' clique em 'Adicionar rede' e informe o nome 'alx-wp-gcp' e em 'Rede *' informe 0.0.0.0/24
   - Em 'Conexões' marque a opção de IP Público
   - Clique em 'Adicionar rede', informe um nome e o IP '0.0.0.0/0'. Esse IP permite que toda a Internet acesse a instância do banco de dados, para mais segurança você pode [pesquisar o IP](https://www.site24x7.com/find-ip-address-of-web-site.html) da instância Cloud Run (após criá-la) e substituir
   - Em 'Armazenamento' escolha '10 GB'
   - Na página após salvar, procure e copie o 'Endereço IP público' para usar depois no Cloud Run
   - Instância criada (pode demorar de 10 a 20 minutos), [crie um banco de dados](https://console.cloud.google.com/sql/instances/alx-wp-gcp/databases) com o nome 'alx-wp-gcp'
   - Crie um usuário para acesso ao banco, evite usar o root. Copie usuário e senha para usar depois
9. Ative a [Cloud SQL Admin API](https://console.cloud.google.com/marketplace/product/google/sqladmin.googleapis.com)
10. Acesse o [IAM Admin](https://console.cloud.google.com/iam-admin/)
    - Em 'Conta de serviço padrão do Compute Engine' (ou 'Compute Engine default service account'), clique no ícone do lápis
    - Clique em 'Adicionar outro papel'
    - Adicione o papel 'Cliente do Cloud SQL'
    - Clique em 'Salvar'

### Configure o Cloud Run:

Considero o Cloud Run o centro de todo esse processo, todos os serviços e procedimentos visam dar suporte ao site que vai estar em containers rodando nesse serviço.

1. [acesse a página do Cloud Run](https://console.cloud.google.com/run) e confirme se você está no projeto correto (veja na barra superior azul)
2. Clique em 'Criar serviço'
3. Selecione 'Implantar continuamente novas revisões de um repositório de origem' e 'Configurar com Cloud Build'
4. Em 'Provedor de repositório' mantenha o GitHub e selecione o repositório 'alx-wp-gcp', o qual você deu um 'fork' antes. Clique em 'Próxima', na 'Ramificação' informe '^dev$' e em 'Build type' informe 'Dockerfile', não altere o 'Local de origem'. Clique em 'Salvar'
5. Em 'Número máximo de instâncias' altere para 1
6. Em 'Autenticação' selecione 'Permitir invocações não autenticadas'
7. Clique em 'Contêiner, conexões, segurança' e em 'Capacidade' altere para 128MiB (ou a menor que houver)
8. Na aba 'Conexões', siga esses pasoss:
   - No item 'Conexões do Cloud SQL', clique em 'Adicionar conexão'
   - Clique em 'Cloud SQL Admin API'
   - Em 'Instância do Cloud SQL 1', selecione a instância criada anteriormente
9. Em 'Variáveis de ambiente' informe os valores abaixo ou os que você tiver definido no Cloud SQL:
   - WORDPRESS_DATABASE_NAME: alx-wp-gcp
   - WORDPRESS_DATABASE_USER: alx-wp-gcp
   - WORDPRESS_DATABASE_PASSWORD: INFORME-A-SENHA-DO-BANCO-DE-DADOS
   - WORDPRESS_DATABASE_HOST: IP-PÚBLICO-DA-INSTÂNCIA-CLOUD-SQL
   
   > * esse não é o meio mais seguro de informar as senhas. Para um meio seguro, saiba mais sobre o [Secret Manager](https://console.cloud.google.com/marketplace/product/google/secretmanager.googleapis.com). Também pode ser interessante evitar variáveis de ambiente.
10. Em seu projeto local, arquivo `/app_data/config/wp-config.staging.php`, insira, também, as informações do Cloud SQL:
    - DB_NAME: alx-wp-gcp
    - DB_USER: alx-wp-gcp
    - DB_PASSWORD: INFORME-A-SENHA-DO-BANCO-DE-DADOS
    - DB_HOST: IP-PÚBLICO-DA-INSTÂNCIA-CLOUD-SQL
    
    > * muita atenção com o repositório Git, ele precisa ser privado ou esses dados serão expostos publicamente.
11. Na tela de 'Detalhes do serviço' você já vai encontrar a URL de sua instalação. Para saber qual é a URL e outras informações [acesse a tela inicial do Cloud Run](https://console.cloud.google.com/run) e clique sobre o nome da instância
    - **Atenção:** não prossiga com a instalação do WordPress, vamos usar os dados existentes e que você pode ter trabalhado em sua instalação local

### Carregando os dados

Agora você precisa migrar o banco de dados de sua instalação local para a instalação da GCP e subir as imagens da pasta /wp-content/uploads.

### Exporte os dados de sua instalação local:

1. Confirme o endereço de sua instalação na GCP. Se você usar um domínio próprio, ele é o endereço correto, senão, [siga essa orientação](#url-da-instalacao) para descobrir a URL
2. [Faça login](https://localhost/wp-login.php) na instalação local com os dados que já informamos e acesse a [página de exportação](https://localhost/wp-admin/tools.php?page=wp-migrate-db) do plugin 'WP Migrate Lite'
3. Clique em '+ New migration', depois em 'Export database' e configure conforme abaixo:
   - Em 'Advanced options' desmarque a opção 'Compress file with gzip' (costuma dar problema)
   - Em 'Custom Find & Replace' informe o endereço da instalação da GCP ao lado de '//localhost' iniciando o endereço com duas barras (ex.: //alexlana.dev.br)
   - Clique no 'X' para remover as outras duas linhas
4. Clique em 'Export database'. Localize o arquivo SQL salvo para a próxima etapa

### Importe os arquivos da pasta /wp-content/uploads

1. Acesse o [Cloud Storage](https://console.cloud.google.com/storage/browser)
2. Entre no bucket criado em passos anteriores
3. Arraste tudo dentro da pasta local 'local_persistence/uploads' para a janela da GCP e aguarde até finalizar o upload. Essa pasta na pasta do projeto local é um volume para a 'wp-content/uploads'

### Importe os dados para o Cloud SQL:

1. Ainda na página do bucket (Cloud Storage) envie o arquivo SQL exportado da instalação local
2. Acesse o [Cloud SQL](https://console.cloud.google.com/sql/instances), clique sobre o nome de sua instância e siga os próximos passos
3. Clique em 'Importar'
4. Selecione o arquivo SQL enviado para o bucket
5. Seleciona o banco de dados criado
6. Clique no botão 'Importar'
7. Após concluir a importação, pode apagar o arquivo SQL no bucket

**Agora você já pode acessar a URL do site na GCP. Se tudo deu certo, o site já estará rodando normalmente.**

## Faça as últimas configurações no WordPress

1. Acesse a administração do WordPress (diretório /wp-admin)
2. Acesse o menu 'Plugins'
3. Ative o plugin 'WP-Stateless'
4. No menu 'Mídia' > 'Stateless Settings', configure dessa forma:
   - Em 'General', selecione 'Stateless'
   - Em 'File URL Replacement', selecione 'Enable editor & meta'
   - Em 'Bucket', informe o nome 'alx-wp-gcp'
   - Em 'Service Account JSON', cole o conteúdo do arquivo JSON gerado ao criar as chaves de acesso ao bucket
   - Salve as alterações
   - Para testar, envie uma imagem no gerenciador de mídia e verifique se o endereço da imagem enviada contém o domínio

## Serviços para melhorar a performance e a segurança de sua aplicação

Você não precisa desses serviços para ter o site rodando e eles geram custos extras, mas eles devem conferir uma melhor qualidade à sua aplicação.

### Rede interna

Para mais segurança você pode usar uma rede interna e ajustar configurações do Cloud SQL e do Cloud Run para utilizá-la. Atenção aos custos extras.

1. Crie um [conector para a rede VPC](https://console.cloud.google.com/networking/connectors/list)
   - Informe um nome
   - Em 'Rede', selecione a rede criada anteriormente
   - Em 'Sub-rede', selecione 'Intervalo de IP personalizado'
   - Em 'Intervalo de IP', informe 10.8.0.0 (se houver erro, tente usar outro IP, como 10.7.0.0, por exemplo)
   - Clique em 'Criar'
2. Acesse a página de detalhes de sua [instância do Cloud Run](https://console.cloud.google.com/run)
   - Clique em 'Editar e implantar uma nova revisão' no topo
   - Em 'Contêiners', troque o valor de WORDPRESS_DATABASE_HOST para :/cloudsql/NOME-DA-CONEXÃO-SQL (você vai encontrar esse nome na [página de detalhes](https://console.cloud.google.com/sql/instances/) da instância Cloud SQL criada)
   - Em 'Conexões', no item 'VPC' selecione a rede VPC criada anteriormente
   - Clique em 'Implantar'
3. [Acesse o Cloud SQL](https://console.cloud.google.com/sql/instances/) e selecione a instância criada
   - Clique em 'Editar'
   - Em 'Conexões', marque 'IP Particular', selecione a rede VPC criada anteriormente e desmarque a opção 'IP Público'
   - Clique em 'Salvar'

> As configurações acima geram custos mais baixos, para mais performance ou maior segurança avalie a configuração mais adequada.

### Load balancing

Configurar um load balancer e uma CDN (Rede de Distribuição de Conteúdo, 'Content Distribution Network', em inglês) vai melhorar muito a performance de sua aplicação. Mas **atenção**, esses serviços têm um custo extra.

Se você tiver um domínio que possa usar nos testes, configure um balanceador de carga e uma CDN, e aponte o domínio/subdomínio para a GCP.

### Configurando o Load Balancer e a CDN

1. **Início da configuração:**
   - Busque por 'load balancing' na caixa de busca no topo do site da GCP
   - Na página do 'Balanciamento de carga', clique em 'Criar balanceador de carga'
   - Na próxima tela, clique em 'Iniciar configuração'
   - Depois selecione 'Da internet para VMs ou serviços sem servidor' e 'Balanceador de carga HTTP(S) global' e clique em 'Continuar'
   - Na tela 'Novo balanceador de carga de HTTPS(S)', informe 'Nome' (campo à esquerda)

2. **Em 'Configuração de front-end':**
   - Informe 'Nome'
   - Selecione protocolo 'HTTPS (inclui HTTP/2)'
   - Em 'IP Address' escolha 'Criar endereço IP' e informe um nome
   - Em 'Certificado', clique em 'Criar um novo certificado' para enviar um certificado próprio, ou solicitar um gerenciado pelo Google, além de informar o domínio que será usado
   - Selecione 'Ativar redirecionamento de HTTP para HTTPS'
   - Clique em 'Concluir'

3. **Em 'Configuração de back-end':**
   - Clique em 'Criar um serviço de back-end'
   - Informe um 'Nome'
   - Em 'Tipo de back-end' selecione 'Grupo de endpoints de rede sem servidor'
   - Em 'Novo back-end' clique em 'Criar grupo de endpoints de rede sem servidor' e configure:
     - Informe 'Nome'
     - Selecione a 'Região' 'us-central1 (Iowa)'
     - Em 'Tipo de grupo de endpoints de rede sem servidor' selecione 'Cloud Run'
     - Selecione o serviço criado na Cloud Run anteriormente
     - Selecione 'Ativar o Cloud CDN'
     - Configure o cache de acordo com as necessidades de seu projeto (**cuidado:** estude e teste bastante se seu site mostra dados pessoais de um usuário para evitar que esses dados apareçam para outros usuários)
     - Clique em 'Criar'

4. Em 'Regras de roteamento' não é necessário alteração
5. Revise em 'Analisar e finalizar' e clique em 'Criar'
6. Siga os próximos passos para configuração do DNS o mais rápido possível para ativação correta do certificado

### Se o domínio não foi comprado na GCP, configure o DNS da hospedagem do domínio

1. Assim que o serviço de Load Balancing for criado na GCP, você pode visualizar o IP em 'Front-end' (é mostrado o par 'IP:Porta', só o IP importa para configuração), copie o IP
2. Na hospedagem do domínio, procure a edição de DNS (ou zona DNS). Cada hospedagem tem sua própria interface, informe-se com o suporte da hospedagem se tiver dúvidas
3. Na linha correspondente ao domínio principal, informe o IP da GCP e salve

Por fim, aguarde a ativação do certificado, o status 'Provisioning' indica que ainda não foi gerado. O 'Active' indica que seu site já deve estar acessível e para outros status será importante você [verificar o erro para corrigir](https://cloud.google.com/load-balancing/docs/ssl-certificates/troubleshooting?hl=pt_br).

## Sobre GitOps

Originalmente, o WordPress conta com facilidades para atualização do sistema, sendo possível atualizar o próprio WordPress, temas e plugins a partir da área de administração. Mas isso gera indisponibilidade e, eventualmente, cria bugs, podendo até derrubar o site permanentemente. Outra desvantagem é um prejuízo para o histórico de versões da aplicação.

Isso é um dos pontos a ser resolvido por este projeto. Se vamos tentar usar cada vez melhor a infraestrutura de nuvem, também podemos utilizar de forma mais eficiente os recursos de GitOps dessas plataformas.

As características do repositório listadas abaixo possibilitam ter uma melhor rotina de atualizações:

- A versão do WordPress é escolhida a partir das imagens da Bitnami disponíveis e é informada no `Dockerfile`
- Os plugins são instalados e testados localmente antes de subirem para staging / homologação e produção
- Arquivos de mídia são armazenados em um bucket com a utilização de plugin apropriado (para a GCP estou usando o WP-Stateless)
- Banco de dados não é atualizado quando os arquivos do sistema são atualizados, a menos que o próprio WordPress ou plugins necessitem. Isso evita sobrepor os dados da aplicação em produção, mas também é um ponto de atenção, é importante ter um backup do banco em produção antes de atualizar

### Passo a passo

A rotina de atualização deve ocorrer normalmente como ocorre quando se utiliza frameworks e CMSs nascidos na era da nuvem:

1. Utilizamos desenvolvimento local com Docker com Docker Compose e branchs separados por desenvolvedor para criação de novas ferramentas ou atualizações de CMS e plugins
2. Se tudo estiver ok com a evolução do sistema em testes locais, podemos criar uma pull request para o branch de staging
3. Se não houver indicação de problemas no merge, faça primeiro um backup do banco de dados do ambiente a ser atualizado online
4. Aprove a pull request e ela será enviada automaticamente para o ambiente de staging. Uma sugestão que considero importante tanto no ambiente de staging, quanto no de produção: configure seu serviço na [Cloud Run](https://console.cloud.google.com/run/), na aba 'Revisões', em 'Gerenciar tráfego' para fazer manualmente o gerenciamento das versões:
   - Não utilize a opção 'Latest healthy revision', ao invés disso, selecione manualmente qual revisão deve ficar acessível
   - Ao lado direito de cada revisão, ao passar o mouse por cima, você vai ver o ícone '+'. Ele serve para gerar um endereço para você fazer um primeiro teste da nova revisão sem afetar os serviço disponível para o público
   - Se tudo estiver ok você pode passar a distribuir a nova revisão e, se o serviço for crítico, você pode substituir gradualmente a revisão antiga pela nova definindo o percentual de entrega de cada uma até substituir 100%
   - Atenção à questão do banco de dados, se WordPress ou algum plugin precisarem atualizar o banco, essa transição aos poucos pode gerar comportamentos inesperados
5. Se tudo correr bem em staging, envie um pull request para produção e siga os mesmos passos

Dessa forma você e sua equipe terão um registro das versões, possibilidade de testar a aplicação o suficiente antes de liberar para o público, possibilidade de verificar erros durante o merge e facilidade em efetuar o roll back do ambiente online se necessário (veja em seu serviço na Cloud Run, aba 'Revisões').

## Sobre as escolhas feitas

Pela praticidade, utilizei uma imagem Docker da Bitnami que contém Nginx, PHP-FPM e WordPress, o que permitiu acelerar a conclusão do projeto. No futuro, penso em testar o uso de containers Nginx e WordPress separados, porque acredito que a separação pode melhorar os tempos do cold start ao trabalhar com o cache do Nginx, talvez, em um volume.

### Arquivos de configuração e inicialização do container

Algumas escolhas não usuais foram feitas com relação ao `wp-config.php` (arquivo de configuração do WordPress) e aos entrypoints.

Como padrão, a Bitnami fornece a possibilidade de variáveis de ambiente para configurar o `wp-config.php`, mas, em testes que fiz, a utilização dessas variáveis aumentou consideravelmente o tempo do cold start. Acredito que será necessário fazer novos testes agora que a solução está finalizada. No estágio atual do projeto é necessário ter atenção extra a um problema de segurança que foi criado com essa escolha se formos displicentes: **é importantíssimo que repositórios Git sejam privados, se forem públicos vão expor os dados de acesso ao banco de dados e outros**, os dados também ficam expostos para toda a equipe de desenvolvimento.

Outro ponto de atenção é que removi o teste de acesso ao banco de dados no arquivo `libwordpress.sh` e isso também cortou tempo do cold start. Pelo que pude verificar, mesmo sem esse teste o pod não sobe na GCP. Caso a conexão não ocorra a versão fica marcada como 'falha'. De qualquer forma, acompanhe o processo de criação das revisões da Cloud Run.

Durante o início do desenvolvimento foi muito comum receber um erro 502 no cold start. Esse foi o motivo para alteração do entrypoint `/nginx-php-fpm/run.sh` e inclusão do `/php/wait.sh`. Vi que antecipar a execução de um comando simples do PHP-FPM resolve esse problema.

## Troubleshooting

### Problema na conexão com o banco de dados

**Se isso está acontecendo localmente:**
- Verifique se os dados da conexão local são os mesmos no `wp-config.dev.php` e no `docker-compose.yml`
- Também verifique se o container do banco de dados está rodando usando `docker logs alx-wp-db` no terminal. Você deverá ver as mensagens do MySQL informando que está pronto para conexão, caso não veja, rode novamente o `docker-compose up -d`

**Se a falha for no ambiente da GCP:**
- Acesse o [Cloud SQL](https://console.cloud.google.com/sql/instances) e confirme se o banco de dados está rodando
- Se ele estiver rodando, confira o arquivo `wp-config.staging.php` em seu projeto contém os dados de conexão corretos. Em caso de dúvida, verifique no Cloud SQL, inclusive trocando a senha
- Se você precisar alterar o `wp-config.staging.php`, atualize o branch do GitHub para subir a atualização

### As imagens estão desaparecendo do gerenciador de mídia do WordPress

Revise as configurações do bucket de acordo com o descrito nesse artigo. Provavelmente, você precisa alterar o 'Controle de acesso' para 'Detalhado'.

## Referências

- [Deploying WordPress with SQL on Google Cloud Platform](https://medium.com/@saurabhagarwal43800/deploying-wordpress-with-sql-on-google-cloud-platform-78c3f646f9f9)
- [3 solutions to mitigate the cold-starts on Cloud Run.](https://medium.com/google-cloud/3-solutions-to-mitigate-the-cold-starts-on-cloud-run-8c60f0ae7894)
- [Documentação da Google Cloud Platform (GCP)](https://cloud.google.com/docs)