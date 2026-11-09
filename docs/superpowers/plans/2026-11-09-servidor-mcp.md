# Servidor MCP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Servidor MCP local (stdio) que deixa a pessoa consultar e gerenciar favoritos, alertas e carteira de FIIs conversando com o Claude Desktop ou o Claude Code.

**Architecture:** O que é regra (variações de 7 e 30 dias, trava de preço, mudança de cotas, validação compartilhada) entra no módulo de FIIs (Domain e Application). O módulo novo `app/Modules/Mcp/` é só um canal de entrada, como o `Telegram`: um `Server` do `laravel/mcp` com dez ferramentas que chamam os casos de uso de FIIs e formatam a resposta em português. Plugin do Claude Code no próprio repositório.

**Tech Stack:** Laravel 13, `laravel/mcp` ^1.1 (v1.1.2), Pest, PostgreSQL, Docker Compose.

**Spec:** `docs/superpowers/specs/2026-11-09-servidor-mcp-design.md`

## Global Constraints

- Só stdio: `Mcp::local('fiis', FiisServer::class)` em `routes/ai.php`. **Nenhum** `Mcp::web(...)` nesta versão.
- Comando: `docker compose -f <pasta>/compose.yaml exec -T web php artisan mcp:start fiis`. A saída padrão do processo tem só JSON-RPC.
- `app/Modules/Mcp` não usa `App\Modules\Fiis\Infrastructure`, `App\Modules\Fiis\Http` nem `Illuminate\Database`. `App\Modules\Fiis` não usa `App\Modules\Mcp`.
- Consultar não chama a brapi. Favoritar usa `AddFavorite`, como o site.
- O MCP não calcula nada que o site já calcula. Números novos (variações de 7 e 30 dias, distância da trava) são calculados no módulo de FIIs.
- Texto em português; datas e horas em `America/Sao_Paulo` (`MarketTime`), data sempre junto da hora: `06/11 17:00`. Dinheiro com `(string) Money` (`R$ 1.234,50`).
- Nada de "oportunidade", "barato", "compre" em textos do app.
- Trava: limite de **30%**, exclusivo (30,00% salva; 30,01% trava), para cima ou para baixo, sobre alvo, mínimo e máximo. Parâmetro `confirmado`.
- Toda alteração bem-sucedida começa com `✅`. Toda resposta que não alterou nada contém `Nada foi alterado.`
- Ticker de qualquer ferramenta: `strtoupper(trim(...))`.
- Comandos pelo `make` (`make test`, `make check`). Para o Composer: `make shell` e `composer require ...` dentro dele.

## Review Focus

- Ticker em minúsculas ou com espaço (`" knri11 "`) → tratado como `KNRI11` em todas as ferramentas (teste na Task 8 e na Task 9).
- Modelo manda o preço como texto com vírgula (`"9,50"`), apesar do schema `number` → aceito como 9,50; `"1.234,50"` recusado (teste na Task 3 e na Task 9).
- Sem favoritos ou carteira vazia → frase amigável ("Você ainda não tem favoritos."), não JSON vazio (teste na Task 8).
- Favorito recém-adicionado sem cotação e sem fechamentos → campos "sem dado", sem exceção (teste na Task 2 e na Task 8).
- Fundo fora do catálogo em "ver favorito", "favoritar" ou "definir cotas" → erro legível, nada gravado (teste na Task 4 e na Task 9).

---

### Task 1: Variação desde N dias (Domain)

**Files:**
- Create: `app/Modules/Fiis/Domain/PriceChange.php`
- Test: `tests/Unit/Modules/Fiis/Domain/PriceChangeTest.php`

**Interfaces:**
- Produces: `PriceChange::since(array $closes, Money $current, string $day, int $calendarDays): ?Percentage` — `$closes` é `list<DailyClose>` em ordem crescente; base = último fechamento com `date <= $day - $calendarDays`; `null` se não houver, ou se essa base for mais de `PriceHistory::WINDOW_START_TOLERANCE_DAYS` (5) dias anterior à data-alvo. Usa `Percentage::change($base, $current)`.

