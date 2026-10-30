# Arquitetura base: plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deixar o projeto rodando em Docker com as quatro camadas do módulo FIIs de pé e provadas por uma fatia fina, do banco até a tela.

**Architecture:** Monólito modular em Laravel com Clean Architecture pragmática em `app/Modules/Fiis/`. A fatia "listar favoritos" atravessa `Http → Application ← Infrastructure`, e a regra de preço alvo prova o `Domain` puro. Testes de arquitetura em Pest garantem as fronteiras.

**Tech Stack:** Laravel com starter kit React (Inertia, TypeScript, Tailwind v4, shadcn/ui), PHP via Docker, Pest, Pint, `brick/math`, PostgreSQL, Docker Compose.

**Spec:** `docs/superpowers/specs/2026-10-29-modulo-fiis-design.md`. Regras de código: skill `backend`.

**Fora deste plano (planos seguintes):** demais regras e antispam; brapi e CVM; agendamento real; telas; CI/CD; instalação nativa no Mac; modo de demonstração.

## Global Constraints

- Sem login, cadastro ou perfil; um usuário por instalação.
- `docker compose up -d` sobe web, scheduler, worker e Postgres, todos com `restart: unless-stopped`.
- Fila no banco, sem Redis. Fuso `America/Sao_Paulo`.
- O app publica porta só em `127.0.0.1`.
- Imagem compatível com `amd64` e `arm64`.
- Segredos só no `.env`; nunca no git nem na imagem.
- Sem `float` em comparação de regra; ausente é `null`, nunca `0`.
- Código em inglês; textos de tela em português do Brasil.

## Review Focus

- Porta publicada em todas as interfaces → o app ficaria aberto na rede sem login. Teste na Task 2.
- `.env` copiado para a imagem → vazaria o token da brapi. Teste na Task 2.
- Rotas de login e cadastro do starter kit ainda acessíveis → devem responder 404. Teste na Task 1.
- Vários contêineres rodando migrations ao mesmo tempo → só o `web` migra. Teste na Task 2.
- Preço com vírgula ou inválido virando zero → `Money` rejeita o valor. Teste na Task 3.

---

### Task 1: Esqueleto Laravel sem autenticação

**Files:**
- Create: projeto do starter kit na raiz, preservando `CLAUDE.md`, `README.md`, `docs/` e `.claude/`
- Modify: `.gitignore` (união com o do starter kit, mantendo `.env`, `.env.*`, `!.env.example`)
- Modify: `.env.example`, `routes/web.php`
- Test: `tests/Feature/NoAuthTest.php`

**Interfaces:**
- Produces: projeto Laravel com Pest e rota `GET /` que responde 200.

- [ ] **Step 1: Commitar a correção pendente da `ui-ux`.** `git add .claude/skills/ui-ux/SKILL.md && git commit -m "docs: remove tunnel usage from ui-ux skill"`. Não incluir `docs/prompt`.

- [ ] **Step 2: Gerar o projeto em pasta temporária ignorada pelo git**

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD/.scaffold:/app" composer:2 \
  create-project laravel/react-starter-kit /app --ignore-platform-reqs --no-scripts
```

Expected: `.scaffold/artisan` existe.

- [ ] **Step 3: Mover para a raiz sem sobrescrever.** Copiar `.scaffold/` para a raiz, sem substituir os arquivos preservados acima, e apagar `.scaffold/`. Manter o `.env` atual e acrescentar as chaves do `.env.example` que faltarem, sem imprimir o conteúdo. Conferir: `git check-ignore .env` responde `.env`.

- [ ] **Step 4: Escrever o teste que falha**

```php
it('has no authentication routes', function (string $path) {
    $this->get($path)->assertNotFound();
})->with(['/login', '/register', '/forgot-password', '/settings/profile']);

