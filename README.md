# Automacao Editorial - Jornal Espaco do Povo

Sistema de automacao para geracao, aprovacao e distribuicao de conteudo do **Jornal Espaco do Povo** (WordPress), focado em noticias e curiosidades das periferias de Sao Paulo.

## O que faz

- **Auto-Pesquisa de Pautas**: Pesquisa automatica semanal sobre territorios perifericos (quilombos, comunidades caicaras, ribeirinhas, favelas, aldeias indigenas, ocupacoes, periferias rurais). Alimenta a planilha de pautas sem intervencao manual.
- **Conteudo Frio** (3x/semana): Curiosidades, guias e historias a partir da planilha de pautas (manual + automatica)
- **Conteudo Diario**: Coleta RSS, filtra por relevancia para moradores de periferia, reescreve com linguagem acessivel
- **Aprovacao A/B via Telegram**: Editor escolhe entre 2 opcoes de titulo (informativo vs storytelling) com 1 toque
- **Imagem Branded**: Gera card personalizado para cada post com logo, editoria colorida, titulo e creditos
- **Distribuicao Multi-Canal**: Facebook, Instagram, Telegram e WhatsApp com imagem branded + texto otimizado por canal
- **Resumo Semanal**: Relatorio automatico com metricas de publicacoes e banco de pautas

## Arquitetura

```
[n8n - Orquestrador]
     |
     +-- LLM (OpenRouter) -> Gera/reescreve conteudo
     |
     +-- Telegram Bot -> Aprovacao editorial A/B
     |
     +-- Plugin WordPress (JEP Automacao) -> Publica posts + dispara webhooks
     |
     +-- Distribuicao: Facebook | Instagram | Telegram Canal | WhatsApp (Evolution API)
```

## Stack

| Componente | Tecnologia | Custo |
|---|---|---|
| Orquestrador | n8n (self-hosted, Docker) | Gratuito |
| CMS + Plugin | WordPress + JEP Automacao | Ja existente |
| LLM | OpenRouter (modelos free tier) | Gratuito |
| Aprovacao | Telegram Bot | Gratuito |
| WhatsApp | Evolution API (self-hosted) | Gratuito |
| Banco de Pautas | Google Sheets | Gratuito |
| Imagem Branded | Browserless (self-hosted) | Gratuito |
| Banco de Dados | PostgreSQL (para n8n) | Gratuito |

## Estrutura

```
.
├── docker-compose.yml                  # n8n + PostgreSQL + Evolution API + Browserless
├── .env.example                        # Template de variaveis de ambiente
├── n8n/
│   ├── card-templates.js               # Templates HTML para cards sociais
│   └── workflows/
│       ├── auto-pesquisa-pautas.json   # Pesquisa automatica de territorios
│       ├── conteudo-frio.json          # Conteudo evergreen (A/B + audio)
│       ├── conteudo-diario.json        # Noticias diarias via RSS
│       ├── distribuicao.json           # Distribuicao com imagem branded
│       ├── gerar-imagem-social.json    # Sub-workflow de geracao de card
│       └── resumo-semanal.json         # Relatorio semanal de metricas
├── wordpress/
│   └── jep-automacao/                  # Plugin WordPress (instalar em wp-content/plugins/)
│       ├── jep-automacao.php           # Arquivo principal do plugin
│       ├── readme.txt                  # Documentacao do plugin
│       ├── includes/
│       │   ├── class-jep-automacao.php      # Classe principal (singleton)
│       │   ├── class-jep-installer.php      # Ativacao e tabelas no banco
│       │   ├── class-jep-settings.php       # Gerenciamento de configuracoes
│       │   ├── class-jep-logger.php         # Registro de atividades
│       │   ├── class-jep-webhook-sender.php # Envia webhooks ao n8n
│       │   ├── class-jep-rest-api.php       # REST API para o n8n
│       │   └── class-jep-admin.php          # Painel administrativo
│       └── admin/
│           ├── css/jep-admin.css       # Estilos do painel
│           ├── js/jep-admin.js         # Scripts do painel
│           └── views/
│               ├── page-dashboard.php  # Dashboard com status e disparo manual
│               ├── page-settings.php   # Pagina de configuracoes
│               └── page-logs.php       # Visualizacao de logs
├── docs/
│   ├── setup.md                        # Guia de instalacao completo
│   ├── prompts.md                      # Prompts do LLM documentados
│   ├── fontes-rss.md                   # Fontes RSS e palavras-chave
│   └── aprovacao.md                    # Fluxo de aprovacao detalhado
└── templates/
    └── planilha-pautas.csv             # Template para Google Sheets
```