- [ ] **Step 1: Write the failing tests**

```php
it('compares with the close on the target day', function () {
    $closes = [new DailyClose('2026-10-30', Money::fromString('10.00')), new DailyClose('2026-11-05', Money::fromString('10.50'))];
    expect(PriceChange::since($closes, Money::fromString('11.00'), '2026-11-06', 7)->toDecimal())->toBe('10');
});

it('uses the previous session when the target day had none', function () {
    // 2026-11-01 é domingo: vale o fechamento de sexta, 30/10.
    $closes = [new DailyClose('2026-10-30', Money::fromString('8.00'))];
    expect(PriceChange::since($closes, Money::fromString('7.60'), '2026-11-08', 7)->toDecimal())->toBe('-5');
});

it('has no change without a close on or before the target day', function () {
    $closes = [new DailyClose('2026-11-05', Money::fromString('10.00'))];
    expect(PriceChange::since($closes, Money::fromString('10.00'), '2026-11-06', 30))->toBeNull();
});

it('has no change when the nearest close is more than 5 days before the target day', function () {
    $closes = [new DailyClose('2026-09-20', Money::fromString('10.00'))];
    expect(PriceChange::since($closes, Money::fromString('11.00'), '2026-11-06', 30))->toBeNull();
});
```

- [ ] **Step 2: Run** `make test` filtrando `PriceChangeTest`. Expected: FAIL, classe não existe.
- [ ] **Step 3: Implement `PriceChange::since`** sem `DateTime` (o teste de arquitetura proíbe no Domain): use `TradingDays::calendarDaysBetween` e a aritmética de datas `Y-m-d` que `PriceHistory` já usa.
- [ ] **Step 4: Run** `make test` filtrando `PriceChangeTest` e `LayersTest`. Expected: PASS.
- [ ] **Step 5: Commit** `feat(fiis): price change since N calendar days`

### Task 2: Resumo dos favoritos (Application)

**Files:**
- Create: `app/Modules/Fiis/Application/FavoriteSummary.php`, `app/Modules/Fiis/Application/SummarizeFavorites.php`
- Modify: `app/Modules/Fiis/Application/AlertRepository.php` (`latest`), sua implementação Eloquent em `app/Modules/Fiis/Infrastructure/`, `app/Modules/Fiis/Application/ListAlerts.php`, `tests/Support/Fiis/InMemoryAlerts.php`
- Test: `tests/Unit/Modules/Fiis/Application/SummarizeFavoritesTest.php`, caso novo no teste do repositório de avisos (Feature)

**Interfaces:**
- Consumes: `PriceChange::since` (Task 1), `DescribeFund::handle`, `MarketData::closesBefore`, `ReportRepository::bookValues/monthlyYields`, `EstimatedDividends::lastTwelve`, `Clock`.
- Produces:
  - `AlertRepository::latest(int $limit, ?string $ticker = null): array` (`list<Alert>`, mais novo primeiro). `ListAlerts::handle(?string $ticker = null, int $limit = 200): array`.
  - `final readonly class FavoriteSummary { FavoriteView $view; ?Percentage $change7d; ?Percentage $change30d; ?MonthlyDividend $lastDividend; list<Alert> $alertsToday; }` — `$alertsToday` são os avisos do fundo com `MarketTime::day($alert->occurredAt)` igual ao dia de hoje.
  - `SummarizeFavorites::all(): list<FavoriteSummary>` (lista leve, `DescribeFund` sem `detailed`) e `SummarizeFavorites::one(string $ticker): ?FavoriteSummary` (`detailed: true`; `null` se não é favorito).
  - Preço atual para as variações: `quote->price`, senão `close->close`; sem preço, as duas variações são `null`. Fechamentos: `closesBefore($ticker, $hoje, $hoje − 40 dias)`.

