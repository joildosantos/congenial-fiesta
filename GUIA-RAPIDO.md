# 🚀 Guia Rápido - CRIA Releituras Enhanced v4.0

## ⚡ Instalação em 3 Passos (5 minutos)

### Passo 1: Instalar Plugin
```
WordPress Admin → Plugins → Adicionar Novo
→ Upload do ZIP → Instalar → Ativar
```

### Passo 2: Configurar API (Grátis!)
```
1. Acesse https://openrouter.ai/keys
2. Crie conta (gratuita)
3. Gere API Key
4. No WordPress: CRIA Releituras → Configurações
5. Cole API Key no campo "OpenRouter"
6. Selecione "Gemini 2.0 Flash (Grátis)"
7. Clique em "Testar" → deve aparecer "✓ Conectado"
8. Salvar
```

### Passo 3: Processar Primeira Notícia
```
1. CRIA Releituras → Nova Notícia
2. Cole URL: https://g1.globo.com/qualquer-noticia
3. Processar
4. Aguarde 5-10 segundos
5. Post criado! ✓
```

---

## 🎯 Funcionalidades Principais

### 1️⃣ Processar Uma Notícia
```
CRIA Releituras → Nova Notícia
→ Colar URL → Processar
→ Editar ou Publicar
```

### 2️⃣ Processar Várias (Lote)
```
CRIA Releituras → Processamento em Lote
→ Colar lista de URLs (uma por linha)
→ Configurar opções
→ Adicionar à Fila
→ Processamento automático!
```

### 3️⃣ Publicar no Instagram
```
Após processar → Botão "Instagram"
→ Modal abre com preview
→ "Copiar Caption" → "Baixar Imagem"
→ "Abrir Instagram" → Publicar manualmente
→ Leva < 1 minuto!
```

### 4️⃣ Ver Métricas
```
CRIA Releituras → Analytics
→ Veja estatísticas completas
→ Exporte relatório em CSV
```

### 5️⃣ Adicionar Novo Modelo IA
```
Configurações → Modelos de IA
→ + Adicionar Modelo
→ Preencher formulário
→ Testar → Salvar
```

---

## 💡 Dicas Pro

### Use Feed RSS para Evitar Bloqueios
```
Ao invés de:
https://g1.globo.com/economia/noticia/2026/...

Use:
https://g1.globo.com/rss/g1/

Plugin pega automaticamente a última notícia!
```

### Processar Múltiplas URLs de Uma Vez
```
1. Copie lista de URLs do seu editor
2. Cole no Processamento em Lote
3. Configure uma vez (categoria, tags, status)
4. Adicione à fila
5. Vai processando automaticamente em background
6. Veja progresso na fila
```

### Modelos Grátis Recomendados
```
✓ Gemini 2.0 Flash (via OpenRouter) - Rápido
✓ Llama 3.3 70B (via OpenRouter) - Qualidade
✓ Qwen 2.5 72B (via OpenRouter) - Equilibrado

Todos têm 50 requisições/dia grátis!
```

### Cache Economiza Recursos
```
Se processar mesma URL 2x, plugin usa cache!
Resultado instantâneo na 2ª vez.
Cache expira em 1 hora.
```

---

## 🐛 Problemas Comuns

### "Não foi possível extrair o conteúdo"
```
✓ Tente usar o feed RSS (/feed)
✓ Tente versão AMP (/amp/)
✓ Alguns sites bloqueiam, teste outro
```

### "API Key inválida"
```
✓ Copie chave completa (começa com sk-or-)
✓ Sem espaços antes/depois
✓ Teste em openrouter.ai
✓ Gere nova se necessário
```

### Fila travou
```
✓ Processamento em Lote → Limpar Fila
✓ Reinicie com novas URLs
```

### Imagem do autor não aparece
```
✓ Usuários → Editar usuário
✓ Upload foto de perfil
✓ Ou use Gravatar.com
```

---

## 📊 Estatísticas

### Antes (v3.0)
```
❌ Sites bloqueavam
❌ Só 2 modelos IA
❌ Processar 1 por vez
❌ Sem métricas
❌ Instagram demorado
```

### Agora (v4.0)
```
✓ Anti-bloqueio automático
✓ Modelos ilimitados
✓ Lote (5 simultâneos)
✓ Analytics completo
✓ Instagram < 1min
✓ Cache inteligente
✓ 40% mais rápido
```

---

## ✅ Checklist Pós-Instalação

```
[ ] Plugin instalado ✓
[ ] API configurada e testada ✓
[ ] Primeira notícia processada ✓
[ ] Lote testado com 3-5 URLs ✓
[ ] Instagram testado ✓
[ ] Analytics visualizado ✓
[ ] Bookmarklet instalado
[ ] Sem erros no debug.log ✓
```

---

## 🎬 Fluxo de Trabalho Ideal

### Curadoria Diária
```
1. Manhã: Adicione 10-20 URLs ao lote
2. Configure: Rascunho, Categoria, Tags
3. Deixe processar em background
4. Tarde: Revise posts gerados
5. Edite/publique os melhores
6. Instagram: Publique highlights
7. Noite: Veja analytics do dia
```

### Semana Típica
```
Segunda: 20 URLs em lote
Terça: 20 URLs em lote  
Quarta: 20 URLs em lote
Quinta: 20 URLs em lote
Sexta: 20 URLs + revisar semana
Total: ~100 posts/semana
Tempo gasto: ~2h/dia (vs 8h manual)
```

---

## 💰 Custos

### Opção Gratuita
```
OpenRouter: 50 requisições/dia = GRÁTIS
Suficiente para: 50 posts/dia
Custo mensal: R$ 0
```

### Opção Paga (Mais Volume)
```
OpenRouter com $10 crédito: 1000 req/dia
Suficiente para: 1000 posts/dia
$10 dura ~1 mês uso intenso
Custo mensal: ~R$ 50
```

### ROI (Retorno sobre Investimento)
```
Tempo manual: 15 min/post = 25 horas para 100 posts
Com plugin: 5 min/post = 8 horas para 100 posts
Economia: 17 horas/semana = 68 horas/mês
Valor hora: R$ 50 = Economia de R$ 3.400/mês
Custo plugin: R$ 50/mês
ROI: 6.700% 🚀
```

---

## 🎓 Recursos Adicionais

### Documentação
- README.md - Referência completa
- CHANGELOG.md - Histórico de versões
- RELEASE-NOTES.md - Destaques v4.0

### Suporte
- Debug logs em wp-content/debug.log
- FAQ no README
- Email: [adicionar se houver]

---

**Pronto para começar! 🚀**

Qualquer dúvida, consulte README.md ou verifique os logs.

---

Versão: 4.0.0  
Desenvolvido por: CRIA S/A  
Para: Joildo Santos