## Plugin WordPress - JEP Automacao

### Instalacao

1. Copie a pasta `wordpress/jep-automacao/` para `wp-content/plugins/`
2. Ative em **Plugins > Plugins Instalados**
3. Acesse **JEP Automacao > Configuracoes** no painel WordPress
4. Preencha a URL do webhook n8n e as credenciais

### Funcionalidades do Plugin

**Webhook ao Publicar**
Ao publicar um post, o plugin envia automaticamente um payload JSON para o n8n:

```json
{
  "event": "post_published",
  "post_id": 123,
  "post_title": "Titulo do post",
  "permalink": "https://jornalespacodopovo.com.br/post",
  "featured_image_url": "https://...",
  "categories": ["Noticias"],
  "tags": ["periferia"],
  "author_name": "Redacao"
}
```

**REST API (n8n -> WordPress)**

Autenticacao via header `X-JEP-Token` (gerado automaticamente na ativacao).

| Metodo | Endpoint | Descricao |
|---|---|---|
| GET | `/wp-json/jep/v1/status` | Status do plugin |
| POST | `/wp-json/jep/v1/posts` | Criar/publicar post |
| POST | `/wp-json/jep/v1/media/from-url` | Upload de imagem por URL |
| GET | `/wp-json/jep/v1/logs` | Consultar logs |
| POST | `/wp-json/jep/v1/logs` | Registrar log do n8n |

**Painel Administrativo**
- Dashboard com status de conexao e logs recentes
- Configuracoes de todos os servicos (n8n, Telegram, Facebook, WhatsApp, etc.)
- Visualizacao e limpeza de logs de atividade
- Disparo manual de workflows

### Compatibilidade SEO

Ao criar posts via API, o plugin define a meta description automaticamente para:
- **Yoast SEO** (`_yoast_wpseo_metadesc`)
- **Rank Math** (`rank_math_description`)
- **All in One SEO** (`_aioseo_description`)

## Quick Start

```bash
# 1. Clonar
git clone https://github.com/joildosantos/congenial-fiesta.git
cd congenial-fiesta

# 2. Configurar
cp .env.example .env
nano .env   # preencher todas as variaveis

# 3. Subir containers (n8n + PostgreSQL + Evolution API + Browserless)
docker compose up -d

# 4. Instalar o plugin WordPress
cp -r wordpress/jep-automacao /caminho/para/wp-content/plugins/
# Ativar via painel WordPress

# 5. Importar workflows no n8n (http://localhost:5678)
# Menu > Import from File > selecionar os 6 arquivos de n8n/workflows/

# 6. Configurar credenciais e ativar workflows
```

Para o guia completo, veja [docs/setup.md](docs/setup.md).

## Workflows

### 1. Auto-Pesquisa de Pautas (semanal)
Toda segunda-feira rotaciona entre 7 tipos de territorios perifericos e gera 7 pautas originais via LLM.

### 2. Conteudo Frio (seg/qua/sex)
Puxa a proxima pauta "pendente" da planilha, reescreve com LLM e envia para aprovacao no Telegram com **2 opcoes de titulo** (A/B).

### 3. Conteudo Diario (todos os dias 6h)
Coleta feeds RSS -> filtra por palavras-chave -> classifica relevancia (LLM) -> reescreve as top 5 -> aprovacao.

### 4. Distribuicao com Imagem Branded
Ao publicar (webhook do plugin), gera card 1200x630 e distribui para todos os canais.

### 5. Resumo Semanal (domingo 20h)
Relatorio via Telegram com posts da semana e status do banco de pautas.

## Custo

**Zero.** Todas as ferramentas sao gratuitas ou self-hosted. Unico custo e a VPS para Docker.

## Licenca

Projeto interno do Jornal Espaco do Povo.
