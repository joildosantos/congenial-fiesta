# Fluxo de Aprovacao

## Visao Geral

Todo conteudo gerado pela automacao passa por aprovacao humana antes de ser publicado. O fluxo de aprovacao e feito via **Telegram Bot**, permitindo aprovar com um clique.

## Como Funciona

### 1. Conteudo Frio

```
CRON (Seg/Qua/Sex 8h)
    |
    v
Busca pauta na planilha
    |
    v
LLM reescreve titulo + conteudo
    |
    v
Bot envia no Telegram:
  - Imagem (se disponivel)
  - Titulo reescrito
  - Tipo e bairro
  - Meta description
  - Categorias e tags
  - Titulo original (para comparacao)
  - Botoes: [Aprovar] [Editar Titulo] [Rejeitar]
    |
    +--[Aprovar]--> Publica no WordPress --> Distribui nas redes
    |
    +--[Editar]--> Bot pede novo titulo --> Atualiza --> Reenvia
    |
    +--[Rejeitar]--> Marca como rejeitado na planilha
```

### 2. Conteudo Diario

```
CRON (Diario 6h)
    |
    v
Coleta RSS --> Filtra --> LLM classifica --> LLM reescreve
    |
    v
Bot envia CADA noticia separada:
  - Titulo reescrito
  - Resumo
  - Categorias e tags
  - Link da fonte original
  - Score de relevancia
  - Botoes: [Aprovar] [Editar] [Rejeitar]
    |
    (Editor aprova individualmente cada noticia)
```

## Configuracao do Bot

### Criar o Bot

1. Abra o Telegram
2. Fale com @BotFather
3. Envie `/newbot`
4. Escolha nome: `Jornal Espaco do Povo Bot`
5. Escolha username: `jornalespacodopovo_bot`
6. Copie o token gerado

### Obter Chat ID do Editor

1. Fale com @userinfobot no Telegram
2. Ele retornara seu Chat ID numerico
3. Coloque no `.env` como `TELEGRAM_EDITOR_CHAT_ID`

### Multiplos Editores

Para enviar para mais de um editor, voce pode:

1. **Criar um grupo** de editores no Telegram
2. Adicionar o bot ao grupo
3. Usar o Chat ID do grupo (negativo) no `.env`
4. Qualquer membro do grupo pode aprovar

## Mensagem de Aprovacao (Exemplo)

```
📰 APROVACAO - Conteudo Frio

Titulo: Como chegar no CEU Paraisopolis de onibus e metro

Tipo: como-chegar | Bairro: Paraisopolis

Resumo: Descubra as melhores rotas de onibus e metro para chegar
ao CEU Paraisopolis. Guia completo com linhas, horarios e dicas.

Categorias: Transporte, Paraisopolis
Tags: onibus, metro, ceu, paraisopolis, como-chegar

---
Titulo original: Como ir pro CEU de Paraisopolis

[Aprovar] [Editar Titulo] [Rejeitar]
```

## Tempo de Resposta

- O workflow aguarda a resposta do editor via webhook
- Nao ha timeout padrao - o conteudo fica pendente ate a acao
- Recomendacao: responder dentro de 2 horas para manter a frequencia

## Horarios Sugeridos

| Tipo | Horario do CRON | Publicacao Ideal |
|---|---|---|
| Diario | 6h da manha | 7h-9h (pico de leitura matinal) |
| Frio | 8h (seg/qua/sex) | 10h-12h ou 18h-20h |

Ajuste os horarios conforme analytics do WordPress e redes sociais.
