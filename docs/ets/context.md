# ETFs — Contexto Completo

## O que são ETFs

ETFs (*exchange-traded funds*) são fundos negociados em bolsa cuja **cota** acompanha (com pequeno desvio) um **índice** ou cesta de ativos. O investidor compra e vende a cota como uma ação; o desempenho não replica o índice ponto a ponto por causa de taxas, caixa e *tracking error*.

Exemplos na B3:

- BOVA11 → Ibovespa (referência comum; conferir prospecto do fundo)
- IVVB11 → exposição ao S&P 500 (via estrutura do fundo; conferir documento)

---

## Características

- Diversificação automática
- Baixo custo
- Gestão passiva (na maioria)

---

## Indicadores principais

### Preço

Valor da cota

---

### Índice de referência

Define o comportamento do ETF

---

### Taxa de administração

Custo do fundo

---

### Tracking Error

Diferença entre ETF e índice

---

## Tipos de ETFs

### Renda variável

- Ações (Ibovespa, S&P 500)

### Setoriais

- tecnologia, bancos, etc.

### Internacionais

- exposição ao exterior

---

## Regras de análise

### Tendência

- alta ou baixa do índice

### Queda relevante

- queda diária ou semanal acima dos limiares definidos no produto (ex.: 3% / 5% — ver `docs/ets/alert.md`)

---

## Riscos

- segue o mercado (não escolhe ativos)
- pode cair forte em crises
- câmbio (ETFs internacionais)

---

## Uso no sistema

Cada ETF deve conter:

- ticker
- preço
- índice
- histórico
- taxa

---

## Objetivo no sistema

- acompanhar mercado geral
- diversificação
- análise de tendências
