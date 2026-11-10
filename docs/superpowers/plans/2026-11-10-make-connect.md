# `make connect` Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Um `make connect` que grava o servidor MCP `me-avisa-ai` no arquivo do cliente de IA escolhido, sem perder o que já existe, testa a conexão e diz o próximo passo.

**Architecture:** O script `bin/connect` (bash, no computador da pessoa) pergunta o cliente, acha o arquivo e chama `compose run tools php artisan mcp:connect`, montando só a pasta desse cliente. O PHP (`ClientConfig` + `ConnectCommand`) junta o JSON, pergunta antes de trocar, faz o backup e grava. Depois, o script testa a conexão com o handshake MCP. O bloco continua sendo gerado só pelo `make mcp-config`.

**Tech Stack:** Bash (compatível com o bash 3.2 do Mac), Make, Laravel 12 / PHP 8.4, Pest, Docker ou Podman Compose.

**Spec:** `docs/superpowers/specs/2026-11-10-make-connect-design.md`

## Global Constraints

- Nada além do que a instalação já pede: sem `jq`, Python ou Node no computador; sem `timeout` (não existe no Mac).
- Linux e Mac. WSL (`/proc/version` contém `microsoft`): diz que ali é manual e mostra o JSON.
- Clientes e caminhos (Mac | Linux):
  - `claude-desktop`: `~/Library/Application Support/Claude/claude_desktop_config.json` | `~/.config/Claude/claude_desktop_config.json`
  - `cursor`: `~/.cursor/mcp.json` nos dois
  - `antigravity`: `~/.gemini/config/mcp_config.json` nos dois
  - `claude-code` e `json`: não gravam arquivo.
- `make mcp-config` continua manual; a única mudança é o caminho completo do programa (`command -v`), com o nome curto se ele não for achado.
- Mensagens em português, no tom do app ("✅ …"). Nunca dizer "pronto" sem ter gravado.
- Backup: `<arquivo>.bak-AAAA-MM-DD-HHMMSS`, ao lado do original, antes de qualquer gravação em arquivo que já existia; a mensagem mostra `cp "<backup>" "<arquivo>"`.
- JSON gravado com 2 espaços de indentação, barras e acentos sem escape e `\n` no fim.
- `plugin/` e o servidor MCP não mudam.

## Review Focus

