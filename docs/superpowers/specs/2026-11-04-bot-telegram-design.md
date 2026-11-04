# Bot do Telegram: design

Data: 2026-11-04. Módulo 2 do projeto. Complementa `docs/bot/instruction.md` (onde este
design diverge, este prevalece) e depende da carteira de FIIs
(`2026-11-04-carteira-de-fiis-design.md`, PR #10) estar na `develop`.

## Intenção

Hoje os avisos só aparecem no site, então a pessoa precisa abrir o app para saber se
algum favorito bateu uma regra. O bot leva os avisos até o Telegram e responde a
comandos rápidos, como "algum favorito bateu alerta hoje?", sem abrir o site.

## Metas

1. Todo aviso que sai no site também é enviado ao Telegram, com o mesmo antispam (o bot
   não decide nada: envia o `Alert` que o módulo de FIIs já gravou).
2. Comandos: `/start <código>`, `/alertas`, `/ver <ticker>`, `/favoritos`, `/carteira`,
   `/ajuda`.
3. Mensagens organizadas e fáceis de ler, com emojis com significado fixo (ver
   "Mensagens").
4. Aba **Telegram** no site, que ensina a criar e conectar o bot e mostra o estado da
   conexão.
5. README com: como criar e conectar o bot, e como desligar e religar o início automático
   do app junto com o computador.

## Fora do escopo

- Silenciar avisos em horários, escolher por favorito o que vai para o Telegram.
- Comandos que mudam dados (favoritar, mudar alvo, editar carteira).
- Ações, ETFs e MCP.
- Mais de um chat por instalação.

## Anti-metas

- Recomendar investimento: as mensagens dizem que uma regra foi atendida, nunca
  "oportunidade" ou "compre".
- Responder a quem não é o chat conectado.
- Token do bot no repositório, no banco ou na conversa com o Claude.
- Expor o app na internet: o app busca as mensagens; o Telegram nunca chama o app.

## Restrições

- **Sem webhook:** o app roda só em `127.0.0.1`. O bot usa *long polling*
  (`getUpdates` com `timeout` de 30 s) da Bot API.
- **Token:** `TELEGRAM_BOT_TOKEN` no `.env`, lido por `config/services.php`, como o
  `BRAPI_TOKEN`. Sem token, o bot fica parado e a aba Telegram explica o que falta.
- **Uma pessoa por instalação:** um chat conectado. Depois de conectado, mensagens de
  outros chats são ignoradas em silêncio (sem resposta) e só ficam no log. Antes de
  conectar, qualquer chat que mande `/start` recebe só a explicação de como conectar,
  sem nenhum dado.
- **Formato:** `parse_mode=HTML`, com nomes e textos vindos de fontes externas escapados.
  Datas e horas em `America/Sao_Paulo`, com a data sempre junto da hora (`30/10, 14:30`).
- **Links:** usam `APP_URL`. Só abrem no computador onde o app roda; a aba e o README
  dizem isso.

## Conectar o bot

1. A pessoa cria o bot no @BotFather (`/newbot`) e põe o token no `.env`.
2. Sobe o app de novo (`make start`, que cria o serviço `bot`).
3. A aba Telegram mostra o nome do bot (`getMe`), um **código de 6 caracteres** válido
   por 15 minutos e o botão **Abrir no Telegram**
   (`https://t.me/<username>?start=<código>`), que já manda `/start <código>`.
4. O bot recebe `/start <código>`, confere, grava o chat e responde com a mensagem de
   boas-vindas. A aba passa a mostrar "Conectado" e o botão **Desconectar**.
5. `/start` sem código, ou com código errado ou vencido: o bot responde só ao chat que
   mandou, explicando que é preciso gerar o código na aba Telegram do app. Não revela
   dados.

Gerar um código novo invalida o anterior. Desconectar apaga o chat; o próximo `/start`
com código válido conecta de novo.

## Mensagens

Significado fixo dos emojis, usado em todas as mensagens:

| Emoji | Significado |
|---|---|
| 🔔 | aviso de regra atendida |
| 🎯 | preço alvo |
| 📥 | faixa de compra |
| 🤖 | regra automática |
| 🔻 | queda forte |
| 💰 | preço |
| 📊 | P/VP |
| 💸 | rendimento / DY |
| 💼 | carteira |
| ⭐ | favoritos |
| ✅ | nada a avisar / deu certo |
| ⚠️ | dado faltando ou problema |

Modelos (o texto exato pode ser ajustado pelo builder, mantendo a estrutura, a ordem e os
emojis):

**Aviso (push):**

```text
🔔 KNRI11 · 🎯 Preço alvo atingido
💰 R$ 146,46 · cotação de 30/10, 14:30
🎯 Sua referência: alvo R$ 150,00
📊 P/VP 0,95 · 💸 DY 12m 9,8%
<motivo da regra, como o site mostra>

Ver no app: http://127.0.0.1:8000/fundos/KNRI11
```

**`/alertas`:**

```text
🔔 Avisos de hoje (30/10)

🎯 KNRI11 · alvo · R$ 146,46 · 14:30
🔻 MXRF11 · queda forte −3,4% · R$ 9,10 · 11:00
```

Sem avisos: `✅ Nenhum favorito bateu regra hoje (30/10).` "Hoje" é o dia corrente em
São Paulo, pela data do aviso (`occurredAt`).

**`/ver KNRI11`:**

```text
🏢 KNRI11 · Kinea Renda Imobiliária
💰 R$ 146,46 (−0,5%) · cotação de 30/10, 14:30
📊 P/VP 0,95 · mediana 12m 0,98
💸 DY 12m 9,8% · último rendimento ≈ R$ 1,10 por cota
⭐ Favorito · regras: alvo R$ 150,00, queda forte

Ver no app: …/fundos/KNRI11
```

Igual à página do fundo: não favorito usa o fechamento da B3 ("fechamento de 30/10
(B3)") e não tem a linha de regras. Ticker em minúsculas é aceito. Fora do catálogo:
`⚠️ Não achei XPTO11. Confira o código na B3.` Sem ticker: explica o uso
(`/ver KNRI11`).

**`/favoritos`:** `⭐ Seus favoritos (4)` e uma linha por fundo: ticker, preço com a
data e 🔔 se teve aviso hoje. Sem favoritos: diz como favoritar no app.

**`/carteira`:** `💼 Sua carteira`, valor total, rendimento estimado por mês (último e
média), quantos fundos e quantos ficaram fora do total; depois as 5 maiores posições.
Sempre com a linha "Estimativa pelo DY e pelo VP informados à CVM. O fundo pode pagar
outro valor." Carteira vazia: diz como adicionar no app.

**`/ajuda`** (e qualquer texto ou comando desconhecido): lista curta dos comandos com
um exemplo de cada.

Dado ausente aparece como "sem preço", "sem P/VP" etc., nunca R$ 0 nem "null".

## Aba Telegram no site

Rota `/telegram`, item **Telegram** no menu lateral, fora do grupo FIIs (antes de
Saúde). Mostra, conforme o estado:

1. **Sem token:** passo a passo do @BotFather, a linha do `.env` e "reinicie o app".
2. **Token inválido** (`getMe` falha): diz isso e manda conferir o token.
3. **Pronto para conectar:** nome do bot, código, botão "Abrir no Telegram", tempo de
   validade e "Gerar outro código".
4. **Conectado:** nome do bot, desde quando, botão "Enviar mensagem de teste" e
   "Desconectar" (com confirmação).

Sempre: a lista dos comandos e o aviso de que os links das mensagens só abrem neste
computador. WCAG 2.2 AA como as outras telas (skill `ui-ux`).

## Arquitetura

- **Módulo novo** `app/Modules/Telegram/`, com as mesmas camadas do de FIIs. Ele usa os
  casos de uso do módulo de FIIs (`ListAlerts`, `DescribeFund`, `ListFavorites`,
  `ShowPortfolio`); o módulo de FIIs não conhece o Telegram.
- **Envio dos avisos:** o módulo de FIIs ganha a interface `AlertNotifier`
  (Application), chamada por `EvaluateFavorites` logo depois de gravar um `Alert`. A
  implementação padrão não faz nada; o módulo Telegram registra a sua, que põe na fila um
  job de envio. Falha no envio não pode desfazer nem atrasar a gravação do aviso: o job
  tenta de novo (`--tries=3`) e a falha aparece na Saúde.
- **Recebimento:** comando `telegram:poll`, num serviço novo `bot` do Compose
  (`restart: unless-stopped`, mesma imagem). Ele fica em laço com `getUpdates`, guarda o
  último `update_id` para não responder duas vezes depois de reiniciar, e sem token
  espera 60 s e confere de novo, em vez de sair (sair faria o Compose reiniciar em laço).
- **Dados:** tabela do chat conectado (chat id, conectado em) e do código pendente
  (código, vence em), e o último `update_id`.
- **Cliente HTTP:** `getMe`, `getUpdates`, `sendMessage`, atrás de uma interface, para os
  testes usarem `Http::fake`.
- **Saúde:** o Telegram entra como fonte (último envio ou leitura com sucesso, último
  erro), como a brapi e a CVM.

## README

Seção nova **Bot do Telegram**:

- criar o bot no @BotFather, pôr `TELEGRAM_BOT_TOKEN` no `.env`, `make start`, abrir a
  aba Telegram e conectar;
- os comandos, com um exemplo cada;
- os links só abrem no computador do app;
- não compartilhar o token; se vazar, `/revoke` no @BotFather e trocar no `.env`.

Seção nova **Iniciar com o computador**, para Docker, OrbStack e Podman:

- hoje os serviços têm `restart: unless-stopped`: voltam sozinhos quando o Docker inicia,
  a menos que tenham sido parados;
- **desligar só o app:** `make stop` (fica parado, inclusive depois de religar o
  computador, até `make start`);
- **desligar o Docker no boot:** Linux `sudo systemctl disable docker` (religar:
  `sudo systemctl enable docker`); Docker Desktop e OrbStack: opção de iniciar ao entrar
  no sistema nas configurações; Podman no Mac: não iniciar a `podman machine`;
- **religar:** `make start`;
- o smoke test (`docker/smoke-test.sh`) passa a esperar cinco serviços com
  `restart: unless-stopped`.

As mesmas seções em `README.en.md`.

## Testes

- Formatação de cada mensagem (aviso, `/alertas` com e sem avisos, `/ver` favorito, não
  favorito e fora do catálogo, `/favoritos`, `/carteira` vazia e com fundos), incluindo
  escape de HTML e dados ausentes.
- Conexão: código certo, errado, vencido, substituído; chat estranho ignorado.
- `EvaluateFavorites` chama o `AlertNotifier` só quando grava um aviso; falha do envio
  não afeta a gravação.
- Poll: não reprocessa `update_id` antigo; sem token não chama a API.
- Aba Telegram: os quatro estados.
- Arquitetura: módulo de FIIs não depende do módulo Telegram.

## Decisões do Claude

Não confirmadas pela pessoa; estão aqui para conferir:

- Conexão por código de 6 caracteres, válido por 15 minutos, com link `t.me/...?start=`.
- Serviço `bot` separado no Compose, em vez de rodar pelo agendador a cada minuto (o
  agendador deixaria a resposta aos comandos com até 1 minuto de atraso).
- Token só no `.env`, não em formulário do site.
- Botão "Enviar mensagem de teste" na aba.
- Mensagem de quem não é o chat conectado: ignorada sem resposta.
- `/carteira` mostra as 5 maiores posições.
- Tabela de emojis acima.
