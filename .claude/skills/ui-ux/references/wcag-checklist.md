# Checklist WCAG 2.2 AA — Me Avisa Aí

Use antes de dar uma tela como pronta. Está organizado pelo que costuma quebrar
**neste** app (cartões clicáveis, números coloridos, gráficos, formulário de
preço), não pela ordem da norma. O número entre parênteses é o critério WCAG.

## Perceptível

- [ ] **Cor nunca sozinha (1.4.1)**: variação tem sinal `+`/`−` e a palavra
      "hoje"; sinal de compra/queda tem texto no badge; linha do gráfico tem
      rótulo, não só cor ou tracejado.
- [ ] **Contraste de texto (1.4.3)**: 4,5:1, e 3:1 só para texto ≥ 24px, ou ≥ 18,66px
      em negrito. Rodar `scripts/check_contrast.py` após mexer em token.
      Texto sobre `*-soft` conta como fundo composto.
- [ ] **Contraste de não-texto (1.4.11)**: borda de input, anel de foco,
      estado do `Switch`/`Checkbox`, linhas do gráfico que carregam dado: 3:1.
- [ ] **Gráfico tem alternativa (1.1.1)**: resumo em texto + tabela opcional.
      Sparkline decorativa → `aria-hidden="true"`.
- [ ] **Ícone decorativo** (`lucide-react`) ao lado de texto → `aria-hidden`.
      Botão só com ícone → `aria-label` com a ação.
- [ ] **Estrutura (1.3.1)**: um `h1` por página; `h2` para seções e títulos
      de cartão; listas em `<ul>`; tabela de dados em `<table>` com `<th scope>`.
- [ ] **Zoom e reflow (1.4.4, 1.4.10)**: em 320px de largura e zoom 200% não há
      rolagem horizontal; nada de altura fixa cortando texto.
- [ ] **Espaçamento de texto (1.4.12)**: nada quebra com `line-height: 1.5` e
      `letter-spacing: .12em` (evitar `truncate` em informação).
- [ ] **Idioma**: `<html lang="pt-BR">`; páginas de erro/404 em português.

## Operável

- [ ] **Teclado (2.1.1)**: tudo funciona com Tab/Shift+Tab/Enter/Espaço/Esc;
      diálogos prendem o foco e devolvem ao botão que abriu.
- [ ] **Ordem de foco (2.4.3)** segue a ordem visual, inclusive no layout
      mobile onde blocos mudam de lugar (reordenar no DOM, não com `order`).
- [ ] **Foco visível (2.4.7) e não escondido (2.4.11)**: `focus-visible:ring-2
      ring-ring ring-offset-2`; cabeçalho fixo não cobre o elemento focado
      (`scroll-padding-top`).
- [ ] **Pular para o conteúdo (2.4.1)**.
- [ ] **Títulos de página (2.4.2)**: `"MXRF11 — Me Avisa Aí"`.
- [ ] **Nome do link faz sentido (2.4.4)**: "MXRF11", não "Ver mais".
- [ ] **Alvo de toque (2.5.8)**: ≥ 24×24px; botões de ícone usam `size="icon"`
      (36px).
- [ ] **Movimento (2.3.3 boa prática)**: animações com `motion-safe:`;
      gráficos com `isAnimationActive={false}`.
- [ ] **Nada atualiza sozinho de forma que atrapalhe (2.2.2)**: atualização
      periódica de cotação não move o foco nem reordena a lista enquanto o
      usuário interage.

## Compreensível

- [ ] **Rótulo em todo campo (3.3.2)**: `<Label htmlFor>` ligado ao `id`;
      `Switch` e `RadioGroupItem` também.
- [ ] **Erro identificado (3.3.1) e com sugestão (3.3.3)**: mensagem em texto
      perto do campo, ligada por `aria-describedby`, `aria-invalid="true"`,
      e foco vai para o primeiro campo com erro ao enviar.
- [ ] **Formato de número aceito**: campo de preço aceita `9,50`, `9.50` e
      `R$ 9,50`; mostra sempre com vírgula.
- [ ] **Sem mudança de contexto ao focar/digitar (3.2.1, 3.2.2)**: trocar o
      `RadioGroup` de alvo para faixa não salva nem navega.
- [ ] **Navegação consistente (3.2.3)**: sidebar igual em todas as páginas.
- [ ] **Ação destrutiva confirmada (3.3.4 boa prática)**: remover favorito
      pede confirmação.

## Robusto

- [ ] **Nome, função, valor (4.1.2)**: componentes shadcn/Radix já trazem
      ARIA. Não trocar `<button>` por `<div onClick>`.
- [ ] **Mensagens de status (4.1.3)**: "Salvo", "Buscando…", "Nenhum
      resultado", "Cotações atualizadas" em `role="status"` / `aria-live="polite"`;
      erro bloqueante em `role="alert"`.

## Como testar rápido

1. `python3 .claude/skills/ui-ux/scripts/check_contrast.py resources/css/app.css`
2. Navegar a tela inteira só com teclado.
3. DevTools → Lighthouse (Acessibilidade) ou extensão axe DevTools: zero
   violações sérias.
4. Largura 320px + zoom 200%.
5. Leitor de tela (Orca no Linux, NVDA no Windows) no fluxo principal:
   favoritar → definir alvo → salvar.
6. Teste automatizado: `vitest-axe` / `jest-axe` nos componentes de página
   (`expect(await axe(container)).toHaveNoViolations()`), e Pest Browser
   (`assertNoAccessibilityIssues()`) se estiver disponível na versão instalada.
