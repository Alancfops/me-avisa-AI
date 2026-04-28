# Alertas — FIIs

## Objetivo

Detectar oportunidades de compra com base em preço, renda (dividendos) e desconto em relação ao valor patrimonial (P/VP), sempre respeitando as preferências do usuário.

---

## Ativação de alertas

- O usuário deve marcar o FII como **favorito**.
- Cada FII pode ter **regras próprias** (preço alvo, faixa, ou apenas o padrão automático do sistema).

---

## Tipos de alerta

### 1. Preço alvo (principal)

O usuário define um valor máximo de compra (uma cota).

**Exemplo:** MXRF11 → R$ 9,50

**Regra:** disparar quando **preço ≤ preço alvo**.

---

### 2. Faixa de preço (zona de compra)

O usuário define um intervalo.

**Exemplo:** entre R$ 9,00 e R$ 9,60

**Regra:** disparar quando o preço **estiver dentro da faixa** (inclusive limites, salvo definição contrária no produto).

---

### 3. Alerta automático (sistema)

Se o usuário **não** configurar alvo/faixa manualmente:

**Regra:**

- preço **abaixo de** média móvel de **30 dias** × **0,95**
- **e** P/VP **abaixo de** **1,0**

---

### 4. Queda brusca

**Regra:** queda **diária** superior a **3%** (variação do pregão inferior a −3%).

---

## Regras de controle (anti-spam)

- No máximo **1 alerta por FII a cada 24 h**.
- Reenviar antes disso **somente** se, após o último alerta, o preço **cair mais 2%** (ajuste fino conforme implementação).

---

## Dados necessários

- Preço atual (e horário da cotação).
- Histórico de preços (para média de 30 dias).
- P/VP.
- Dividendos (últimos 12 meses ou último provento, conforme o cálculo de DY no sistema).

---

## Formato do alerta

🚨 **Oportunidade em FII**

**Ativo:** MXRF11  
**Preço atual:** R$ 9,55  
**Sua referência:** faixa R$ 9,00–R$ 9,60 (ou alvo R$ 9,60)

**P/VP:** 0,98  
**DY estimado (mensal):** 1,1% — *especificar no produto se o número é mensal, anualizado ou TTM (12 meses).*

**Status:** dentro da faixa de compra ✅

Ver mais: `[link-do-sistema]`

---

## Integração com calculadora

Quando o produto incluir estimativa de renda:

> Se investir R$ 1.000 hoje, estimativa de renda mensal: R$ X

Usar a mesma convenção de DY (mensal vs. anual) do restante do sistema para evitar números incompreensíveis.