- [ ] **Step 1: Write the failing tests**, com os `InMemory*` de `tests/Support/Fiis`:
  - `it('summarizes a favorite with quote, changes, last dividend and alerts of today')` — cotação 11,00; fechamentos que dão `change7d` `'10'` e `change30d` `'-5'` ou similar; um aviso de hoje e um de ontem → `alertsToday` tem 1.
  - `it('leaves changes and dividend empty for a favorite without quote or closes')` → `change7d`, `change30d`, `lastDividend` `null`, sem exceção.
  - `it('returns null in one() for a fund that is not a favorite')`.
  - `it('filters latest alerts by ticker')` no repositório (Feature, banco de teste) e no `InMemoryAlerts`.
- [ ] **Step 2: Run** e ver falhar.
- [ ] **Step 3: Implement** `latest` com filtro, `FavoriteSummary`, `SummarizeFavorites`. A tela de Avisos continua chamando `ListAlerts::handle()` sem argumentos.
- [ ] **Step 4: Run** `make test`. Expected: tudo verde, inclusive `AlertController` e Telegram, que usam `latest`.
- [ ] **Step 5: Commit** `feat(fiis): favorite summary with 7 and 30 day changes`

### Task 3: Validação compartilhada do alerta

**Files:**
- Create: `app/Modules/Fiis/Application/InvalidInput.php`, `app/Modules/Fiis/Application/FavoriteSettingsInput.php`
- Modify: `app/Modules/Fiis/Http/Requests/UpdateFavoriteSettingsRequest.php`, `app/Modules/Fiis/Http/Requests/AddFavoriteRequest.php`, `app/Modules/Fiis/Http/Requests/SaveHoldingRequest.php` (só a mensagem de catálogo)
- Test: `tests/Unit/Modules/Fiis/Application/FavoriteSettingsInputTest.php`; `tests/Feature/Modules/Fiis/FundPageTest.php` e `PortfolioTest.php` continuam verdes sem mudança

**Interfaces:**
- Produces:
  - `final class InvalidInput extends DomainException { public function __construct(public array $errors) }` — `array<string, string>` campo → mensagem; `getMessage()` junta as mensagens com espaço. `public const NOT_IN_CATALOG = 'Esse fundo não está no catálogo. Confira o código na B3.';`
  - `FavoriteSettingsInput::parse(string $mode, ?string $target, ?string $rangeMin, ?string $rangeMax, bool $sharpDrop): FavoriteSettings` — troca vírgula por ponto (como o `prepareForValidation` de hoje), aplica as regras e as mensagens **exatas** do `UpdateFavoriteSettingsRequest` atual: `mode.*` "Escolha preço alvo, faixa de compra ou automático.", `target` obrigatório "Informe o preço alvo.", formato "Use só números, como 9,50." (e `9,00`/`9,60` para mínimo/máximo), "O valor precisa ser maior que zero.", "O máximo precisa ser maior ou igual ao mínimo.", "Informe o mínimo da faixa.", "Informe o máximo da faixa.". Lança `InvalidInput` com as chaves `mode`, `target`, `rangeMin`, `rangeMax`.
  - `UpdateFavoriteSettingsRequest` delega a `FavoriteSettingsInput` (por exemplo, no `after()` do FormRequest, convertendo `InvalidInput::$errors` em erros de validação); `settings()` devolve o `FavoriteSettings` de `parse`. As regras de formato não ficam escritas em dois lugares.

- [ ] **Step 1: Write the failing tests** (dataset): `'9,50'` e `'9.50'` → alvo R$ 9,50; `'1.234,50'` → erro `target` "Use só números, como 9,50."; `'0'` → "O valor precisa ser maior que zero."; faixa 9 a 8 → erro `rangeMax`; `mode` `'x'` → erro `mode`; `range` sem mínimo → "Informe o mínimo da faixa.".
- [ ] **Step 2: Run** e ver falhar.
- [ ] **Step 3: Implement** e refatorar os FormRequests para usar `FavoriteSettingsInput` e `InvalidInput::NOT_IN_CATALOG`.
- [ ] **Step 4: Run** `make test`. Expected: o teste novo passa; `FundPageTest`, `FavoritesTest` e `PortfolioTest` passam **sem alteração**.
- [ ] **Step 5: Commit** `refactor(fiis): share alert settings validation between site and MCP`

### Task 4: Mudar cotas (Application)

