# Módulo 1 — FIIs: design

Data: 2026-10-29 · Status: aprovado em 2026-10-30

## Intenção

O Me Avisa Aí acompanha os FIIs que o usuário favoritou e diz quando algum está
num **preço bom para comprar**, segundo regras que ele mesmo configura ou uma
regra automática. Ele não quer ficar abrindo home broker para conferir preço:
quer olhar uma tela e saber "tem algo bom hoje?".

Este módulo entrega o núcleo: catálogo de FIIs, dados de mercado, favoritos com
regras, avaliação das regras e avisos **no site**. Telegram, MCP, ações e ETFs
vêm depois, como módulos separados, e devem conseguir reaproveitar o que este
módulo cria (avaliação de regras e registro de avisos) sem reescrever.

O projeto também é estudo de arquitetura: preferir fronteiras claras e código
testável a atalhos, sem cair em cerimônia que não se paga para um usuário só.

## Objetivos

1. Buscar um FII por ticker ou nome e favoritá-lo.
2. Para cada favorito, escolher o modo de referência: **preço alvo**, **faixa de
   compra** ou **automático**; e ligar/desligar o alerta de **queda forte**.
3. Atualizar cotações sozinho, a cada 30 minutos, das 10h às 18h (horário de
   Brasília), em dias úteis.
4. Importar valor patrimonial por cota e DY mensal do Informe Mensal da CVM.
5. Avaliar as regras a cada atualização e registrar **avisos** quando uma regra
   começa a valer, com antispam.
6. Mostrar tudo no site: Painel, Fundo, Favoritar, Avisos, Configurações mínimas.
7. Subir com um comando e voltar sozinho quando a máquina liga, com Docker ou sem
   ele (ver "Distribuição").

## Fora do escopo (agora)

- Login, cadastro, múltiplos usuários.
- Bot do Telegram e servidor MCP (módulos 2 e 3).
- Ações e ETFs (módulo 4).
- Calendário de feriados da B3 (nos feriados as checagens rodam e a cotação
  simplesmente não muda).
- Calculadora de renda estimada.
- Recuperar avisos "perdidos" enquanto a máquina estava desligada.
- Deploy fora do localhost; CI/CD (o usuário monta depois).

## Antiobjetivos (falha mesmo funcionando)

- Regra que dispara diferente do que está em `docs/fiis/alert.md`.
- Mostrar cotação velha como se fosse do momento, ou dado ausente como zero.
- Encher a lista de avisos com repetições do mesmo fato.
- Texto que soa como recomendação de investimento ("compre agora").
- Tela que reprova na WCAG 2.2 AA.

## Restrições

- **Stack:** Laravel (versão atual estável) com o starter kit oficial React
  (Inertia + TypeScript + Tailwind v4 + shadcn/ui), PostgreSQL, Docker Compose.
- **Sem autenticação:** remover/desligar as rotas e telas de login, cadastro e
  perfil do starter kit. O app escuta só em localhost.
- **Um comando** (`docker compose up -d`) sobe: web, scheduler, worker de fila e
  Postgres, todos com `restart: unless-stopped`. Fila no próprio banco (sem
  Redis).
- **Fuso:** `America/Sao_Paulo` para agendamento e exibição.
- **Fontes de dados grátis:** brapi plano gratuito (15 mil requisições/mês) e
  CVM Dados Abertos. O plano grátis aceita **1 ativo por requisição**. Orçamento:
  6 favoritos × 2 checagens/h × 8 h × 22 dias ≈ 2,1 mil requisições/mês. O limite
  é contado em 30 dias corridos.
- **Front:** seguir a skill `ui-ux` (`.claude/skills/ui-ux/`) e o protótipo em
  `docs/prototipo-lovable/` como referência visual.
- **Regras de domínio** vêm de `docs/fiis/`; se o código precisar de algo que
  não está lá, atualizar o doc primeiro (`docs/legado.md`).

## Distribuição (acrescentado em 2026-10-30)

O projeto é pessoal, serve de portfólio e deve poder ser instalado por outras
pessoas. Cada instalação atende uma pessoa, com as próprias chaves.

