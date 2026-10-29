# Me Avisa Aí

Acompanha seus FIIs favoritos e avisa quando algum chega num preço bom para
comprar: preço alvo, faixa de compra, regra automática (abaixo da média de 30
dias com P/VP descontado) ou queda forte no dia.

> Recomeço em 2026-10-29. A versão anterior (Rails + React) está nas branches
> `develop` e `feature/fii-dashboard-brapi-catalog`; o que foi aproveitado está em
> [`docs/legado.md`](docs/legado.md).

## Stack

Laravel · React (Inertia) · Tailwind v4 · shadcn/ui · PostgreSQL · Docker Compose.
Roda localmente. Dados de mercado: [brapi](https://brapi.dev) (cotações) e
[CVM Dados Abertos](https://dados.cvm.gov.br/dataset/fii-doc-inf_mensal)
(valor patrimonial e DY).

## Módulos

1. **FIIs**: favoritos, dados de mercado, regras de alerta, avisos no site *(em construção)*
2. Bot do Telegram
3. Servidor MCP (gerenciar favoritos conversando com o Claude)
4. Ações e ETFs

## Documentação

- Regras de produto: [`docs/fiis/`](docs/fiis/), [`docs/acoes/`](docs/acoes/), [`docs/ets/`](docs/ets/), [`docs/bot/`](docs/bot/)
- Protótipo visual: [`docs/prototipo-lovable/`](docs/prototipo-lovable/)
- UI/UX e acessibilidade: [`.claude/skills/ui-ux/`](.claude/skills/ui-ux/)

O app é informativo e não faz recomendação de investimento.
