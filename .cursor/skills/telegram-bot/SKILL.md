---
name: telegram-bot
description: Orienta o serviço do bot Telegram em backend/bot conforme docs/bot/: sem regras de negócio próprias, só consome o Rails; emojis e link do site nas mensagens. Usar quando o pedido envolver comandos Telegram, webhooks, push ou integração bot↔API neste repositório.
---

# Bot Telegram

## Quando aplicar

Código e configuração em **`backend/bot/`** (ou equivalente definido na arquitetura): comandos, envio de mensagens, webhook/polling, cliente HTTP para o Rails.

## Papel do bot (sem regras locais)

- O bot **não cria nem duplica** regras de alerta, thresholds, cooldowns ou cálculos financeiros.
- **Toda regra** vive no **backend Rails** (alinhado a `docs/`); o bot apenas **chama endpoints internos**, exibe o retorno e trata erros de rede/HTTP.
- Antes de alterar comportamento do bot, **ler por completo `docs/bot/`** (hoje `instruction.md`; novos arquivos na mesma pasta passam a valer junto).
- Se algo parecer “regra de produto”, implementar no **Rails** e expor contrato estável para o bot consumir.

## Integração

- Seguir **`.cursor/rules/architecture.mdc`**: bot → Rails por **HTTP interno** autenticado; sem acesso ao Supabase.
- Ler **`docs/bot/`** para comandos (`/start`, `/help`, `/ver`, `/alertas`), tom de voz e formato sugerido de notificações push (detalhado em `docs/bot/instruction.md`).

## Mensagens: emojis + link do site

- Usar **emojis** de forma consistente com **`docs/bot/`**: 🟢 🔴 🚨 📉 (e outros só se já estiverem nessa pasta ou na resposta da API).
- Incluir em toda mensagem relevante o **link do sistema** (site/app): URL vinda de **variável de ambiente** (ex.: `SITE_URL` / `APP_BASE_URL`) ou **campo devolvido pela API** — nunca hardcoded; o placeholder nos docs é `link-do-sistema`.
- Botões inline ou links Markdown/HTML conforme a API do Telegram escolhida na stack; o texto deve deixar claro “Ver no site” / gráfico completo quando couber em **`docs/bot/`**.

## UX das mensagens

- Curto, direto, profissional; não assessoria de investimentos (texto do doc).
- Erros amigáveis quando o Rails falhar (timeout, 401, 404).

## Referência

- Fonte de verdade do bot: pasta **`docs/bot/`** na raiz do repositório (equivalente a anexar `@docs/bot/` no Cursor). Manter código alinhado a todos os `.md` dessa pasta.