**Files:**
- Create: `app/Modules/Fiis/Application/ChangeHolding.php`, `app/Modules/Fiis/Application/HoldingChange.php`
- Test: `tests/Unit/Modules/Fiis/Application/ChangeHoldingTest.php`

**Interfaces:**
- Consumes: `HoldingRepository` (`shares`, `save`, `remove`), `CatalogRepository::exists`, `InvalidInput` (Task 3).
- Produces: `ChangeHolding::handle(string $ticker, ?int $total, ?int $delta): HoldingChange`; `final readonly class HoldingChange { string $ticker; ?int $before; int $after; bool $removed; }`.

Regras e mensagens (chave `shares` no `InvalidInput`):

| Caso | Resultado |
|---|---|
| `total` e `delta` juntos, ou nenhum | "Informe o total de cotas ou quanto somar ou tirar, não os dois." |
| `delta` 0 | "Informe quanto somar ou tirar, diferente de zero." |
| `total` < 0 | "Informe um número inteiro de cotas, como 100." |
| fundo fora do catálogo | `InvalidInput::NOT_IN_CATALOG` (chave `ticker`) |
| resultado 0 | `remove`, `removed: true`, `after: 0` (se não estava na carteira e `total` 0: "{T} não está na carteira.") |
| resultado < 0, ou `delta` < 0 fora da carteira | "{T} tem {n} cotas na carteira; não dá para tirar {abs(delta)}." / "{T} não está na carteira." |
| `delta` > 0 fora da carteira | entra com `delta` cotas, `before: null` |

- [ ] **Step 1: Write the failing tests**, um por linha da tabela, com `InMemoryHoldings` e catálogo em memória; o caso de erro confere que o repositório não mudou.
- [ ] **Step 2: Run** e ver falhar.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `make test`. Expected: PASS.
- [ ] **Step 5: Commit** `feat(fiis): change holdings by total or delta`

### Task 5: Trocar alerta com trava (Domain + Application)

**Files:**
- Create: `app/Modules/Fiis/Domain/PriceLock.php`, `app/Modules/Fiis/Application/ChangeAlert.php`, `app/Modules/Fiis/Application/AlertChange.php`, `app/Modules/Fiis/Application/AlertChangeStatus.php`
- Test: `tests/Unit/Modules/Fiis/Domain/PriceLockTest.php`, `tests/Unit/Modules/Fiis/Application/ChangeAlertTest.php`

**Interfaces:**
- Consumes: `FavoriteSettings`, `UpdateFavoriteSettings`, `FavoriteRepository::settings`, `MarketData::latestQuote/latestClose`, `FundSnapshot`, `Percentage::change`.
- Produces:
  - `PriceLock::LIMIT = '30'`; `PriceLock::distances(FavoriteSettings $settings, Money $current): array` → `array<string, Percentage>` com chaves `target`, `rangeMin`, `rangeMax` presentes; `PriceLock::exceeded(FavoriteSettings $settings, Money $current): array` → só as que passam de 30% em módulo.
  - `enum AlertChangeStatus: string { Saved, Unchanged, NeedsConfirmation, NotFavorite }`.
  - `final readonly class AlertChange { AlertChangeStatus $status; string $ticker; ?FavoriteSettings $before; FavoriteSettings $requested; ?Money $currentPrice; ?Quote $quote; ?DailyClose $close; array $exceeded; bool $triggersNow; bool $priceUnchecked; }`
  - `ChangeAlert::handle(string $ticker, FavoriteSettings $settings, bool $confirmed): AlertChange` — não favorito → `NotFavorite`; sem preço → salva, `priceUnchecked: true`; `exceeded` não vazio e `! $confirmed` → `NeedsConfirmation`, **nada gravado**; igual ao atual (`changedRules` vazio) → `Unchanged`; senão grava via `UpdateFavoriteSettings` → `Saved`. `triggersNow`: só para alvo e faixa, a regra de referência de `$settings->rules()` avaliada num `new FundSnapshot($ticker, $currentPrice)` dá `matched`.

