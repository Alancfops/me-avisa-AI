---
name: ui-ux
description: UI/UX e acessibilidade (WCAG 2.2 AA) do front do Me Avisa Aí — React + Inertia + Tailwind v4 + shadcn/ui dentro do Laravel. Use sempre que for criar, alterar ou revisar qualquer tela, página, componente, layout, formulário, gráfico, cor, tema claro/escuro, tipografia, estado de carregando/vazio/erro ou texto de interface do projeto, mesmo que o pedido não fale em "design" ou "acessibilidade" (ex.: "faz a página do fundo", "adiciona um filtro nos avisos", "o card tá feio", "arruma o form do alvo", "revisa essa tela"). Também use ao mexer em resources/js/pages, resources/js/components ou resources/css/app.css.
---

# UI/UX do Me Avisa Aí

O Me Avisa Aí acompanha os FIIs favoritos de uma pessoa e avisa no site quando
algum chega num preço bom. O site é onde ela **olha rápido** ("tem algo bom hoje?")
e **ajusta quando quer ser avisada** (alvo, faixa, regra automática). Toda decisão
de interface serve a essas duas coisas.

O visual tem que ser **agradável e com cara de app financeiro moderno**: limpo,
números grandes e legíveis, verde de "compra" como estrela, e nada de poluição.
E tem que passar na **WCAG 2.2 AA**, não como enfeite: o app mostra dinheiro e
sinais de compra, então um número ilegível ou um sinal que só existe na cor é
um bug de produto.

## Antes de começar

1. Leia `references/telas.md` na parte da tela que vai mexer. Ali está a visão
   de cada tela: o que mostra, em que ordem, e os estados.
2. Use os tokens de `references/tokens.css`. Não invente cor solta
   (`text-green-500`, `#22c55e`); se faltar um token, crie no CSS dos dois temas
   e rode o verificador de contraste.
3. Ao terminar, passe pelo `references/wcag-checklist.md` e rode:
   ```bash
   python3 .claude/skills/ui-ux/scripts/check_contrast.py resources/css/app.css
   ```

## Stack e onde as coisas ficam

- Laravel + **Inertia** + React + TypeScript (starter kit oficial). Páginas em
  `resources/js/pages/`, componentes em `resources/js/components/`, primitivos
  shadcn em `resources/js/components/ui/` (não editar à mão; recompor por fora).
- Navegação com `<Link>` de `@inertiajs/react`; formulários com `useForm` ou `<Form>`
  do Inertia; erros de validação chegam do Laravel em `errors`.
- Tailwind **v4** com tokens em `resources/css/app.css` (`@theme inline`).
- Ícones: `lucide-react`. Gráficos: `recharts`.
- Confira o que o starter kit instalado realmente usa antes de assumir caminho
  ou versão; se divergir, siga o projeto e atualize esta skill.

O protótipo do Lovable (TanStack Router), em `docs/prototipo-lovable/`, é
**referência visual**, não código para copiar: traduza rotas para páginas Inertia e dados fictícios para props vindas
do controller.

## Identidade visual

| Elemento | Decisão | Por quê |
|---|---|---|
| Tema | Claro e escuro; padrão segue o sistema | Uso à noite no celular é comum; escuro é "preto de verdade" |
| Fundo / cartão | Branco papel / preto, cartões `surface` (borda fina + sombra suave, `rounded-xl`) | Separa blocos sem peso visual |
| Azul (`primary`) | Ações, links, gráfico, foco | Cor de "interagir" |
| Verde (`signal`) | Preço bom / regra de compra bateu | É a notícia que o usuário quer; precisa saltar |
| Vermelho (`destructive`) | Queda forte, ação de remover | Atenção, sem alarmismo |
| Cinza (`muted-foreground`) | Rótulos e textos de apoio | Hierarquia |
| Títulos e números | **Sora** (`font-display`, utilitário `num` com `tabular-nums`) | Números alinhados em coluna e com personalidade |
| Texto corrido | **Manrope** | Legível em tamanho pequeno |

Use o verde com moderação: se tudo é verde, nada é sinal. O brilho
`surface-signal` vai só no cartão do fundo com preço bom.

## Regras de UX do domínio

**Números financeiros** sempre pelos helpers de formatação (criar em
`resources/js/lib/format.ts`):
- moeda `R$ 9,42` → `toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })`;
- variação com sinal e vírgula: `+1,2%` / `−3,6%`, sempre acompanhada de "hoje"
  ou do período;
- P/VP com 2 casas (`0,98`); DY dizendo o período (`DY 12m 12,4%`), porque mensal
  e anual confundem;
- faixa como `R$ 150–156`, nunca `150–156` solto;
- toda cotação mostra **de quando é** ("cotação de 14:15"), já que os dados podem
  ter atraso e o mercado fecha.

**Sinal do fundo** é o coração da interface. O badge diz **qual regra bateu**,
não um conselho, porque o app informa e não recomenda:

