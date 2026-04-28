# Code review + GitHub

Atue como o subagente **github-specialist** (`.cursor/agents/github-specialist.md`) e como revisor de código sênior.

## Git e histórico

- Seguir **`@.cursor/skills/github-workflow/SKILL.md`** — branch a partir de **`develop`** (preferir **`git flow`** quando instalado), Conventional Commits em **inglês**, self-review com **`git diff --staged`** antes de cada commit, PR para **`develop`** após revisão.
- Remoto de referência: **https://github.com/Alancfops/me-avisa-AI.git**

## Revisão de código

- Conferir diff atual ou arquivos indicados: correção, segurança (secrets, injeção, auth), aderência a **`docs/`** e às **`.cursor/rules/`**.
- Cobertura de testes quando a mudança for comportamental (RSpec/Jest conforme camada).

## Saída

- Achados por severidade (crítico / importante / sugestão).
- Sugestão de **mensagem de commit** em Conventional Commits (inglês) se houver alterações pendentes.

Use o contexto do chat (arquivos abertos / seleção) como foco da revisão.
