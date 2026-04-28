---
name: brapi-data
description: Define validação e normalização de dados vindos da API Brapi para fiis, acoes e ets: nunca confiar no payload bruto, tratar campos ausentes e evitar chamadas redundantes. Usar quando integrar, parsear ou persistir cotações e métricas de mercado da Brapi neste repositório.
---

# Dados Brapi (validação e modelo)

## Quando aplicar

Parser, client HTTP, jobs de sync, serializers e qualquer código que **leia a resposta da Brapi** antes de persistir ou expor na API Rails.

## Princípio

A Brapi é **fonte externa**: respostas podem mudar, falhar ou vir **incompletas**. **Nunca assumir** 100% correto; **validar**, **normalizar** e só então usar ou salvar.

## Consultar `docs/` antes de enriquecer o modelo

- **FII:** `docs/fiis/context.md` e `docs/fiis/alert.md` (ex.: P/VP, DY, histórico, liquidez).
- **Ação:** `docs/acoes/context.md` e `docs/acoes/alert.md` (ex.: P/L, variação, setor).
- **ETF:** `docs/ets/context.md` e `docs/ets/alert.md` (ex.: índice de referência, mínima 52 semanas).

Campos extras além da base abaixo devem **existir nos docs** ou estar documentados no projeto antes de codificar — alinhar com **`.cursor/rules/dev-rules.mdc`**.

## Contrato interno mínimo (todos os ativos)

Objeto normalizado após validação (nomes em **snake_case** no domínio Rails; mapear desde os nomes que a Brapi devolver):

| Campo | Tipo / regra | Notas |
|--------|----------------|--------|
| `ticker` | string, presente, **B3 em maiúsculas** | normalizar com `strip.upcase`; rejeitar formato inválido. |
| `type` | enum **`fii` \| `acao` \| `etf`** | inferir pela rota/módulo chamado ou metadado confiável; não adivinhar só pelo sufixo `11` sem regra explícita. |
| `price` | decimal, **opcional na entrada** | se ausente ou não numérico: **não inventar**; marcar como indisponível ou abortar o registro conforme política. |
| `change_percent` | decimal ou nil | mapear desde `changePercent` (ou chave real da Brapi); validar range plausível (ex.: cap absurdo opcional); nil se a API omitir. |

**Evolução do contrato:** acrescentar atributos (ex.: `p_vp`, `dividend_yield`) só quando forem **usados** por alertas/UI e estiverem cobertos em `docs/` + testes.

## Validação (obrigatória)

1. **Schema por etapa:** JSON parseável → chaves esperadas → tipos → ranges básicos (preço não negativo se “cotação válida”, percentual numérico).
2. **Campos obrigatórios para persistir:** definir no código (ex.: exige `ticker` + `price` + `type`; `change_percent` pode ser nil se o doc de produto permitir).
3. **Inconsistências:** log estruturado, métrica ou fila de revisão; não sobrescrever dados bons com payload claramente quebrado.
4. **Datas e timezone:** alinhar pregão B3 ao documentar; não misturar “último fechamento” com intraday sem rótulo.

## Dados faltantes

- Representar ausência com **`nil`** / coluna nullable com significado documentado.
- **Não propagar** string vazia, `"N/A"` ou zero como “valor real” sem flag explícita.
- Na API pública do Rails, expor ausência de forma clara (omitir chave ou `null`) para o front não interpretar errado.

## Chamadas à Brapi

- Reutilizar orientações de **`.cursor/skills/backend-rails/`** (cache, batch, TTL, env da chave).
- **Deduplicar** tickers na mesma janela de sync; não refetch por request de usuário se o snapshot ainda estiver dentro do TTL.

## Anti-padrões

- `JSON.parse` → `Quote.create!(json)` sem camada intermediária.
- Confiar em `default: 0` para preço ou variação quando a API falhou.
- Um único hash global sem `type` nem validação por módulo.

## Referência externa

- Detalhes da API: [reference.md](reference.md).