it('opens the app without login', function () {
    $this->get('/')->assertOk();
});
```

- [ ] **Step 5: Rodar e ver falhar.** `docker run --rm -v "$PWD:/app" -w /app composer:2 sh -c "composer install --ignore-platform-reqs && php artisan test --filter=NoAuthTest"`. Expected: FAIL em `/login`. Se a imagem não tiver as extensões, rode este passo depois da Task 2 com `docker compose run --rm web php artisan test`.

- [ ] **Step 6: Remover a autenticação.** Apagar rotas, controllers, páginas, testes e pacote de autenticação do kit, conforme o que foi instalado (conferir `composer.json`, ex. Fortify). `GET /` renderiza a página inicial do kit, sem exigir login.

- [ ] **Step 7: Rodar os testes.** Expected: PASS.

- [ ] **Step 8: Commit.** `git add -A -- . ':!docs/prompt' && git commit -m "feat: scaffold Laravel React app without authentication"`. Antes, `git status` não pode listar `.env`.

### Task 2: Docker Compose com Postgres

**Files:**
- Create: `Dockerfile`, `compose.yaml`, `.dockerignore`, `docker/entrypoint-web.sh`
- Modify: `.env.example`, `config/app.php`, `phpunit.xml` (o padrão do kit é SQLite em memória; usar `pgsql` com banco `testing`)
- Test: `tests/Feature/TimezoneTest.php`, `tests/Feature/DatabaseTest.php`, `docker/smoke-test.sh`

**Interfaces:**
- Consumes: projeto da Task 1.
- Produces: `docker compose run --rm web php artisan test`, usado por todas as tasks seguintes; `pgsql` como conexão padrão.

- [ ] **Step 1: Escrever os testes que falham**

```php
it('uses the Sao Paulo timezone', fn () => expect(config('app.timezone'))->toBe('America/Sao_Paulo'));
it('runs on Postgres', fn () => expect(DB::connection()->getDriverName())->toBe('pgsql'));
```

`docker/smoke-test.sh`, com `set -eu`, verifica:
- `docker compose config` publica a porta com `host_ip: 127.0.0.1` e nenhuma outra interface;
- a imagem construída não contém `.env`: `docker run --rm --entrypoint sh <imagem> -c 'test ! -f /app/.env'` passa, sem o volume do projeto;
- só o serviço `web` usa `entrypoint-web.sh` (`grep`) e só ele roda `migrate --force`;
- `curl -fsS http://127.0.0.1:8000/up` responde 200 depois de `docker compose up -d --wait`;
- os quatro serviços têm `restart: unless-stopped`.

- [ ] **Step 2: Rodar e ver falhar.** `sh docker/smoke-test.sh`. Expected: FAIL por falta do `compose.yaml`.

- [ ] **Step 3: Criar os arquivos.**
  - `Dockerfile` em etapas: `node:22` faz o build do front, `php:8.4-cli` com `pdo_pgsql` e `bcmath` roda o app. Usar bases oficiais multi-arquitetura.
  - `compose.yaml`: `web` (`php artisan serve --host=0.0.0.0 --port=8000`, publicado em `127.0.0.1:8000:8000`), `scheduler` (`schedule:work`), `worker` (`queue:work`), `postgres` (`postgres:17`, volume nomeado, healthcheck). `scheduler` e `worker` esperam o `web` saudável.
  - `docker/entrypoint-web.sh`: `php artisan migrate --force`, depois `exec "$@"`.
  - `.dockerignore`: `.env`, `.env.*`, `.git`, `node_modules`, `vendor`, `docs`.
  - `.env.example`: `DB_CONNECTION=pgsql`, `QUEUE_CONNECTION=database`, `APP_TIMEZONE=America/Sao_Paulo`, `BRAPI_TOKEN=`.
  - Em `.env`, ajustar as mesmas chaves sem imprimir o token.

- [ ] **Step 4: Verificar.** `sh docker/smoke-test.sh && docker compose run --rm web php artisan test`. Expected: tudo PASS.

- [ ] **Step 5: Commit.** `git commit -m "feat: run app, scheduler, worker and Postgres with Docker Compose"`.

