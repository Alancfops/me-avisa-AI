# Página do fundo: plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A página do fundo ganha resumo para decidir (P/VP, médias de 6 e 12 meses, último rendimento), gráficos de preço, P/VP e rendimento estimado, e o cartão "Sobre o fundo".

**Architecture:** Contas no `Domain` (médias por janela, série diária de P/VP, rendimento estimado), leitura em `DescribeFund` e props no `FundController`, como hoje. Dados novos da CVM entram como colunas opcionais da `fund_reports`. Tela em componentes novos chamados por `funds/show.tsx`, com Recharts.

**Tech Stack:** Laravel, Inertia, Pest, `brick/math`, Postgres, React 19, Tailwind v4, Recharts.

**Spec:** `docs/superpowers/specs/2026-11-03-pagina-do-fundo-design.md`. Regras: `docs/fiis/alert.md`. Em divergência, o spec vence o plano.

## Global Constraints

- Branch `feature/pagina-do-fundo`, a partir da `docs/pagina-do-fundo`. Um commit por tarefa, Conventional Commits, sem push.
- Verificação: `make test`, `make lint`, `make check`; front com `npm run types:check`, `npm run check` e `npm run build`. Nunca `compose run web` avulso.
- Nenhuma chamada nova à brapi. Testes nunca chamam brapi nem CVM (`Http::fake`).
- O `Domain` não usa relógio nem classes de data do PHP: datas são `Y-m-d` em texto, como em `FundSnapshot` e `TradingDays`.
- Colunas novas da CVM são **opcionais** no CSV: ausentes viram `null`, nunca "mudou de formato". Ler só as colunas usadas (limite de memória, commit `a85b902`).
- Rendimento sempre rotulado "estimado", com o mês de referência, nunca a data de pagamento.
- Média de janela incompleta não é mostrada: "Juntando histórico: N de M meses".
- Textos informam e não recomendam. Paleta e `surface` de `resources/css/app.css`; WCAG 2.2 AA (skill `ui-ux`).

## Review Focus

- Fundo sem nenhum informe ou com ticker ambíguo: "Sobre o fundo" e rendimentos somem com texto de indisponível, sem erro. Teste na Task 3.
- Fundo recém-favoritado, só com 3 meses: médias de 6 e 12 meses "juntando histórico", gráfico com a parte vazia apagada, P/VP comparado com 1,0. Testes nas Tasks 2 e 3.
- Informe com DY e sem VP (ou o contrário) no mesmo mês: o mês fica fora do rendimento estimado, sem zero. Teste na Task 2.
- Sem cotação (`price` nulo): página abre, gráficos usam só fechamentos, cartões de comparação mostram "Indisponível". Teste na Task 3.
- Arquivo da CVM de ano anterior sem as colunas novas: importa VP e DY como hoje. Teste na Task 1.

---

### Task 1: Importar os dados do fundo da CVM

**Files:**
- Create: `database/migrations/2026_11_03_000000_add_profile_to_fund_reports.php`
- Modify: `app/Modules/Fiis/Infrastructure/Cvm/CvmFundReports.php`, `app/Modules/Fiis/Application/FundReportRecord.php`, `app/Modules/Fiis/Infrastructure/Persistence/{EloquentReportRepository,FundReportModel}.php`, `tests/Fixtures/cvm/{geral,complemento}.csv`
- Test: `tests/Feature/Modules/Fiis/Cvm/ImportReportsTest.php`

**Interfaces:**
- Produces: colunas `segment` (string 120), `administrator` (string 255), `started_on` (date), `net_assets` (decimal 24,2), `holders` (unsigned int), todas nulas. `FundReportRecord` ganha, no fim e com padrão `null`: `?string $segment`, `?string $administrator`, `?string $startedOn`, `?string $netAssets`, `?int $holders`.

