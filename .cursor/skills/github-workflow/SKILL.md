---
name: github-workflow
description: Define fluxo Git/GitHub no projeto com apoio da extensão git-flow (AVH): branches, Conventional Commits em inglês, revisão antes de commitar e PR para develop após code review. Usar quando o pedido envolver branches, commits, push, pull request ou merge neste repositório.
---

# GitHub e fluxo de trabalho

## Quando aplicar

Qualquer alteração versionada: código, docs relevantes ao produto, config de CI. Não se aplica a arquivos locais ignorados.

## Repositório remoto (referência do time)

- Origem GitHub prevista: **https://github.com/Alancfops/me-avisa-AI.git** — alinhar `git remote` e PRs a este URL salvo decisão contrária.

## Regras de branch

- **Nunca** desenvolver nem commitar **direto em `main`** (protegida para releases/estável, salvo política explícita do time).
- **Integração contínua:** fluxo padrão aponta para **`develop`** após revisão.
- **Uma funcionalidade nova → uma branch nova** (feature branch), partindo de `develop` atualizada:

  `feature/<slug-curto-descritivo>` ou `feat/<slug>` — ex.: `feature/alert-price-fii`, `feat/api-favorites`.

## Git Flow (extensão `git-flow`)

Usar a **extensão de linha de comando [git-flow (AVH Edition)](https://github.com/petervanderdoes/gitflow-avh)** quando estiver instalada (`git flow version`) — automatiza prefixos, merges e limpeza de branches, alinhada ao modelo [git-flow de Vincent Driessen](https://nvie.com/posts/a-successful-git-branching-model/).

### Configuração única por clone (`git flow init`)

- Branch de **produção:** `main`
- Branch de **desenvolvimento:** `develop`
- Prefixo **feature:** `feature/` (ou `feat/` se o time padronizar e ajustar no init)
- Aceitar defaults para `release/` e `hotfix/` — usar quando houver release ou correção urgente em produção

### Dia a dia (features → `develop`)

| Objetivo | Com git-flow | Equivalente manual (se não houver extensão) |
|----------|----------------|-----------------------------------------------|
| Começar feature | `git flow feature start <slug>` | `git checkout develop && git pull` → `git checkout -b feature/<slug>` |
| Publicar branch | `git flow feature publish <slug>` (opcional) | `git push -u origin feature/<slug>` |
| Após merge do PR em `develop` | `git flow feature finish <slug>` (no clone atualizado) ou remover branch remota no GitHub | `git branch -d feature/<slug>` + apagar remota se aplicável |

- **Pull Request:** continua obrigatório no GitHub antes do merge em `develop`; o `git flow feature finish` pode ser usado **depois** do PR aprovado, no diretório local, para fechar a feature de forma consistente (ou só no remoto via “Squash and merge” no GitHub — o time escolhe uma convenção e mantém).
- Se **`git flow`** não estiver instalado: seguir a coluna **manual** sem bloquear o trabalho.

### Instalação (referência rápida)

- **Windows:** por exemplo [Scoop](https://scoop.sh/) `git-flow-avh`, [Chocolatey](https://chocolatey.org/) ou Git em WSL; confirmar com `git flow version`.
- **macOS:** `brew install git-flow-avh`
- **Linux:** pacote `git-flow` da distro ou build a partir do repositório AVH.

## Fluxo obrigatório (ordem)

1. **Criar branch** a partir de `develop` (pull antes se necessário) — **preferir** `git flow feature start <slug>` quando a extensão existir.
2. **Desenvolver** a funcionalidade (escopo da branch = uma feature coerente).
3. **Revisar o diff** (self code review): escopo, nomes, duplicação, segredos, alinhamento a `docs/` e às rules do projeto.
4. **Commitar** (mensagens em **inglês**, Conventional Commits — ver abaixo).
5. **Push** da branch para `origin`.
6. **Abrir Pull Request** para **`develop`** (descrição clara do que mudou e por quê).
7. **Code review** no PR (outra pessoa ou checklist rigoroso se solo).
8. **Merge no `develop`** somente após aprovação/review resolvido.

## Conventional Commits (sempre em inglês)

Formato: `<type>(<optional scope>): <description in imperative mood>`

**Types comuns:** `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`, `perf`, `ci`.

**Exemplos:**

- `feat(api): add price snapshot endpoint`
- `fix(bot): handle invalid ticker from Telegram`
- `docs(fiis): clarify alert cooldown rules`
- `chore(ci): add lint step to workflow`

- Descrição **curta**, **imperativa** (*add*, *fix*, *update*), **sem ponto final** obrigatório; corpo do commit opcional para detalhes.
- **Não** misturar português na primeira linha (regra do time: mensagens **100% inglês**).

## Antes de cada commit

- `git diff --staged` — conferir tudo que entra no commit.
- Commits **pequenos e atômicos** quando possível (um tema por commit).
- Não commitar credenciais, `.env`, dumps grandes.

## Pull Request

- Título alinhado ao escopo (pode espelhar o Conventional Commit principal).
- Mencionar issue/ticket se existir; listar breaking changes se houver.
- Garantir CI verde antes de pedir merge.

## Anti-padrões

- Vários commits não relacionados na mesma branch “genérica”.
- Merge direto em `main` sem processo acordado.
- Mensagens vagas: `update`, `fix stuff`, `changes`.

## Referência

- [Conventional Commits](https://www.conventionalcommits.org/) — especificação oficial (tipos e breaking change).
- [git-flow AVH](https://github.com/petervanderdoes/gitflow-avh) — documentação da extensão de comandos.