### Task 3: Domínio puro com a regra de preço alvo

**Files:**
- Create: `app/Modules/Fiis/Domain/Money.php`, `FundSnapshot.php`, `AlertRule.php`, `RuleResult.php`, `PriceTargetRule.php`
- Test: `tests/Unit/Modules/Fiis/Domain/MoneyTest.php`, `PriceTargetRuleTest.php`, `tests/Arch/LayersTest.php`

**Interfaces:**
- Produces:
  - `Money::fromString(string $value): Money` e `isAtMost(Money $other): bool`, `__toString(): string`
  - `FundSnapshot(string $ticker, Money $price)`
  - `interface AlertRule { evaluate(FundSnapshot $fund): RuleResult }`
  - `RuleResult::matched(string $reason)`, `RuleResult::notMatched()`, `bool $matched`, `?string $reason`
  - `PriceTargetRule(Money $target) implements AlertRule`

- [ ] **Step 1: Instalar `brick/math`.** `docker compose run --rm web composer require brick/math`.

- [ ] **Step 2: Escrever os testes que falham**

```php
it('fires at or below the target', function (string $price, bool $expected) {
    $rule = new PriceTargetRule(Money::fromString('9.50'));
    $fund = new FundSnapshot('MXRF11', Money::fromString($price));
    expect($rule->evaluate($fund)->matched)->toBe($expected);
})->with([['9.49', true], ['9.50', true], ['9.51', false]]);

it('rejects invalid prices instead of becoming zero', function (string $value) {
    expect(fn () => Money::fromString($value))->toThrow(InvalidArgumentException::class);
})->with(['', 'abc', '9,50', '-1']);

it('formats as Brazilian currency', fn () =>
    expect((string) Money::fromString('9.5'))->toBe('R$ 9,50'));
```

```php
arch('domain has no framework')
    ->expect('App\Modules\Fiis\Domain')
    ->not->toUse(['Illuminate', 'Laravel', 'App\Modules\Fiis\Application', 'App\Modules\Fiis\Infrastructure', 'App\Modules\Fiis\Http']);

arch('application does not know outer layers')
    ->expect('App\Modules\Fiis\Application')
    ->not->toUse(['Illuminate', 'Laravel', 'App\Modules\Fiis\Infrastructure', 'App\Modules\Fiis\Http']);

arch('http does not know infrastructure')
    ->expect('App\Modules\Fiis\Http')
    ->not->toUse('App\Modules\Fiis\Infrastructure');
```

- [ ] **Step 3: Rodar e ver falhar.** `docker compose run --rm web php artisan test --filter=Fiis`. Expected: FAIL por classe inexistente.

- [ ] **Step 4: Implementar.** `Money` guarda `Brick\Math\BigDecimal`; aceita só `^\d+(\.\d+)?$`. A vírgula é rejeitada aqui: normalizar entrada do usuário é papel do Form Request. Classes `final readonly`. O motivo da regra usa o `__toString` de `Money`.

- [ ] **Step 5: Rodar os testes.** Expected: PASS.

- [ ] **Step 6: Commit.** `git commit -m "feat: add FIIs domain with price target rule and layer tests"`.

### Task 4: Fatia completa de favoritos até a tela

**Files:**
- Create: `app/Modules/Fiis/Application/FavoriteView.php`, `FavoriteRepository.php`, `ListFavorites.php`
- Create: `app/Modules/Fiis/Infrastructure/Persistence/FavoriteModel.php`, `EloquentFavoriteRepository.php`
- Create: `app/Modules/Fiis/Http/DashboardController.php`, `app/Modules/Fiis/FiisServiceProvider.php`
- Create: `database/migrations/*_create_favorites_table.php`, `resources/js/pages/dashboard.tsx`
- Modify: `routes/web.php`, `bootstrap/providers.php`
- Test: `tests/Unit/Modules/Fiis/Application/ListFavoritesTest.php`, `tests/Feature/Modules/Fiis/DashboardTest.php`

