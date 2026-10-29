# Comportamento do Bot (Telegram)

## Objetivo

O bot é a ponta de entrega imediata: o usuário não precisa abrir o site para saber que um favorito atingiu regra de alerta; o bot avisa. Resumos sob demanda (`/ver`) complementam as notificações push.

**Escopo:** O bot não presta assessoria de investimentos; mensagens são informativas, baseadas em dados e nas regras configuradas pelo usuário no sistema (favoritos + alertas).

---

## Comandos

### `/start`

- Apresenta o propósito do bot (monitorar **FIIs**, **ações** e **ETFs** favoritos).
- Lista os comandos disponíveis (equivalente prático a um menu inicial).
- Pode incluir link para o site/app onde o usuário gerencia favoritos e preços alvo.

### `/help`

- Lista enxuta de comandos com um exemplo de ticker por tipo (`MXRF11`, `PETR4`, `BOVA11`).
- Indica que tickers devem ser os da B3, em geral **4 letras + dígito** (ex.: `HGLG11`, `ITSA4`).

### `/ver <ticker>`

Consulta única ao ativo **já cadastrado como favorito** (ou comportamento a definir: negar com mensagem se não for favorito — documentar a regra de produto escolhida).

**Exemplos:** `/ver MXRF11`, `/ver PETR4`, `/ver BOVA11`

**Resposta sugerida (campos conforme o tipo):**

| Tipo   | Campos mínimos |
|--------|----------------|
| FII    | Preço, variação do dia, P/VP, DY (deixar claro se mensal ou anual), link para o ativo no site |
| Ação   | Preço, variação do dia, P/L e/ou DY se disponíveis, link |
| ETF    | Preço, variação do dia, índice de referência (se conhecido), link |

- Normalizar ticker para **maiúsculas** na entrada.
- Se o ticker for inválido ou não encontrado: mensagem curta + sugestão de conferir o código na B3.

### `/alertas`

Mostra **snapshot do dia** dos favoritos que satisfazem **alguma** condição de alerta ativa, não apenas “preço abaixo do alvo”. Alinhar com os documentos:

- **Preço alvo** (preço ≤ valor definido).
- **Faixa de preço** (quando configurada; ex.: FIIs).
- **Regras automáticas** (ex.: FII sem configuração manual — média 30d × 0,95 e P/VP abaixo de 1,0).
- **Quedas / movimentos** quando aplicável (percentuais por tipo: ver `docs/fiis/alert.md`, `docs/acoes/alert.md`, `docs/ets/alert.md`).

Indicar na mensagem **qual regra** disparou (ex.: “alvo”, “faixa”, “automático”, “queda X%”) para o usuário entender o contexto.

### Comandos futuros (opcional)

Documentar no backlog de produto, não obrigatórios na v1:

- `/favoritos` — listar tickers monitorados.
- `/silenciar` ou preferências de horário — reduzir notificações em janelas definidas.

---

## Regras de notificação (push)

- **Gatilho:** o preço ou a variação cruza a condição configurada (preço alvo, faixa, regra automática, queda percentual, etc.), conforme o tipo de ativo.
- **Frequência:** em linha com anti-spam dos docs de alerta — tipicamente **até 1 envio por ativo a cada 24 h**, com exceções explícitas (ex.: movimento forte, nova mínima de 52 semanas em ETF), quando o produto assim definir.
- **Conteúdo da mensagem:**
  - Nome do ativo + emoji coerente com o evento (ver Tom de voz).
  - Preço atual, referência ao alvo/faixa ou à regra (ex.: “queda 5,2% no dia”).
  - Botão ou link: “Ver no site” / gráfico completo.

Cooldown e exceções devem ser **idênticos** aos descritos em `docs/*/alert.md` para evitar divergência entre push e `/alertas`.

---

## Tom de voz

- Curto, direto e profissional.
- Emojis sugeridos: 🟢 (preço favorável / dentro da estratégia), 🔴 (queda forte ou alerta de risco), 🚨 (condição de compra/alvo atingido), 📉 (ETF ou mercado em queda relevante).

---

## Referência rápida na documentação

- FIIs: `docs/fiis/context.md`, `docs/fiis/alert.md`
- Ações: `docs/acoes/context.md`, `docs/acoes/alert.md`
- ETFs: `docs/ets/context.md`, `docs/ets/alert.md`
