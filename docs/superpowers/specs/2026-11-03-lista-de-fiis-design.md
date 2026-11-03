# Lista de FIIs, fechamentos da B3 e preço teto: design

Data: 2026-11-03. Complementa `2026-10-29-modulo-fiis-design.md` e
`2026-11-03-pagina-do-fundo-design.md`, que continuam valendo. Rascunho visual aprovado
em `.superpowers/brainstorm/*/content/lista-fiis.html` (fora do git).

## Intenção

Hoje o app só mostra os favoritos. O usuário quer **ver os outros fundos**, entender
rapidamente como cada um está e **favoritar** o que gostar, sem sair da lista. Para
decidir se vale abrir um fundo, ele olha **P/VP, DY e tipo (tijolo ou papel)**, e quer
filtrar por tipo e por **base 10 / base 100**.

Junto entra o **preço teto pelo rendimento** na página do fundo: "para render X% ao ano,
este fundo precisa estar abaixo de R$ Y", com um botão que usa esse valor como alvo.

## Metas

1. Página "Todos os FIIs" com os fundos negociados: ticker, nome, tipo, segmento,
   preço, P/VP, DY 12m e botão de favoritar.
2. Filtros por tipo e por base, busca por ticker ou nome, ordenação.
3. Página de qualquer fundo do catálogo, favorito ou não.
4. Fechamentos diários de todos os FIIs pela B3, sem gastar requisição da brapi, com 12
   meses de histórico preenchidos desde o primeiro dia.
5. Preço teto pelo rendimento na página do fundo.

## Fora do escopo

- P/VP do segmento ou comparação com pares.
- Aviso de corte de rendimento (mexe nas regras; só depois de uso real).
- Mudar regras de alerta, cotação a cada 30 min dos favoritos ou o painel.
- Tirar a brapi do histórico dos favoritos (fica como está; ver "Decisões do Claude").

## O que seria um fracasso

- Gastar requisições da brapi com fundos que não são favoritos.
- Mostrar o preço do fechamento como se fosse cotação ao vivo, sem dizer a data.
- Classificar papel como tijolo (como o segmento da CVM faz com o MXRF11) sem avisar.
- Lista lenta ou pesada a ponto de travar a página.
- Um dia sem arquivo da B3 (feriado, atraso) virar erro na tela ou buraco permanente.

## Restrições

- **brapi grátis:** 15.000 requisições por 30 dias, 1 ativo por requisição. Só os
  favoritos usam a brapi.
- **B3, série histórica (COTAHIST):** testada em 2026-11-03.
  - Diário: `https://bvmf.bmfbovespa.com.br/InstDados/SerHist/COTAHIST_DDDMMAAAA.ZIP`
    (ex. `COTAHIST_D30102026.ZIP`, ~590 KB, 286 FIIs). O arquivo do dia ainda não
    existia durante o pregão (404): sai depois do fechamento, horário não confirmado.
  - Anual: `COTAHIST_AAAAAA.ZIP` (`COTAHIST_A2026.ZIP` ~90 MB em zip). Ler em stream,
    linha a linha, como o importador da CVM faz por causa do limite de memória.
  - Layout posicional, Latin-1. Registro `01`; data em 3–10 (`AAAAMMDD`); `CODBDI` em
    11–12 (`12` = FII); ticker em 13–24; tipo de mercado em 25–27 (`010` = à vista);
    fechamento `PREULT` em 109–121, com 2 casas implícitas.
  - Conferido: fechamentos de 30/10 de KNRI11 (150,50), MXRF11 (8,88) e GARE11 (8,20)
    iguais aos gravados pela brapi.
- **CVM, composição da carteira:** arquivo `inf_mensal_fii_ativo_passivo_AAAA.csv` do
  mesmo zip já baixado. Ler só as colunas usadas.
- Paleta, componentes e WCAG 2.2 AA como na página do fundo (skill `ui-ux`).

## Decisões do usuário

- Preço da lista pela B3, não pela brapi (ele aprovou o teste e a proposta).
- Lista com P/VP, DY 12m, segmento e tipo; favoritar na própria lista.
- Filtros por tipo e por base 10 / base 100, com corte em **R$ 50** no preço da cota na
  bolsa (aprovou o rascunho que dizia isso).
- Clicar num fundo abre a página dele, mesmo sem ser favorito (aprovou o rascunho).
- Preço teto pelo rendimento junto com esta entrega.

## Dados

### Fechamentos da B3

- Importar o arquivo diário de cada pregão para **todos os FIIs do catálogo**
  (`CODBDI` 12, mercado `010`), gravando em `daily_prices` (único por ticker e dia).
- Ticker fora do catálogo é ignorado. Fechamento zero ou inválido é descartado.
- Agendamento: tentar depois do fechamento e de novo na manhã seguinte; dia sem arquivo
  (404) não é erro se for fim de semana ou feriado, e é tentado de novo na rodada
  seguinte. Buscar todos os dias úteis faltando nos últimos 10, para cobrir a máquina
  desligada. Falhas aparecem na Saúde, como as outras fontes.
- **Primeira carga:** arquivos anuais do ano corrente e do anterior, para ter 12 meses
  desde o primeiro dia. Só uma vez (ou sob comando), não a cada dia.

### Tipo do fundo

Pela composição da carteira no informe mais recente (`ativo_passivo`), em fração de
`Total_Investido`:

