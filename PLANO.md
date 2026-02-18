# Plano de Automacao - Jornal Espaco do Povo

## Visao Geral

Sistema de automacao editorial baseado em **n8n** (self-hosted) para geracao, aprovacao e distribuicao de conteudo do Jornal Espaco do Povo (WordPress).

---

## Arquitetura

```
[Fontes de Conteudo]
    |
    v
[n8n - Orquestrador Central]
    |
    +--> [LLM Gratuito via OpenRouter] --> Reescrita de titulo + conteudo
    |
    +--> [Fluxo de Aprovacao] --> Telegram Bot (envio p/ aprovacao)
    |                                |
    |                          [Aprovado / Rejeitado]
    |                                |
    +--> [WordPress REST API] <------+
    |
    +--> [Distribuicao Multi-Canal]
             |
             +--> Instagram (via Facebook Graph API)
             +--> Facebook Page (Graph API)
             +--> Telegram Canal (Bot API)
             +--> WhatsApp (via WhatsApp Business API / Evolution API)
```

---

## Stack Tecnologica

| Componente | Tecnologia | Custo |
|---|---|---|
| Orquestrador | n8n (self-hosted via Docker) | Gratuito |
| CMS | WordPress (ja existente) | -- |
| LLM | OpenRouter (modelos gratuitos: google/gemma-3-27b-it, deepseek/deepseek-chat-v3-0324) | Gratuito |
| Aprovacao | Telegram Bot | Gratuito |
| Distribuicao Social | Facebook Graph API, Telegram Bot API | Gratuito |
| WhatsApp | Evolution API (self-hosted) ou WhatsApp Business API | Gratuito (Evolution) |
| Infraestrutura | Docker Compose (n8n + Evolution API) | VPS propria |

---

## Fluxos (Workflows)

### Fluxo 1: Conteudo Frio (Curiosidades / Evergreen)

**Trigger:** Agendamento (CRON) - ex: 3x por semana (seg, qua, sex as 8h)

**Etapas:**

1. **Busca de pautas frias** - Banco de pautas em Google Sheets ou Airtable (gratuito)
   - Colunas: titulo_rascunho, tema, bairro, tipo (curiosidade/como-chegar/evento), imagem_url, categorias, tags, status
   - Filtra proxima pauta com status "pendente"

2. **Reescrita com LLM (OpenRouter)**
   - Prompt de sistema com tom editorial do jornal (linguagem acessivel, identidade periferica)
   - Reescrita do titulo (SEO-friendly, engajamento)
   - Reescrita/expansao do conteudo (300-600 palavras)
   - Output estruturado: titulo, conteudo_html, meta_description

3. **Montagem do rascunho**
   - Combina: titulo reescrito + conteudo + imagem + categorias + tags
   - Formata preview para aprovacao

4. **Envio para aprovacao (Telegram)**
   - Envia mensagem ao editor com: imagem, titulo, resumo do conteudo
   - Botoes inline: [Aprovar] [Editar Titulo] [Rejeitar]

5. **Aguarda resposta (Webhook)**
   - Aprovado -> Segue para publicacao
   - Editar -> Solicita novo titulo via mensagem
   - Rejeitado -> Marca como rejeitado na planilha, notifica

6. **Publicacao no WordPress**
   - Via REST API: POST /wp-json/wp/v2/posts
   - Envia: titulo, conteudo HTML, imagem destacada (upload via media endpoint), categorias, tags
   - Status: "publish"

7. **Distribuicao multi-canal** (detalhado no Fluxo 3)

---

### Fluxo 2: Conteudo Diario (Noticias)

**Trigger:** Agendamento (CRON) - diario as 6h + possibilidade de trigger manual

**Etapas:**

1. **Coleta de noticias relevantes (RSS/Scraping)**
   - Fontes RSS configuradas no n8n:
     - Agencia Brasil (governo, programas sociais)
     - G1 (regional)
     - Folha de S.Paulo
     - Portais locais
   - Filtros por palavras-chave: salario minimo, bolsa familia, vacinacao, transporte, moradia, educacao, saude, periferia, favela, etc.

2. **Selecao e curadoria**
   - LLM classifica relevancia (0-10) para moradores de periferia
   - Filtra apenas score >= 7
   - Seleciona top 3-5 noticias do dia

3. **Reescrita com LLM (OpenRouter)**
   - Prompt editorial: reescrever com linguagem acessivel, foco no impacto para moradores de periferia
   - Gera: titulo, conteudo reescrito (200-400 palavras), meta_description
   - Inclui fonte original como credito

4. **Busca de imagem**
   - Tenta usar imagem da fonte original (com credito)
   - Fallback: busca no Unsplash API (gratuito) ou Pexels API (gratuito)
   - Ultima opcao: imagem padrao por categoria

5. **Envio para aprovacao (Telegram)** - Mesmo fluxo do Fluxo 1
   - Envia lote de noticias para aprovacao
   - Editor aprova individualmente cada uma

6. **Publicacao no WordPress** - Mesmo mecanismo

7. **Distribuicao multi-canal** (Fluxo 3)

---

### Fluxo 3: Distribuicao Multi-Canal

**Trigger:** Automatico apos publicacao no WordPress (webhook post_published)

**Canais:**

1. **Facebook Page**
   - Facebook Graph API: POST /{page-id}/feed
   - Envia: link do post + imagem + texto resumido
   - Requer: Facebook App + Page Access Token

2. **Instagram**
   - Facebook Graph API (Instagram Content Publishing)
   - Envia: imagem + caption (resumo + link na bio)
   - Requer: Instagram Business Account vinculado a Facebook Page
   - Limitacao: apenas imagens, nao links diretos