**Interfaces:**
- Consumes: `docker compose run --rm web php artisan test` (Task 2); `tests/Arch/LayersTest.php` (Task 3).
- Produces:
  - `FavoriteView(string $ticker)`
  - `interface FavoriteRepository { /** @return list<FavoriteView> */ all(): array }`
  - `ListFavorites(FavoriteRepository $favorites)` com `handle(): array`
  - Página Inertia `dashboard` com prop `favorites: { ticker: string }[]`

- [ ] **Step 1: Escrever os testes que falham**

```php
// ListFavoritesTest: repositório falso, sem Laravel
it('returns the favorites from the repository', function () {
    $repository = new class implements FavoriteRepository {
        public function all(): array { return [new FavoriteView('MXRF11')]; }
    };
    expect((new ListFavorites($repository))->handle())->toEqual([new FavoriteView('MXRF11')]);
});

// DashboardTest: com Postgres
it('shows saved favorites on the dashboard', function () {
    FavoriteModel::create(['ticker' => 'MXRF11']);
    $this->get('/')->assertInertia(fn ($page) => $page
        ->component('dashboard')
        ->where('favorites', [['ticker' => 'MXRF11']]));
});

it('shows an empty dashboard without favorites', function () {
    $this->get('/')->assertInertia(fn ($page) => $page->where('favorites', []));
});
```

- [ ] **Step 2: Rodar e ver falhar.** `docker compose run --rm web php artisan test --filter=Fiis`. Expected: FAIL por classe inexistente.

- [ ] **Step 3: Implementar.** Migration com `id`, `ticker` (`string(12)`, único) e timestamps. `FiisServiceProvider` liga `FavoriteRepository` a `EloquentFavoriteRepository`. O controller recebe `ListFavorites` e transforma `FavoriteView` em props. A página segue a skill `ui-ux`: título "Painel", lista de tickers e estado vazio "Nenhum favorito ainda".

- [ ] **Step 4: Verificar tudo.** `docker compose run --rm web php artisan test && docker compose run --rm web vendor/bin/pint --test && docker compose up -d --build --wait && curl -fsS http://127.0.0.1:8000/ | grep -c dashboard`. Expected: testes e Pint PASS; `grep` conta ao menos 1.

- [ ] **Step 5: Commit.** `git commit -m "feat: list favorites through all FIIs module layers"`.

### Task 5: Documentação, revisão e GitHub

**Files:**
- Modify: `README.md`, `CLAUDE.md` (seção Estado), `.claude/skills/backend/SKILL.md` (só se a estrutura real divergir)

**Interfaces:**
- Consumes: tudo acima.

- [ ] **Step 1: Atualizar docs.** README com instalação em três passos: copiar `.env.example`, preencher `BRAPI_TOKEN`, `docker compose up -d`. `CLAUDE.md` deixa de dizer "Ainda não há código".

- [ ] **Step 2: Revisar.** Rodar o subagente `code-review` sobre `main...develop` e corrigir o que for "Bloqueia".

- [ ] **Step 3: Verificar de novo e commitar.** `docker compose run --rm web php artisan test`. Expected: PASS. `git commit -m "docs: document installation and current state"`.

- [ ] **Step 4: Trocar a `develop` e a `main` no GitHub** (pedido pelo usuário para o fim). Conferir que o remoto ainda aponta para os SHAs conhecidos e que `main` local avança sem conflito:

```bash
git fetch origin develop:refs/remotes/origin/develop
git tag legado/develop 7852ae377fd7e6d4c145b2f326768097dafab4f7
git push origin legado/develop
git checkout main && git merge --ff-only develop
git push origin main
git push --force-with-lease=develop:7852ae377fd7e6d4c145b2f326768097dafab4f7 origin develop
```

Expected: `git ls-remote --heads origin` mostra `main` e `develop` no mesmo commit, e `git ls-remote --tags origin` mostra `legado/develop`. Se o SHA da `develop` remota tiver mudado, parar e avisar.