- [ ] **Step 1: Write the failing tests**
  - `PriceLockTest`: preço 10,00 → alvo 13,00 (30%) não excede; 13,01 excede; 6,99 excede; faixa 9,00 a 14,00 → só `rangeMax` excede; automático → nenhuma distância.
  - `ChangeAlertTest`: alvo 9,50 com preço 9,62 → `Saved`, gravado; alvo 95,00 → `NeedsConfirmation`, repositório inalterado; o mesmo com `confirmed: true` → `Saved`; sem cotação nem fechamento → `Saved` e `priceUnchecked`; alvo 10,00 com preço 8,80 → `Saved` e `triggersNow`; faixa que contém o preço → `triggersNow`; mesmo alerta de antes → `Unchanged`; fundo não favorito → `NotFavorite`.
- [ ] **Step 2: Run** e ver falhar.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `make test` (inclui `LayersTest`). Expected: PASS.
- [ ] **Step 5: Commit** `feat(fiis): change alert with a 30% price lock`

### Task 6: Pacote, servidor e saída limpa

**Files:**
- Modify: `composer.json`, `composer.lock`, `tests/Arch/LayersTest.php`
- Create: `routes/ai.php`, `app/Modules/Mcp/FiisServer.php`
- Test: `tests/Feature/Modules/Mcp/StdioTest.php`

**Interfaces:**
- Produces: `App\Modules\Mcp\FiisServer extends Laravel\Mcp\Server` com `#[Name('Me Avisa Aí')]`, `#[Version('1.0.0')]`, `protected array $tools = []` (as Tasks 8 e 9 preenchem) e `#[Instructions]` com exatamente:

  > Dados dos FIIs favoritos e da carteira de uma pessoa, guardados no app Me Avisa Aí, que roda no computador dela. O app informa e não recomenda investimentos: um aviso diz que uma regra foi atendida, não que o fundo está barato ou deve ser comprado. Ao citar um preço, diga de onde e de quando ele é. Antes de trocar alerta, remover favorito ou mexer na carteira, confira com a pessoa o fundo e os valores. Valores em reais, com vírgula decimal.

- [ ] **Step 1:** `make shell`, `composer require laravel/mcp:^1.1`. O pacote publica `routes/ai.php` sozinho? Se não, crie: `Mcp::local('fiis', FiisServer::class);`.
- [ ] **Step 2: Write the failing tests**
  - `LayersTest`: `arch('mcp does not touch storage or the site')->expect('App\Modules\Mcp')->not->toUse(['App\Modules\Fiis\Infrastructure', 'App\Modules\Fiis\Http', 'Illuminate\Database'])` e `arch('fiis does not know mcp')->expect('App\Modules\Fiis')->not->toUse('App\Modules\Mcp')`.
  - `StdioTest`: roda `php artisan mcp:start fiis` com `Process` (input: uma linha `initialize` e uma `tools/list` em JSON-RPC) e confere que **toda** linha da saída padrão é JSON válido e que a resposta de `initialize` traz o nome `Me Avisa Aí`. `it('has no web transport')`: nenhuma rota com URI `mcp`.
- [ ] **Step 3: Run** e ver falhar; **Step 4: Implement**; se aparecer texto que não é JSON na saída (aviso do PHP, log), redirecione para stderr (ex.: `ini_set('display_errors', 'stderr')` em `routes/ai.php`) em vez de silenciar.
- [ ] **Step 5: Run** `make test` e `make check`. Expected: PASS.
- [ ] **Step 6: Commit** `feat(mcp): laravel/mcp with a local fiis server`

### Task 7: Formatação em português

**Files:**
- Create: `app/Modules/Mcp/Format.php`
- Test: `tests/Unit/Modules/Mcp/FormatTest.php`

