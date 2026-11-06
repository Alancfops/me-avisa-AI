# Lista de FIIs, fechamentos da B3 e preço teto: plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Página com todos os FIIs (P/VP, DY, tipo, filtros, favoritar), fechamentos diários e 12 meses de histórico pela B3, página do fundo para não favoritos e preço teto pelo rendimento.

**Architecture:** Nova fonte `B3` em `Infrastructure/B3` atrás de uma interface de `Application`, gravando em `daily_prices`. Composição da carteira entra no `ImportReports` existente como colunas de `fund_reports`; o tipo é classificado no `Domain`. Lista montada por um caso de uso novo e filtrada no front.

**Tech Stack:** Laravel, Inertia, Pest, `brick/math`, Postgres, React 19, Tailwind v4, Recharts.

**Spec:** `docs/superpowers/specs/2026-11-03-lista-de-fiis-design.md`. Base: `2026-11-03-pagina-do-fundo-design.md` e `docs/fiis/alert.md`. Em divergência, o spec vence o plano.

## Global Constraints

- Branch `feature/lista-de-fiis` (a partir da `feature/pagina-do-fundo`). Um commit por tarefa, Conventional Commits, sem push.
- Verificação: `make test`, `make lint`, `make check`; front com `npm run types:check`, `npm run check`, `npm run build`. Nunca `compose run web` avulso.
- **Nenhuma requisição da brapi para fundos que não são favoritos.** Testes nunca chamam brapi, CVM nem B3 (`Http::fake`).
- B3: `https://bvmf.bmfbovespa.com.br/InstDados/SerHist/COTAHIST_D{DDMMAAAA}.ZIP` e `COTAHIST_A{AAAA}.ZIP`; Latin-1, posicional; registro `01`; data 3–10; `CODBDI` 11–12 = `12`; ticker 13–24; mercado 25–27 = `010`; `PREULT` 109–121 com 2 casas implícitas. URL base em `config/services.php` (`services.b3.history_url`).
- Arquivos grandes lidos em stream, linha a linha (limite de memória, como `CvmFundReports`).
- Preço de fechamento sempre com a data ("fechamento de 30/10"). Textos informam e não recomendam. WCAG 2.2 AA, tokens de `resources/css/app.css`.
- Corte de base: preço < R$ 50 = Base 10; ≥ R$ 50 = Base 100. Tipo: > 50% de `Total_Investido`.

## Review Focus

- Dia sem arquivo da B3 (feriado, fim de semana, ainda não publicado → 404): não é erro na Saúde; é tentado de novo na rodada seguinte. Teste na Task 1.
- Fundo do catálogo sem informe ou com ticker ambíguo: aparece na lista com "—" e "Sem dados", ordenado no fim. Teste na Task 4.
- Linha da B3 com ticker fora do catálogo, mercado fracionário (`020`) ou preço zero: ignorada. Teste na Task 1.
- Fundo não favorito: a página abre com o último fechamento e sem regras; nenhuma chamada à brapi. Teste na Task 4.
- Preço teto com menos de 12 meses de rendimento: diz quantos faltam, sem valor. Coberto na Task 5 (lógica em `lib`, conferida por tipos e no navegador).

---

### Task 1: Fechamentos diários da B3

**Files:**
- Create: `app/Modules/Fiis/Application/{ExchangeCloses,ExchangeClose,ImportExchangeCloses}.php`, `app/Modules/Fiis/Infrastructure/B3/B3ExchangeCloses.php`, `app/Modules/Fiis/Infrastructure/Console/ImportExchangeClosesCommand.php`, `tests/Fixtures/b3/COTAHIST_D30102026.TXT`
- Modify: `app/Modules/Fiis/Application/Source.php` (caso `B3 = 'b3'`), `config/services.php`, `routes/console.php`, binding no service provider do módulo, `HealthController`/tela de Saúde se listarem fontes fixas
- Test: `tests/Feature/Modules/Fiis/B3/B3ExchangeClosesTest.php`, `tests/Unit/Modules/Fiis/Application/ImportExchangeClosesTest.php`

**Interfaces:**
- Produces:
  - `interface ExchangeCloses { /** @return iterable<ExchangeClose> */ public function forDay(string $day): iterable; /** @return iterable<ExchangeClose> em stream (generator) */ public function forYear(int $year): iterable; }` com `final readonly class ExchangeClose { __construct(public string $ticker, public DailyClose $close) }` em `Application` — lança `SourceUnavailable` em falha de rede ou arquivo quebrado; devolve vazio em 404.
  - `ImportExchangeCloses::handle(): void` — para cada dia útil (`TradingDays`) dos últimos 10 sem fechamento da B3 gravado para nenhum ticker do catálogo, chama `forDay`, filtra pelo catálogo e grava com `MarketData::saveCloses` (upsert). Registra sucesso/falha em `SourceLog` com `Source::B3`.
  - Comando `fiis:import-exchange-closes`, agendado `weekdays()->dailyAt('20:30')` e `dailyAt('08:00')` no fuso `MarketTime::ZONE`.