- `mcpServers` existe mas não é objeto (`[]`, `"x"`, `null`): o arquivo fica intocado e a mensagem explica. Teste na Task 1.
- Arquivo com outros servidores e chaves com objeto vazio (`"preferences": {}`, `"env": {}`): saem iguais, `{}` continua `{}`. Teste na Task 1.
- Caminho com espaço (`Application Support` no Mac, pasta do repo com espaço): o script cita todas as variáveis, e o `mcp-config` já escapa `"` e `\`. Teste na Task 2 (`--shown-as` com espaço) e na Task 3 (passo manual com espaço).
- Sem terminal (`CLIENT=cursor make connect < /dev/null`) e bloco diferente: não troca, sai com código 2 e sugere rodar de forma interativa. Teste na Task 2.
- App parado: grava assim mesmo e diz "Configurado, mas o app está parado: rode `make start`", sem mostrar o teste como falha. Passo manual na Task 3.

---

## File Structure

| Arquivo | Responsabilidade |
|---|---|
| `app/Modules/Mcp/Connect/ClientConfig.php` (novo) | Ler, juntar e escrever o JSON do cliente. Puro, sem I/O. |
| `app/Modules/Mcp/Connect/InvalidClientConfig.php` (novo) | Exceção com a mensagem para a pessoa. |
| `app/Modules/Mcp/Connect/ConnectCommand.php` (novo) | `mcp:connect`: lê o arquivo, pergunta, faz o backup, grava. |
| `app/Modules/Mcp/McpServiceProvider.php` (novo) | Registra o comando. |
| `bootstrap/providers.php` | Acrescenta o `McpServiceProvider`. |
| `bin/connect` (novo, executável) | Pergunta o cliente, monta a pasta, chama o artisan, testa, mostra o próximo passo. |
| `Makefile` | Alvo `connect`; caminho completo no `mcp-config`. |
| `README.md`, `README.en.md` | `make connect` como caminho principal da seção do MCP; tabela de comandos. |
| `tests/Unit/Modules/Mcp/ClientConfigTest.php`, `tests/Feature/Modules/Mcp/ConnectCommandTest.php` (novos) | Testes. |

---

### Task 1: `ClientConfig`, a junção do JSON

**Files:**
- Create: `app/Modules/Mcp/Connect/ClientConfig.php`, `app/Modules/Mcp/Connect/InvalidClientConfig.php`
- Test: `tests/Unit/Modules/Mcp/ClientConfigTest.php`

**Interfaces:**
- Produces:
  - `final class InvalidClientConfig extends \RuntimeException` (a mensagem é o texto para a pessoa).
  - `ClientConfig::fromJson(string $contents): self`. Lança `InvalidClientConfig` se não for JSON válido, se a raiz não for objeto, se `mcpServers` existir e não for objeto, ou se `mcpServers.me-avisa-ai` existir e não for objeto. Vazio ou só espaços conta como `{}`.
  - `ClientConfig::serverFrom(string $mcpConfigOutput): \stdClass`: extrai `mcpServers.me-avisa-ai` da saída do `make mcp-config`; lança `InvalidClientConfig` se faltar.
  - `$config->server(): ?\stdClass`: o bloco `me-avisa-ai` atual, ou `null`.
  - `$config->withServer(\stdClass $new): self`: cópia com `command` e `args` do `$new`, mantendo as outras chaves do bloco atual (`env`, `disabled`…) e tudo o mais no arquivo. Cria `mcpServers` se faltar.
  - `ClientConfig::sameServer(\stdClass $a, \stdClass $b): bool`: compara só `command` e `args`.
  - `$config->toJson(): string`

A decisão central: decodificar **sem** `associative: true` (objetos viram `stdClass`), para `{}` continuar `{}` ao gravar. `withServer` trabalha numa cópia profunda (`json_decode(json_encode(...))`), sem mexer no original. `toJson` usa `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`, troca a indentação de 4 para 2 espaços (`preg_replace` no início de cada linha, metade dos espaços) e acrescenta `"\n"`.

- [ ] **Step 1: Escrever os testes que falham**

Testes em `tests/Unit/Modules/Mcp/ClientConfigTest.php` (`$new` = `{"command":"/usr/bin/docker","args":["compose","--project-directory","/home/a/me avisa","exec","-T","web","php","artisan","mcp:start","fiis"]}`):

- `it('treats a missing or blank file as an empty object')`: `fromJson('')` e `fromJson("  \n")` → `server()` é `null`; `withServer($new)->toJson()` decodificado é igual a `{"mcpServers":{"me-avisa-ai":$new}}`.
- `it('keeps other servers, keys and empty objects untouched')`: entrada `{"preferences":{},"mcpServers":{"other":{"command":"x","args":[],"env":{}}},"n":1.5}` → a saída contém `"preferences": {}` e `"env": {}`, `other` igual, `"n": 1.5`, e a ordem das chaves do topo se mantém (`preferences`, `mcpServers`, `n`).
- `it('rejects invalid json')`: `fromJson('{"a":')` lança `InvalidClientConfig`.
- `it('rejects a root that is not an object')`: `fromJson('[]')` e `fromJson('"x"')` lançam.
- `it('rejects mcpServers or the block that are not objects')`: com dataset `'{"mcpServers":[]}'`, `'{"mcpServers":"x"}'`, `'{"mcpServers":null}'`, `'{"mcpServers":{"me-avisa-ai":[]}}'` → lança.
- `it('replaces command and args and keeps the other block keys')`: atual `{"command":"docker","args":["a"],"env":{"X":"1"},"disabled":false}` → `server()` depois de `withServer($new)` tem o `command`/`args` de `$new`, `env == {"X":"1"}` e `disabled === false`.
- `it('compares only command and args')`: `sameServer` é `true` entre `$new` e `$new` + `env`; `false` com `args` diferentes.
- `it('reads the block from the mcp-config output')`: `serverFrom` da saída atual do `mcp-config` devolve o bloco; `serverFrom('{}')` lança.
- `it('writes two-space json with unescaped slashes and a final newline')`: `toJson()` começa com `"{\n  \"mcpServers\""`, contém `"/usr/bin/docker"` sem `\/` e termina com `"}\n"`.

- [ ] **Step 2: Rodar e ver falhar**

Run: `make shell` e lá `php artisan test tests/Unit/Modules/Mcp/ClientConfigTest.php`
Expected: FAIL, classe `ClientConfig` não encontrada.

- [ ] **Step 3: Implementar `InvalidClientConfig` e `ClientConfig` com as assinaturas acima**

Textos das exceções: JSON inválido → `"O arquivo não é um JSON válido. Corrija ou apague o arquivo e rode de novo; nada foi alterado."`; raiz ou `mcpServers` que não são objeto → `"O arquivo tem um formato que o comando não conhece (esperava um objeto em {chave}). Nada foi alterado."`; bloco ausente no `serverFrom` → `"A saída do make mcp-config não tem o bloco me-avisa-ai."`

- [ ] **Step 4: Rodar e ver passar**

Run: `php artisan test tests/Unit/Modules/Mcp/ClientConfigTest.php` (no `make shell`)
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Mcp/Connect tests/Unit/Modules/Mcp/ClientConfigTest.php
git commit -m "feat(mcp): merge the me-avisa-ai block into a client config"
```

