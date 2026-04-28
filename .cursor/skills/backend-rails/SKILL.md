---
name: backend-rails
description: Orienta o backend Ruby on Rails com Supabase (ActiveRecord) e integração Brapi para os módulos fiis, acoes e ets; validação antes de persistir, cache e aderência a docs/. Usar quando o pedido envolver API Rails, sincronização de cotações, modelos, jobs, Brapi ou persistência no Supabase neste repositório.
---

# Backend Rails (Supabase + Brapi)

## Quando aplicar

Tarefas em `backend/`: modelos, controllers, services, jobs, clients HTTP, migrations e regras de domínio para **FII**, **ação** e **ETF**.

## Regras do projeto (obrigatório)

1. Ler **`.cursor/rules/dev-rules.mdc`** e **`.cursor/rules/architecture.mdc`**.
2. Ler **`docs/`** relevante antes de implementar: `docs/fiis|acoes|ets` (`context.md` + `alert.md`) e `docs/bot/instruction.md` quando afetar alertas ou bot.
3. **Não inventar** métricas, thresholds ou fórmulas fora do que está em `docs/`.

## Stack

- **Ruby on Rails** — API REST, domínio e persistência.
- **Supabase** — PostgreSQL via **ActiveRecord** (`DATABASE_URL`); migrations e constraints no banco alinhadas ao domínio.
- **Brapi** — fonte atual de dados de mercado para **fiis**, **acoes** e **ets**; tratar como dependência externa com limite de uso.

## Organização dos módulos

- Manter fronteiras claras **`fiis`**, **`acoes`**, **`ets`** (nomes alinhados a `docs/`, pasta `docs/ets` para ETFs).
- Cliente HTTP da Brapi em camada única (ex.: `Brapi::Client` ou PORO) com adaptadores ou métodos por tipo, para **trocar ou complementar provedores** depois sem espalhar `Net::HTTP`/`Faraday` nos models.
- Controllers finos; regras de negócio e integração Brapi em **services** / **interactors**; models com validações ActiveRecord + regras documentadas.

## Brapi — uso responsável

- **Chave/token** só por variável de ambiente (nunca no código versionado).
- **Evitar chamadas desnecessárias:**
  - cache em Redis ou tabela de “snapshot” com TTL conforme produto;
  - debounce / não refetch em loop a cada request do front se os dados já estiverem frescos;
  - agrupar tickers quando a API permitir batch.
- Tratar **timeout, rate limit e erro 4xx/5xx** com logs e fallback (ex.: não apagar dados bons por falha parcial).
- Normalizar payload Brapi → **DTO interno** → domínio; não persistir hash cru sem validar.

## Validação antes de salvar

- **ActiveRecord** `validates` + tipos/colunas no PostgreSQL (CHECK, NOT NULL, FK) onde fizer sentido.
- Rejeitar registros com preços negativos inválidos, ticker em formato inesperado, datas futuras incoerentes, etc.
- Em imports em massa: **transação** por lote pequeno ou upsert idempotente para não corromper estado em meio a erro.

## Código limpo

- Métodos curtos, nomes explícitos, um nível de abstração por método.
- Testes ou specs nos pontos críticos: parser Brapi → modelo, regras de alerta alinhadas a `docs/*/alert.md`.

## Referência externa

- Documentação e limites da Brapi: ver [reference.md](reference.md).
