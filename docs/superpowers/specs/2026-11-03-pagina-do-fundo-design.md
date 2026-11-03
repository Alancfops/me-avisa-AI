# Página do fundo: design

Data: 2026-11-03. Complementa `2026-10-29-modulo-fiis-design.md`, que continua valendo
para fontes, regras e antispam. Rascunho visual aprovado em
`.superpowers/brainstorm/*/content/pagina-fundo-v2.html` (fora do git).

## Intenção

A página de um FII favorito responde uma pergunta: **"compro mais agora ou espero?"**.
Para decidir, o usuário olha primeiro o **P/VP** e se o **preço está abaixo da média** de
6 a 12 meses, porque quer ver o progresso do fundo. Depois quer os **dados do fundo**
(segmento, administrador, patrimônio) e os **dividendos em R$ por cota**.

## Metas

1. Resumo no topo com quatro números: P/VP com a mediana de 12 meses, preço × média de
   6 meses, preço × média de 12 meses e último rendimento por cota.
2. Gráfico de preço com períodos de 3, 6 e 12 meses, mostrando se subiu ou caiu.
3. Gráfico de rendimento por cota dos últimos 12 meses, em R$.
4. Cartão "Sobre o fundo".
5. Manter "Quando me avisar" e "Situação das regras" como estão.

## Fora do escopo

- Separar as listas (todos os FIIs × favoritos): outro design, depois deste.
- Gestora: a CVM só informa o administrador e o usuário disse que a gestora não é
  importante.
- Fonte nova de dados (B3 COTAHIST, Fnet, plano pago da brapi).
- Mudar regras de alerta ou limites: continuam como em `docs/fiis/alert.md`.

## O que seria um fracasso

- Mostrar um número como se fosse completo quando o histórico ainda é curto
  (média de 12 meses com 3 meses de dados sem avisar).
- Mostrar rendimento estimado como se fosse o oficial.
- Gastar mais requisições da brapi do que hoje.
- Fugir da paleta, das fontes e do estilo de gráfico do app, ou reprovar no WCAG 2.2 AA.

## Restrições

- **brapi grátis:** histórico de só 3 meses. Dividendos em R$ exigem o plano Startup
  (R$ 119,99/mês; testado em 2026-11-03 com KNRI11, resposta 403
  `FEATURE_NOT_AVAILABLE`). Nenhuma chamada nova à brapi.
- **Histórico de preço cresce com o uso:** o app grava um fechamento por pregão
  (`daily_prices`). Os 12 meses só ficam completos depois de um ano. O usuário aceitou
  isso.
- **CVM:** o importador já lê `geral` e `complemento` lendo só as colunas usadas, por
  causa do limite de memória (commit `a85b902`). As colunas novas entram do mesmo jeito.
- Paleta, fontes, `surface` e badges de `resources/css/app.css`; regras da skill `ui-ux`.

## Decisões do usuário

- O gráfico começa com o que houver (3 meses) e cresce até 12. Sem fonte nova para
  preencher o ano.
- Dividendos em **R$ por cota, estimados**, sempre com o rótulo "estimado". Fonte
  oficial fica para depois, se a estimativa se mostrar ruim.
- "Administrador" no lugar de gestora, com esse nome.
- Gráfico de P/VP diário só com os preços guardados, crescendo como o de preço. O
  rascunho mostrava 24 meses, o que exigiria fonte nova de preço; o usuário escolheu
  esta forma em vez de trocar por VP por cota ou tirar o gráfico.
- Visual do rascunho 2 aprovado ("isso aí, gostei"): paleta e gráfico do app, quatro
  cartões no resumo, gráfico de preço com 3m/6m/12m, gráfico de P/VP, barras de
  rendimento e "Sobre o fundo" na lateral.

## Dados

### Novas colunas da CVM

| Dado | Arquivo | Coluna |
|---|---|---|
| Segmento | `geral` | `Segmento_Atuacao` |
| Administrador | `geral` | `Nome_Administrador` |
| Em funcionamento desde | `geral` | `Data_Funcionamento` |
| Patrimônio | `complemento` | `Patrimonio_Liquido` |
| Cotistas | `complemento` | `Total_Numero_Cotistas` |

Conferidas no arquivo de 2026 (KNRI11: Multicategoria, INTRAG DISTRIBUIDORA DE TÍTULOS
E VALORES MOBILIÁRIOS LTDA, 2010-08-11). Valem as mesmas regras de versão do VP: maior
`Versao` da competência; "Sobre o fundo" usa o informe mais recente e diz de qual mês é.