| Sinal | Texto do badge | Cor | Quando |
|---|---|---|---|
| alvo | "No seu alvo" | verde | preço ≤ alvo |
| faixa | "Na faixa de compra" | verde | preço dentro da faixa |
| automático | "Abaixo da média" | verde | modo automático: preço < média 30d × 0,95 e P/VP < mediana de 12 meses do fundo (reserva: P/VP < 1,0 com menos de 60 pregões, dito na tela) |
| queda | "Queda forte" | vermelho | variação do dia abaixo de −3%; exatamente −3% não conta (tem prioridade visual) |
| observando | "Observando" | cinza | nenhuma regra bateu |

O badge sempre tem texto e um ponto/ícone; a cor só reforça. As regras vêm de
`docs/fiis/alert.md`; se as duas divergirem, o documento vence e a skill deve ser
atualizada.

**Linguagem**: português do Brasil, frases curtas, tom de amigo que entende de
investimento. "Favoritar FII", "Quando me avisar", "Nenhum aviso ainda". Sem
jargão técnico em erro ("Não conseguimos atualizar as cotações agora", não
"Erro 502"). Botão diz a ação ("Salvar alvo"), não "OK".

## Padrões de componente

**Cartão de FII clicável**: link no ticker com o padrão de link esticado, para o
leitor de tela anunciar só "MXRF11, link" e não o cartão inteiro:

```tsx
<article className="surface relative p-5 focus-within:ring-2 focus-within:ring-ring motion-safe:transition-transform motion-safe:hover:-translate-y-0.5">
  <h2 className="font-display text-lg font-semibold">
    <Link href={`/fundos/${fii.ticker}`} className="after:absolute after:inset-0 focus-visible:outline-none">
      {fii.ticker}
    </Link>
  </h2>
  {/* resto do conteúdo; sparkline com aria-hidden="true" */}
</article>
```

**Formulários** (alvo, faixa):
- `<Label htmlFor>` em todo campo, inclusive `Switch` e cada `RadioGroupItem`;
- campo de preço: `inputMode="decimal"`, prefixo visual `R$` fora do `<input>`,
  aceitar `9,50`/`9.50`, normalizar no envio e exibir com vírgula;
- erro: texto vermelho abaixo do campo com `id`, `aria-invalid` e
  `aria-describedby` apontando para ele; ao enviar com erro, focar o primeiro
  campo inválido;
- validar coisas do domínio com mensagem útil: faixa com mínimo > máximo, alvo
  ≤ 0, alvo muito longe do preço atual (aviso, não erro);
- sucesso: toast **e** texto em `role="status"`.

**Gráficos** (`recharts`): sem animação (`isAnimationActive={false}`), cores dos
tokens (`var(--chart-1)` etc.), linhas de referência com rótulo de texto, eixos
em `R$` pt-BR, e sempre uma alternativa em texto (resumo + "Ver dados em tabela").

**Estados**: toda tela com dados tem carregando (`Skeleton`), vazio (frase + ação),
erro (o que houve + quando tenta de novo) e dados velhos/mercado fechado. Ver a
tabela no fim de `references/telas.md`.

**Responsivo**: o produto é só web e roda em localhost, sem app nativo, mas a tela
continua funcionando em janela estreita (WCAG 1.4.10). Pensar primeiro na menor. Uma
coluna no celular, grade em `md`/`lg`; na tela do fundo, o bloco "Quando me
avisar" sobe para logo depois do preço no celular, mudando a ordem no DOM e não
com `order`, para o foco seguir o visual. Nada de rolagem horizontal em 320px.

## O que corrigir do protótipo do Lovable

Se for portar algo de lá, já traga corrigido:

1. Verde, vermelho e borda de campo do tema claro reprovavam no contraste
   (3,4:1, 4,4:1 e 1,3:1); use `references/tokens.css`.
2. `Switch` das regras sem rótulo associado (4.1.2).
3. Cartão inteiro dentro do `<Link>` (nome de link gigante); usar link esticado.
4. Formulário só com alvo; falta **faixa** e a escolha entre alvo, faixa e
   automático.
5. Campo de alvo exibia `9.50` com ponto; padronizar vírgula.
6. Faixa exibida sem `R$`.
7. `truncate` escondendo o texto do aviso.
8. Páginas 404 e de erro em inglês.
9. "Telegram conectado" fixo no código; deve refletir o estado real.
10. Tema sempre escuro por padrão e trocado após a pintura (pisca); seguir o
    sistema e aplicar antes da pintura.
11. Falta: diálogo de favoritar, tela de configurações, estados de
    carregando/vazio/erro, horário da cotação, link "pular para o conteúdo".

## Ao revisar uma tela

Responda nesta ordem, curto e direto:
1. **Bloqueia AA**: item, critério WCAG, onde, correção.
2. **Atrapalha o uso**: problemas de UX (hierarquia, texto, estado faltando).
3. **Acabamento visual**: alinhamento, espaçamento, consistência com tokens.
4. Resultado do `check_contrast.py`, se tocou em cor.
