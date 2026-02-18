=== JEP Automacao Editorial ===
Contributors: jornalespacodopovo
Tags: automacao, n8n, webhook, editorial, conteudo, telegram, distribuicao
Requires at least: 5.9
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automacao editorial completa: integra WordPress com n8n para geracao, aprovacao e distribuicao de conteudo em multiplos canais.

== Descricao ==

O **JEP Automacao Editorial** conecta o WordPress ao sistema de automacao baseado em n8n do Jornal Espaco do Povo, permitindo:

* Notificacao automatica do n8n ao publicar posts (webhook)
* Criacao de posts via REST API (n8n -> WordPress)
* Upload de imagens a partir de URL
* Fluxo de aprovacao via Telegram Bot
* Distribuicao automatica para Facebook, Instagram, Telegram e WhatsApp
* Painel de controle com logs de todas as atividades
* Disparo manual de workflows diretamente do WordPress

= Workflows Suportados =

* **Conteudo Frio**: Pautas evergreen reescritas com LLM (3x/semana)
* **Conteudo Diario**: Noticias via RSS filtradas e reescritas (diario)
* **Pesquisa Automatica de Pautas**: Gera 7 pautas por semana sobre territorios perifericos
* **Distribuicao Multi-Canal**: Facebook, Instagram, Telegram, WhatsApp com imagem branded
* **Resumo Semanal**: Relatorio automatico via Telegram

= Stack do Projeto =

* **Orquestrador**: n8n (self-hosted via Docker)
* **LLM**: OpenRouter (modelos gratuitos: Gemma, DeepSeek)
* **Aprovacao**: Telegram Bot com botoes inline
* **WhatsApp**: Evolution API (self-hosted)
* **Imagem Branded**: Browserless/Chrome headless

= Endpoints REST API =

Autenticacao via header `X-JEP-Token`.

* `GET  /wp-json/jep/v1/status` - Status do plugin
* `POST /wp-json/jep/v1/posts` - Criar/publicar post
* `POST /wp-json/jep/v1/media/from-url` - Upload de imagem por URL
* `GET  /wp-json/jep/v1/logs` - Consultar logs
* `POST /wp-json/jep/v1/logs` - Registrar log externo

== Instalacao ==

1. Copie a pasta `jep-automacao` para `wp-content/plugins/`
2. Ative o plugin em **Plugins > Plugins Instalados**
3. Acesse **JEP Automacao > Configuracoes** e preencha as credenciais
4. Suba o n8n via Docker Compose (veja `docs/setup.md` no repositorio)
5. Importe os 6 workflows de `n8n/workflows/` no n8n
6. Use o Token Secreto gerado no plugin para autenticar o n8n

== Perguntas Frequentes ==

= O plugin funciona sem o n8n? =

O plugin funciona para receber e exibir logs, mas os workflows de geracao de conteudo dependem do n8n rodando.

= Como obter o Token Secreto? =

O token e gerado automaticamente na ativacao do plugin. Acesse **Configuracoes** para visualiza-lo.

= O plugin e compativel com Yoast SEO, Rank Math e AIOSEO? =

Sim. Ao criar posts via API, o plugin preenche a meta description nos formatos de todos esses plugins.

= Como configurar multiplos editores no Telegram? =

Crie um grupo no Telegram, adicione o bot como admin e use o Chat ID do grupo (numero negativo) nas configuracoes.

== Changelog ==

= 1.0.0 =
* Lancamento inicial
* Webhook de post publicado para o n8n
* REST API completa para criacao de posts e upload de midia
* Painel administrativo com dashboard, configuracoes e logs
* Disparo manual de workflows
* Compatibilidade com Yoast SEO, Rank Math e AIOSEO