### Médias de 6 e 12 meses

- Média dos fechamentos guardados nos últimos 6 e 12 meses de calendário, sem o preço ao
  vivo de hoje (mesmo critério da média de 30 dias).
- **Histórico incompleto:** se o fechamento mais antigo guardado é mais novo que o início
  da janela, o cartão não mostra a média; mostra "Juntando histórico: N de M meses".
  (Decisão do Claude: o rascunho mostrava a média de 6m com 4 meses; vale este texto.)

### Cartão de P/VP com histórico curto

Segue `alert.md`: com menos de 60 pregões de P/VP calculável, o cartão compara com
**P/VP 1,0** e diz "comparando com P/VP 1,0 até juntar histórico", como a regra
automática faz. Com 60 ou mais, compara com a mediana de 12 meses do que houver.

### Rendimento por cota (estimado)

- R$ por cota do mês = `Percentual_Dividend_Yield_Mes` × `Valor_Patrimonial_Cotas` do
  mesmo informe. A CVM grava o percentual como fração (`0.006725` = 0,67%).
- Exemplo real, KNRI11 em 2026: R$ 1,10 na maioria dos meses, R$ 0,88 em janeiro e
  R$ 1,38 em junho. Não conferido com o valor oficial.
- O mês mostrado é a **competência** do informe, não a data de pagamento; a tela diz
  isso.
- Gráfico: 12 últimas competências com informe. Total de 12 meses e DY 12m (já existe)
  acima das barras.

## Tela

Mesma estrutura de `resources/js/pages/funds/show.tsx`: conteúdo em 2/3 e lateral em 1/3
no `lg`; no celular, a ordem atual ("Quando me avisar" logo depois do preço) continua.

1. **Cabeçalho:** como hoje (ticker, badges, nome, preço, variação do dia, horário). O
   segmento entra ao lado do nome.
2. **Resumo:** quatro cartões `surface` (2 colunas no celular, 4 no `md`).
3. **Preço:** gráfico de área com o estilo do protótipo (`docs/prototipo-lovable/routes/fundo.$ticker.tsx`):
   área `--primary` com degradê, grade só horizontal em `--border`, eixos em
   `--muted-foreground`, média do período tracejada, alvo em `--signal` quando o modo é
   preço alvo. Botões 3m/6m/12m (padrão 6m). Acima do gráfico, a variação do período
   ("−4,1% no período · de R$ 158,90 para R$ 152,40"). Período sem histórico aparece
   apagado com "ainda sem histórico" e uma frase explicando que o app guarda um
   fechamento por dia. Vale para qualquer período maior que o histórico guardado,
   inclusive o 6m padrão nos primeiros meses: a parte guardada aparece, o resto fica
   apagado, e a média tracejada usa só o que há (decisão do Claude).
   Abaixo do gráfico: "Média de 30 pregões: R$ X", que a regra automática usa e hoje
   está no cartão "Dados do fundo" (decisão do Claude).
4. **P/VP ao longo do tempo:** linha com o P/VP de cada pregão guardado (fechamento ÷ VP
   vigente naquele dia, pela `Data_Entrega`, como a mediana já faz). Mesmo estilo e
   mesmos períodos do gráfico de preço, com referência tracejada em 1,0. Começa com o
   histórico guardado e cresce até 12 meses; período sem dados aparece apagado.
5. **Rendimento por cota:** barras por mês, a última destacada, com a nota de estimativa.
6. **Cartão "Dados do fundo" de hoje:** sai. P/VP e mediana vão para o resumo, DY 12m
   para o rendimento, média de 30 pregões para o gráfico de preço. "Situação das regras"
   continua na coluna principal, depois do rendimento.
7. **Lateral:** "Sobre o fundo" (segmento, administrador, patrimônio, cotistas, desde,
   mês do informe), depois "Quando me avisar" e "Remover", sem mudanças.

Cada gráfico tem alternativa em texto para leitor de tela (resumo do período ou tabela
oculta) e não depende só de cor.

## Deixado para quem implementar

- Biblioteca de gráfico: Recharts, a mesma do protótipo e a usada pelo `chart` do
  shadcn/ui (ainda não instalada).
- Onde guardar as colunas novas (na `fund_reports` ou numa tabela de dados do fundo) e
  como montar os dados dos gráficos nos casos de uso, seguindo a skill `backend`.
- Formato do patrimônio ("R$ 4,62 bi") e dos cotistas ("301 mil").
