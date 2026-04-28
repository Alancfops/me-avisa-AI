# Alertas — Ações

## Objetivo

Identificar oportunidades de compra, quedas relevantes e cruzamentos com o preço médio informado pelo usuário (quando houver).

---

## Ativação de alertas

- O usuário deve **favoritar** a ação.
- Pode configurar **regras individuais** (preço alvo e, opcionalmente, preço médio da posição).

---

## Tipos de alerta

### 1. Preço alvo

O usuário define o valor máximo que aceita pagar por ação.

**Regra:** disparar quando **preço ≤ valor definido**.

---

### 2. Queda percentual

**Regra:**

- queda **diária** superior a **5%**, **ou**
- queda **acumulada** na janela de **5 pregões** superior a **10%**.

---

### 3. Preço médio do usuário (opcional)

Se o usuário informar posição (preço médio de compra):

**Regra:** disparar quando **preço atual estiver abaixo do preço médio** informado (útil para aviso de prejuízo na marcação ou oportunidade de média).

---

### 4. Alerta de movimento

**Regra:** alta ou queda **fora do padrão** do ativo — ex.: **variação diária absoluta superior a 3%** (ajustar por volatilidade histórica se o produto evoluir).

---

## Regras de controle

- **1 alerta por ativo a cada 24 h** como padrão.
- **Exceção:** movimentos fortes — ex.: **variação diária absoluta superior a 7%** — pode gerar alerta adicional conforme política de produto.

---

## Dados necessários

- Preço atual e variação diária (%).
- Histórico para queda acumulada.
- Preço médio do usuário (se cadastrado).

---

## Formato do alerta

🚨 **Movimento em ação**

**Ativo:** PETR4  
**Preço:** R$ 32,10  
**Variação:** −5,4%

**Status:** queda relevante

**Seu alvo:** R$ 31,50

Ver gráfico: `[link-do-sistema]`