| Tipo | Regra |
|---|---|
| Tijolo | `Direitos_Bens_Imoveis` > 50% |
| Papel | `CRI` + `CRI_CRA` + `LCI` + `LCI_LCA` + `Letras_Hipotecarias` + `LIG` > 50% |
| Fundo de fundos | `FII` > 50% |
| Híbrido | nenhum acima de 50% |
| Sem dados | sem informe ou `Total_Investido` ≤ 0 |

Exemplos reais de set/2026: KNRI11 90% imóveis → tijolo; MXRF11 75% CRI → papel.
A tela mostra o tipo com a origem ("pela carteira informada à CVM").

### Valores da lista

- **Preço:** último fechamento da B3, com a data ("fechamento de 30/10").
- **P/VP:** esse fechamento ÷ VP do informe vigente naquele dia (regra de vigência
  existente). Ticker ambíguo (mais de um fundo no mesmo ISIN) fica sem P/VP, como hoje.
- **DY 12m:** soma dos 12 últimos DY mensais (regra existente).
- **Base:** "Base 10" quando o preço < R$ 50; "Base 100" quando ≥ R$ 50. Sem preço, fica
  fora dos dois filtros.
- Fundos sem fechamento nos últimos 10 dias úteis (não negociados) não aparecem.

### Preço teto pelo rendimento

- Preço teto = soma do rendimento estimado por cota dos últimos 12 meses ÷ taxa anual
  desejada. Exemplo: R$ 13,35 ÷ 10% = R$ 133,50.
- Só aparece com 12 meses de rendimento estimado; senão, diz quantos meses faltam.

## Tela

### Todos os FIIs

Nova página no menu, ao lado do painel. Conforme o rascunho:

1. Título, total de fundos e a frase "preços do fechamento de {data} (B3) · VP e DY dos
   informes da CVM".
2. Filtros em cartão: busca, tipo (Todos, Tijolo, Papel, Fundo de fundos, Híbrido),
   preço da cota (Todas, Base 10, Base 100), ordenar (menor P/VP, maior DY 12m, ticker,
   maior patrimônio).
3. Tabela: fundo (ticker e nome), tipo, segmento, preço, P/VP (verde abaixo de 1,0),
   DY 12m, favoritar. No celular, tipo e segmento somem da tabela.
4. Favoritar na linha usa o mesmo fluxo de hoje (grava e importa o histórico); o botão
   vira "★ Favorito". Remover continua na página do fundo.
5. Clicar no ticker abre a página do fundo.

### Página do fundo de quem não é favorito

Mesma página da entrega anterior, com estas diferenças: preço = último fechamento da B3
com a data; sem "Quando me avisar", sem "Situação das regras" e sem "Remover"; botão
"Favoritar" no lugar.

### Preço teto

Cartão na página do fundo: campo "Quero render (% ao ano)", resultado "Preço teto:
R$ X" e comparação com o preço atual ("R$ 4,30 acima" ou "abaixo"). Em favoritos, botão
"Usar como alvo", que preenche o alvo em "Quando me avisar" (não salva sozinho).

## Decisões do Claude

- Os favoritos continuam com o histórico da brapi (`fiis:record-closes`); a B3 grava
  nos mesmos dias com o mesmo valor. Tirar a brapi do histórico fica para depois.
- Corte de 50% para o tipo e a lista de colunas de "papel".
- Fundos sem fechamento nos últimos 10 dias úteis ficam fora da lista.
- A taxa do preço teto começa em 10% e não é salva no banco (só no navegador, se for
  fácil).
- Filtros e ordenação no front, com a lista inteira (~300 linhas) numa só resposta.
- **Universo da lista:** fundos do catálogo (`funds`, sincronizado da brapi) com
  fechamento da B3. Ticker da B3 fora do catálogo é ignorado; o catálogo continua sendo
  a fonte dos tickers válidos.
- **Tipo por fundo da CVM:** colunas da composição gravadas em `fund_reports` (mesma
  chave CNPJ + competência, maior `Versao`, lidas no mesmo `ImportReports`, só as
  colunas da tabela "Tipo do fundo" mais `Total_Investido`). O tipo do ticker vem da
  competência mais recente que tem composição. Ticker ambíguo fica "Sem dados", como o
  VP.
- **Primeira carga:** comando `fiis:import-b3-history` (anuais do ano corrente e do
  anterior), disparado sozinho pelo agendador quando ainda não houve carga completa e
  disponível para rodar à mão. Grava com upsert: o valor da B3 prevalece no mesmo dia.
- **Campos nulos na lista:** sem informe, P/VP, DY e tipo mostram "—" ("Sem dados" no
  tipo); na ordenação, nulos vão para o fim.
- **Favoritar na lista:** mesmo pedido de hoje (síncrono, uma requisição da brapi para
  a cotação e o histórico); o botão mostra estado de carregando e não deixa clicar duas
  vezes.
- **Preço teto:** usa o `EstimatedDividends` existente (só competências com VP e DY
  entregues). Compara com a cotação para favoritos e com o último fechamento da B3 para
  os outros.

## Deixado para quem implementar

- Onde guardar o tipo (coluna em `fund_reports` ou calculado na leitura).
- Nome da rota e do item do menu.
- Mensagem exata dos estados vazios e de "sem preço".
