# Guia de Instalacao

## Pre-requisitos

- VPS com Linux (Ubuntu 22.04+ recomendado) ou maquina local
- Docker e Docker Compose instalados
- Dominio apontando para o servidor (para webhooks funcionarem)
- Acesso admin ao WordPress do Jornal Espaco do Povo

## 1. Clonar o repositorio

```bash
git clone https://github.com/joildosantos/congenial-fiesta.git
cd congenial-fiesta
```

## 2. Configurar variaveis de ambiente

```bash
cp .env.example .env
nano .env
```

Preencha todas as variaveis. Veja a secao "Credenciais" abaixo.

## 3. Subir os containers

```bash
docker compose up -d
```

Verifique se os servicos estao rodando:

```bash
docker compose ps
```

## 4. Acessar o n8n

Acesse `http://SEU_IP:5678` no navegador.

- Usuario: o que voce definiu em `N8N_BASIC_AUTH_USER`
- Senha: o que voce definiu em `N8N_BASIC_AUTH_PASSWORD`

## 5. Importar os workflows

No painel do n8n:

1. Clique em **"..."** (menu) > **"Import from File"**
2. Importe os 3 arquivos da pasta `n8n/workflows/`:
   - `conteudo-frio.json`
   - `conteudo-diario.json`
   - `distribuicao.json`

## 6. Configurar credenciais no n8n

No painel do n8n, va em **Settings > Credentials** e configure:

### WordPress (HTTP Basic Auth)
- Name: `WordPress Basic Auth`
- User: seu usuario WordPress
- Password: Application Password (veja secao abaixo)

### Google Sheets (OAuth2)
- Siga o guia do n8n: criar Service Account no Google Cloud Console
- Compartilhar a planilha com o email da Service Account

### Telegram Bot
- Name: `Telegram Bot`
- Token: obtido via @BotFather

### OpenRouter
- Configurado diretamente nos nodes via header Authorization
- A API key vai na variavel de ambiente `OPENROUTER_API_KEY`

## 7. Configurar o WordPress

### Application Password

1. Acesse o painel WordPress
2. Va em **Usuarios > Seu Perfil**
3. Role ate **Application Passwords**
4. Crie uma nova com nome "n8n-automacao"
5. Copie a senha gerada para o `.env`

### Webhook de publicacao (para distribuicao)

Instale o plugin **WP Webhooks** ou adicione ao `functions.php` do tema:

```php
add_action('publish_post', function($post_id) {
    $post = get_post($post_id);
    $webhook_url = 'http://SEU_SERVIDOR:5678/webhook/wp-post-published';

    $featured_image = get_the_post_thumbnail_url($post_id, 'full');
    $categories = wp_get_post_categories($post_id, ['fields' => 'names']);

    $data = [
        'ID' => $post_id,
        'post_title' => $post->post_title,
        'post_excerpt' => $post->post_excerpt,
        'guid' => get_permalink($post_id),
        'featured_image_url' => $featured_image,
        'categories_names' => $categories,
    ];

    wp_remote_post($webhook_url, [
        'body' => json_encode($data),
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 15,
    ]);
});
```

## 8. Configurar o Telegram Bot

1. Abra o Telegram e fale com @BotFather
2. Envie `/newbot` e siga as instrucoes
3. Copie o token para o `.env`
4. Para obter seu Chat ID, fale com @userinfobot
5. Para o canal, adicione o bot como admin e use o formato `@nomedocanal`

## 9. Configurar a Evolution API (WhatsApp)

1. Acesse `http://SEU_IP:8080`
2. Crie uma instancia com o nome definido em `EVOLUTION_INSTANCE_NAME`
3. Escaneie o QR Code com o WhatsApp do jornal
4. Anote o ID do grupo/lista de transmissao para o `.env`

## 10. Configurar a Planilha Google Sheets

1. Crie uma planilha no Google Sheets
2. Na primeira aba, renomeie para `pautas-frias`
3. Crie os cabecalhos conforme o template em `templates/planilha-pautas.csv`
4. Compartilhe com o Service Account do Google
5. Copie o ID da planilha (da URL) para o `.env`

## 11. Configurar Facebook/Instagram

1. Acesse [developers.facebook.com](https://developers.facebook.com)
2. Crie um App do tipo Business
3. Adicione os produtos: Facebook Login, Pages API, Instagram Basic Display
4. Gere um Page Access Token com permissoes:
   - `pages_manage_posts`
   - `pages_read_engagement`
   - `instagram_basic`
   - `instagram_content_publish`
5. Copie o token e IDs para o `.env`

**Importante:** Page Access Tokens expiram. Use um token de longa duracao ou configure renovacao automatica.

## 12. Ativar os workflows

No n8n, ative os 3 workflows clicando no toggle de cada um.

## 13. Testar

1. Adicione uma pauta na planilha Google Sheets com status "pendente"
2. Execute manualmente o workflow "Conteudo Frio"
3. Verifique se a mensagem de aprovacao chegou no Telegram
4. Aprove e verifique se foi publicado no WordPress e distribuido

## Solucao de Problemas

| Problema | Solucao |
|---|---|
| n8n nao inicia | Verifique logs: `docker compose logs n8n` |
| Webhook nao funciona | Verifique se WEBHOOK_URL esta correto e acessivel externamente |
| LLM retorna erro | Verifique API key do OpenRouter e tente outro modelo gratuito |
| WordPress rejeita post | Verifique Application Password e permissoes do usuario |
| Evolution API nao conecta | Verifique logs: `docker compose logs evolution-api` |
| Telegram nao envia | Verifique token do bot e chat ID |