**Interfaces:**
- Produces (todos `static`, retornam `string`):
  - `money(?Money)` → `R$ 9,50` ou `sem dado`
  - `percent(?Percentage)` → `+1,2%`, `-3,6%` (2 casas no máximo, sem zeros à direita, vírgula), `0%`, ou `sem dado`
  - `moment(DateTimeImmutable)` → `06/11 17:00` (São Paulo)
  - `day(string $ymd)` → `06/11/2026`
  - `priceSource(?Quote, ?DailyClose)` → `cotação da brapi, 06/11 17:00` / `fechamento de 06/11/2026` / `sem preço`
  - `settings(FavoriteSettings)` → `automático + queda forte`, `preço alvo R$ 150,00 + queda forte`, `faixa de compra R$ 8,00 a R$ 9,00, sem queda forte`
  - `rule(RuleKind)` → `preço alvo`, `faixa de compra`, `automática`, `queda forte`
  - `ticker(string)` → `strtoupper(trim(...))`

- [ ] **Step 1: Write the failing tests**, um `it` por função, com os exemplos acima e um horário UTC (`2026-11-06T20:00:00Z` → `06/11 17:00`).
- [ ] **Step 2–4:** ver falhar, implementar, ver passar.
- [ ] **Step 5: Commit** `feat(mcp): portuguese formatting for tool answers`

### Task 8: Ferramentas de leitura

**Files:**
- Create em `app/Modules/Mcp/Tools/`: `ListFavoritesTool.php`, `ShowFavoriteTool.php`, `RecentAlertsTool.php`, `ShowPortfolioTool.php`, `SearchFundsTool.php`
- Modify: `app/Modules/Mcp/FiisServer.php` (`$tools`)
- Test: `tests/Feature/Modules/Mcp/ReadToolsTest.php`

**Interfaces:**
- Consumes: `SummarizeFavorites` (Task 2), `ListAlerts` (Task 2), `ShowPortfolio`, `ListFunds` (tipo e segmento; `CatalogEntry` só tem código e nome), `HoldingRepository::shares`, `Format` (Task 7).
- Produces: cinco ferramentas com `#[IsReadOnly]`, nome em snake_case em inglês (`list_favorites`, `show_favorite`, `recent_alerts`, `show_portfolio`, `search_funds`) e descrição em português. Resposta `Response::json(...)` com **chaves em português e valores já formatados** pelo `Format`:
  - `list_favorites`: `{favoritos: [{codigo, nome, preco, preco_de, variacao_dia, variacao_7d, variacao_30d, pvp, pvp_mediana_12m, dy_12m, ultimo_rendimento_por_cota, alerta, avisos_hoje: [{regra, motivo, horario}]}]}`; lista vazia → `Response::text('Você ainda não tem favoritos. Dá para favoritar pela conversa, com o código do fundo.')`.
  - `show_favorite(codigo)`: os campos acima + `medias: {6m, 12m}` + `regras: [{regra, atendida, motivo}]` (motivo = `reason`, `unavailableReason` ou `note`) + `rendimentos_12m`; não favorito → `Response::error('{T} não é favorito. Favorite primeiro para ver os dados dele.')`.
  - `recent_alerts(codigo?, quantidade? = 10, máx. 50)`: `{avisos: [{codigo, regra, preco, referencia, motivo, horario}]}`.
  - `show_portfolio`: `{fundos: [{codigo, nome, cotas, preco, preco_de, valor, rendimento_ultimo_mes, rendimento_medio, meses_de_rendimento}], totais: {valor, rendimento_ultimo_mes, rendimento_medio, sem_preco, sem_rendimento}, observacao: 'Estimativa pelo DY e pelo VP informados à CVM. O fundo pode pagar outro valor.'}`; vazia → texto "Sua carteira está vazia."
  - `search_funds(texto)`: até 10 `{codigo, nome, tipo, segmento, favorito, na_carteira}`.

- [ ] **Step 1: Write the failing tests** (banco de teste, `FundModel`, `DailyPriceModel`, `HoldingModel` etc., como `PortfolioTest`; `Carbon::setTestNow`), chamando `FiisServer::tool(ListFavoritesTool::class, [...])`:
  - lista com um favorito com cotação: `assertSee` do preço `R$ 8,80`, `cotação da brapi`, variações;
  - **mesmos números do site:** para o mesmo cenário, `preco`, `pvp` e `dy_12m` de `list_favorites` correspondem às props `price`, `priceToBook`, `dividendYield12m` de `GET /` (formatadas por `Format`); `totais.valor` de `show_portfolio` corresponde a `totals.value` de `GET /carteira`;
  - favorito sem cotação e sem fechamentos → `sem dado`, `assertOk`;
  - sem favoritos e carteira vazia → as frases acima;
  - `show_favorite` com `' knri11 '` funciona; com fundo não favorito → `assertHasErrors`;
  - `recent_alerts` filtra por código e respeita a quantidade;
  - `search_funds` marca `favorito` e `na_carteira`.