- [ ] **Step 1:** Fixture com ~8 linhas reais do arquivo de 30/10 (cabeçalho `00`, KNRI11 150,50, MXRF11 8,88, GARE11 8,20, um FII no mercado `020`, uma ação `CODBDI` 02, um FII fora do catálogo, um com `PREULT` zero, trailer `99`), em Latin-1, zipada no teste como `cvmZip` faz.
- [ ] **Step 2: Testes que falham**
  - `it('reads FII closes from the daily file')`: `forDay('2026-10-30')` traz KNRI11 `150.5`, MXRF11 `8.88`, GARE11 `8.2`, nada de ação nem de `020`, nada com zero.
  - `it('returns nothing when the file does not exist yet')`: 404 → vazio, sem exceção.
  - `it('fails on a broken zip')`: `SourceUnavailable`.
  - `it('imports missing business days for catalog funds only')` (unit, fakes): com `Clock` em `2026-11-03 21:00`, chama `forDay` para os dias úteis sem fechamento e grava só tickers do catálogo.
  - `it('does not refetch a day already imported')`.
  - `it('keeps going when one day fails')` e registra falha em `SourceLog`.
- [ ] **Step 3:** `make test`; esperado FAIL.
- [ ] **Step 4:** Implementar (stream do zip, `substr` pelas posições, `Money::fromString` do `PREULT` / 100).
- [ ] **Step 5:** `make test`, `make lint`, `make check` passam.
- [ ] **Step 6:** Commit `feat(fiis): import daily closes of all FIIs from B3`.

### Task 2: Primeira carga com 12 meses

**Files:**
- Create: `app/Modules/Fiis/Application/ImportExchangeHistory.php`, `app/Modules/Fiis/Infrastructure/Console/ImportExchangeHistoryCommand.php`
- Modify: `app/Modules/Fiis/Infrastructure/B3/B3ExchangeCloses.php` (`forYear`), `routes/console.php`
- Test: `tests/Feature/Modules/Fiis/B3/B3ExchangeClosesTest.php`, `tests/Unit/Modules/Fiis/Application/ImportExchangeHistoryTest.php`

**Interfaces:**
- Consumes: `ExchangeCloses`, `MarketData::saveCloses`, `CatalogRepository`.
- Produces: `ImportExchangeHistory::handle(bool $force = false): void` — se `$force` ou se não há fechamento com mais de 300 dias para nenhum ticker do catálogo, importa `forYear(ano − 1)` e `forYear(ano)`, gravando em lotes por ticker (nunca o ano inteiro em memória). Comando `fiis:import-exchange-history {--force}`, agendado `dailyAt('21:00')` (sai rápido quando já carregado).

- [ ] **Step 1: Testes que falham**
  - `it('streams a yearly file')`: zip anual de fixture com duas datas → fechamentos das duas.
  - `it('loads two years once')` e `it('skips when history already exists')`, `it('reloads with force')`.
  - Teste de memória: arquivo de fixture com 20.000 linhas geradas, `memory_get_peak_usage` não cresce mais que 20 MB durante `forYear` (mesma técnica do commit `a85b902`, se houver; senão, este limite).
- [ ] **Step 2:** `make test`; esperado FAIL.
- [ ] **Step 3:** Implementar.
- [ ] **Step 4:** `make test`, `make lint`, `make check` passam.
- [ ] **Step 5:** Commit `feat(fiis): load a year of B3 closes on first run`.

### Task 3: Tipo do fundo pela carteira

**Files:**
- Create: `database/migrations/2026_11_03_100000_add_portfolio_to_fund_reports.php`, `app/Modules/Fiis/Domain/{FundKind,Portfolio}.php`
- Modify: `app/Modules/Fiis/Infrastructure/Cvm/CvmFundReports.php`, `app/Modules/Fiis/Application/FundReportRecord.php`, `app/Modules/Fiis/Infrastructure/Persistence/{EloquentReportRepository,FundReportModel}.php`, `app/Modules/Fiis/Application/ReportRepository.php`, `tests/Support/Fiis/InMemoryReports.php`, `tests/Fixtures/cvm/` (novo `ativo_passivo.csv`)
- Test: `tests/Unit/Modules/Fiis/Domain/FundKindTest.php`, `tests/Feature/Modules/Fiis/Cvm/ImportReportsTest.php`