- [ ] **Step 1:** Acrescentar às fixtures as colunas `Nome_Administrador`, `Data_Funcionamento` (geral) e `Total_Numero_Cotistas` (complemento), com valores para MXRF (`BTG PACTUAL SERVIÇOS FINANCEIROS S.A. DTVM`, `2012-08-01`, `1250000`; `Patrimonio_Liquido` `3900000000.55` na versão 2 de setembro). Salvar a geral em Latin-1, como está.
- [ ] **Step 2: Testes que falham**
  - `it('reads the fund profile from the latest version')`: setembro/MXRF tem `segment` `Títulos e Valores Mobiliários`, `administrator` o nome acima, `startedOn` `2012-08-01`, `netAssets` `3900000000.55`, `holders` `1250000`.
  - `it('imports a year whose files lack the profile columns')`: com o 2025 de cabeçalho mínimo (como o `fakeCvm` faz hoje) mais uma linha, VP e DY são gravados e os campos novos ficam `null`.
  - `it('stores the profile columns')`: depois de `ImportReports`, a linha de `fund_reports` de MXRF/2026-09 tem os cinco campos.
  - Valor inválido (`Total_Numero_Cotistas` `abc`, `Data_Funcionamento` `31/12/2010`, patrimônio negativo) vira `null` sem descartar a linha.
- [ ] **Step 3:** Rodar `make test`; esperado FAIL nos testes novos.
- [ ] **Step 4:** Implementar: colunas opcionais lidas só se existirem no cabeçalho (lista separada das obrigatórias); migration; `save` inclui os campos no `upsert`.
- [ ] **Step 5:** `make test` e `make check` passam.
- [ ] **Step 6:** Commit `feat(fiis): import fund profile from CVM reports`.

### Task 2: Contas do Domain

**Files:**
- Create: `app/Modules/Fiis/Domain/{WindowAverage,PricePoint,PriceHistory,MonthlyDividend,EstimatedDividends}.php`
- Test: `tests/Unit/Modules/Fiis/Domain/{PriceHistoryTest,EstimatedDividendsTest}.php`

**Interfaces:**
- Produces:
  - `final readonly class WindowAverage { public function __construct(public int $months, public int $monthsStored, public ?Money $average) }`; `average` é `null` se `monthsStored < months`.
  - `final readonly class PricePoint { public function __construct(public string $date, public Money $close, public ?Ratio $priceToBook) }`
  - `PriceHistory::average(array $closes, string $day, int $months): WindowAverage`: fechamentos com `date < $day` e `date >= ` `$day` menos `$months` meses (subtração em texto; `2026-08-31` − 6 = `2026-02-31` serve como limite de comparação). `monthsStored` = meses inteiros entre o fechamento mais antigo (sem limite de janela) e `$day`, no máximo `$months`.
  - `PriceHistory::points(array $closes, array $reports, string $day): list<PricePoint>`: fechamentos dos últimos 12 meses antes de `$day`, em ordem, cada um com o P/VP do informe vigente naquele dia (mesma regra de `FundSnapshot::reportInForce`; extrair para um método público estático `FundSnapshot::reportInForce` ou mover para `PriceHistory` e usar nos dois).
  - `final readonly class MonthlyDividend { public function __construct(public string $month, public Money $perShare) }`
  - `EstimatedDividends::lastTwelve(array $bookValues, array $yields, string $day): list<MonthlyDividend>`: só meses com VP e DY entregues até `$day`; `perShare` = VP × fração; as 12 competências mais recentes, da mais antiga para a mais nova.

- [ ] **Step 1: Testes que falham**
  - `it('averages the closes of the window')`: 200 fechamentos de `10.00` a partir de `2026-01-01` e `$day` `2026-10-30`, 6 meses → `average` `R$ 10,00`, `monthsStored` 6.
  - `it('does not average an incomplete window')`: 60 pregões desde `2026-08-01`, `$day` `2026-10-30`, 12 meses → `average` `null`, `monthsStored` 2.
  - `it('ignores the closes of the day and after')`.
  - `it('pairs each close with the report in force that day')`: VP 10 entregue em `2026-02-10`, VP 20 em `2026-03-15`; fechamento `20.00` em `2026-03-01` → P/VP `2,00`, em `2026-03-20` → `1,00`, em `2026-01-05` → `null`.
  - `it('keeps only the last 12 months of points')`.
  - `it('estimates dividends from yield times book value')`: VP `163.64`, fração `0.006725` → `R$ 1,10`.
  - `it('skips months missing either value and reports not yet delivered')`.
  - `it('keeps the 12 most recent months in order')`: 14 meses → 12, primeiro é o 3º mais antigo.
