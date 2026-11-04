# Carteira de FIIs Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Tela "Minha carteira" que guarda as cotas de cada FII e mostra o valor e o rendimento estimado por mês, por fundo e somado.

**Architecture:** Cálculo das linhas e totais no Domain (PHP puro, `Money`); `HoldingRepository` + casos de uso na Application; `PortfolioController` com Inertia; página React nova e botão no `IncomeCalculator`.

**Tech Stack:** Laravel 12, Pest, PostgreSQL, React + Inertia + TypeScript, Tailwind v4, shadcn/ui.

**Spec:** `docs/superpowers/specs/2026-11-04-carteira-de-fiis-design.md`

## Global Constraints

- Cotas: inteiro maior que zero; um fundo aparece uma vez (salvar de novo atualiza).
- Só fundos do catálogo; mensagem de erro igual à de favoritar: "Esse fundo não está no catálogo. Confira o código na B3."
- Preço: favorito usa a cotação (`MarketData::latestQuote`); não favorito usa `MarketData::latestClose`. Nunca chamar a brapi.
- Rendimento por cota: `EstimatedDividends::lastTwelve`; "último" = mês mais novo; "média" = média dos meses disponíveis (até 12).
- Falta de dado nunca vira R$ 0: fica fora do total, e o total diz quantos ficaram de fora.
- Texto fixo: "Estimativa pelo DY e pelo VP informados à CVM. O fundo pode pagar outro valor."
- Dinheiro calculado no backend com `Money`; o front só formata (`brl`).
- Ordem: maior valor de posição primeiro; sem preço no fim (entre eles, por ticker).
- Comandos pelo `make` (`make test`, `make check`); front: `npm run check`, `npm run types:check`.

## Review Focus

- Ticker em minúsculas na URL (`/carteira/knri11`) → normalizado para maiúsculas, como em `FavoriteController`.
- Cotas "1.000" ou "10,5" no formulário → "1.000" vira 1000 no front; decimal é recusado com mensagem.
- Fundo da carteira que sai do catálogo → a linha continua, com nome nulo, sem quebrar a página.
- Remover um fundo que não está na carteira → 404, sem erro 500.
- Favoritar/desfavoritar não mexe na carteira e vice-versa.

---

### Task 1: Linhas e totais no Domain

**Files:**
- Modify: `app/Modules/Fiis/Domain/Money.php` (adicionar `plus`)
- Create: `app/Modules/Fiis/Domain/PortfolioLine.php`, `app/Modules/Fiis/Domain/PortfolioTotals.php`
- Test: `tests/Unit/Modules/Fiis/Domain/PortfolioTotalsTest.php`, caso novo em `MoneyTest.php` se existir

**Interfaces:**
- Produces:
  - `Money::plus(Money $other): Money`
  - `final readonly class PortfolioLine(string $ticker, int $shares, ?Money $price, list<MonthlyDividend> $dividends)` com `positionValue(): ?Money`, `lastIncome(): ?Money`, `averageIncome(): ?Money`, `dividendMonths(): int`
  - `final readonly class PortfolioTotals(?Money $value, ?Money $lastIncome, ?Money $averageIncome, int $withoutPrice, int $withoutDividends)` com `static of(list<PortfolioLine> $lines): self`

- [ ] **Step 1: Testes que falham** em `PortfolioTotalsTest.php`:
  - `it('multiplies price and dividends by the shares')`: 100 cotas, preço 160.00, dividendos [0.90, 1.10] → `positionValue` 16000.00, `lastIncome` 110.00, `averageIncome` 100.00, `dividendMonths` 2.
  - `it('has no value without price and no income without dividends')`: preço null e `[]` → os três `null`, `dividendMonths` 0.
  - `it('sums only the lines with data and counts the rest')`: linhas A (preço 10, div [0.10], 10 cotas), B (sem preço, div [0.20], 5 cotas), C (preço 20, sem dividendos, 1 cota) → `value` 120.00, `lastIncome` 2.00, `averageIncome` 2.00, `withoutPrice` 1, `withoutDividends` 1.
  - `it('has null totals for an empty portfolio')`: `of([])` → valores `null`, contadores 0.
