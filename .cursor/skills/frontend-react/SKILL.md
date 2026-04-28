---
name: frontend-react
description: Orienta UI/UX em aplicação web React com Tailwind, responsiva (mobile, tablet, desktop), mobile-first, consumo só da API REST do backend e gráficos legíveis no mobile. Usar quando o pedido envolver telas, layout responsivo, componentes, Tailwind, fetch ou visualizações neste repositório.
---

# Frontend React + Tailwind

## Plataforma: web + responsivo

- O produto é **web** (SPA ou rotas no **navegador**), não app nativo, salvo decisão futura documentada.
- **Uma base de código** deve servir **mobile, tablet e desktop** via **layout responsivo** (larguras fluidas, breakpoints, tipografia e grids que se adaptam).
- **Mobile-first:** desenhar e implementar primeiro para viewport estreito; em seguida evoluir para telas maiores (`sm`/`md`/`lg` no Tailwind).

## Quando aplicar

Trabalho em `frontend/`: páginas, componentes, hooks de dados, layout, tema, acessibilidade e gráficos.

## Regras do projeto

- **`.cursor/rules/dev-rules.mdc`** — mobile-first; ler `docs/` quando a feature tocar regras de produto.
- **`.cursor/rules/architecture.mdc`** — o front **só fala com o Rails via REST**; **não** acessar Supabase, service role nem banco direto.

## UI e UX

- Priorizar **clareza** e **ritmo visual**: hierarquia tipográfica, espaçamento consistente, estados vazios/erro/carregamento explícitos.
- Interface **bonita, intuitiva e compreensível** sem depender de textos longos; rótulos curtos e ações óbvias.
- **Evitar exageros de informação** na mesma tela: agrupar o secundário em detalhes expansíveis, abas ou página seguinte; não empilhar métricas sem prioridade.
- Contraste e tamanho de toque adequados para **mobile** (alvos tocáveis, leitura ao sol).

## React + Tailwind

- Estilização com **Tailwind**; breakpoints a partir do menor viewport (`sm:`, `md:`… só quando necessário), garantindo boa leitura tanto no **telefone** quanto no **monitor**.
- Componentes pequenos e reutilizáveis; estado e efeitos colados ao que a tela precisa.
- URL da API por **variável de ambiente**; tratar erros da API com mensagens humanas.

## Dados

- **Somente endpoints do backend** (`fetch`/client HTTP centralizado); um módulo ou hook por recurso (ex.: favoritos, cotação).
- Cache no cliente só quando fizer sentido (evitar refetch agressivo); respeitar loading e stale.

## Gráficos

- **Responsivos** e de **fácil legibilidade**, principalmente no **mobile**:
  - usar container que redimensiona com o viewport (ex.: `ResponsiveContainer` se usar Recharts);
  - poucas séries por gráfico no mobile; simplificar eixos e rótulos; fonte mínima legível;
  - evitar legenda poluída — preferir tooltip ou rótulo direto quando couber.
- Escolha padrão do time: biblioteca **leve** e bem mantida; não renderizar gráfico pesado acima da dobra sem necessidade.

## Anti-padrões

- Chamar Supabase ou expor secrets no bundle.
- Telas “painel de controle” com dezenas de números sem hierarquia.
- Gráficos microscópicos ou com 10+ cores/linhas no mobile.

## Referência

- Padrões de cópia e alertas por tipo de ativo: `docs/fiis`, `docs/acoes`, `docs/ets` e `docs/bot/instruction.md`.