- [ ] **Step 2:** `make test`; esperado FAIL (classes não existem).
- [ ] **Step 3:** Implementar. `FundSnapshot` continua igual por fora; se mover `reportInForce`, os testes dele seguem passando.
- [ ] **Step 4:** `make test` e `make check` passam.
- [ ] **Step 5:** Commit `feat(fiis): compute window averages, daily P/VP and estimated dividends`.

### Task 3: Levar os dados à página

**Files:**
- Create: `app/Modules/Fiis/Application/FundProfile.php`
- Modify: `app/Modules/Fiis/Application/{ReportRepository,DescribeFund,FavoriteView}.php`, `app/Modules/Fiis/Infrastructure/Persistence/EloquentReportRepository.php`, `app/Modules/Fiis/Http/FundController.php`, `tests/Support/Fiis/InMemoryReports.php`, `resources/js/types/fiis.ts`
- Test: `tests/Feature/Modules/Fiis/FundPageTest.php`

**Interfaces:**
- Consumes: Task 1 (colunas) e Task 2 (`PriceHistory`, `EstimatedDividends`).
- Produces:
  - `final readonly class FundProfile { public function __construct(public string $month, public ?string $segment, public ?string $administrator, public ?string $startedOn, public ?string $netAssets, public ?int $holders) }`
  - `ReportRepository::profile(string $ticker): ?FundProfile`: informe de competência mais recente com algum campo de perfil; `null` se ambíguo ou sem informe.
  - `FavoriteView` ganha `?FundProfile $profile`, `WindowAverage $average6m`, `WindowAverage $average12m`, `list<PricePoint> $history`, `list<MonthlyDividend> $dividends`. O dia de referência é o da cotação; sem cotação, o de hoje (`Clock`).
  - Props (adicionadas a `fund` em `fundProps`):
    ```ts
    average6m: { months: number; monthsStored: number; value: string | null };
    average12m: { months: number; monthsStored: number; value: string | null };
    history: { date: string; close: string; priceToBook: string | null }[];
    dividends: { month: string; perShare: string }[];
    profile: { month: string; segment: string | null; administrator: string | null;
               startedOn: string | null; netAssets: string | null; holders: number | null } | null;
    ```
    Valores com `toDecimal()`. A lista do painel usa o mesmo `fundProps`: os campos novos só são montados em `show` (parâmetro ou método separado), para o painel não carregar histórico.

- [ ] **Step 1: Testes que falham** em `FundPageTest`:
  - `it('sends the fund profile from the latest report')`.
  - `it('sends a year of closes with the daily P/VP')`: 3 fechamentos e um informe → `fund.history` com 3 itens e `priceToBook` calculado nos dias depois da entrega.
  - `it('says the 12 month average is still being collected')`: 3 meses de fechamentos → `fund.average12m.value` `null`, `monthsStored` 3, `months` 12.
  - `it('sends estimated dividends per share')`.
  - `it('opens without quote, reports or history')`: `profile` `null`, `history` e `dividends` vazios, status 200.
  - `it('keeps the dashboard light')`: `/` não traz `history`.
- [ ] **Step 2:** `make test`; esperado FAIL.
- [ ] **Step 3:** Implementar, atualizando `InMemoryReports` e o tipo `Fund` em `fiis.ts`.
- [ ] **Step 4:** `make test`, `make check` e `npm run types:check` passam.
- [ ] **Step 5:** Commit `feat(fiis): send history, dividends and profile to the fund page`.

### Task 4: Tela

**Files:**
- Create: `resources/js/components/fund/{decision-summary,price-chart,price-to-book-chart,dividends-chart,fund-profile,chart-period}.tsx`, `resources/js/lib/history.ts`
- Modify: `resources/js/pages/funds/show.tsx`, `resources/js/lib/format.ts`, `package.json`
- Referência visual: `.superpowers/brainstorm/*/content/pagina-fundo-v2.html` e `docs/prototipo-lovable/routes/fundo.$ticker.tsx`.

