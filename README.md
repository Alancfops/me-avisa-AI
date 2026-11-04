# Me Avisa Aí

**Português** · [English](README.en.md)

Acompanha seus fundos imobiliários (FIIs) favoritos e avisa quando algum chega ao
preço que você definiu ou cai além do normal. Em vez de abrir o home broker para
conferir preço, você olha uma tela e sabe se tem algo para analisar hoje.

> **Em construção.** O módulo de FIIs já funciona com Docker: favoritos, cotações, regras,
> avisos no site e no Telegram, carteira e a tela de Saúde. A interface ainda é mínima, sem o acabamento visual, e
> o modo de demonstração não existe ainda. Fundos em que mais de um registro da CVM declara
> o mesmo ISIN ficam sem VP, e a regra automática não dispara para eles.
>
> Situação da instalação: **Docker** testado no Linux; **OrbStack** e **Podman** ainda não
> testados; **sem contêineres** ainda não disponível.

O "Aí" vem de "me avisa aí". Não é IA.

## O que ele faz

O módulo de FIIs oferece:

- **Favoritos:** busca por ticker ou nome.
- **Preço alvo:** avisa quando o preço chega ao valor definido ou fica abaixo dele.
- **Faixa de compra:** avisa quando o preço entra em um intervalo.
- **Regra automática:** avisa quando o preço está 5% abaixo da média de 30 pregões e
  o P/VP está abaixo da mediana de 12 meses do próprio fundo.
- **Queda forte:** avisa quando a cota cai mais de 3% no dia.
- **Antispam:** evita repetir o mesmo aviso, sem esconder um aviso diferente.
- **Página do fundo:** P/VP comparado com a mediana de 12 meses, preço comparado com as
  médias de 6 e 12 meses, gráficos de preço e P/VP e o último rendimento estimado.
- **Todos os FIIs:** lista dos fundos negociados na B3, com tipo (tijolo, papel etc.),
  segmento, preço, P/VP e DY 12m, filtros e botão de favoritar.
- **Bot do Telegram:** recebe os avisos e responde a comandos como `/alertas` e `/ver KNRI11`.
- **Preço teto:** o preço máximo para o fundo render a taxa anual que você escolher,
  com um botão para usá-lo como preço alvo.

As cotações são atualizadas a cada 30 minutos, das 10h às 18h, em dias úteis. Os
avisos aparecem no site. As regras completas estão em
[`docs/fiis/alert.md`](docs/fiis/alert.md).

**Este app informa e não recomenda investimentos.** Um aviso diz que uma regra foi
atendida, não que o ativo está barato ou deve ser comprado.

## Instalação

Cada instalação atende uma pessoa, com as próprias chaves, e fica acessível só no
seu computador. Não há login, então não exponha o app na internet.