- [ ] **Step 2–4:** ver falhar, implementar, ver passar com `make test`.
- [ ] **Step 5: Commit** `feat(mcp): read tools for favorites, alerts, portfolio and search`

### Task 9: Ferramentas de alteração

**Files:**
- Create em `app/Modules/Mcp/Tools/`: `AddFavoriteTool.php`, `RemoveFavoriteTool.php`, `ChangeAlertTool.php`, `ChangeHoldingTool.php`, `RemoveHoldingTool.php`
- Modify: `app/Modules/Mcp/FiisServer.php`
- Test: `tests/Feature/Modules/Mcp/WriteToolsTest.php`

**Interfaces:**
- Consumes: `AddFavorite`, `RemoveFavorite`, `FavoriteRepository`, `CatalogRepository`, `FavoriteSettingsInput` e `InvalidInput` (Task 3), `ChangeHolding` (Task 4), `ChangeAlert` (Task 5), `Format` (Task 7).
- Produces: `add_favorite(codigo)`, `remove_favorite(codigo)` `#[IsDestructive]`, `change_alert(codigo, tipo: automatic|target|range, preco_alvo?, faixa_min?, faixa_max?, queda_forte: bool, confirmado?: bool = false)`, `change_holding(codigo, total?: int, variacao?: int)`, `remove_holding(codigo)` `#[IsDestructive]`. Preços aceitam número ou texto; converta para texto sem notação científica antes de `FavoriteSettingsInput::parse`. Erros: `Response::error(<mensagem> . ' Nada foi alterado.')`.

Textos (`{T}` = código, `{x}` = `Format::*`; linhas separadas por `\n`):

| Situação | Resposta |
|---|---|
| favoritou | `✅ {T} favoritado.` / `Alerta: automático + queda forte (o padrão; dá para trocar).` / `Cada favorito gasta cerca de 350 requisições da brapi por mês (o plano grátis tem 15 mil).` |
| já era favorito | `{T} já era favorito. Nada foi alterado.` |
| removeu favorito | `✅ {T} removido dos favoritos.` / `O alerta era: {settings}.` / `Os avisos antigos continuam na aba Avisos.` |
| alerta `Saved` | `✅ Alerta salvo para {T}.` / `Antes: {settings antes}` / `Agora: {settings depois}` (+ `O preço atual ({money}, {priceSource}) já atende esse alerta; o aviso deve sair agora ou na próxima atualização.` se `triggersNow`; + `Sem preço atual para conferir a distância do alvo.` se `priceUnchecked`) |
| alerta `Unchanged` | `O alerta de {T} já era esse: {settings}. Nada foi alterado.` |
| alerta `NeedsConfirmation` (não é erro) | `⚠️ Nada foi alterado.` / uma linha por valor: `{campo} {money} está {percent sem sinal} {acima/abaixo} do preço atual de {T} ({money}, {priceSource}).` / `Confirme com a pessoa. Se ela disser que sim, chame de novo com confirmado: true.` (campo: `O alvo`, `O mínimo da faixa`, `O máximo da faixa`) |
| cotas | `✅ Carteira atualizada.` / `{T}: {antes} → {depois} cotas` ou `{T}: entrou com {depois} cotas` ou `{T}: {antes} → 0 cotas, saiu da carteira.` |
| tirou da carteira | `✅ {T} saiu da carteira (tinha {antes} cotas).` |

A descrição de `change_alert` diz: um fundo tem um só alerta principal e trocar substitui o atual; se a pessoa não falar da queda forte, mantenha o valor atual (que aparece em `list_favorites`); `confirmado: true` só depois de a pessoa dizer que sim.