3. **Telegram Canal**
   - Telegram Bot API: sendPhoto + sendMessage
   - Envia: imagem + titulo + resumo + link
   - Requer: Bot adicionado como admin do canal

4. **WhatsApp**
   - Via Evolution API (self-hosted, gratuito):
     - Envia para lista de transmissao ou grupo
     - Imagem + texto resumido + link
   - Alternativa: WhatsApp Business Cloud API (gratuito ate 1000 msgs/mes)

---

## Estrutura do Projeto (Docker Compose)

```
congenial-fiesta/
|
|-- docker-compose.yml          # n8n + Evolution API
|-- .env.example                # Variaveis de ambiente (template)
|-- n8n/
|   |-- workflows/
|   |   |-- conteudo-frio.json       # Workflow exportado
|   |   |-- conteudo-diario.json     # Workflow exportado
|   |   |-- distribuicao.json        # Workflow exportado
|   |-- credentials.md               # Guia de configuracao de credenciais
|
|-- docs/
|   |-- setup.md                # Guia de instalacao
|   |-- fontes-rss.md           # Lista de fontes RSS configuradas
|   |-- prompts.md              # Prompts do LLM usados
|   |-- aprovacao.md            # Guia do fluxo de aprovacao
|
|-- templates/
|   |-- planilha-pautas.csv     # Template da planilha de pautas frias
|
|-- README.md
```

---

## Configuracao de Credenciais Necessarias

| Servico | O que precisa | Como obter |
|---|---|---|
| WordPress | URL + Application Password | Painel WP > Usuarios > Application Passwords |
| OpenRouter | API Key | openrouter.ai (conta gratuita) |
| Telegram Bot | Bot Token | @BotFather no Telegram |
| Telegram | Chat ID do editor + Canal ID | Via bot ou @userinfobot |
| Facebook | Page Access Token | developers.facebook.com (App + Page) |
| Instagram | Vinculado via Facebook | Business Account + Facebook Page |
| Google Sheets | Service Account JSON | Google Cloud Console (gratuito) |
| Unsplash | API Key | unsplash.com/developers |
| Evolution API | Instancia + Token | Self-hosted via Docker |

---

## Modelos LLM Gratuitos (OpenRouter)

Modelos com uso gratuito no OpenRouter (sujeito a disponibilidade):

- `google/gemma-3-27b-it:free` - Bom para reescrita e classificacao (principal)
- `deepseek/deepseek-chat-v3-0324:free` - Alta qualidade geral
- `qwen/qwq-32b:free` - Bom para raciocinio e classificacao
- `mistralai/mistral-small-3.1-24b-instruct:free` - Rapido e eficiente

**Estrategia:** Configurar fallback entre modelos caso um esteja indisponivel.

---

## Fluxo de Aprovacao (Detalhado)

```
n8n gera conteudo
       |
       v
Envia no Telegram (mensagem com botoes inline)
       |
       +-- [Aprovar] --> Publica no WP --> Distribui
       |
       +-- [Editar Titulo] --> Pede novo titulo --> Atualiza --> Reenvia para aprovacao
       |
       +-- [Rejeitar] --> Marca como rejeitado --> Notifica motivo
```

**Por que Telegram e nao Email/WhatsApp para aprovacao?**
- Telegram Bot API e gratuita e robusta
- Suporta botoes inline (aprovacao com 1 clique)
- Notificacoes instantaneas
- Facil de implementar no n8n (node nativo)
- WhatsApp nao suporta botoes de forma confiavel para automacao
- Email e mais lento para fluxo de aprovacao rapida

---

## Etapas de Implementacao

### Fase 1: Infraestrutura
- [ ] Docker Compose com n8n
- [ ] Configuracao do .env
- [ ] Setup inicial do n8n

### Fase 2: Fluxo de Conteudo Frio
- [ ] Integracao Google Sheets (banco de pautas)
- [ ] Integracao OpenRouter (reescrita LLM)
- [ ] Fluxo de aprovacao via Telegram
- [ ] Publicacao no WordPress

### Fase 3: Fluxo de Conteudo Diario
- [ ] Configuracao de fontes RSS
- [ ] Filtro e classificacao por relevancia (LLM)
- [ ] Reescrita e busca de imagem
- [ ] Aprovacao + Publicacao

### Fase 4: Distribuicao Multi-Canal
- [ ] Facebook Page
- [ ] Instagram
- [ ] Telegram Canal
- [ ] WhatsApp (Evolution API)

### Fase 5: Documentacao e Ajustes
- [ ] Documentacao completa
- [ ] Testes end-to-end
- [ ] Ajuste de prompts baseado em feedback

---

## Observacoes Importantes

1. **LLM Gratuito:** Os modelos gratuitos do OpenRouter tem rate limits. Para o volume de um jornal comunitario (5-10 posts/dia), deve ser suficiente.

2. **Instagram:** A API do Instagram exige conta Business vinculada a Facebook Page. Publicacao automatica de imagens funciona, mas Reels/Stories requer abordagem diferente.

3. **WhatsApp:** A Evolution API e a melhor opcao gratuita para automacao. Requer um numero de telefone dedicado.

4. **Imagens:** O upload de imagens destacadas no WordPress e feito em 2 etapas: primeiro upload da midia, depois vinculacao ao post.

5. **SEO:** Os prompts do LLM incluirao instrucoes para gerar meta descriptions e titulos otimizados para busca.

6. **Creditos:** Todo conteudo diario reescrito incluira credito a fonte original, evitando problemas de direitos autorais.