**Interfaces:**
- Produces:
  - Colunas nulas em `fund_reports`: `total_invested`, `real_estate`, `receivables` (soma de `CRI`, `CRI_CRA`, `LCI`, `LCI_LCA`, `Letras_Hipotecarias`, `LIG`), `fii_shares` — `decimal(24,2)`.
  - `enum FundKind: string { case Brick = 'tijolo'; case Paper = 'papel'; case FundOfFunds = 'fundo_de_fundos'; case Hybrid = 'hibrido'; }`
  - `final readonly class Portfolio { __construct(public string $totalInvested, public string $realEstate, public string $receivables, public string $fiiShares) ; public function kind(): ?FundKind }` — `null` quando total ≤ 0; > 50% decide; senão `Hybrid`.
  - `ReportRepository::kinds(): array<string, FundKind>` (ticker → tipo, competência mais recente com composição, maior versão, sem ambíguos) e `kindOf(string $ticker): ?FundKind`.
  - O arquivo `ativo_passivo` é opcional no zip (ausente → colunas nulas, nunca "mudou de formato").

- [ ] **Step 1: Testes que falham**
  - `FundKindTest`: KNRI11 set/26 (`4670807419.04` total, `4203284231.19` imóveis, `389643777.26` CRI_CRA) → `Brick`; MXRF11 (`5176421015.19`, `287878537.64`, `3892924050.06`, FII `718369408.35`) → `Paper`; FII 60% → `FundOfFunds`; 40/40/20 → `Hybrid`; total 0 → `null`.
  - `ImportReportsTest`: composição gravada na maior versão; zip sem `ativo_passivo` importa VP e DY e deixa nulo; `kinds()` ignora ticker ambíguo.
- [ ] **Step 2:** `make test`; esperado FAIL.
- [ ] **Step 3:** Implementar (somas com `BigDecimal`, só as colunas usadas).
- [ ] **Step 4:** `make test`, `make lint`, `make check` passam.
- [ ] **Step 5:** Commit `feat(fiis): classify funds as brick, paper, fund of funds or hybrid`.

### Task 4: Lista e página de qualquer fundo (backend)

**Files:**
- Create: `app/Modules/Fiis/Application/{ListFunds,FundListItem}.php`, `app/Modules/Fiis/Http/FundListController.php`
- Modify: `app/Modules/Fiis/Application/DescribeFund.php`, `FavoriteView.php`, `app/Modules/Fiis/Http/{FundController,FavoriteController}.php`, `app/Modules/Fiis/Http/Requests/AddFavoriteRequest.php`, `app/Modules/Fiis/Application/{MarketData,CatalogRepository}.php` e implementações/fakes, `routes/web.php`, `resources/js/types/fiis.ts`
- Test: `tests/Feature/Modules/Fiis/FundListTest.php`, `tests/Feature/Modules/Fiis/FundPageTest.php`, `tests/Feature/Modules/Fiis/FavoritesTest.php`

**Interfaces:**
- Consumes: Tasks 1–3 (`daily_prices` de todos, `ReportRepository::kinds()`), `FundSnapshot::reportInForce`, `DividendYield::trailing12Months`, `ReportRepository::profile`.
- Produces:
  - `MarketData::latestCloses(int $tradingDays = 10): array<string, DailyClose>` (último fechamento por ticker, só quem negociou nos últimos 10 dias úteis, numa consulta).
  - `CatalogRepository::all(): list<CatalogEntry>`.
  - `final readonly class FundListItem { ticker, name, ?FundKind kind, ?string segment, ?Money close, ?string closeDay, ?Ratio priceToBook, ?string dividendYield12m, ?string netAssets, bool favorite }`; `ListFunds::handle(): list<FundListItem>` sem N+1 (VP, DY, perfil e tipo carregados em lote).
  - Rota `GET /fundos` → `funds/index` com `funds` (lista), `closesOn` (dia mais recente). Props por item: `{ ticker, name, kind, segment, close, closeDay, priceToBook, dividendYield12m, netAssets, favorite }`.
  - `DescribeFund::handle` aceita fundo do catálogo não favorito: `settings` e `rules` nulos/vazios; sem cotação de favorito, `price` = último fechamento e `priceSource` = `'close'` com `closeDay`. `FundController::show` envia `favorite: bool`, `settings: null` para não favoritos; 404 só fora do catálogo.
  - `AddFavoriteRequest` aceita `stay` (bool opcional); com `stay`, `FavoriteController::store` responde `redirect()->back()`.

- [ ] **Step 1: Testes que falham**
  - `FundListTest`: lista com P/VP do fechamento ÷ VP vigente, DY 12m, tipo, `favorite` true para favoritos; fundo sem informe com nulos; fundo sem fechamento recente fora; ticker ambíguo sem P/VP e sem tipo; `closesOn` correto; número de consultas constante com 3 e com 30 fundos (`DB::enableQueryLog`).
  - `FundPageTest`: não favorito abre com `favorite` false, `settings` null, `rules` vazio, preço do fechamento com `closeDay`, e `Http::assertNothingSent()`; ticker fora do catálogo → 404.
  - `FavoritesTest`: `stay=true` volta para a página anterior.