- [ ] **Step 1: Write the failing tests**, um por linha da tabela, conferindo **o banco** além do texto: `NeedsConfirmation` não grava; com `confirmado: true` grava; `preco_alvo: "9,50"` (texto) grava R$ 9,50; `"1.234,50"` dá erro e não grava; ticker `' mxrf11 '` funciona; favoritar fundo fora do catálogo → erro `Esse fundo não está no catálogo. Confira o código na B3. Nada foi alterado.`; `change_holding` com `total` e `variacao` juntos → erro; remover favorito que não existe → `{T} não é favorito. Nada foi alterado.` (erro).
- [ ] **Step 2–4:** ver falhar, implementar, ver passar com `make test` e `make check`.
- [ ] **Step 5: Commit** `feat(mcp): tools to manage favorites, alerts and portfolio`

### Task 10: Plugin, configuração e documentação

**Files:**
- Create: `.claude-plugin/marketplace.json`, `plugin/.claude-plugin/plugin.json`, `plugin/.mcp.json`, `plugin/skills/fiis/SKILL.md`
- Modify: `Makefile` (alvo `mcp-config`), `README.md`, `README.en.md`, `CLAUDE.md` (Estado e Decisões), `.dockerignore` (`plugin/`, `.claude-plugin/`)
- Test: `tests/Feature/Modules/Mcp/PluginManifestTest.php`

**Interfaces:**
- Marketplace `me-avisa-ai` com o plugin `me-avisa-ai` (`source: ./plugin`). `plugin/.mcp.json`: servidor `me-avisa-ai`, `command: docker`, `args: ["compose", "-f", "${ME_AVISA_AI_DIR}/compose.yaml", "exec", "-T", "web", "php", "artisan", "mcp:start", "fiis"]`. A pessoa define `ME_AVISA_AI_DIR` (README mostra como, no shell e no `settings.json` do Claude Code em `env`).
- `make mcp-config` imprime o trecho JSON para o `claude_desktop_config.json` com o caminho absoluto da pasta (`$(CURDIR)`) e o `$(COMPOSE)` em uso (Podman: `podman compose`).
- `SKILL.md`: quando a pessoa perguntar de FIIs, favoritos, alertas ou carteira, use as ferramentas `me-avisa-ai`; repita o tom das `Instructions` da Task 6.
- Extensão `.mcpb` do Claude Desktop: **fora deste plano** (opcional no spec); o README ensina o JSON.

- [ ] **Step 1: Write the failing test** `PluginManifestTest`: os três JSON são válidos; o `args` de `plugin/.mcp.json` termina em `["mcp:start", "fiis"]`; o nome do servidor `fiis` existe em `routes/ai.php` (`app(Registrar::class)->getLocalServer('fiis') !== null`).
- [ ] **Step 2–4:** ver falhar, criar os arquivos, ver passar.
- [ ] **Step 5: README (pt e en)**: seção nova "Conversar com uma IA (MCP)" depois de "Bot do Telegram": o que é (qualquer cliente MCP local; exemplos de perguntas), Claude Desktop (`make mcp-config`, onde fica o arquivo no Linux, Mac e Windows, reiniciar o Desktop), Claude Code (marketplace e `ME_AVISA_AI_DIR`), outro cliente (Cursor: mesmo JSON em `~/.cursor/mcp.json`), "precisa do app ligado", a trava de 30% e por que Claude web e celular ainda não funcionam. Tabela "Módulos": MCP vira **Pronto** e a descrição deixa de dizer "com o Claude". Tabela "Comandos": `make mcp-config`. Arquitetura: `app/Modules/Mcp/`.
- [ ] **Step 6: Conferência manual com a pessoa** no Claude Desktop da instalação dev (`:8001`): "como estão meus FIIs hoje?", trocar um alerta para um valor 10× maior (deve pedir confirmação), favoritar e remover, "comprei mais 10 cotas de …". Anotar o que saiu estranho.
- [ ] **Step 7: Run** `make test`, `make check`, `npm run check`.
- [ ] **Step 8: Commit** `feat(mcp): claude code plugin, desktop config and docs`