**Antes de começar:** crie uma conta gratuita na [brapi](https://brapi.dev/dashboard)
e copie seu token. O plano gratuito permite 15 mil requisições por mês, e cada fundo
acompanhado consome cerca de 350.

Escolha uma das formas abaixo:

| Forma | Indicada para |
|---|---|
| [Docker](#docker) | Linux e Windows, ou quem já usa Docker |
| [OrbStack](#orbstack-mac) | Mac, com um consumo menor que o do Docker Desktop |
| [Podman](#podman) | Quem prefere não usar o Docker |
| [Sem contêineres](#sem-contêineres) | Quem quer instalar tudo direto na máquina (em breve) |

Todas usam PostgreSQL. Os comandos de `make` do dia a dia valem para as formas com contêiner.

### Docker

Requer [Docker](https://docs.docker.com/get-docker/) com Compose e `make`.

```bash
git clone https://github.com/Alancfops/me-avisa-AI.git
cd me-avisa-AI
make install   # cria o .env, constrói as imagens e prepara o banco
```

Coloque o token no `.env`:

```dotenv
BRAPI_TOKEN=seu_token
```

Depois, suba o app e abra <http://127.0.0.1:8000>:

```bash
make start
```

Na primeira vez, carregue o catálogo da busca, os informes da CVM e os fechamentos da
B3. Depois disso, eles se atualizam sozinhos, uma vez por dia. O histórico anual da B3
baixa arquivos de cerca de 90 MB e pode demorar alguns minutos:

```bash
docker compose exec web php artisan fiis:sync-catalog
docker compose exec web php artisan fiis:import-reports
docker compose exec web php artisan fiis:import-exchange-closes
docker compose exec web php artisan fiis:import-exchange-history
```

Sem `make`, como costuma ser no Windows, use o Compose direto:

```bash
cp .env.example .env
docker compose up -d --build
```

No Linux, se o seu usuário não tiver o UID 1000, defina `APP_UID=$(id -u)` antes desses
comandos, ou o app não consegue gravar no `.env`.

### OrbStack (Mac)

O [OrbStack](https://orbstack.dev) oferece os comandos `docker` e `docker compose`,
então os passos são os mesmos da seção [Docker](#docker). Confira a licença na
página do OrbStack se o uso for profissional.

```bash
brew install orbstack
open -a OrbStack
```

### Podman

Requer [Podman](https://podman.io/docs/installation) com suporte a Compose. No Mac,
inicie antes a máquina virtual:

```bash
brew install podman podman-compose
podman machine init
podman machine start
```

Instale e suba o app indicando o comando do Podman:

```bash
git clone https://github.com/Alancfops/me-avisa-AI.git
cd me-avisa-AI
make install COMPOSE="podman compose"
make start COMPOSE="podman compose"
```

Nos comandos da primeira carga, troque `docker compose` por `podman compose`.

No Podman, o app pode não voltar sozinho quando o computador liga. Depois de ligar a
máquina, rode `podman machine start`, se estiver no Mac, e `make start COMPOSE="podman compose"`.

### Sem contêineres

> **Ainda não disponível.** Os comandos `make native-*` não existem ainda; esta seção
> mostra como essa forma vai funcionar.

Requer PHP 8.4 (com `pdo_pgsql`, `bcmath` e `zip`), Composer, Node 22, PostgreSQL 17 e `make`.
No Mac, com o [Homebrew](https://brew.sh):

```bash
brew install php composer node@22 postgresql@17
brew services start postgresql@17
createdb me_avisa_ai
```

Instale e suba o app:

```bash
git clone https://github.com/Alancfops/me-avisa-AI.git
cd me-avisa-AI
make native-install   # cria o .env, instala as dependências e prepara o banco
make native-start     # sobe o site, o agendador e a fila juntos
```

Ajuste no `.env` o `BRAPI_TOKEN` e os dados de acesso ao banco. Nesta forma, o app
não inicia sozinho com o computador por padrão.

## Bot do Telegram

Com o bot, todo aviso do site também chega no seu Telegram, e você consulta favoritos,
carteira e fundos por comandos, sem abrir o app.

**Por que cada pessoa cria o próprio bot?** Quem lê e responde as mensagens é o app
instalado no seu computador. Um bot só para todo mundo exigiria um servidor na internet
guardando os dados de cada pessoa, e o Me Avisa Aí roda só na sua máquina. Criar o bot
é de graça e leva uns 2 minutos.

### 1. Criar o bot

1. No Telegram, abra o [@BotFather](https://t.me/BotFather) e mande `/newbot`.
2. Quando ele pedir o **nome**, mande `Me Avisa Aí`.
3. Quando ele pedir o **nome de usuário**, invente um que termine em `bot`. Ele precisa
   ser único no Telegram inteiro, então ponha algo seu no meio, como
   `meavisaai_maria_bot`.
4. O @BotFather responde com o **token**, uma linha como `123456789:AAH...`. Copie.

### 2. Colocar o token no app

Abra a aba **Telegram** do app (<http://127.0.0.1:8000/telegram>), cole o token e toque
em **Salvar token**. O app confere o token com o Telegram e guarda criptografado no
banco. Em alguns segundos o bot começa a funcionar, sem reiniciar nada.

O app também deixa o bot com o nome, a descrição e o menu de comandos padrão (o que
aparece ao digitar `/` na conversa).

> Prefere arquivo? Ponha `TELEGRAM_BOT_TOKEN=seu_token` no `.env` e rode `make start`.
> O token do `.env` tem prioridade sobre o da aba.

### 3. Conectar a sua conversa

Na mesma aba aparecem um **QR code** e o botão **Abrir no Telegram**:

- **no celular:** aponte a câmera para o QR code e toque em **Começar** no Telegram;
- **no computador:** toque em **Abrir no Telegram** e depois em **Começar**.

O bot responde "✅ Conectado" e a aba muda sozinha para **Conectado**. Use **Enviar
mensagem de teste** para conferir.

O código dentro do link prova que quem mandou `/start` é quem está com o app aberto. Ele
vale 15 minutos e é usado uma vez só. Depois de conectado, o bot ignora qualquer outra
conversa.

### Comandos

| Comando | O que responde |
|---|---|
| `/alertas` | Favoritos que bateram regra hoje |
| `/ver CÓDIGO` | Preço, P/VP, DY e rendimento de um fundo (ex.: o código da B3, que termina em 11) |
| `/favoritos` | Seus favoritos com o preço |
| `/carteira` | Valor e rendimento estimado da carteira |
| `/ajuda` | A lista de comandos |

Os links "Ver no app" das mensagens só abrem no computador onde o app roda. No celular,
use os comandos.

### Desconectar, trocar de bot e segurança

- **Desconectar:** botão **Desconectar** na aba. Os avisos continuam no site. Para
  voltar, conecte de novo pelo QR code.
- **Trocar de bot:** **Usar outro bot** na aba, e cole o token novo.
- **Token vazou:** mande `/revoke` ao @BotFather, escolha o bot e cole o token novo na
  aba (ou no `.env`). Quem tem o token controla o bot, então não compartilhe.
- O app busca as mensagens no Telegram; nada do seu computador fica exposto na
  internet.

### Se o bot não responder

| O que aparece | O que fazer |
|---|---|
| Nenhuma resposta | Confira se o app está ligado (`make start`). Sem o app rodando, o bot fica mudo. |
| "O Telegram recusou o token" | O token foi trocado ou digitado errado. Copie de novo do @BotFather. |
| "O Telegram não respondeu agora" | Falta de internet ou Telegram fora do ar. O bot volta sozinho. |
| "Este bot ainda não está conectado" | Gere o código na aba e conecte pelo QR code. |

A aba **Saúde** mostra o último envio ou leitura com sucesso e o último erro do bot.

## Iniciar com o computador

Com Docker (Linux), Docker Desktop ou OrbStack, o app volta sozinho quando o computador
liga: o Docker inicia junto com o sistema e religa os serviços do app, a menos que você
os tenha parado com `make stop`. No Podman é diferente (veja abaixo).

Com o app parado, os avisos não saem nem no site nem no Telegram. Ao religar, ele
recupera os fechamentos e os informes que perdeu.

### Desligar e religar só o app

| Quero | Comando |
|---|---|
| Parar o app agora e não deixar voltar quando o computador ligar | `make stop` |
| Ligar de novo (e voltar a iniciar com o computador) | `make start` |

É o jeito mais simples: o Docker continua iniciando com o sistema, mas o app fica parado
até você rodar `make start`.

### Não iniciar o Docker com o computador

Para nada do Docker abrir sozinho:

| Onde | Desligar | Religar |
|---|---|---|
| Linux (Docker Engine) | `sudo systemctl disable docker.service docker.socket` | `sudo systemctl enable --now docker.service docker.socket` |
| Docker Desktop (Windows, Mac, Linux) | **Settings → General**, desmarque **Start Docker Desktop when you sign in** | Marque a mesma opção |
| OrbStack (Mac) | **Settings → General**, desmarque **Start at login** | Marque a mesma opção |

Com o Docker desligado no boot, abra o Docker (ou rode `sudo systemctl start docker`) e
depois `make start` quando quiser usar o app.

### Podman

O Podman não tem um serviço que religue os contêineres sozinho, então o app **não**
inicia com o computador. Depois de ligar a máquina:

```bash
podman machine start              # só no Mac
make start COMPOSE="podman compose"
```

Para parar: `make stop COMPOSE="podman compose"`.

## Comandos

| Comando | O que faz |
|---|---|
| `make` | Lista os comandos |
| `make install` | Cria o `.env` sem sobrescrever um existente, constrói as imagens e prepara o banco |
| `make start` / `make stop` | Sobe ou para o app |
| `make restart` / `make logs` | Reinicia ou acompanha os logs |
| `make test` | Roda os testes em um banco separado, sem tocar nos seus dados |
| `make lint` / `make format` | Confere ou corrige a formatação |
| `make migrate` | Atualiza o banco |
| `make shell` | Abre um terminal em um contêiner de ferramentas |
| `make check` | Roda as checagens de backend do CI |
| `make backtest TICKER=KNRI11` | Conta os avisos que teriam saído nos preços passados, sem gravar nada; não diz se a compra daria lucro |

## Arquitetura

Monólito modular em Laravel, com Clean Architecture pragmática. As regras de alerta
não dependem do framework, do banco nem das APIs, então podem ser testadas sozinhas.

```text
app/Modules/Fiis/
├── Domain/          regras de alerta e antispam, só PHP
├── Application/     casos de uso e interfaces
├── Infrastructure/  PostgreSQL, brapi, CVM, B3
└── Http/            controllers e respostas do Inertia

app/Modules/Telegram/  bot: mensagens, comandos e envio dos avisos
```

Testes de arquitetura garantem que essas camadas não se misturem.

**Stack:** Laravel, React (Inertia), TypeScript, Tailwind v4, shadcn/ui, PostgreSQL e
Docker Compose.

**Dados:** cotações dos favoritos da [brapi](https://brapi.dev); valor patrimonial,
dividend yield e composição da carteira do
[Informe Mensal de FII da CVM](https://dados.cvm.gov.br/dataset/fii-doc-inf_mensal);
fechamentos diários e históricos da
[B3 (COTAHIST)](https://www.b3.com.br/pt_br/market-data-e-indices/servicos-de-dados/market-data/historico/mercado-a-vista/cotacoes-historicas/).

## Próximos módulos

1. **FIIs:** favoritos, dados de mercado, regras e avisos no site *(funcional, com interface mínima)*
2. **Bot do Telegram:** avisos e comandos *(funcional)*
3. Servidor MCP, para gerenciar favoritos conversando com o Claude
4. Ações e ETFs

## Documentação

- [Design do módulo de FIIs](docs/superpowers/specs/2026-10-29-modulo-fiis-design.md)
- Regras de produto: [FIIs](docs/fiis/), [ações](docs/acoes/), [ETFs](docs/etfs/) e [bot](docs/bot/)
- [Protótipo visual](docs/prototipo-lovable/)
- [O que veio da versão anterior](docs/legado.md)

## Licença

[MIT](LICENSE)