- **Docker Compose** é o caminho padrão, com imagem publicada para `amd64` e
  `arm64` (Mac com Apple Silicon roda sem emulação).
- **OrbStack e Podman:** o mesmo `compose.yaml` deve funcionar nos dois, sem arquivo
  específico. Um usuário de Mac consultado usa OrbStack e, às vezes, Podman. O
  `Makefile` aceita `COMPOSE="podman compose"`.
- **Sem contêineres:** instalação nativa documentada com PHP, Composer, Node e
  Postgres. `make native-install` e `make native-start` sobem web, scheduler e
  worker juntos. Início automático com a máquina fica opcional e fora da primeira
  versão desse caminho.
- **Comandos:** `Makefile` como caminho principal (`make install`, `make start` etc.).
- **Commits:** Conventional Commits (`feat:`, `fix:`, `docs:`...), para o CI gerar
  versão e notas de release depois.
- **READMEs:** `README.md` em português e `README.en.md` em inglês; cada forma de
  instalação só é dada como pronta depois de testada.
- **Banco:** Postgres nos dois caminhos, para não testar dois bancos.
- **Repositório:** só `.env.example`, sem dados de cotação; licença MIT; aviso de
  que o app informa e não recomenda investimento. O app escuta só em `127.0.0.1`.
- **Atualização:** versões marcadas, com migrations aplicadas sem perder dados.
- **Portfólio:** modo de demonstração com dados fictícios, que não exige token;
  README com imagens e registro das decisões de arquitetura.

## Decisões

### Dados de mercado

- **Cotação** (preço, variação do dia, volume, nome): brapi
  `GET /api/v2/stocks/quote?symbols=KNRI11`, uma chamada por favorito, com
  `BRAPI_TOKEN` no header `Authorization: Bearer`. Testado em 2026-10-30 com o
  plano grátis em KNRI11. Os endpoints `/api/v2/fii/*` exigem o plano Pro e não
  são usados.
- **Atraso da cotação:** a brapi documenta atualização a cada 30 minutos no plano
  grátis. A tela mostra o horário de `regularMarketTime`, não o da consulta.
- **Histórico de preço diário:** brapi
  `GET /api/v2/stocks/historical?symbols=KNRI11&range=3mo&interval=1d` ao favoritar
  (preenche a média de 30 dias desde o primeiro dia). Usar `close`, não
  `adjustedClose`. O plano grátis limita a 3 meses, cerca de 60 pregões, então a
  mediana do P/VP começa com 3 meses e cresce até 12 conforme o app guarda
  fechamentos.
- **Catálogo para a busca:** brapi
  `GET /api/v2/tickers?type=fund&subType=fii` (333 FIIs em 2026-10-30, paginado),
  guardado localmente e atualizado uma vez por dia.
- **VP por cota e DY do mês:** CVM, Informe Mensal Estruturado de FII
  (`https://dados.cvm.gov.br/dados/FII/DOC/INF_MENSAL/DADOS/inf_mensal_fii_AAAA.zip`).
  Arquivos `*_geral_*.csv` (`CNPJ_Fundo_Classe`, `Data_Referencia`, `Versao`,
  `Data_Entrega`, `Nome_Fundo_Classe`, `Codigo_ISIN`, `Segmento_Atuacao`) e
  `*_complemento_*.csv` (`Valor_Patrimonial_Cotas`,
  `Percentual_Dividend_Yield_Mes`, `Patrimonio_Liquido`, `Cotas_Emitidas`). CSV
  com `;` em Latin-1; colunas conferidas no arquivo de 2026. Importar o ano
  corrente e o anterior; reimportar uma vez por dia (o arquivo é atualizado
  conforme os fundos entregam informes). Para cada competência, vale a linha de
  maior `Versao`.
- **P/VP** = preço atual ÷ VP por cota do informe mais recente. Mostrar a
  competência do informe ("VP de setembro/2026").
- **Vigência do VP:** cada informe vale a partir da sua `Data_Entrega`, não da
  competência. Assim o histórico não usa um número que ainda não era público.
