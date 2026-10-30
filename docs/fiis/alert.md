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
- **Fechamento do dia:** a barra de hoje só vale como fechamento a partir das 19:00 (São Paulo). Antes disso ela é o preço ao vivo.
- **Mínimo de 30 fechamentos:** com menos, a média não existe, e a regra não dispara e diz por quê.
- **Fechamentos velhos:** com mais de 2 dias úteis sem fechamento antes da cotação, a média não vale, e a regra não dispara e diz por quê. O limite cobre o carnaval (sexta a quarta), porque o app não tem calendário de feriados.
- **Contagem dos 60 pregões:** contam os dias da janela de 12 meses com P/VP calculável, ou seja, com fechamento e informe já entregue.

---

### 4. Queda brusca

**Regra:** queda **diária** superior a **3%** (variação do pregão inferior a −3%; exatamente −3% não dispara). Cada pregão é um evento novo.

**Variação:** preço ÷ fechamento anterior − 1, com os números da própria cotação. O percentual da brapi vem arredondado para 2 casas e só é usado se o fechamento anterior faltar.

---

## Regras de controle (anti-spam)

Valem **por regra de cada favorito**: uma queda brusca de manhã não impede o aviso de preço alvo à tarde.

- O alerta nasce quando a regra **passa a valer**. Se ela deixa de valer, um novo cruzamento gera novo alerta.
- No máximo **1 alerta por regra de cada FII a cada 24 h**.
- Antes disso, ou enquanto a regra continua valendo, reenviar **somente** se preço ≤ preço do último alerta daquela regra × **0,98**.
- Alerta bloqueado pelo limite de 24 h sai quando o limite liberar, se a regra ainda valer.
- Mudar a configuração do favorito reavalia na hora e começa um ciclo novo para a regra alterada.
- **Sem dado não é deixar de valer:** a regra que não pôde ser avaliada mantém o ciclo, para não repetir o aviso quando o dado voltar.

---

## Recuperação de dados perdidos

A agenda não refaz o que perdeu com a máquina desligada. A cada 30 minutos, em qualquer dia, o app confere:

- **Fechamentos:** se o último é anterior ao pregão esperado (o dia útil anterior), busca o histórico de novo, uma vez por dia por fundo; se a fonte falhar, tenta na rodada seguinte. Se seguirem velhos, a falha aparece na Saúde.
- **Informes da CVM:** se a última importação tem mais de 26 h, importa de novo, com 2 h entre tentativas que falharam.

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