---

### Task 2: comando `mcp:connect`

**Files:**
- Create: `app/Modules/Mcp/Connect/ConnectCommand.php`, `app/Modules/Mcp/McpServiceProvider.php`
- Modify: `bootstrap/providers.php` (acrescentar `McpServiceProvider::class`)
- Test: `tests/Feature/Modules/Mcp/ConnectCommandTest.php`

**Interfaces:**
- Consumes: `ClientConfig`, `InvalidClientConfig` (Task 1).
- Produces: `php artisan mcp:connect {file} {--block=} {--shown-as=}`
  - `file`: caminho do arquivo como o contêiner o vê (`/connect/mcp.json`).
  - `--block`: a saída inteira do `make mcp-config`.
  - `--shown-as`: o caminho no computador da pessoa, usado em todas as mensagens (padrão: `file`).
  - Códigos de saída, que o script usa: `0` configurado (gravado ou já igual), `1` erro (arquivo inválido, bloco inválido), `2` nada mudou por escolha (respondeu não, ou bloco diferente sem terminal). Constante `ConnectCommand::UNCHANGED = 2`.

Comportamento:
1. Lê o arquivo (inexistente conta como `''`), `ClientConfig::fromJson`, `serverFrom(--block)`. `InvalidClientConfig` → `error(mensagem)`, saída 1.
2. `server()` igual (`sameServer`) → `"✅ Já estava configurado em {shown-as}. Nada foi alterado."`, saída 0.
3. `server()` diferente: mostra o atual e o novo (`command` + `args` unidos por espaço, uma linha cada, rotulados "Atual" e "Novo"). Se `! $this->input->isInteractive()` → `warn("Já existe um me-avisa-ai diferente em {shown-as}. Rode make connect num terminal para escolher se troca. Nada foi alterado.")`, saída 2. Senão, `$this->confirm('Trocar pelo novo?', false)`; não → `"Nada foi alterado."`, saída 2.
4. Se o arquivo existia, copia para `{file}.bak-{now()->format('Y-m-d-His')}` antes de gravar.
5. Grava `toJson()` num `{file}.tmp` na mesma pasta e faz `rename` por cima (não deixa o arquivo pela metade).
6. `"✅ Gravado em {shown-as}."` e, se houve backup, `"Backup: {shown-as}.bak-…  Para voltar: cp \"{shown-as}.bak-…\" \"{shown-as}\""`. Saída 0.