- **DY 12m** = soma dos 12 últimos `Percentual_Dividend_Yield_Mes`, rotulada com a
  origem ("DY 12m, informes da CVM"). A CVM grava fração (`0.004342` = 0,43%). É
  só exibição: nenhuma regra usa esse número.
- **Média 30d** = média dos fechamentos dos últimos 30 pregões, sem o preço ao
  vivo de hoje.
- **Ligação ticker ↔ fundo da CVM:** pelo ISIN. O código de negociação de cota de
  FII é a raiz do ISIN (posições 3–6, ex. `BRMXRFCTF008` → `MXRF`) + `11`.
  Confirmar com a brapi (o ticker precisa existir lá). Fundos que não casarem
  ficam sem VP e a interface diz "VP indisponível".
- **Dados das fontes são validados** antes de salvar (ver `docs/legado.md`):
  ausente é `null`, nunca zero; resposta quebrada não sobrescreve dado bom.
- **Cada fonte atrás de uma interface** (ex.: `Quotes` com implementação brapi;
  `FundReports` com implementação CVM), para testar com dados falsos e trocar de
  fonte sem mexer nas regras.

### Regras de alerta

Implementar como classes puras (sem banco nem HTTP), uma por regra, recebendo um
retrato do fundo (preço, variação, médias, P/VP, histórico de P/VP, config do
favorito) e devolvendo se bateu e por quê.

| Regra | Quando o favorito usa | Dispara quando |
|---|---|---|
| Preço alvo | modo **alvo** | preço ≤ alvo |
| Faixa de compra | modo **faixa** | mínimo ≤ preço ≤ máximo |
| Automática | modo **automático** | preço < média 30d × 0,95 **e** P/VP < mediana do P/VP do próprio fundo nos últimos 12 meses |
| Queda forte | interruptor ligado (padrão: ligado), em qualquer modo | variação do dia < −3% (exatamente −3% não dispara) |

- **Favorito novo** começa no modo **automático**, com queda forte ligada.
- **Mediana do P/VP:** P/VP diário = fechamento do dia ÷ VP do informe vigente
  naquele dia. Janela de até 12 meses com o que houver guardado. Com menos de 60
  pregões de histórico, usar **P/VP < 1,0** como reserva e dizer isso na tela
  ("comparando com P/VP 1,0 até juntar histórico").
- Sem VP disponível, a regra automática não dispara e a tela explica por quê.
- `docs/fiis/alert.md` é a referência dessas regras e já traz esta definição.

### Avisos e antispam

O antispam vale **por regra de cada favorito**: uma queda forte às 10h não impede
o aviso de alvo às 14h. Cada favorito tem um modo e a queda forte, então são no
máximo 2 avisos por fundo em 24 h, fora a exceção dos 2%.

- Um **aviso** é registrado quando uma regra **passa a valer** para um favorito
  (na avaliação anterior não valia).
- Enquanto a regra continua valendo, não gera novo aviso, **exceto** se preço ≤
  preço do último aviso daquela regra × 0,98.
- Teto: no máximo 1 aviso por regra de cada favorito a cada 24 h, salvo a exceção
  dos 2%.
- Quando a regra deixa de valer, o estado zera e um novo cruzamento gera aviso.
- **Queda forte:** cada pregão é um evento novo, mesmo que ontem ela já valesse no
  fechamento.
- **Bloqueado pelo teto não se perde:** se a regra passou a valer dentro das 24 h,
  o aviso sai quando o teto liberar, caso ela ainda valha nesse momento.
- **Mudar a configuração** de um favorito reavalia na hora, com a última cotação,
  e começa um ciclo novo para a regra alterada, sem herdar o teto.
- Ao ligar a máquina, a primeira avaliação usa o estado atual e respeita o
  registro anterior (não repete aviso de algo que já valia antes de desligar).
- Cada aviso guarda: fundo, regra, preço, referência (alvo/faixa/média/P/VP),
  momento. Esse registro é o que os módulos de Telegram e MCP vão consumir depois.

