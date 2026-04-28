# Subagentes do projeto (me_avisaAI)

Ficheiros em **`.cursor/agents/`** — o Cursor usa o **nome do ficheiro** e o **frontmatter** (`name`, `description`) para delegação. Peça explicitamente ao agente ou use os comandos em **`.cursor/commands/`** que os referenciam.

| `name` (YAML) | Ficheiro | Quando usar |
|---------------|-----------|----------------|
| `github-specialist` | [`agents/github-specialist.md`](agents/github-specialist.md) | Branches (preferir **git-flow AVH**: `git flow feature start/finish`), Conventional Commits (EN), PR para `develop`, self-review antes de commit, remoto [Alancfops/me-avisa-AI](https://github.com/Alancfops/me-avisa-AI). Skill: `github-workflow`. |
| `senior-backend-rails` | [`agents/senior-backend-rails.md`](agents/senior-backend-rails.md) | API Rails, Supabase (ActiveRecord), Brapi, módulos fiis/acoes/ets, bot em `backend/bot/`, RSpec + FactoryBot. Skills: `backend-rails`, `brapi-data`, `telegram-bot`, `testing-quality`. |
| `senior-frontend-react` | [`agents/senior-frontend-react.md`](agents/senior-frontend-react.md) | React + Tailwind em `frontend/`, mobile-first, REST apenas, Jest. Skills: `frontend-react`, `testing-quality`. |

## Comandos que acionam estes agentes

- **`/gerar`** — encadeia Git → back → front → Git (ver [`commands/gerar.md`](commands/gerar.md)).
- **`/gerar-back`** → `senior-backend-rails`
- **`/gerar-front`** → `senior-frontend-react`
- **`/code-review`** → `github-specialist` + revisão de código

## Regras e arquitetura

- Regras globais: **`.cursor/rules/dev-rules.mdc`**, **`.cursor/rules/architecture.mdc`**
- Meta-regra para `.cursor/`: **`.cursor/rules/cursor-artifacts.mdc`**