`McpServiceProvider` segue o `TelegramServiceProvider`: `boot()` com `$this->commands([ConnectCommand::class])` dentro de `runningInConsole()`.

- [ ] **Step 1: Escrever os testes que falham**

Em `tests/Feature/Modules/Mcp/ConnectCommandTest.php`, cada teste usa uma pasta temporária nova (`sys_get_temp_dir().'/connect-'.uniqid()`), apagada no `afterEach`, e `$block` = a saída do `mcp-config` com `command` `/usr/bin/docker` e pasta `/home/a/me avisa`. `Carbon::setTestNow('2026-11-10 10:20:30')` no `beforeEach`.

- `it('creates the file when it does not exist')`: `artisan('mcp:connect', [file, '--block' => $block, '--shown-as' => '/home/a/My Files/mcp.json'])` → `assertExitCode(0)`, `expectsOutputToContain('Gravado em /home/a/My Files/mcp.json')`; o arquivo existe com o bloco; nenhum `.bak-*` na pasta.
- `it('keeps what the file already had and makes a backup')`: arquivo com `{"preferences":{"x":1},"mcpServers":{"other":{"command":"o","args":[]}}}` → saída 0; o `.bak-2026-11-10-102030` tem o conteúdo original byte a byte; o novo tem `preferences`, `other` e `me-avisa-ai`; a saída contém `cp "`.
- `it('says it was already configured and writes nothing')`: arquivo já com o bloco igual (+ `env`) → saída 0, `expectsOutputToContain('Já estava configurado')`, conteúdo e `filemtime` iguais, sem backup.
- `it('replaces a different block when the person says yes')`: bloco com `args` antigos e `env:{"X":"1"}` → `expectsConfirmation('Trocar pelo novo?', 'yes')`, saída 0, `args` novos e `env` mantido, backup criado.
- `it('changes nothing when the person says no')`: `expectsConfirmation('Trocar pelo novo?', 'no')`, `assertExitCode(2)`, conteúdo igual, sem backup.
- `it('changes nothing without a terminal')`: mesmo arquivo, `--no-interaction` → `assertExitCode(2)`, `expectsOutputToContain('make connect num terminal')`, conteúdo igual.
- `it('leaves an invalid file untouched')`: arquivo `{"a":` → `assertExitCode(1)`, conteúdo igual, sem backup.

- [ ] **Step 2: Rodar e ver falhar**

Run: `php artisan test tests/Feature/Modules/Mcp/ConnectCommandTest.php` (no `make shell`)
Expected: FAIL, comando `mcp:connect` não existe.

- [ ] **Step 3: Implementar `ConnectCommand`, `McpServiceProvider` e registrar o provider**

- [ ] **Step 4: Rodar e ver passar; depois a checagem completa**

Run: `make check`
Expected: Pint, PHPStan nível 7 e todos os testes passam.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Mcp bootstrap/providers.php tests/Feature/Modules/Mcp/ConnectCommandTest.php
git commit -m "feat(mcp): mcp:connect writes the server into a client config"
```

---

### Task 3: `bin/connect`, `make connect` e o caminho completo no `mcp-config`

**Files:**
- Create: `bin/connect` (`chmod +x`, `#!/usr/bin/env bash`, `set -u`)
- Modify: `Makefile` (alvo `connect`, `.PHONY`, receita do `mcp-config`)

**Interfaces:**
- Consumes: `mcp:connect` e seus códigos de saída (Task 2); `make mcp-config`.
- Produces: `make connect [CLIENT=claude-desktop|claude-code|cursor|antigravity|json] [COMPOSE=…]`.

