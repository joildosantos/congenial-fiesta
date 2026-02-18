# CRIA Releituras Enhanced v4.0

## Sistema Avançado de Curadoria e Reescrita de Notícias

Plugin WordPress profissional desenvolvido pela CRIA S/A para Joildo Santos e equipe do Instituto Crias/Grupo CRIA.

---

## 🎯 Visão Geral

O CRIA Releituras Enhanced é um sistema completo de automação jornalística que permite:

- ✅ **Extrair** conteúdo de múltiplos formatos (HTML, RSS, JSON)
- ✅ **Reescrever** usando modelos de IA avançados
- ✅ **Processar** em lote dezenas de URLs simultaneamente
- ✅ **Publicar** com um clique em redes sociais
- ✅ **Analisar** métricas e performance do sistema
- ✅ **Cachear** inteligentemente para economia de recursos

---

## 🚀 Principais Recursos v4.0

### 1. Sistema Anti-Bloqueio Avançado
```
✓ 3 estratégias automáticas de retry
✓ 6 user agents rotativos
✓ Exponential backoff (2s → 4s → 8s)
✓ Fallback para versão AMP
✓ Detecção de Cloudflare/CAPTCHA
```

### 2. Múltiplos Formatos Suportados
```
✓ HTML - Parsing avançado com limpeza
✓ RSS/Atom - Detecção automática
✓ JSON API - Estrutura compatível
✓ AMP - Fallback automático
```

### 3. Gerenciador de Modelos de IA
```
✓ Adicionar/remover modelos ilimitados
✓ 6 providers pré-configurados
✓ Suporte OAuth (Qwen, etc)
✓ 3 formatos de API (OpenAI, Gemini, Anthropic)
```

### 4. Processamento em Lote (NOVO!)
```
✓ Upload de lista de URLs
✓ Processamento assíncrono em background
✓ Fila com pausar/retomar
✓ Relatório de progresso
✓ Até 5 URLs simultaneamente
```

### 5. Sistema de Analytics (NOVO!)
```
✓ Métricas de performance
✓ Taxa de sucesso/falha
✓ Fontes mais usadas
✓ Modelos mais eficientes
✓ Tempo médio de processamento
✓ Exportação de relatórios CSV
```

### 6. Cache Inteligente (NOVO!)
```
✓ Cache de conteúdo extraído
✓ Cache de respostas IA
✓ Limpeza automática
✓ Estatísticas hit/miss
✓ Economia de recursos
```

### 7. Instagram Modal Completo
```
✓ Preview 1080x1080px
✓ Caption: Título + Legenda + URL + Hashtags
✓ Copiar + Baixar + Publicar
✓ < 1 minuto para publicar
```

### 8. Imagem do Autor Corrigida
```
✓ Suporte a colunistas
✓ Integração com tema
✓ Funciona em todos os lugares
```

---

## 📦 Instalação

### Requisitos
- WordPress 5.8+
- PHP 7.4+
- 1 API Key de IA (gratuita disponível)

### Passo a Passo
```bash
1. Upload do ZIP via WordPress Admin
2. Ativar plugin
3. Configurar API de IA (2 minutos)
4. Pronto para usar!
```

---

## ⚙️ Configuração Rápida

### 1. API de IA (Recomendado: OpenRouter)
```
1. Acesse https://openrouter.ai/keys
2. Crie conta gratuita
3. Gere API Key
4. Cole em Configurações → APIs
5. Selecione modelo grátis
6. Teste conexão
```

### 2. Processamento em Lote
```
1. Vá em "Processamento em Lote"
2. Cole lista de URLs (uma por linha)
3. Configure opções:
   - Categoria
   - Status (rascunho/publicado)
   - Tags
4. Clique em "Adicionar à Fila"
5. Processamento inicia automaticamente
```

### 3. Analytics
```
1. Acesse "Analytics"
2. Visualize métricas:
   - Total processado
   - Taxa de sucesso
   - Modelos mais usados
   - Fontes preferidas
3. Exporte relatório em CSV
```

---

## 🔧 Uso Avançado

