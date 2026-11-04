# Carteira de FIIs: design

Data: 2026-11-04. Complementa `2026-10-29-modulo-fiis-design.md`,
`2026-11-03-pagina-do-fundo-design.md` e `2026-11-03-lista-de-fiis-design.md`, que
continuam valendo.

## Intenção

Hoje a calculadora "Quanto rende" da página do fundo faz a conta para um fundo de cada
vez e não guarda nada. A pessoa quer informar uma vez quantas cotas tem de cada FII e ver,
numa tela só, quanto a carteira rende por mês somando todos.

## Metas

1. Opção **Minha carteira** no grupo FIIs do menu lateral (rota `/carteira`).
2. Adicionar um FII com a quantidade de cotas, editar a quantidade e remover.
3. Uma linha por fundo: ticker, nome, cotas, preço atual, valor da posição, rendimento
   estimado por mês (pelo último rendimento e pela média de 12 meses).
4. Topo com os totais: valor da carteira e rendimento estimado do mês, pelas duas
   formas.
5. Vale para qualquer FII do catálogo, favorito ou não.

## Fora do escopo (decisões futuras)

- Preço médio pago, lucro/prejuízo e rendimento sobre o investido.
- Histórico de compras e vendas, datas, corretoras, importação de nota.
- Ações e ETFs na carteira.
- Alertas ligados à carteira. A carteira não muda favoritos nem regras.

## Anti-metas

- Parecer recomendação: os números são estimativas, ditas como estimativas.
- Mostrar R$ 0 quando falta dado: falta de dado aparece como falta.
- Gastar requisições da brapi com quem não é favorito.

## Regras

- **Cotas:** inteiro maior que zero. Um fundo aparece uma vez na carteira; adicionar um
  fundo que já está lá atualiza a quantidade.
- **Preço:** o mesmo da página do fundo: favorito usa a cotação da brapi; não favorito usa
  o último fechamento da B3. A linha diz de onde veio e de quando (como em
  `CeilingPrice`, "fechamento de 30/10 (B3)" ou "cotação de 14:30").
- **Rendimento por cota:** `EstimatedDividends::lastTwelve` (DY × VP do informe da CVM),
  o mesmo da página do fundo e da calculadora. "Último" é o mês mais recente; "média" é a
  média dos meses disponíveis, até 12, dizendo quantos quando forem menos de 12.
- **Valor da posição:** preço × cotas. Sem preço, a linha mostra "sem preço" e o fundo
  fica fora do valor total.
- **Totais:** somam só os fundos com o dado. Se algum ficou de fora, o topo diz quantos
  e por quê ("2 fundos sem rendimento informado ficaram fora do total").
- Texto fixo na tela: estimativa pelo DY e pelo VP informados à CVM; o fundo pode pagar
  outro valor.

## Como adicionar

- Na página do fundo, o cartão "Quanto rende" ganha o botão **Salvar na carteira**, que
  grava a quantidade digitada. Se o fundo já está na carteira, o campo vem preenchido e o
  botão vira **Atualizar na carteira**.
- Na tela da carteira, um botão **Adicionar FII** abre a busca do catálogo (a mesma do
  diálogo de favoritar) com o campo de cotas.
- Editar a quantidade e remover ficam na linha do fundo, na tela da carteira. Remover
  pede confirmação.

## Arquitetura

Segue o módulo `app/Modules/Fiis/` e a skill `backend`:

- **Banco:** tabela `holdings` (ticker único, ligado ao catálogo; `shares` inteiro
  positivo; timestamps).
- **Application:** `HoldingRepository` (interface), casos de uso `SaveHolding`,
  `RemoveHolding` e `ShowPortfolio`. `ShowPortfolio` monta as linhas e os totais com o
  preço e o rendimento já usados por `DescribeFund`, sem buscar nada na brapi.
- **Domain:** o cálculo dos totais (o que entra e o que fica de fora) é PHP puro e tem
  teste unitário.
- **Http:** `PortfolioController` com `GET /carteira`, `PUT /carteira/{ticker}` (cria ou
  atualiza) e `DELETE /carteira/{ticker}`, com Form Request validando as cotas.
- **Front:** página `resources/js/pages/portfolio.tsx`, item no grupo FIIs do menu,
  botão no `IncomeCalculator`. WCAG 2.2 AA como nas outras telas (skill `ui-ux`).

## Testes

- Unitário: totais com fundo sem preço, sem rendimento e com menos de 12 meses.
- Feature: salvar, atualizar, remover, validação (0, negativo, decimal, ticker fora do
  catálogo), página vazia e com fundos.
- Arquitetura: as camadas continuam separadas (`tests/Arch`).

## Decisões do Claude

Não confirmadas pela pessoa; estão aqui para conferir:

- Adicionar pela página do fundo e pela busca na carteira; editar e remover só na
  carteira.
- Valores em dinheiro calculados no backend com a mesma precisão da `Money`, e o front só
  formata.
- Ordem da tabela: maior valor de posição primeiro; sem preço no fim.
- Sem limite de quantidade de fundos.
