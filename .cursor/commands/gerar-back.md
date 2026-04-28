# Gerar / evoluir backend

Atue como o subagente **senior-backend-rails** (`.cursor/agents/senior-backend-rails.md`).

## Antes de codar

- Ler **`.cursor/skills/backend-rails/SKILL.md`**, **`.cursor/skills/brapi-data/SKILL.md`**, **`.cursor/skills/telegram-bot/SKILL.md`**, **`.cursor/skills/testing-quality/SKILL.md`** (RSpec).
- **`docs/`** por tipo (`docs/fiis`, `docs/acoes`, `docs/ets`, `docs/bot`) e **`.cursor/rules/dev-rules.mdc`**.

## Escopo

- **`backend/`** — API Rails, Supabase via ActiveRecord, integração Brapi, módulos **fiis / acoes / ets**.
- **`backend/bot/`** — Telegram consumindo endpoints internos do Rails (sem regras duplicadas).

## Entrega

- Código + **RSpec**; FactoryBot; **sem chamadas reais à Brapi** nos testes.

Execute a tarefa que o usuário descreveu no chat (contexto atual).
