# Me Avisa Aí

App pessoal que acompanha FIIs favoritos e avisa no Telegram quando algum chega
num preço bom. "Aí" de "me avisa aí" — não é IA. Também é projeto de estudo de
arquitetura, CI/CD, agentes, plugins e MCP.

## Estado

Recomeço do zero (2026-10-29). **Ainda não há código**: só docs, skill e agente.
O repositório antigo (`Alancfops/me-avisa-AI`, branch `develop`) é só referência.

## Decisões

- Projeto separado em módulos. **Módulo 1: FIIs** (favoritos, dados de mercado,
  regras de alerta, avisos no site). Depois: Telegram, MCP, ações, ETFs.
- Laravel + React (Inertia) + Tailwind v4 + shadcn/ui, Postgres, Docker Compose.
- Roda em localhost e sobe junto com a máquina. Sem login: um usuário só.
- Cotação: brapi (plano grátis). VP e DY: Informe Mensal de FII da CVM.
- Aprendizados da versão antiga: `docs/legado.md`.

## Onde está cada coisa

| O quê | Onde |
|---|---|
| Regras de FII e alertas | `docs/fiis/` (ações, ETFs e bot em `docs/acoes`, `docs/ets`, `docs/bot`) |
| Protótipo visual | `docs/prototipo-lovable/` |
| UI/UX e WCAG | skill `ui-ux` (`.claude/skills/ui-ux/`) |
| Front | subagente `frontend-senior` |

## Sessões

- Quando o usuário rodar `/session`, use a skill `session` para salvar onde
  parou, o que foi feito e os próximos passos na memória do projeto.
- Ao começar uma conversa, se o índice de memória tiver "Última sessão", leia
  esse arquivo antes de propor o que fazer.
