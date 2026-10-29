# Protótipo do Lovable (2026-10-29)

Referência **visual** do front, exportada do Lovable. Não é código para copiar:
usa TanStack Router e dados fictícios (`lib/fiis.ts`), e tem problemas de
acessibilidade conhecidos. A versão que vale está na skill
`.claude/skills/ui-ux/` (`SKILL.md` → seção "O que corrigir do protótipo",
`references/telas.md` e `references/tokens.css`).

| Arquivo | Tela |
|---|---|
| `routes/__root.tsx` | Casca: sidebar, cabeçalho, tema, 404/erro |
| `routes/index.tsx` | Painel (resumo + cartões de FII) |
| `routes/fundo.$ticker.tsx` | Detalhe do fundo (gráfico + "Quando me avisar") |
| `routes/alertas.tsx` | Histórico de avisos |
| `routes/estilo.tsx` | Guia de estilo |
| `components/` | Sidebar, badge de sinal, botão de tema |
| `styles.css` | Tokens originais (o tema claro reprova em contraste) |