- [ ] **Step 2:** `make test`; esperado FAIL.
- [ ] **Step 3:** Implementar e atualizar `fiis.ts` (`FundListItem`, `favorite`, `priceSource`, `closeDay`, `settings: Settings | null`).
- [ ] **Step 4:** `make test`, `make lint`, `make check`, `npm run types:check` passam.
- [ ] **Step 5:** Commit `feat(fiis): list all funds and open the page of any catalog fund`.

### Task 5: Telas

**Files:**
- Create: `resources/js/pages/funds/index.tsx`, `resources/js/components/fund/{fund-list-filters,fund-list-table,favorite-button,ceiling-price}.tsx`, `resources/js/lib/fund-list.ts`
- Modify: `resources/js/components/app-sidebar.tsx`, `resources/js/pages/funds/show.tsx`, `resources/js/components/when-to-alert.tsx` (receber alvo sugerido)
- Referência visual: `.superpowers/brainstorm/*/content/lista-fiis.html`.

**Interfaces:**
- Consumes: props da Task 4 e `dividends` da página do fundo.
- Produces em `lib/fund-list.ts`: `type KindFilter = 'all' | FundKind`; `type BaseFilter = 'all' | 'base10' | 'base100'`; `type SortKey = 'priceToBook' | 'dividendYield' | 'ticker' | 'netAssets'`; `filterFunds(funds, { query, kind, base }): FundListItem[]` (base pelo `close`: < 50 / ≥ 50; sem `close` fora dos dois); `sortFunds(funds, key)` (nulos no fim); `ceilingPrice(dividends, annualRate): { value: number } | { missingMonths: number }`.

- [ ] **Step 1: Página "Todos os FIIs"** conforme o rascunho: título, "{N} fundos negociados · preços do fechamento de {data} (B3) · VP e DY dos informes da CVM"; filtros em `surface` (busca, tipo com `ToggleGroup`, base, ordenar com `select` do projeto), padrão "Menor P/VP"; tabela com ticker (link para `/fundos/{ticker}`), nome, tipo (chip), segmento, preço, P/VP (`text-signal` abaixo de 1,0), DY 12m, botão favoritar; tipo e segmento escondidos abaixo de `md`; "Mostrando N de M"; estado vazio do filtro ("Nenhum fundo com esses filtros.") e do catálogo. Filtros guardados na URL (query string) para o voltar do navegador manter a busca.
- [ ] **Step 2: `favorite-button`**: `POST /favoritos` com `stay: true`, estado de carregando, `aria-pressed`, texto "Favoritar"/"Favorito"; usado na lista e na página do fundo não favorito.
- [ ] **Step 3: Menu**: item "Todos os FIIs" (`/fundos`) logo depois de "Painel".
- [ ] **Step 4: Página do fundo não favorito**: cabeçalho com o fechamento e "Fechamento de {dd/mm} (B3)"; sem "Quando me avisar", "Situação das regras" e "Remover"; `favorite-button` na lateral. Cartões e gráficos como hoje.
- [ ] **Step 5: `ceiling-price`** (lateral, todos os fundos): campo "Quero render (% ao ano)" (padrão 10, guardado em `localStorage` com try/catch), "Preço teto: R$ X" e "R$ Y acima/abaixo do preço atual"; com menos de 12 meses, "Faltam N meses de rendimento para calcular". Em favoritos, botão "Usar como alvo" preenche o alvo em "Quando me avisar" no modo alvo, sem salvar.
- [ ] **Step 6: Acessibilidade**: tabela com `caption` sr-only, cabeçalhos com `scope`, ordenação anunciada (`aria-sort` no cabeçalho ativo), contagem de resultados em `aria-live="polite"`, nomes acessíveis começando pelo texto visível; contraste nos dois temas.
- [ ] **Step 7:** `npm run types:check`, `npm run check`, `npm run build`, `make test` passam; `make start` e conferir `/fundos`, um não favorito e um favorito nos dois temas.
- [ ] **Step 8:** Commit `feat(fiis): add the all-funds page, favorite from the list and ceiling price`.

### Task 6: Dados reais e revisão

- [ ] `make migrate`; `docker compose exec web php artisan fiis:import-reports`, `fiis:import-exchange-history` e `fiis:import-exchange-closes`; conferir `/fundos` com dados reais (KNRI11 tijolo, MXRF11 papel).
- [ ] `code-review` da branch inteira; corrigir o confirmado com `fix(fiis): …`.