Makefile:
- `mcp-config`: o awk recebe `BIN="$$(command -v $(firstword $(COMPOSE)) || echo $(firstword $(COMPOSE)))"` e usa `ENVIRON["BIN"]` no lugar de `c[1]` para o `command`.
- `connect: ## Liga uma IA (Claude, Cursor, Antigravity) ao app: make connect [CLIENT=cursor]` → `@COMPOSE='$(COMPOSE)' CLIENT='$(CLIENT)' bin/connect`.

`bin/connect`, em ordem:
1. `cd` para a pasta do repositório (`dirname "$0"/..`). Sem `.env` → `"O app ainda não foi instalado. Rode make install antes."`, sai 1.
2. Escolha do cliente: `CLIENT` vazio e `[ -t 0 ]` → menu numerado com os cinco (rótulos "Claude Desktop", "Claude Code", "Cursor", "Antigravity", "Só mostrar o JSON") e `read`. `CLIENT` vazio sem terminal → explica `make connect CLIENT=cursor` e lista os nomes, sai 1. Nome desconhecido → mesma explicação, sai 1. A lista fica numa função `client_file <cliente>` com um `case` (um cliente novo = uma linha).
3. Mostra `"A IA vai usar a instalação em $PWD."`
4. WSL (`grep -qi microsoft /proc/version 2>/dev/null`) ou `json` → (no WSL, `"No Windows, a configuração é manual: cole o JSON abaixo no arquivo do cliente."`) imprime `make --no-print-directory mcp-config COMPOSE="$COMPOSE"`, sai 0.
5. `claude-code`: se a primeira palavra do `COMPOSE` é `docker`, mostra `export ME_AVISA_AI_DIR="$PWD"` e os dois comandos `/plugin` do README; senão, mostra `claude mcp add --scope user me-avisa-ai -- <command> <args…>`, com o comando e os args montados como no `mcp-config` (cada arg entre aspas simples). Sai 0, sem teste.
6. Arquivos: `uname` (`Darwin` → caminhos do Mac, senão Linux) → `file`; `mkdir -p "$(dirname "$file")"`; `block="$(make --no-print-directory mcp-config COMPOSE="$COMPOSE")"`; `$COMPOSE run --rm $tty -v "$(dirname "$file"):/connect" tools php artisan mcp:connect "/connect/$(basename "$file")" --block="$block" --shown-as="$file" $noint`, com `tty=-T` e `noint=--no-interaction` quando `! [ -t 0 ]`. `COMPOSE` fica sem aspas de propósito (pode ter duas palavras), como no Makefile.
7. Código 1 ou 2 do artisan → sai com o mesmo código, sem teste nem próximo passo.
8. Teste: monta o comando gravado (caminho completo + resto do `COMPOSE` + `--project-directory "$PWD" exec -T web php artisan mcp:start fiis`) e manda as três linhas do handshake do `tests/Feature/Modules/Mcp/StdioTest.php` (`initialize`, `notifications/initialized`, `tools/list`) por `printf … |`. A saída tem `list_favorites` → `"✅ Conexão testada: a IA enxerga as ferramentas do app."`. Não tem: se `$COMPOSE ps --status running -q web` vem vazio → `"Configurado, mas o app está parado: rode make start."`; senão → `"⚠️ Configurado, mas o teste falhou:"` e as últimas linhas da saída de erro.
9. Próximo passo: `"Feche e abra o <rótulo do cliente> para ele carregar o app."`

- [ ] **Step 1: Mudar o `mcp-config` e conferir**

Run: `make mcp-config` e `make mcp-config COMPOSE="nao-existe compose"`
Expected: `"command": "/usr/bin/docker"` (ou o caminho de `command -v docker`) no primeiro; `"command": "nao-existe"` no segundo. `make test` continua verde (`PluginManifestTest` não muda).