- [ ] **Step 2:** `make test` → FAIL (classes não existem).
- [ ] **Step 3:** Implementar `Money::plus` e as duas classes. Totais `null` quando nenhuma linha tem o dado. Use `Money::average` para a média e `times((string) $shares)` para multiplicar.
- [ ] **Step 4:** `make test` → PASS; `make check` → PASS.
- [ ] **Step 5:** Commit `feat(fiis): portfolio lines and totals`.

### Task 2: Banco, repositório e casos de uso

**Files:**
- Create: `database/migrations/2026_11_04_000000_create_holdings_table.php` (`id`, `ticker` string 12 unique, `shares` unsignedInteger, timestamps)
- Create: `app/Modules/Fiis/Application/HoldingRepository.php`, `SaveHolding.php`, `RemoveHolding.php`, `ShowPortfolio.php`, `PortfolioRow.php`, `PortfolioView.php`
- Create: `app/Modules/Fiis/Infrastructure/Persistence/HoldingModel.php`, `EloquentHoldingRepository.php`
- Create: `tests/Support/Fiis/InMemoryHoldings.php`
- Modify: `app/Modules/Fiis/FiisServiceProvider.php` (binding)
- Test: `tests/Unit/Modules/Fiis/Application/ShowPortfolioTest.php`, `tests/Feature/Modules/Fiis/HoldingRepositoryTest.php`

**Interfaces:**
- Consumes: `PortfolioLine`, `PortfolioTotals` (Task 1); `FavoriteRepository::exists`, `MarketData::latestQuote|latestClose`, `ReportRepository::bookValues|monthlyYields`, `CatalogRepository::nameOf`, `Clock`, `MarketTime::day`.
- Produces:
  - `interface HoldingRepository { /** @return array<string,int> ticker => cotas, por ticker */ all(): array; shares(string $ticker): ?int; save(string $ticker, int $shares): void; remove(string $ticker): bool; }` (`remove` devolve `false` se não existia)
  - `SaveHolding::handle(string $ticker, int $shares): void`, `RemoveHolding::handle(string $ticker): bool`
  - `final readonly class PortfolioRow(PortfolioLine $line, ?string $name, ?Quote $quote, ?DailyClose $close)`
  - `final readonly class PortfolioView(list<PortfolioRow> $rows, PortfolioTotals $totals)`
  - `ShowPortfolio::handle(): PortfolioView` — linhas já na ordem da spec.

- [ ] **Step 1: Testes que falham:**
  - `HoldingRepositoryTest`: salva, atualiza a mesma linha (continua 1 registro), `shares` devolve null para ausente, `remove` devolve true/false.
  - `ShowPortfolioTest` (com os fakes `InMemory*`): favorito usa a cotação e não favorito o fechamento (`quote`/`close` preenchidos no `PortfolioRow` certo); ordem por valor de posição com sem preço no fim; ticker fora do catálogo → `name` null; carteira vazia → `rows` [] e totais nulos.
- [ ] **Step 2:** `make test` → FAIL.
- [ ] **Step 3:** Implementar. Em `ShowPortfolio`, o dia é `MarketTime::day($this->clock->now())`; dividendos por `EstimatedDividends::lastTwelve($reports->bookValues($t), $reports->monthlyYields($t), $day)`. Não carregar histórico de preços.
- [ ] **Step 4:** `make test` → PASS; `make check` → PASS (inclui `tests/Arch`).
- [ ] **Step 5:** Commit `feat(fiis): store holdings and build the portfolio`.

### Task 3: Rotas e controller

**Files:**
- Create: `app/Modules/Fiis/Http/PortfolioController.php`, `app/Modules/Fiis/Http/Requests/SaveHoldingRequest.php`
- Modify: `routes/web.php`; `app/Modules/Fiis/Http/FundController.php` (prop `holding`)
- Test: `tests/Feature/Modules/Fiis/PortfolioTest.php`; caso novo em `FundPageTest.php`

