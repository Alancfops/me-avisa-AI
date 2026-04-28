# Alertas — ETFs

## Objetivo

Monitorar movimentos relevantes da **cota do ETF** (que acompanha um índice ou cesta) e oportunidades de entrada alinhadas ao preço alvo do usuário.

---

## Ativação de alertas

- O ETF deve estar **favoritado**.
- Pode ter **preço alvo** definido pelo usuário.

---

## Tipos de alerta

### 1. Preço alvo

**Regra:** disparar quando **preço da cota ≤ valor definido** pelo usuário.

---

### 2. Queda relevante (cota)

Monitora a variação da **cota negociada** (proxy do movimento do índice subjacente).

**Regra:**

- queda **diária** superior a **3%**, **ou**
- queda **semanal** superior a **5%**, considerando **5 pregões**.

---

### 3. Mínima histórica (52 semanas)

**Regra:** preço atual **≤ menor preço** dos últimos **12 meses** (~252 pregões), com dados disponíveis.

---

### 4. Movimento de tendência

**Regra:** rompimento de suporte ou resistência — **opcional / futuro**; depende de série histórica e definição técnica no produto.

---

## Regras de controle

- **1 alerta por ETF a cada 24 h** como padrão.
- **Exceção:** nova **mínima de 52 semanas** pode disparar fora do cooldown, se assim for definido no produto.

---

## Dados necessários

- Preço atual da cota e variação diária.
- Histórico de pelo menos **1 ano** para mínima de 52 semanas.
- Série para variação semanal.

---

## Formato do alerta

📉 **Movimento em ETF**

**Ativo:** BOVA11  
**Preço:** R$ 110,20  
**Variação:** −3,2%

**Status:** queda relevante (cota / mercado)

**Seu alvo:** R$ 108,00

Ver detalhes: `[link-do-sistema]`
