# Módulo FIIs, backend: plano de implementação

> **For agentic workers:** executar tarefa a tarefa, com TDD. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Acompanhar favoritos com cotação real, avaliar as regras e registrar avisos, com telas mínimas funcionais desde a primeira tarefa.

**Architecture:** Continua em `app/Modules/Fiis/`, nas camadas da skill `backend`. Cada tarefa entrega algo visível no navegador. O acabamento visual é do `frontend-senior`, em plano próprio.

**Tech Stack:** a da base: Laravel, Inertia, Pest, `brick/math`, Postgres, `make`.

**Spec:** `docs/superpowers/specs/2026-10-29-modulo-fiis-design.md`. Regras: `docs/fiis/alert.md`. Em divergência, o doc vence o plano.

## Global Constraints

- Branch única: `feature/modulo-fiis-backend`, a partir da `develop`. Um commit por tarefa, em Conventional Commits, sem push.
- Verificação: `make test`, `make lint`, `make check`. Nunca `compose run web` em comando avulso: ele migra o banco real.
- Testes nunca chamam brapi nem CVM: respostas gravadas com `Http::fake`, sem token real.
- Brapi: `GET /api/v2/stocks/quote?symbols=`, `GET /api/v2/stocks/historical?symbols=&range=3mo&interval=1d`, `GET /api/v2/tickers?type=fund&subType=fii`, `GET /api/v2/user/usage`. Header `Authorization: Bearer`. Um ativo por requisição. Usar `close`, não `adjustedClose`.
- Preço zero ou negativo vindo de fonte é inválido: rejeitar, registrar em log, manter o último dado bom. Ausente é `null`.
- Horário exibido é `regularMarketTime`. Fuso `America/Sao_Paulo`.
- Textos de tela informam e não recomendam ("No seu alvo", nunca "compre").
- Limites com nome e origem: `0,95`, `−3%` (estrito), `0,98`, 24 h, 60 pregões.

## Review Focus

- Mudança de horário ou feriado: cotação sem novidade não pode gerar aviso repetido. Teste na Task 3.
- Resposta quebrada ou 429 da brapi em um fundo: os demais seguem, e o dado bom fica. Teste na Task 1.
- Regra deixa de valer e volta dentro das 24 h: aviso sai quando o teto liberar, se ainda valer. Teste na Task 3.
- Fundo sem VP ou com menos de 60 pregões: regra automática não dispara ou usa a reserva, e a tela diz por quê. Teste nas Tasks 2 e 4.
- Fundo da CVM que não casa com ticker, ou com várias versões do informe: "VP indisponível" e maior `Versao`. Teste na Task 4.

---

### Task 1: Favoritar e ver cotação real

**Files:** `Application/Quotes.php`, `Application/FundCatalog.php`, `Infrastructure/Brapi/*`, `Application/{AddFavorite,RemoveFavorite,RefreshQuotes,SearchFunds}.php`, migrations de `funds` (ticker, nome), `daily_prices` e cotação atual em `favorites` ou tabela própria, comando `fiis:refresh-quotes`, `Http/*`, página `dashboard` e rotas.

- [ ] Testes: parser da brapi com resposta gravada, incluindo quebrada, preço zero, `null`, 401, 403, 404 e 429 com `Retry-After`; `RefreshQuotes` continua nos outros fundos quando um falha; token nunca aparece em log.
- [ ] Implementar. Ao favoritar: grava o histórico de 3 meses. Catálogo sincronizado por `fiis:sync-catalog`.
- [ ] Tela: busca, favoritar e remover; painel com preço, variação do dia e "cotação de HH:MM"; estado vazio e dado indisponível.
- [ ] Verificar `make check`. Conferir no app real com o token do `.env`, gastando poucas requisições: favoritar 1 fundo, rodar o comando e ver o preço. Commit.

### Task 2: As quatro regras

**Files:** `Domain/{BuyRangeRule,AutomaticRule,SharpDropRule}.php`, ampliar `FundSnapshot` com variação do dia, média 30d, P/VP, mediana e pregões disponíveis; `Domain/Percentage.php` se necessário.

- [ ] Testes com os exemplos de `alert.md` e limites exatos: faixa inclusiva nas pontas; automática estrita em `< média × 0,95` e `P/VP < mediana`; reserva `P/VP < 1,0` com menos de 60 pregões; sem VP não dispara e diz o motivo; queda em exatamente −3% não dispara. Média sem o preço ao vivo.
- [ ] Implementar como classes puras. Sem relógio real. Commit.

### Task 3: Antispam, avisos e configuração

**Files:** `Domain/AlertPolicy.php`, `Application/{EvaluateFavorites,UpdateFavoriteSettings,ListAlerts}.php`, migrations de `alerts` e estado por regra, configuração do favorito, página `alerts` e página `funds/show` ("Quando me avisar").

- [ ] Testes do antispam, por regra de cada favorito, com relógio injetado: cruzamento gera aviso; continuar valendo não repete; `preço ≤ último × 0,98` reavisa antes das 24 h; teto de 24 h; bloqueado pelo teto sai depois, se ainda valer; deixou de valer zera; queda forte é novo evento a cada pregão; queda às 10h não bloqueia alvo às 14h; religar não repete; mudar configuração reavalia e começa ciclo novo; cotação sem novidade não gera aviso.
- [ ] Implementar. Aviso guarda fundo, regra, preço, referência e momento, para os próximos módulos consumirem.
- [ ] Telas: escolher alvo, faixa ou automático; ligar queda forte; lista de avisos com estado vazio. Favorito novo começa automático, com queda forte ligada. Commit.

### Task 4: CVM, P/VP e DY

**Files:** `Application/FundReports.php`, `Infrastructure/Cvm/*`, migration de informes, comando `fiis:import-reports`.

- [ ] Testes com CSV gravado em Latin-1 com `;`: maior `Versao`; vigência por `Data_Entrega`, sem look-ahead; ISIN `BRMXRFCTF008` → `MXRF11`; sem casar → "VP indisponível"; ausente é `null`; DY como fração, soma dos 12 últimos e rotulado; P/VP diário para a mediana.
- [ ] Implementar importando ano corrente e anterior. Ligar à regra automática e à tela do fundo com a competência ("VP de setembro/2026").
- [ ] Conferir no app real com uma importação. Commit.

### Task 5: Agendamento e Saúde

**Files:** `routes/console.php`, `Application/SourceHealth.php`, página `health`.

- [ ] Testes: agenda a cada 30 min, 10:00–18:00, seg–sex, em `America/Sao_Paulo`; fechamento, catálogo e CVM uma vez ao dia fora do pregão; falha da fonte fica registrada e aparece na Saúde.
- [ ] Implementar. Saúde: última atualização e erros recentes por fonte, em linguagem simples, e consumo por `/api/v2/user/usage`.
- [ ] `make check`. Commit.

### Task 6: Simulador

**Files:** comando `fiis:backtest`, alvo `backtest` no `Makefile`.

- [ ] Testes: usa as mesmas regras e o mesmo antispam, sem gravar nem enviar nada; aviso de que é só contagem.
- [ ] Implementar `make backtest TICKER=KNRI11`. Atualizar os READMEs, sem chamar de pronto o que não foi testado. Commit.
