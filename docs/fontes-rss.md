# Fontes RSS Configuradas

Lista de feeds RSS utilizados no workflow de conteudo diario para coleta automatica de noticias.

---

## Fontes Ativas

### Noticias Nacionais

| Fonte | Feed RSS | Foco |
|---|---|---|
| Agencia Brasil | `http://agenciabrasil.ebc.com.br/rss/ultimasnoticias/feed.xml` | Governo, programas sociais, politicas publicas |
| G1 | `https://g1.globo.com/rss/g1/` | Geral, ampla cobertura |
| Folha Cotidiano | `https://www1.folha.uol.com.br/rss/cotidiano.xml` | Cotidiano, servicos, cidade |

### Fontes Sugeridas (adicionar conforme necessidade)

| Fonte | Feed RSS | Foco |
|---|---|---|
| UOL Noticias | `https://rss.uol.com.br/feed/noticias.xml` | Geral |
| R7 | `https://noticias.r7.com/feed.xml` | Geral |
| Agencia Brasil Educacao | `http://agenciabrasil.ebc.com.br/rss/educacao/feed.xml` | Educacao |
| Agencia Brasil Saude | `http://agenciabrasil.ebc.com.br/rss/saude/feed.xml` | Saude |
| Governo Federal | `https://www.gov.br/pt-br/noticias/RSS` | Programas, politicas |
| Catraca Livre | `https://catracalivre.com.br/feed/` | Cultura, eventos gratuitos |
| Ponte Jornalismo | `https://ponte.org/feed/` | Direitos humanos, periferia |
| Noticias ao Minuto BR | `https://www.noticiasaominuto.com.br/rss/ultima-hora` | Breaking news |
| Periferia em Movimento | `https://periferiaemmovimento.com.br/feed/` | Periferias, cultura periferica |

---

## Palavras-Chave de Filtro

As noticias coletadas sao filtradas pelas seguintes palavras-chave antes de serem enviadas ao LLM para classificacao:

### Economia e Renda
- salario minimo, bolsa familia, auxilio, cad.?unico, cadastro unico, cesta basica, inflacao, gas

### Saude
- sus, ubs, vacinacao, vacina, saude, hospital, upa, medicamento

### Transporte
- transporte, onibus, metro, trem, bilhete unico, tarifa

### Moradia
- moradia, minha casa, aluguel, habitacao

### Educacao
- educacao, escola, creche, enem, prouni, fies, sisu

### Trabalho
- emprego, trabalho, carteira assinada, clt

### Servicos Basicos
- energia, luz, agua, saneamento

### Beneficios
- programa social, beneficio, inss, aposentadoria

### Territorio
- periferia, favela, comunidade, quebrada

### Seguranca
- seguranca, violencia, policia

### Cultura
- cultura, evento gratuito, show gratuito

---

## Como Adicionar Novas Fontes

1. Abra o workflow `conteudo-diario.json` no n8n
2. Adicione um novo node **RSS Feed Read**
3. Configure a URL do feed
4. Conecte ao node **Combinar Feeds**
5. Salve e teste

## Como Adicionar Palavras-Chave

1. Abra o node **Filtrar por Palavras-Chave** no workflow
2. Adicione as novas palavras ao array `keywords` no codigo
3. Salve e teste