- [ ] **Step 2: Escrever `bin/connect` e o alvo `connect`**

- [ ] **Step 3: Conferir a sintaxe**

Run: `bash -n bin/connect` e, se houver, `shellcheck bin/connect`
Expected: sem erros.

- [ ] **Step 4: Testes manuais, com `HOME` numa pasta temporária para não tocar nos arquivos reais**

Run, com o app de dev no ar (`make start`), `export HOME=$(mktemp -d)`:
1. `make connect CLIENT=cursor` → cria `$HOME/.cursor/mcp.json`, mensagem "Gravado", "Conexão testada", "Feche e abra o Cursor".
2. De novo → "Já estava configurado", sem backup novo.
3. Editar os `args` do arquivo e rodar `make connect` no terminal, escolher Cursor, responder não → nada muda; responder sim → backup + `cp "…"` mostrado.
4. `CLIENT=cursor make connect < /dev/null` com bloco diferente → código 2 e a sugestão de rodar no terminal.
5. `echo '{"a":' > $HOME/.gemini/config/mcp_config.json; make connect CLIENT=antigravity` → código 1, arquivo igual.
6. `make connect CLIENT=claude-desktop` com `$HOME/.config/Claude/claude_desktop_config.json` contendo `{"preferences":{"x":true}}` → `preferences` mantido.
7. `make connect CLIENT=claude-code` e `make connect CLIENT=claude-code COMPOSE="podman compose"` → plugin no primeiro, `claude mcp add` no segundo.
8. `make stop`, `make connect CLIENT=cursor` com bloco diferente aceito → grava e diz "o app está parado: rode make start".
9. Pasta do repositório com espaço: um `git worktree add "/tmp/me avisa" HEAD` (com `.env` copiado) e `make connect CLIENT=json` lá → o caminho sai inteiro no JSON.
10. `make connect CLIENT=foo` e `make connect < /dev/null` → explicação de `CLIENT=` e código 1.

Expected: cada item como descrito; `~/.cursor`, `~/.config/Claude` e `~/.gemini` reais sem mudança.

- [ ] **Step 5: Commit**

```bash
git add bin/connect Makefile
git commit -m "feat(mcp): make connect writes the client config and tests the connection"
```

---

### Task 4: README (pt e en)

**Files:**
- Modify: `README.md` (seção do MCP, linhas ~475-525, e a tabela de comandos ~592), `README.en.md` (mesmos trechos, ~482-532 e ~599)

- [ ] **Step 1: Reescrever o começo da seção de ligar a IA**

Nova subseção antes de "Ligar no Claude Desktop": **"Ligar com `make connect`"** (en: "Connect with `make connect`"): rode `make connect`, escolha o cliente; o comando grava sem apagar o que existe, faz backup, testa e diz o próximo passo; `CLIENT=` para não perguntar (lista dos cinco nomes); Podman: `make connect COMPOSE="podman compose"`; Windows: manual. As subseções atuais (Desktop, Claude Code, outros clientes) ficam abaixo como o caminho manual, sob um título "Ligar à mão" (en: "Manual setup"). O TIP sobre rodar na pasta certa passa a valer para os dois comandos.

- [ ] **Step 2: Tabela de comandos**

Linha nova acima do `mcp-config`: `| make connect | Liga uma IA (Claude, Cursor, Antigravity) ao app |` / `| make connect | Connects an AI (Claude, Cursor, Antigravity) to the app |`. A linha do `mcp-config` passa a "Imprime o JSON do servidor MCP para colar à mão" / "Prints the MCP server JSON to paste by hand".

- [ ] **Step 3: Conferir**

Run: `make help` (mostra `connect`) e uma leitura dos dois README renderizados.
Expected: as duas versões dizem o mesmo; os links do sumário continuam apontando para títulos que existem.

- [ ] **Step 4: Commit**

```bash
git add README.md README.en.md
git commit -m "docs: make connect in the readme"
```
