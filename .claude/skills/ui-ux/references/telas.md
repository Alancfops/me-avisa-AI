# Telas do Me Avisa Aí

Visão de como o front deve ficar, tela a tela. A base é o protótipo do Lovable
(2026-10-29), mas ele é **ponto de partida, não contrato**: onde o protótipo
fere a WCAG ou a experiência, vale o que está aqui.

Sumário: [Casca](#casca-layout) · [Painel](#painel-) · [Fundo](#fundo-fundoticker) ·
[Favoritar](#favoritar-diálogo) · [Avisos](#avisos-avisos) · [Configurações](#configurações-configuracoes) ·
[Guia de estilo](#guia-de-estilo-estilo) · [Estados comuns](#estados-comuns)

---

## Casca (layout)

- **Sidebar** (shadcn `Sidebar collapsible="icon"`): logo (sino em quadrado azul +
  "Me Avisa Aí"), itens **Painel**, **Avisos**, **Configurações**. No celular vira
  gaveta (`Sheet`) aberta pelo `SidebarTrigger`.
- **Rodapé da sidebar**: status do Telegram, vindo do backend, nunca fixo.
  Conectado → pílula verde "Telegram conectado". Desconectado → pílula neutra
  "Telegram desconectado" com link para Configurações. Usar `role="status"`.
- **Cabeçalho** (h-14): `SidebarTrigger` à esquerda; à direita, o horário da
  última cotação ("Cotações de 14:15") e o botão de tema.
- **Link "Pular para o conteúdo"** como primeiro foco da página (2.4.1), visível
  ao receber foco.
- `<main id="conteudo">` com largura máxima (`max-w-6xl`, `max-w-3xl` em listas)
  e `p-4 md:p-8`.
- **Tema**: o padrão segue `prefers-color-scheme`; a escolha manual fica salva.
  Aplicar a classe `dark` antes da pintura (script inline no `<head>` do Blade)
  para não piscar. O botão diz a ação: `aria-label="Usar tema claro"` /
  `"Usar tema escuro"`.

## Painel (`/`)

Objetivo: em 3 segundos, saber se **algum fundo está com preço bom** e se
**algo caiu forte hoje**.

1. **Cabeçalho**: data por extenso pequena e cinza ("Quinta, 29 de outubro"),
   `h1` "Seus fundos", botão principal **"Favoritar FII"** (abre o diálogo).
2. **Três números** (`Stat`): Favoritos · Com preço bom agora (verde) ·
   Quedas fortes hoje (vermelho). Cada número tem rótulo em texto; a cor reforça,
   não informa sozinha.
3. **Grade de cartões** (1 coluna no celular, 2 em `md`, 3 em `lg`), ordenada:
   primeiro preço bom, depois queda forte, depois observando. Cada cartão:
   - ticker (`h2`, Sora) + nome · segmento (cinza);
   - **badge de sinal** no canto, dizendo *qual regra* bateu (ver SKILL.md);
   - preço grande (`num`), variação do dia com sinal e a palavra "hoje";
   - sparkline de 30 dias, **decorativa** (`aria-hidden`), porque a informação
     já está no texto;
   - rodapé com 3 mini-dados: **Alvo** (alvo `R$ 9,50`, faixa `R$ 150–156`
     ou "Automático"), **P/VP**, **DY 12m**.
   - Cartão com preço bom ganha `surface-signal`.
   - **Link**: o nome acessível do link é só o ticker. Usar o padrão "link
     esticado" (link no ticker + `after:absolute after:inset-0`) em vez de
     embrulhar o cartão inteiro em `<a>`, para o leitor de tela não ler o
     cartão todo como nome do link. Foco visível no cartão (`focus-within:ring`).
   - Animação de hover (`-translate-y-0.5`) só com `motion-safe:`.

## Fundo (`/fundos/{ticker}`)

Objetivo: entender o momento do fundo e **ajustar quando ser avisado**.

1. "← Voltar" para o Painel.
2. **Topo**: `h1` ticker + badge; nome · segmento; à direita (abaixo no celular)
   preço grande + variação do dia + horário da cotação.
3. **Gráfico 90 dias** (2/3 da largura em `lg`):
   - área azul, linha tracejada da **média 30d** e linha verde do **alvo**
     (ou faixa sombreada), cada linha com **rótulo de texto** na ponta, não só
     legenda por estilo de traço;
   - eixos com `R$` no padrão pt-BR, tooltip em `brl()`;
   - alternativa em texto logo abaixo: frase-resumo ("Nos últimos 90 dias
     variou de R$ 9,10 a R$ 10,02; hoje está 2% abaixo da média de 30 dias") e
     um botão "Ver dados em tabela" que abre a tabela da série (1.1.1, 1.3.1);
   - abaixo: Média 30d · P/VP · DY 12 meses (deixar claro que é 12 meses) ·
     data do último informe da CVM em texto pequeno.
4. **"Quando me avisar"** (1/3 da largura em `lg`; no celular vem **logo
   depois do topo**, antes do gráfico, porque é a ação principal):
   - escolha do tipo de referência, com `RadioGroup`: **Preço alvo** ·
     **Faixa de compra** · **Automático** (média 30d × 0,95 e P/VP < 1);
   - campos conforme a escolha: alvo (um campo) ou faixa (mínimo e máximo);
     ver "Formulários" no SKILL.md;
   - `Switch` "Queda forte no dia (3% ou mais)", com `Label` ligado ao switch;
   - nota fixa em caixa cinza: "No máximo 1 aviso por fundo a cada 24h";
   - botão "Salvar" em largura total; sucesso confirmado por toast **e**
     mensagem em `role="status"`;
   - ação secundária no fim: "Remover dos favoritos" (`variant="ghost"`,
     vermelho), com confirmação em `AlertDialog`.

## Favoritar (diálogo)

Não existe no protótipo; precisa existir.

- `Dialog` com `CommandInput` (shadcn `Command`), em padrão combobox: digita
  ticker ou nome, lista mostra "MXRF11 — Maxi Renda · Papel" e preço.
- Busca com debounce (~300 ms); estados: digitando · carregando ("Buscando…"
  em `aria-live`) · nenhum resultado ("Nenhum FII encontrado. Confira o
  código na B3.") · erro da fonte de dados.
- Fundo já favoritado aparece marcado e desabilitado ("Já está nos favoritos").
- Ao escolher, vai direto para a tela do fundo, com o foco no bloco
  "Quando me avisar", para configurar na hora.

## Avisos (`/avisos`)

Histórico do que foi mandado ao Telegram.

- `h1` "Avisos" + subtítulo "O que já foi mandado pro seu Telegram."
- Lista (`<ul>`) em um cartão com divisórias, agrupada por dia (**Hoje**,
  **Ontem**, **27 de outubro**) com subtítulos `h2`.
- Cada item: ícone do tipo de aviso (decorativo), ticker em destaque + tipo
  ("Alvo atingido", "Queda forte", "Regra automática", "Faixa de preço"),
  texto completo (quebra linha, sem `truncate` escondendo informação), badge,
  `<time datetime="…">14:02</time>`. Link para o fundo.
- Filtro opcional por fundo e por tipo (`ToggleGroup`), com a contagem.
- Estado vazio: "Nenhum aviso ainda. Assim que um fundo bater uma regra, ele
  aparece aqui e no seu Telegram."

## Configurações (`/configuracoes`)

- **Telegram**: status da conexão, chat vinculado e passo a passo para
  vincular (abrir o bot, mandar `/start`, colar o código). Botão "Enviar aviso
  de teste".
- **Checagem de preços**: intervalo (somente leitura, ex.: "a cada 15 min,
  10h–18h em dias úteis") e horário da última checagem bem-sucedida.
- **Tema**: Sistema · Claro · Escuro (`RadioGroup`).
- **MCP**: como conectar o Claude ao app (endereço local e token), com botão
  copiar.

## Guia de estilo (`/estilo`)

Página interna, só em ambiente local. Mostra as cores com o par de contraste
medido, as fontes, botões, badges, cartão de FII e campos de formulário nos
dois temas. É a vitrine para conferir o visual depois de mudar tokens.

## Estados comuns

Toda tela que mostra dados precisa ter os quatro:

| Estado | Como fica |
|---|---|
| Carregando | `Skeleton` com o formato do conteúdo; `aria-busy="true"` no container |
| Vazio | ilustração mínima (ícone), uma frase do que fazer e o botão da ação ("Favoritar FII") |
| Erro | mensagem humana do que houve e do que fazer ("Não conseguimos atualizar as cotações. Tentaremos de novo às 14:30."), sem stack trace |
| Dados velhos | cotação com mais de 30 min no pregão, ou mercado fechado: aviso discreto "Mercado fechado — última cotação de sexta, 17:55" |
