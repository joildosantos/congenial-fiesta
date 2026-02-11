# Automacao Editorial - Jornal Espaco do Povo

Sistema de automacao para geracao, aprovacao e distribuicao de conteudo do **Jornal Espaco do Povo** (WordPress), focado em noticias e curiosidades das periferias de Sao Paulo.

## O que faz

- **Conteudo Frio** (3x/semana): Curiosidades das periferias, como chegar em locais, eventos - puxa pautas de uma planilha Google Sheets
- **Conteudo Diario**: Coleta noticias via RSS, filtra por relevancia para moradores de periferia, reescreve com linguagem acessivel
- **Aprovacao via Telegram**: Todo conteudo passa por aprovacao humana com botoes (Aprovar/Editar/Rejeitar)
- **Distribuicao automatica**: Apos publicar no WordPress, distribui para Facebook, Instagram, Telegram e WhatsApp

## Stack

| Componente | Tecnologia |
|---|---|
| Orquestrador | n8n (self-hosted, Docker) |
| CMS | WordPress |
| LLM | OpenRouter (modelos gratuitos) |
| Aprovacao | Telegram Bot |
| WhatsApp | Evolution API (self-hosted) |
| Banco de Pautas | Google Sheets |

## Estrutura

```
.
├── docker-compose.yml           # n8n + PostgreSQL + Evolution API
├── .env.example                 # Template de variaveis de ambiente
├── n8n/
│   └── workflows/
│       ├── conteudo-frio.json   # Workflow de conteudo evergreen
│       ├── conteudo-diario.json # Workflow de noticias diarias
│       └── distribuicao.json    # Workflow de distribuicao multi-canal
├── docs/
│   ├── setup.md                 # Guia de instalacao completo
│   ├── prompts.md               # Prompts do LLM documentados
│   ├── fontes-rss.md            # Fontes RSS e palavras-chave
│   └── aprovacao.md             # Fluxo de aprovacao detalhado
├── templates/
│   └── planilha-pautas.csv      # Template para planilha de pautas
└── wordpress/
    └── webhook-publish.php      # Snippet para functions.php do WordPress
```

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

# 4. Acessar n8n
# http://localhost:5678

# 5. Importar workflows
# Menu > Import from File > selecionar os 3 JSONs

# 6. Configurar credenciais e ativar
```

Para o guia completo, veja [docs/setup.md](docs/setup.md).

## Custo

**Zero em tokens e APIs.** Unico custo e a VPS para rodar o Docker.

- n8n self-hosted: gratuito
- OpenRouter (modelos free): gratuito
- Telegram Bot API: gratuito
- Evolution API: gratuito
- Google Sheets: gratuito

## Licenca

Projeto interno do Jornal Espaco do Povo.