### API REST (NOVO!)

O plugin expõe endpoints REST para automação externa:

```php
// Processar URL via API
POST /wp-json/cria-releituras/v1/process
Body: {
  "url": "https://exemplo.com/noticia",
  "options": {
    "status": "draft",
    "category_id": 5
  }
}

// Adicionar à fila em lote
POST /wp-json/cria-releituras/v1/batch
Body: {
  "urls": ["url1", "url2", "url3"],
  "options": {...}
}

// Obter métricas
GET /wp-json/cria-releituras/v1/analytics?period=30days
```

### Webhooks (NOVO!)

Configure webhooks para integrar com outros sistemas:

```php
// Notificar quando artigo for processado
add_action('anrp_article_processed', function($post_id, $data) {
    wp_remote_post('https://seu-webhook.com', [
        'body' => [
            'post_id' => $post_id,
            'title' => $data['title'],
            'url' => get_permalink($post_id)
        ]
    ]);
});
```

### Cache Personalizado

```php
// Usar cache em seu código
$cache = new ANRP_Cache_Manager();

// Armazenar
$cache->set('minha_chave', $dados, 3600);

// Recuperar
$dados = $cache->get('minha_chave');

// Remember pattern
$dados = $cache->remember('chave', 3600, function() {
    // Código pesado aqui
    return resultado();
});
```

---

## 📊 Especificações Técnicas

### Performance
```
Tempo médio: 5-10 segundos
Cache hit rate: 70-85%
Throughput batch: 5 URLs/minuto
Memória: < 64 MB por processo
```

### Providers de IA Suportados
```
1. OpenRouter (500+ modelos)
2. Google Gemini
3. OpenAI (GPT-4o)
4. Anthropic (Claude)
5. Alibaba Qwen
6. Groq
+ Custom providers ilimitados
```

### Formatos Suportados
```
Input: HTML, RSS, Atom, JSON, AMP
Output: WordPress Post, JSON API
Imagens: JPG, PNG, WebP
```

---

## 🐛 Troubleshooting

### Erro: "Não foi possível extrair o conteúdo"

**Soluções**:
```
1. Tente URL do feed RSS (/feed)
2. Tente versão AMP (/amp/)
3. Verifique cache (limpe se necessário)
4. Veja logs em wp-content/debug.log
```

### Erro: "API Key inválida"

**Soluções**:
```
1. Verifique se copiou chave completa
2. Teste em https://openrouter.ai
3. Gere nova chave se necessário
4. Verifique créditos (se aplicável)
```

### Fila em lote travada

**Soluções**:
```
1. Vá em Processamento em Lote
2. Clique em "Limpar Fila Completa"
3. Ou limpe apenas itens falhados
4. Reinicie fila com novos itens
```

---

## 📈 Roadmap

### v4.1 (Q2 2026)
- [ ] Mais providers OAuth
- [ ] Templates de caption personalizáveis
- [ ] Agendamento social avançado
- [ ] Integração Buffer/Hootsuite

### v4.2 (Q3 2026)
- [ ] Machine learning para extração
- [ ] Suporte a vídeos/podcasts
- [ ] Dashboard analytics avançado
- [ ] Webhooks configuráveis via UI

### v5.0 (Q4 2026)
- [ ] Multi-site support
- [ ] White label
- [ ] Marketplace de templates
- [ ] AI training personalizado

---

## 📄 Licença

GPL v2 or later  
Copyright © 2026 CRIA S/A

---

## 🙏 Créditos

**Desenvolvido por**: CRIA S/A  
**Para**: Joildo Santos, Instituto Crias/Grupo CRIA  
**Foco**: Economia das Margens, Comunicação de Periferia

---

## 📞 Suporte

**Documentação**: README.md (este arquivo)  
**Guia de Instalação**: GUIA-INSTALACAO.md  
**Changelog**: CHANGELOG.md  
**Release Notes**: RELEASE-NOTES.md

---

**Versão**: 4.0.0 Enhanced  
**Data**: Fevereiro 2026  
**Status**: Production Ready 🚀
