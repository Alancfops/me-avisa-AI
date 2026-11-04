# FIIs (Fundos Imobiliários) — Contexto Completo

## O que são FIIs

FIIs (fundos de investimento imobiliário) são ativos negociados na **B3** que representam participação em empreendimentos imobiliários ou em títulos ligados ao setor (CRIs, LCIs etc., conforme o tipo do fundo).

O investidor compra cotas e recebe rendimentos periódicos (geralmente mensais).

---

## Tipos de FIIs

### Tijolo

- Investem em imóveis físicos (shoppings, galpões, escritórios)
- Receita vem de aluguel
- Mais previsíveis, porém expostos à vacância

### Papel

- Investem em CRIs (Créditos Imobiliários)
- Receita vem de juros (IPCA, CDI)
- Sensíveis a taxa de juros

### Híbrido

- Mistura dos dois

### Fundo de fundos

- Investem em cotas de outros FIIs

No app, o tipo vem da composição da carteira no Informe Mensal da CVM (regra em
`docs/superpowers/specs/2026-11-03-lista-de-fiis-design.md`, "Tipo do fundo").

---

## Indicadores principais

### Preço

Valor atual da cota no mercado.

---

### Dividend Yield (DY)

DY representa o retorno em dividendos em relação ao preço.

DY mensal:
DY = dividendo / preço

DY anual:
DY = (dividendos últimos 12 meses) / preço

---

### P/VP (Preço / Valor Patrimonial)

P/VP = preço / valor patrimonial

Interpretação:

- abaixo de 1 → pode indicar desconto (não é garantia de “barato”)
- próximo de 1 → alinhado ao patrimônio
- acima de 1 → prêmio em relação ao VP

---

### Dividendos

- Valor pago por cota
- Geralmente mensal

---

### Vacância (FIIs de tijolo)

Percentual de imóveis desocupados.

Alta vacância indica risco.

---

### Liquidez

Volume negociado no mercado.

Baixa liquidez pode dificultar compra/venda.

---

## Conceito de “Preço Normal”

O sistema deve definir um valor de referência para avaliar oportunidades.

Possíveis abordagens:

### Média móvel

Preço normal = média dos últimos 20 ou 30 dias

### Baseado em P/VP

Preço normal ≈ valor patrimonial (P/VP = 1)

### Baseado em DY

Se DY está acima da média histórica, pode indicar desconto

---

## Regras de alerta

As regras que valem, com todos os detalhes, estão em [`alert.md`](alert.md). Em resumo:

- **Regra automática:** preço abaixo da média de 30 pregões × 0,95 **e** P/VP abaixo da
  mediana de 12 meses do próprio fundo (com menos de 60 pregões, P/VP abaixo de 1,0).
- **Queda forte:** queda diária superior a 3%.

---

## Riscos

- DY alto pode indicar risco elevado
- P/VP baixo pode refletir problemas estruturais
- Dados podem não estar em tempo real
- Eventos externos impactam FIIs (juros, economia)

---

## Uso no sistema

Cada FII deve conter:

- ticker
- preço atual
- histórico de preços
- DY
- P/VP
- dividendos
- tipo (tijolo/papel)
- liquidez

---

## Fluxo do usuário (favoritos)

1. O usuário pesquisa por nome ou ticker do FII.
2. O sistema consulta a fonte de mercado e retorna opções encontradas.
3. O usuário adiciona o ativo à carteira de favoritos.
4. Na carteira, o usuário visualiza:
   - informações específicas do fundo (preço, DY, P/VP, liquidez, tipo);
   - gráfico do ativo (histórico de preços);
   - configuração de alertas por ativo.

---

## Objetivo no sistema

- Avisar quando uma regra definida pelo usuário ou a regra automática é atendida
- Informar, sem recomendar compra: um aviso não diz que o ativo está barato
- Permitir análise rápida numa tela só (web, no computador do usuário; o layout também
  funciona em telas estreitas)