### Agendamento

- A cada 30 min, 10:00–18:00, seg–sex (a brapi grátis atualiza nesse ritmo): atualizar cotações dos favoritos → avaliar
  regras.
- Diariamente fora do pregão: gravar o fechamento do dia no histórico; reimportar
  a CVM.
- Comandos Artisan equivalentes para rodar na mão (útil em desenvolvimento).
- Falha de fonte: registrar em log, mostrar na interface o horário da última
  atualização bem-sucedida, tentar de novo na próxima rodada.

### Interface (escopo deste módulo)

Seguir `.claude/skills/ui-ux/references/telas.md`, limitado a:

- **Painel**, **Fundo** (com "Quando me avisar": alvo / faixa / automático +
  queda forte), **Favoritar** (diálogo de busca), **Avisos**.
- **Configurações** só com tema. Seções de Telegram e MCP ficam fora.
- **Saúde** (tela própria): para cada fonte, a última atualização bem-sucedida e
  os erros recentes em linguagem simples; para a brapi, o consumo da cota
  (`GET /api/v2/user/usage`). Serve para quem instalou descobrir o que deu errado.
- Remover o status "Telegram conectado" da sidebar neste módulo.
- Busca do diálogo: catálogo local de FIIs vindo da brapi (ticker + nome). A
  ligação com a CVM só serve para VP e DY.

### Qualidade

- Testes (Pest) obrigatórios para as regras de alerta e o antispam, usando os
  exemplos de `docs/fiis/alert.md`, e para os parsers da brapi e da CVM com
  respostas gravadas.
- Fontes externas nunca são chamadas nos testes.
- **Simulador (`make backtest`):** roda as mesmas regras sobre preços passados e
  mostra os avisos que teriam saído, sem enviar nem gravar nada. Entra depois das
  regras e do antispam. Mostra a quantidade de avisos, não se a compra daria lucro.

## Riscos

1. **Plano grátis da brapi pode mudar.** Testado em 2026-10-30: cotação de KNRI11,
   VISC11 e XPML11, histórico de 3 meses e catálogo funcionam; vários tickers na
   mesma chamada e `/api/v2/fii/*` não. O texto do plano fala em cotações "para
   testing"; se o acesso mudar, trocar a implementação de `Quotes`.
2. **Ligação ISIN → ticker** pode falhar para fundos com mais de uma classe de
   cota ou código fora do padrão `11`. Mitigação: confirmar na brapi e permitir
   ajustar o ticker manualmente no futuro.
3. **Informe da CVM atrasa** (fundos entregam até ~15 dias após o mês). O VP
   usado pode ter 1–2 meses; a tela mostra a competência.

## Decisões do Claude, a confirmar na aprovação

O usuário decidiu o antispam por regra. Estas ele ainda não confirmou:

1. Queda forte estrita (< −3%, como em `docs/fiis/alert.md`); cada pregão é um
   evento novo.
2. Média 30d com os últimos 30 fechamentos, sem o preço ao vivo.
3. VP vigente a partir de `Data_Entrega`.
4. Favorito novo no modo automático; mudar a configuração reavalia na hora e
   começa um ciclo novo.
5. DY 12m como soma dos 12 últimos DY mensais da CVM, só para exibição.
6. Exceção do antispam: preço ≤ preço do último aviso da regra × 0,98.
7. Aviso bloqueado pelo teto sai quando o teto libera, se a regra ainda valer.
8. Regra automática com a mediana de 12 meses do P/VP do próprio fundo e, com
   menos de 60 pregões, reserva de P/VP < 1,0 (detalha o pedido de P/VP relativo
   ao fundo).

## Deixado para quem constrói

- Estrutura de pastas e nomes de classes, desde que regras, fontes de dados e
  interface fiquem separadas e o módulo FIIs tenha fronteira clara para os
  próximos módulos.
- Esquema do banco.
- Imagem Docker e servidor web (Sail, FrankenPHP, nginx + php-fpm…).
- Componentes de UI dentro do que a skill `ui-ux` define.
