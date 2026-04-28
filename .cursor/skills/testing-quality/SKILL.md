---
name: testing-quality
description: Orienta testes e qualidade no monorepo Rails+React: RSpec (API e bot), Jest (UI), prioridade em alertas, calculadora/indicadores de FIIs e contrato da API; mocks da Brapi, FactoryBot e regressão entre módulos. Usar quando escrever testes, corrigir falhas, CI ou revisar cobertura crítica neste repositório.
---

# Testes, segurança e ausência de bugs

## Quando aplicar

Implementação ou alteração em `backend/` ou `frontend/`; refatoração que toque regras de domínio, integração Brapi, API pública ou alertas.

## Ferramentas

| Camada | Ferramenta | Escopo |
|--------|------------|--------|
| **Backend (Rails)** | **RSpec** | Lógica da **API REST**, serviços, modelos, jobs e **bot** (handlers que chamam o Rails ou serviços compartilhados). |
| **Frontend (React)** | **Jest** (e React Testing Library quando aplicável) | **Componentes**, telas, hooks que formatam dados da API. |

## Prioridade de cobertura

1. **Regras de alerta** — quando o preço (ou variação) cruza a condição documentada em `docs/*/alert.md`, o sistema deve **disparar** (ou não) de forma determinística nos testes; alinhar thresholds e cooldowns ao doc, sem inventar valores (`.cursor/rules/dev-rules.mdc`).
2. **Calculadora e indicadores de FIIs** — cálculos e campos usados em UI/alertas (ex.: DY, P/VP) conforme `docs/fiis/`; testes de regressão quando o código compartilhado mudar.
3. **Contrato API → front** — serialização JSON: chaves, tipos e nulls que o front espera; testes de request/spec ou contract para **não entregar formato errado**.

## Segurança (mínimo nos testes)

- **Não** colocar tokens reais, chaves Brapi ou credenciais Supabase em fixtures ou VCR; usar env de teste ou stubs.
- Testar rotas sensíveis com **autenticação/autorização** quando o produto exigir; evitar expor dados de outro usuário em specs de API.

## Regras práticas

### Mocks (Brapi)

- **Nunca** deixar a suíte chamar a **API Brapi real** — usar **doubles**, **WebMock/VCR gravado offline** ou payloads fixture; objetivo: rapidez, determinismo e **não gastar crédito/cota**.
- Alinhar payloads falsos com **`.cursor/skills/brapi-data/`** (campos válidos, ausência de dados, tipos).

### FactoryBot (Rails)

- Usar **factories** para criar usuários, favoritos, ativos e snapshots em teste; evitar `create` espalhado com dezenas de atributos repetidos.
- Factories **explícitas** para cada tipo (`fii`, `acao`, `etf`) quando o domínio divergir.

### Definição de pronto

- Uma tarefa **só está pronta** se houver **teste** cobrindo o comportamento novo ou alterado e a suíte **verde** (RSpec + Jest relevantes).
- Corrigir teste flaky antes de merge; não silenciar falha com `pending` sem issue.

### Regressão entre módulos (fiis / acoes / ets)

- Manter testes **por boundary** de módulo; ao mudar código de **ações**, a CI deve rodar specs que protejam **FIIs** e **ETFs** quando compartilharem serviço, concern ou serializer comum.
- Preferir **testes unitários** apertados por módulo + **poucos testes de integração** que atravessam camadas para detectar efeito colateral.

## Anti-padrões

- `sleep` arbitrário para “esperar” API.
- Teste que só cobre `expect(true).to be true`.
- Mock que não reflete o formato real documentado em `docs/` ou em `brapi-data`.

## Referência de domínio

- Alertas e indicadores: `docs/fiis`, `docs/acoes`, `docs/ets` e `docs/bot/instruction.md` quando o fluxo envolver notificação.