**Interfaces:**
- Consumes: Task 2.
- Produces (Inertia `portfolio`):
  - `rows`: `{ticker, name, shares, price: string|null, priceNote: string|null, positionValue, lastIncome, averageIncome: string|null, dividendMonths: int}`; `priceNote` como na página do fundo ("cotação de HH:MM" ou "fechamento de DD/MM (B3)").
  - `totals`: `{value, lastIncome, averageIncome: string|null, withoutPrice: int, withoutDividends: int}`
  - `search`: mesmo formato do `DashboardController` (`query`, `catalogEmpty`, `results`), com `inPortfolio: bool` no lugar de `favorite`.
  - Na página do fundo: `holding: int|null` (cotas salvas).
- Rotas: `GET carteira` (`portfolio`), `PUT carteira/{ticker}` (`portfolio.save`, body `shares`, `stay` opcional), `DELETE carteira/{ticker}` (`portfolio.remove`). Ticker em maiúsculas. Depois de salvar: `back()` se `stay`, senão `portfolio`.

- [ ] **Step 1: Testes que falham** em `PortfolioTest.php`: página vazia; página com linhas e totais (valores como strings decimais `toDecimal()`); salvar e atualizar; validação com dataset `0`, `-1`, `1.5`, `'abc'`, vazio → erro em `shares` "Informe um número inteiro de cotas, como 100."; ticker fora do catálogo → erro em `ticker` com a mensagem global; `/carteira/knri11` salva `KNRI11`; DELETE de ausente → 404; favoritar não cria linha na carteira. Em `FundPageTest`: `holding` é null e depois 100.
- [ ] **Step 2:** `make test` → FAIL.
- [ ] **Step 3:** Implementar. `SaveHoldingRequest` segue `AddFavoriteRequest` (ticker vem da rota, validar com o catálogo; `shares` `required|integer|min:1`).
- [ ] **Step 4:** `make test` → PASS; `make check` → PASS.
- [ ] **Step 5:** Commit `feat(fiis): portfolio routes`.

### Task 4: Telas

**Files:**
- Create: `resources/js/pages/portfolio.tsx`, `resources/js/components/portfolio/add-holding-dialog.tsx`, `resources/js/components/portfolio/holding-row.tsx`
- Modify: `resources/js/components/app-sidebar.tsx` (item "Minha carteira", `href: '/carteira'`, ícone `Wallet`, terceiro no grupo FIIs); `resources/js/components/fund/income-calculator.tsx`; `resources/js/pages/funds/show.tsx` (passa `holding`); `resources/js/types/fiis.ts`

**Interfaces:**
- Consumes: props da Task 3.
- `IncomeCalculator` ganha `ticker: string` e `holding: number | null`: campo começa com `holding`; botão "Salvar na carteira" (ou "Atualizar na carteira" se `holding !== null`) faz `router.put('/carteira/{ticker}', {shares, stay: true}, {preserveScroll: true})` com toast de sucesso/erro como `FavoriteButton`. Desabilitado enquanto a quantidade for inválida.

- [ ] **Step 1:** Página: título "Minha carteira"; topo com valor total e rendimento estimado do mês (último e média); aviso de quantos ficaram fora ("2 fundos sem rendimento informado ficaram fora do total", singular/plural); texto fixo da estimativa; tabela com ticker (link para `/fundos/{ticker}`), nome, cotas, preço + `priceNote`, valor, rendimentos ("sem preço"/"sem rendimento informado" quando nulo); por linha, editar cotas (campo + salvar) e remover com confirmação (`modal.tsx` ou `dialog`, como `remove-favorite.tsx`); estado vazio com `empty-state.tsx` e o botão "Adicionar FII".
- [ ] **Step 2:** `AddHoldingDialog`: busca via `router.get('/carteira', {q})` como `favorite-dialog.tsx`, escolha do fundo, campo de cotas, salva com `router.put`.
- [ ] **Step 3:** Botão no `IncomeCalculator` e item no menu.
- [ ] **Step 4:** `npm run check`, `npm run types:check`, `npm run build` → PASS; `make start` e abrir `/carteira` e a página de um fundo: salvar, editar, remover, menu recolhido.
- [ ] **Step 5:** Commit `feat(fiis): portfolio screen`.
