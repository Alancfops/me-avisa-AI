# Servidor MCP: design

Data: 2026-11-09. Módulo 3 do projeto. Depende do módulo de FIIs (favoritos, regras,
avisos e carteira) como está na `develop` em `214589d`.

## Intenção

Hoje, para saber como estão os FIIs favoritos (preço, se subiu ou caiu no dia e nas
últimas semanas, se algum alerta disparou), a pessoa precisa abrir o app. O bot do
Telegram ajuda, mas só entende comandos fixos (`/ver KNRI11`).

O servidor MCP deixa a pessoa perguntar em linguagem natural, numa conversa com uma IA
("como estão meus FIIs hoje?", "comprei mais 10 cotas de KNRI11", "me avisa quando o
MXRF11 chegar a 9,50"). A IA chama ferramentas do app por baixo dos panos e responde do
jeito dela. MCP é um protocolo aberto: o servidor não é "do Claude", serve a qualquer
cliente MCP. O cliente que a pessoa vai usar no dia a dia é o **Claude Desktop** (já
instalado no Linux dela).

Também é estudo de MCP e de plugins, um dos objetivos do projeto.

## Metas

1. **Consultar os favoritos** em linguagem natural: preço, de quando é o preço, variação
   no dia, em 7 e em 30 dias, P/VP comparado com a mediana de 12 meses, DY 12m, último
   rendimento, alerta configurado e se algum alerta disparou hoje.
2. **Detalhe de um favorito:** o que a página do fundo mostra (P/VP contra a mediana,
   preço contra as médias de 6 e 12 meses, situação de cada regra, rendimentos).
3. **Avisos recentes:** o que disparou e quando.
4. **Carteira:** cotas, valor de cada posição, rendimento estimado (último mês e média
   de até 12 meses) e totais.
5. **Buscar fundo** por código ou nome em todo o catálogo, só para achar o que favoritar
   ou pôr na carteira.
6. **Gerenciar favoritos:** adicionar, remover e trocar o alerta.
7. **Gerenciar a carteira:** definir o total de cotas, somar ou subtrair cotas, tirar um
   fundo.
8. Funcionar no **Claude Desktop** e no **Claude Code** em modo local (stdio), com um
   **plugin do Claude Code** instalável pelo próprio repositório.
9. README (pt e en) com a configuração do Claude Desktop, do Claude Code (plugin) e de
   pelo menos um outro cliente MCP.

## Fora do escopo

- Consultar dados de FIIs que não são favoritos (a busca de catálogo da meta 5 é a
  exceção, e só devolve código, nome, tipo e segmento). Decisão da pessoa: "por
  enquanto só dos meus".
- **Claude web (claude.ai) e o app do Claude no celular.** Os conectores deles são
  chamados pela nuvem da Anthropic e exigem um endereço HTTPS público. Isso exige túnel
  e autenticação (OAuth) e muda a decisão "só localhost, sem login, sem túnel". Fica
  para uma etapa futura com brainstorming próprio.
- ChatGPT e outros clientes que só aceitam servidor remoto, pelo mesmo motivo.
- Mais de um alerta principal por fundo (o app tem um só; ver "Alertas").
- Preço médio de compra e histórico de compras na carteira (o app guarda só o total de
  cotas).
- Configurações (chave da brapi, token do bot), Saúde, backtest.
- Mostrar no site as variações de 7 e 30 dias (podem ir depois, ver "Números").
- Ações e ETFs.

## Anti-metas

O que seria fracasso mesmo funcionando (a pessoa citou os dois primeiros):

- **Alerta diferente do pedido.** Ex.: pedir preço alvo de R$ 9,50 e ficar R$ 95,00, ou
  trocar o alerta do fundo errado.
- **Números diferentes dos do site.** O MCP não faz conta própria para nada que o site
  mostra.
- Recomendar investimento: respostas e descrições das ferramentas informam, nunca dizem
  "oportunidade", "barato" ou "compre" (mesma regra do app e do bot).
- Expor o app na internet.
- Exigir que a pessoa use comandos: a interface é a conversa.

## Restrições

- **Local:** o servidor MCP roda na mesma instalação e lê o mesmo banco. Nada novo
  escuta fora de `127.0.0.1`.
- **Uma pessoa por instalação, sem login:** o mesmo modelo de confiança do site. Quem
  consegue rodar processos na máquina ou acessar `127.0.0.1:8000` já tem o site.
- **Pacote:** `laravel/mcp` (oficial do Laravel). Ele oferece servidor local (stdio,
  comando Artisan) e servidor web (Streamable HTTP, rota) a partir das mesmas
  ferramentas.
- **Arquitetura:** novo módulo `app/Modules/Mcp/`, um canal de entrada como o
  `Telegram`. As ferramentas chamam os casos de uso de `app/Modules/Fiis/Application`;
  não acessam Eloquent, brapi, CVM nem B3 diretamente. Os testes de arquitetura
  (`tests/Arch/LayersTest.php`) devem cobrir o módulo novo.
- **Texto:** descrições de ferramentas, parâmetros e mensagens em português, como o resto
  do app. Datas e horas em `America/Sao_Paulo`, com a data junto da hora.
- **Cota da brapi:** o MCP não faz requisição à brapi ao consultar. Usa as cotações que o
  agendador já guardou (a cada 30 min no pregão). Favoritar continua importando o
  histórico como o site faz hoje.

## Como roda

### Local (stdio): o caminho principal

O Claude Desktop e o Claude Code iniciam um processo e conversam com ele pela entrada e
saída padrão. O comando, com o app no ar (`make start`):

```bash
docker compose -f /caminho/do/me-avisa-AI/compose.yaml exec -T web php artisan mcp:start fiis
```

O nome exato do comando e do servidor fica a critério de quem implementar. A saída
padrão do processo deve ter só o protocolo MCP: nada de log, aviso do PHP ou banner
escrito nela.

Se o app estiver parado, o comando falha, e o Claude mostra o erro. O README explica
que, como o bot, o MCP precisa do app ligado.

Podman: o mesmo comando com `podman compose`. Sem contêineres (ainda não disponível):
`php artisan mcp:start fiis` na pasta do app.

### HTTP: fora desta versão

*Decisão de quem escreveu o design, depois da revisão:* não haverá endpoint HTTP
(`/mcp`) nesta versão. As rotas de escrita do site têm proteção CSRF, e um endpoint MCP
por POST não teria. Qualquer página aberta no navegador poderia tentar chamá-lo (DNS
rebinding, Origin forjado) e mudar alertas ou a carteira. Os dois clientes da pessoa
usam stdio, então o HTTP não faz falta agora. Ele volta junto com a etapa de túnel e
autenticação.

### Plugin do Claude Code

O repositório vira um marketplace de plugins do Claude Code, com um plugin `me-avisa-ai`
que registra o servidor MCP local. Instalação:

```text
/plugin marketplace add Alancfops/me-avisa-AI
/plugin install me-avisa-ai@me-avisa-ai
```

O plugin precisa saber onde o app está instalado (para o `-f .../compose.yaml`). Como
resolver isso (variável de ambiente, configuração do plugin, script que procura a
pasta) fica a critério de quem implementar, desde que o README explique em um passo.

O plugin pode trazer uma skill curta com o tom das respostas (informar, não recomendar;
dizer de quando é o preço). Opcional.

### Extensão do Claude Desktop (`.mcpb`)

Desejável, não obrigatória nesta versão: um pacote que o Claude Desktop instala com um
clique, sem editar o `claude_desktop_config.json`. Se ficar caro (por exemplo, se o
formato não combinar bem com um comando `docker compose exec`), fica de fora e o README
ensina a editar o JSON.

## Ferramentas

Nomes em inglês ou português ficam a critério de quem implementar, desde que
consistentes. Os nomes abaixo são descritivos. Cada ferramenta tem descrição clara
para qualquer modelo (inclusive modelos locais menores), parâmetros simples e marca se
só lê ou se altera dados (anotações `readOnly`/`destructive` do MCP, quando o pacote
oferecer).

### Leitura

| Ferramenta | Entrada | Devolve |
|---|---|---|
| Listar favoritos | nada | Um item por favorito: código, nome, preço, origem e horário do preço, variação no dia, em 7 e em 30 dias, P/VP e mediana de 12 meses, DY 12m, último rendimento por cota, alerta configurado, alertas disparados hoje |
| Ver favorito | código | O que a página do fundo mostra, incluindo a situação de cada regra (atendida ou não, e por quê) |
| Avisos recentes | opcional: código, quantidade (padrão pequeno) | Avisos com fundo, regra, preço, referência, motivo e horário |
| Ver carteira | nada | O mesmo da tela Carteira: cotas, preço e de onde veio, valor da posição, rendimento do último mês e média (com quantos meses), totais |
| Buscar fundo | texto (código ou nome) | Até ~10 fundos do catálogo: código, nome, tipo, segmento, se já é favorito e se está na carteira |

"Ver favorito" com um código que não é favorito responde que não é favorito e sugere
favoritar. Não devolve os dados (fora do escopo).

### Alteração

Toda alteração responde com o **antes e o depois** em texto, para a pessoa conferir na
hora.

| Ferramenta | Entrada | Faz | Responde |
|---|---|---|---|
| Favoritar | código | `AddFavorite`. Entra com o padrão do site: automático + queda forte | Que favoritou, o alerta padrão e o lembrete de que cada favorito gasta cerca de 350 requisições da brapi por mês. Se já era favorito, diz isso e não muda nada |
| Remover favorito | código | `RemoveFavorite` | Que removeu e qual era o alerta ("era preço alvo R$ 9,50 + queda forte"), para dar para refazer. Diz que os avisos antigos ficam |
| Trocar alerta | código, tipo (`automatic`, `target`, `range`), preço alvo ou mínimo e máximo, queda forte (sim/não) | `UpdateFavoriteSettings` | Antes e depois: "antes: automático + queda forte; agora: preço alvo R$ 150,00 + queda forte" |
| Definir cotas | código, e **ou** total de cotas **ou** variação (+10, −5), nunca os dois | `SaveHolding`, ou `RemoveHolding` quando o resultado é zero | Antes e depois: "KNRI11: 100 → 110 cotas" |
| Tirar da carteira | código | `RemoveHolding` | Que tirou e quantas cotas eram |

Regras de "Definir cotas" (*decisão de quem escreveu o design*):

- Variação positiva num fundo que não está na carteira: entra com aquela quantidade
  ("comprei 10 cotas de HGLG11"). O fundo precisa estar no catálogo, como no site.
- Total ou variação que dá exatamente zero: tira o fundo da carteira, como "Tirar da
  carteira", e a resposta diz isso ("KNRI11: 100 → 0 cotas, saiu da carteira").
- Resultado negativo, ou variação negativa num fundo fora da carteira: recusa, com
  mensagem dizendo quantas cotas há hoje.
- A conta da variação fica no módulo de FIIs (aplicação), não no MCP.

## Alertas

No app, cada favorito tem **um** alerta principal (automático, preço alvo ou faixa de
compra) e a queda forte ligada ou desligada (`FavoriteSettings`). "Me avisa quando o
KNRI11 chegar a 150" **troca** o alerta atual, igual ao site. Decisão da pessoa: "trocar
o alerta atual tá ótimo".

A ferramenta "Trocar alerta" recebe todos os campos explicitamente, incluindo a queda
forte. Se a pessoa não falar da queda forte, a IA deve manter o valor atual. A descrição
da ferramenta diz isso, e o valor atual aparece em "Listar favoritos" e "Ver favorito".

Proteções contra alerta errado:

1. **Mesma validação do site:** preço positivo, até 6 casas decimais, faixa não
   invertida, tipo válido, fundo precisa ser favorito. Hoje essa validação mora em
   `UpdateFavoriteSettingsRequest` e `SaveHoldingRequest` (camada HTTP). Ela deve ser
   compartilhada entre site e MCP, não copiada. Como (mover para o domínio, para a
   aplicação ou para um validador comum) fica a critério de quem implementar.
2. **Valores em número:** o preço chega como número decimal (`9.5`), não como texto com
   vírgula. Nada de adivinhar separador de milhar.
3. **Confirmação do cliente:** o Claude Desktop pede permissão antes de cada ferramenta
   e mostra os parâmetros.
4. **Trava de preço distante, com confirmação** (decisão da pessoa): se o preço alvo, ou
   o mínimo ou o máximo da faixa, ficar a mais de **30%** do preço atual (cotação ou
   último fechamento), para cima ou para baixo, a ferramenta **não salva**. Ela responde
   com o valor pedido, o preço atual e a distância em %, e pede que a IA confirme com a
   pessoa ("o alvo de R$ 95,00 está 888% acima do preço atual, R$ 9,62. Confirme com a
   pessoa antes de salvar"). Só grava se for chamada de novo com um parâmetro de
   confirmação (ex.: `confirmado: true`). A descrição da ferramenta diz que esse
   parâmetro só pode ir depois de a pessoa dizer que sim. Sem preço atual, a trava não
   tem com o que comparar: salva normalmente e a resposta diz que não deu para conferir.
   O limite de 30% é decisão de quem escreveu o design e pode mudar com o uso real.
5. **Mensagem de concluído** (pedido da pessoa): toda alteração que deu certo começa com
   uma linha clara de sucesso ("✅ Alerta salvo", "✅ KNRI11 favoritado", "✅ Carteira
   atualizada"), seguida do antes e depois. Quando não salvou (trava, erro de
   validação), a resposta deixa claro que **nada foi alterado**.
6. Erros voltam como mensagem legível (a mesma do site, quando houver), com o MCP
   marcando a resposta como erro, para a IA explicar à pessoa.

## Números

Regra: **o MCP não calcula nada que o site já calcula.** Preço, P/VP, mediana, DY,
médias, situação das regras, valores e rendimentos da carteira vêm de `DescribeFund`,
`ListFavorites`, `ListAlerts` e `ShowPortfolio`, os mesmos que montam as telas. Se um
número estiver errado, está errado nos dois lugares, e a correção vale para os dois.

As **variações de 7 e 30 dias** são novas (o site não mostra). Elas são calculadas no
módulo de FIIs (domínio ou aplicação), a partir dos mesmos fechamentos diários que
alimentam os gráficos da página do fundo, e não dentro do módulo MCP. Assim o site pode
passar a mostrá-las depois sem conta duplicada. Definição: variação percentual entre o
preço atual (cotação, ou último fechamento) e o fechamento do pregão de 7 e de 30 dias
corridos atrás (ou o pregão anterior mais próximo). Sem fechamento suficiente, o valor é
"sem dado", nunca zero.

Toda resposta com preço diz **de onde e de quando** ele é: "cotação da brapi às 14:30 de
09/11" ou "fechamento de 07/11". Fora do pregão, isso evita parecer que o preço é de
agora.

Formato dos dados devolvidos: texto curto e estruturado ou JSON pequeno, com unidades
explícitas (R$, %, fração) e rótulos claros. A escolha fica a critério de quem
implementar, pensando em modelos pequenos também. Valores em reais com 2 casas, como no
site.

## Tom

A IA escreve a resposta final. O app influencia com:

- **Instruções do servidor** (campo `instructions` do MCP): o app informa e não
  recomenda; diga de quando é o preço; um aviso significa que uma regra foi atendida,
  não que o fundo está barato.
- Descrições das ferramentas que repetem o essencial.
- Skill opcional no plugin do Claude Code.

## Testes

- Cada ferramenta tem teste com o `laravel/mcp` (chamada da ferramenta, resposta,
  erro), no banco de teste, como o resto (`make test`).
- Teste que garante que os números de "Listar favoritos" e "Ver carteira" são os mesmos
  das props das telas correspondentes, com os mesmos dados.
- Testes de domínio para as variações de 7 e 30 dias (pregões faltando, sem histórico,
  feriado).
- Testes da trava: alvo e faixa dentro e fora dos 30%, com e sem `confirmado`, sem preço
  atual; a resposta de trava não grava nada.
- Testes das alterações: antes e depois, favorito inexistente, fundo fora do catálogo,
  faixa invertida, total e variação ao mesmo tempo (recusado), variação que zera as
  cotas (sai da carteira), que deixa negativo (recusada) e positiva em fundo fora da
  carteira (entra).
- Teste de arquitetura: `app/Modules/Mcp` não usa Eloquent nem as integrações
  externas.
- Conferência manual no Claude Desktop antes da PR: "como estão meus FIIs hoje?",
  trocar um alerta, favoritar e remover, mexer na carteira.

## A critério de quem implementar

- Nomes das ferramentas, do servidor e do comando Artisan.
- Formato exato das respostas (texto ou JSON), dentro das regras acima.
- Como o plugin descobre a pasta da instalação.
- Onde mora a validação compartilhada.
- Se a extensão `.mcpb` entra nesta versão.
- O tamanho padrão de "Avisos recentes" e da busca.