**Interfaces:**
- Consumes: props da Task 3.
- Produces em `lib/history.ts`: `type Period = 3 | 6 | 12`; `periodStart(lastDay: string, months: Period): string`; `inPeriod(history, months)`; `periodAverage(points): number | null`; `periodChange(first: string, last: string): number`. Em `format.ts`: `compactBrl(value: string)` ("R$ 4,62 bi") e `compactNumber(value: number)` ("301 mil") com `Intl.NumberFormat('pt-BR', { notation: 'compact' })`.

- [ ] **Step 1:** `npm install recharts` (versão estável atual, fixada no `package-lock.json`).
- [ ] **Step 2: Resumo** (`decision-summary`): quatro cartões `surface`, 2 colunas no celular e 4 no `md`.
  - P/VP: valor; com `priceToBookDays < 60`, "Comparando com P/VP 1,0 até juntar histórico"; senão "Mediana 12m: X". "Mais barato"/"Mais caro" pela comparação, em `text-signal` quando abaixo.
  - Preço × média 6m e 12m: `price / value − 1` com `signedPercent`, `text-signal` quando abaixo; detalhe "Média R$ X". Janela incompleta: "—" e "Juntando histórico: N de M meses". Sem preço: "Indisponível".
  - Último rendimento: último de `dividends`, "Por cota · {mês} · estimado".
- [ ] **Step 3: Gráfico de preço**: `AreaChart` com o estilo do protótipo (degradê `--primary` 0,3→0, grade horizontal `--border`, eixos `--muted-foreground` sem linha). Eixo X numérico por data, domínio do início do período até o último fechamento; trecho antes do primeiro fechamento com `ReferenceArea` em `--muted` e "ainda sem histórico". Média do período tracejada; alvo em `--signal` só com `settings.mode === 'target'`. Botões 3m/6m/12m (`toggle-group` do projeto, `aria-pressed`), padrão 6m. Acima: variação do período com as cores do `DayChange` (`quote-info.tsx`), do primeiro fechamento do período ao preço atual. Abaixo: "Média de 30 pregões: R$ X" e, se faltar histórico, a frase de que o app guarda um fechamento por dia.
- [ ] **Step 4: Gráfico de P/VP**: `LineChart` com os mesmos períodos e o mesmo eixo, linha `--primary`, referência tracejada em 1,0. Pontos sem P/VP ficam fora.
- [ ] **Step 5: Rendimentos**: `BarChart` com os 12 meses, barras `--primary`, a última opaca e as outras com 55%. Acima: total em R$ e DY 12m. Nota de estimativa (texto do spec). Sem dados: "Sem informes da CVM para estimar."
- [ ] **Step 6: Sobre o fundo** (lateral): segmento, administrador (como a CVM grava, quebrando linha), patrimônio, cotistas, "Em funcionamento desde" (`monthLabel`), "Informe da CVM de {mês}". Sem perfil: "Sem informe da CVM para este fundo."
- [ ] **Step 7: Montar `show.tsx`**: cabeçalho como hoje, com o segmento ao lado do nome; coluna principal com resumo, preço, P/VP, rendimentos e "Situação das regras"; lateral com "Sobre o fundo", "Quando me avisar" e "Remover". No celular, "Quando me avisar" continua logo depois do preço. O cartão "Dados do fundo" sai.
- [ ] **Step 8: Acessibilidade**: cada gráfico com `role="img"` e `aria-label` resumindo o período (ex. "Preço caiu 4,1% em 6 meses, de R$ 158,90 para R$ 152,40") e uma tabela `sr-only` com os pontos mensais; contraste conferido nos dois temas.
- [ ] **Step 9:** `npm run types:check`, `npm run check`, `npm run build` e `make test` passam; abrir `http://127.0.0.1:8000/fundos/<favorito>` nos dois temas e em 375 px de largura.
- [ ] **Step 10:** Commit `feat(fiis): show charts, decision summary and fund profile`.

### Task 5: Revisão

- [ ] `code-review` na branch inteira; corrigir o que for confirmado, com commits `fix(fiis): …`.
- [ ] Rodar `make migrate` e `php artisan fiis:import-reports` no contêiner do app (`docker compose exec web`, não `run`) no app real para preencher o perfil dos favoritos e conferir a página com dados reais.
