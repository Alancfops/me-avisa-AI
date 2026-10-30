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

Se o usuário **não** configurar alvo/faixa manualmente (favorito novo começa neste modo):

**Regra:**

- preço **abaixo de** média de **30 dias** × **0,95**
- **e** P/VP **abaixo da** mediana do P/VP **do próprio fundo** nos últimos **12 meses**

**Definições:**

- **Média de 30 dias:** fechamentos dos últimos 30 pregões, sem o preço ao vivo de hoje.
- **P/VP de cada dia:** fechamento ÷ VP do informe vigente naquele dia. O informe vale a partir da data de entrega à CVM.
- **Histórico curto:** com menos de 60 pregões, comparar com **P/VP abaixo de 1,0** e dizer isso na tela.
- **Sem VP:** a regra não dispara e a tela explica o motivo.

---

### 4. Queda brusca

**Regra:** queda **diária** superior a **3%** (variação do pregão inferior a −3%; exatamente −3% não dispara). Cada pregão é um evento novo.

---

## Regras de controle (anti-spam)

Valem **por regra de cada favorito**: uma queda brusca de manhã não impede o aviso de preço alvo à tarde.

- O alerta nasce quando a regra **passa a valer**. Se ela deixa de valer, um novo cruzamento gera novo alerta.
- No máximo **1 alerta por regra de cada FII a cada 24 h**.
- Antes disso, ou enquanto a regra continua valendo, reenviar **somente** se preço ≤ preço do último alerta daquela regra × **0,98**.
- Alerta bloqueado pelo limite de 24 h sai quando o limite liberar, se a regra ainda valer.
- Mudar a configuração do favorito reavalia na hora e começa um ciclo novo para a regra alterada.

---

## Dados necessários

- Preço atual (e horário da cotação).
- Histórico de preços (para média de 30 dias).
- P/VP.
- DY 12m: soma dos 12 últimos DY mensais do Informe da CVM (só exibição; nenhuma regra usa).

---

## Formato do alerta

🚨 **Oportunidade em FII**

**Ativo:** MXRF11  
**Preço atual:** R$ 9,55  
**Sua referência:** faixa R$ 9,00–R$ 9,60 (ou alvo R$ 9,60)

**P/VP:** 0,98  
**DY 12m (informes da CVM):** 11,8%

**Status:** dentro da faixa de compra ✅

Ver mais: `[link-do-sistema]`

---

## Integração com calculadora

Quando o produto incluir estimativa de renda:

> Se investir R$ 1.000 hoje, estimativa de renda mensal: R$ X

Usar a mesma convenção de DY (mensal vs. anual) do restante do sistema para evitar números incompreensíveis.
