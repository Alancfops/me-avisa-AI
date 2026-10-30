# O que veio da versão antiga

A primeira versão (Rails + React + Supabase, branches `develop` e
`feature/fii-dashboard-brapi-catalog` deste repositório) foi descartada no
recomeço de 2026-10-29. O que dela continua valendo, exceto onde divergir do
design atual (`docs/superpowers/specs/2026-10-29-modulo-fiis-design.md`), que
prevalece, por exemplo nas rotas da brapi e no envio do token por header:

## Mapeamento da brapi já testado

| Campo brapi (`/api/quote/{ticker}`) | Uso |
|---|---|
| `symbol` | ticker (normalizar com trim + maiúsculas) |
| `longName` → `shortName` → ticker | nome do fundo |
| `regularMarketPrice` | preço atual |
| `regularMarketChangePercent` | variação do dia (%) |
| `regularMarketVolume` | liquidez |
| `priceToBook` | P/VP (pode não vir para FII no plano grátis) |
| `dividendYield` | DY 12 meses (idem) |
| `historicalDataPrice` (com `range`/`interval`) | histórico de preços |

- Busca por nome/ticker: `GET /api/quote/list?search=<texto>`; resultados em
  `stocks[]` com `stock` e `name`.
- Token via query string `token=` (variável `BRAPI_TOKEN`).
- Em 2026-10-29 até a cotação simples respondeu `MISSING_TOKEN` sem token.

## Princípios de dados

- A brapi é fonte externa: validar o payload (JSON → chaves → tipos → faixa
  plausível) antes de salvar.
- Dado ausente é `null` com significado, nunca `0`, `""` ou `"N/A"`.
- Não sobrescrever dado bom com resposta quebrada; registrar o problema em log.
- Deduplicar tickers numa mesma rodada de busca.
- Não rotular último fechamento como cotação do momento.

## Regras de desenvolvimento

- Não inventar regra, limite ou fórmula financeira que não esteja em `docs/`;
  se faltar, atualizar o doc primeiro.
- Separar FII, ação e ETF em módulos próprios.
- Interface pensada primeiro para o celular.
