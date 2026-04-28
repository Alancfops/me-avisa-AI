# Gerar feature completa (back + front + Git)

Coordena **todo o processo** para a funcionalidade descrita no chat, **nesta ordem**. Em **cada fase**, o trabalho deve **respeitar integralmente** o que está em **`@docs/`**, nas skills **`@.cursor/skills/`** e nas regras **`@.cursor/rules/`** — não inventar regras de produto nem métricas fora do documentado.

## Obrigatório (aplicar do início ao fim)

### `@docs/`

- Antes de implementar: **ler `docs/` por completo** quando for **feature nova** (ex.: `docs/bot/`, `docs/fiis`, `docs/acoes`, `docs/ets` — `context.md` + `alert.md` + `instruction.md` do bot).
- Para correções pontuais: pelo menos os **`.md` do módulo afetado** e qualquer doc citado no pedido.
- Código e mensagens devem **espelhar** estes documentos; se algo faltar, **atualizar `docs/` primeiro** ou registar decisão explícita no doc (não improvisar só no código).

### `@.cursor/skills/` (ler `SKILL.md` de cada pasta relevante)

| Fase | Skills a seguir |
|------|------------------|
| Git | `github-workflow` |
| Backend | `backend-rails`, `brapi-data`, `telegram-bot`, `testing-quality` |
| Frontend | `frontend-react`, `testing-quality` |
| Todos | Cruzar com `brapi-data` e `testing-quality` sempre que houver dados de mercado ou testes |

### `@.cursor/rules/`

- **`dev-rules.mdc`** — mobile-first, separação FII/ação/ETF, não inventar cálculos financeiros.
- **`architecture.mdc`** — monorepo, REST, Supabase só no Rails, bot via HTTP interno, Docker/Render.
- **`cursor-artifacts.mdc`** — só se estiveres a alterar ficheiros dentro de **`.cursor/`**.

## 0. Contexto

- Anexar ou ler explicitamente **`@docs/`**, **`@.cursor/skills/`** (pastas listadas acima) e **`@.cursor/rules/dev-rules.mdc`**, **`@.cursor/rules/architecture.mdc`**.
- Só depois avançar para Git → back → front.

## 1. Git (início)

Atue como **github-specialist** (`.cursor/agents/github-specialist.md`).

- Garantir branch de feature a partir de **`develop`**: preferir **`git flow feature start <slug>`** (extensão [git-flow AVH](https://github.com/petervanderdoes/gitflow-avh)); se não houver `git flow`, criar `feature/...` manualmente como na skill.
- Seguir **`@.cursor/skills/github-workflow/SKILL.md`**; remoto: **https://github.com/Alancfops/me-avisa-AI.git**
- **Não** commitar na `main`.

## 2. Backend

Atue como **senior-backend-rails** (`.cursor/agents/senior-backend-rails.md`).

- Skills: **`@.cursor/skills/backend-rails/`**, **`@.cursor/skills/brapi-data/`**, **`@.cursor/skills/telegram-bot/`**, **`@.cursor/skills/testing-quality/`** (RSpec).
- Pastas: **`backend/`**, **`backend/bot/`** conforme necessário.
- Entregar **código + RSpec**; FactoryBot; mocks Brapi (sem API real nos testes).

## 3. Frontend

Atue como **senior-frontend-react** (`.cursor/agents/senior-frontend-react.md`).

- Skills: **`@.cursor/skills/frontend-react/`**, **`@.cursor/skills/testing-quality/`** (Jest).
- Pasta: **`frontend/`** apenas; consome a **API** do passo 2; copiar e rótulos alinhados a **`@docs/`** quando afetarem UI/alertas.
- Entregar **código + Jest** (HTTP mockado).

## 4. Git (fecho)

Novamente fluxo **github-specialist** + **`@.cursor/skills/github-workflow/SKILL.md`**:

- `git diff --staged` / revisão do conjunto **back + front** (inclui aderência a **`@docs/`** e regras).
- Commits **atómicos** em **inglês**, **Conventional Commits** (ex.: `feat(api): ...` e `feat(ui): ...` em commits separados se fizer sentido).
- Instruções para **push** e abertura de **PR para `develop`** (título, descrição, como testar).

---

**Comandos atómicos** (se só precisar de uma parte): use `/gerar-back`, `/gerar-front` ou `/code-review`.

Execute com base no **pedido atual do usuário** no chat.
