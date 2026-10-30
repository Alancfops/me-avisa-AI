# Me Avisa Aí

App que acompanha FIIs favoritos e avisa quando algum chega num preço bom. "Aí" de
"me avisa aí" — não é IA. É projeto pessoal, portfólio e open source (MIT, repositório
público), instalável por outras pessoas. Também serve de estudo de arquitetura, CI/CD,
agentes, plugins e MCP.

## Estado

**Ainda não há código**: só docs, skills e agentes. Design aprovado em
`docs/superpowers/specs/2026-10-29-modulo-fiis-design.md`; próximo passo em
`docs/superpowers/plans/2026-10-30-arquitetura-base.md`.
**Só comece a implementar quando o usuário disser "pode começar".**

## Decisões

- Projeto separado em módulos. **Módulo 1: FIIs** (favoritos, dados de mercado,
  regras de alerta, avisos no site). Depois: Telegram, MCP, ações, ETFs.
- Laravel + React (Inertia) + Tailwind v4 + shadcn/ui, Postgres. Monólito modular com
  Clean Architecture pragmática em `app/Modules/`.
- Só web, em localhost. Sem login, sem túnel e sem app nativo: uma pessoa por instalação.
- Instalação: Docker Compose, OrbStack, Podman ou sem contêineres. Comandos por
  `Makefile` (`make install`, `make start`, `make test`, `make check`).
- Cotação: brapi (plano grátis, 1 ativo por requisição, a cada 30 min). VP e DY: Informe
  Mensal de FII da CVM. Limites de alerta só mudam depois de uso real.
- Aprendizados da versão antiga: `docs/legado.md` (o design prevalece).

## Git e CI/CD

- Conventional Commits. Fluxo: `feature/*` → PR → `develop` → PR → `main`.
- Toda mudança, inclusive de agentes, começa em uma branch própria criada a partir da
  `develop`: `feature/<assunto>`, `fix/<assunto>` ou `docs/<assunto>`. Não commite direto
  na `develop` nem na `main`.
- Commit local não aciona nada. CI (GitHub Actions) roda em PR e merge; CD roda na tag
  `vX.Y.Z` e publica imagem `amd64`/`arm64` no GHCR e a release. Ainda não implementados.
- Push e PR só quando o usuário pedir. `docs/prompt` não entra no git.

## Onde está cada coisa

| O quê | Onde |
|---|---|
| Regras de FII e alertas | `docs/fiis/` (ações, ETFs e bot em `docs/acoes`, `docs/ets`, `docs/bot`) |
| Protótipo visual | `docs/prototipo-lovable/` |
| UI/UX e WCAG | skill `ui-ux` (`.claude/skills/ui-ux/`) |
| Front | subagente `frontend-senior` |
| Backend, arquitetura e checklist de review | skill `backend` (`.claude/skills/backend/`) |
| Implementação do backend | subagente `backend-senior` |
| Revisão do backend | subagente `code-review` |

## Sessões

- Quando o usuário rodar `/session`, use a skill `session` para salvar onde
  parou, o que foi feito e os próximos passos na memória do projeto.
- Ao começar uma conversa, se o índice de memória tiver "Última sessão", leia
  esse arquivo antes de propor o que fazer.
