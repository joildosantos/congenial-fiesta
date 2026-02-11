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

## Stack

| Componente | Tecnologia | Custo |
|---|---|---|
| Orquestrador | n8n (self-hosted, Docker) | Gratuito |
| CMS | WordPress | Ja existente |
| LLM | OpenRouter (modelos free tier) | Gratuito |
| Aprovacao | Telegram Bot | Gratuito |
| WhatsApp | Evolution API (self-hosted) | Gratuito |
| Banco de Pautas | Google Sheets | Gratuito |
| Imagem Branded | Browserless (self-hosted) | Gratuito |
| Banco de Dados | PostgreSQL (para n8n) | Gratuito |

## Estrutura

```
.
├── docker-compose.yml              # n8n + PostgreSQL + Evolution API + Browserless
├── .env.example                    # Template de variaveis de ambiente
├── n8n/
│   └── workflows/
│       ├── auto-pesquisa-pautas.json  # Pesquisa automatica de territorios
│       ├── conteudo-frio.json         # Conteudo evergreen (A/B + audio)
│       ├── conteudo-diario.json       # Noticias diarias via RSS
│       ├── distribuicao.json          # Distribuicao com imagem branded
│       ├── gerar-imagem-social.json   # Sub-workflow de geracao de card
│       └── resumo-semanal.json        # Relatorio semanal de metricas
├── docs/
│   ├── setup.md                    # Guia de instalacao completo
│   ├── prompts.md                  # Prompts do LLM documentados
│   ├── fontes-rss.md               # Fontes RSS e palavras-chave
│   └── aprovacao.md                # Fluxo de aprovacao detalhado
├── templates/
│   └── planilha-pautas.csv         # Template para Google Sheets
└── wordpress/
    └── webhook-publish.php         # Snippet para functions.php do WP
```

## Workflows

### 1. Auto-Pesquisa de Pautas (semanal)
Toda segunda-feira, o sistema rotaciona entre 7 tipos de territorios perifericos e gera 7 pautas originais via LLM:
- Favelas urbanas de SP
- Comunidades quilombolas
- Comunidades ribeirinhas
- Comunidades caicaras
- Periferias rurais e assentamentos
- Comunidades indigenas urbanas
- Ocupacoes e movimentos de moradia

As pautas sao adicionadas na planilha com status "sugestao" para revisao editorial.

### 2. Conteudo Frio (seg/qua/sex)
Puxa a proxima pauta "pendente" da planilha, reescreve com LLM e envia para aprovacao no Telegram com **2 opcoes de titulo** (A/B testing) + resumo para audio.

### 3. Conteudo Diario (todos os dias 6h)
Coleta feeds RSS -> filtra por palavras-chave -> classifica relevancia (0-10 via LLM) -> reescreve as top 5 -> envia para aprovacao.

### 4. Distribuicao com Imagem Branded
Ao publicar no WordPress, gera automaticamente um card 1200x630 com:
- Logo do jornal
- Badge da editoria (com cor propria)
- Titulo em destaque
- Nome do autor e data
- URL do site

Distribui para Facebook, Instagram, Telegram e WhatsApp com textos otimizados para cada canal.

### 5. Resumo Semanal (domingo 20h)
Relatorio via Telegram com: posts da semana, status do banco de pautas, territorios cobertos e dicas contextuais.

## Quick Start

```bash
# 1. Clonar
git clone https://github.com/joildosantos/congenial-fiesta.git
cd congenial-fiesta

# 2. Configurar
cp .env.example .env
nano .env   # preencher todas as variaveis

# 3. Subir
docker compose up -d

# 4. Acessar n8n em http://localhost:5678

# 5. Importar os 6 workflows (Menu > Import from File)

# 6. Configurar credenciais e ativar workflows
```

Para o guia completo, veja [docs/setup.md](docs/setup.md).

## Custo

**Zero.** Todas as ferramentas sao gratuitas ou self-hosted. Unico custo e a VPS para Docker.

## Licenca

Projeto interno do Jornal Espaco do Povo.
