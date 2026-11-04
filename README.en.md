# Me Avisa Aí

[Português](README.md) · **English**

Tracks your favorite Brazilian real estate funds (FIIs) and tells you when one reaches
the price you set or drops more than usual. Instead of opening your brokerage app to
check prices, you look at one screen and see whether there's anything to analyze today.

> **Under construction.** The FII module already works with Docker: favorites, quotes,
> rules, alerts on the website and on Telegram, the portfolio and the Health page. The interface is still minimal, without the
> visual polish, and the demo mode does not exist yet. Funds whose ISIN is declared by more
> than one CVM record have no book value, so the automatic rule does not fire for them.
>
> Installation status: **Docker** tested on Linux; **OrbStack** and **Podman** not tested
> yet; **without containers** not available yet.

The name is Brazilian Portuguese for roughly "give me a heads-up". The "Aí" is not "AI".

## What it does

The FII module offers:

- **Favorites:** search by ticker or name.
- **Target price:** alerts you when the price reaches or falls below your value.
- **Buy range:** alerts you when the price enters a range.
- **Automatic rule:** alerts you when the price is 5% below its 30-session average and
  the P/B ratio is below the fund's own 12-month median.
- **Sharp drop:** alerts you when the share price falls more than 3% in a day.
- **Anti-spam:** avoids repeating the same alert without hiding a different one.
- **Fund page:** P/B compared with the 12-month median, price compared with the 6- and
  12-month averages, price and P/B charts, and the latest estimated distribution.
- **All FIIs:** a list of the funds traded on B3, with type (brick, paper etc.), segment,
  price, P/B and 12-month DY, filters and a favorite button.
- **Telegram bot:** gets the alerts and answers commands such as `/alertas` and `/ver KNRI11`.
- **Ceiling price:** the highest price at which the fund yields the annual rate you pick,
  with a button to use it as the target price.

Quotes update every 30 minutes, from 10:00 to 18:00 Brasília time, on weekdays. Alerts
appear on the website. The full rules, in Portuguese, are in
[`docs/fiis/alert.md`](docs/fiis/alert.md).

**This app provides information, not investment advice.** An alert means a rule was
met, not that an asset is cheap or should be bought.

## Installation

Each installation serves one person, with their own keys, and is reachable only from
their computer. There is no login, so don't expose the app to the internet.

**Before you start:** create a free [brapi](https://brapi.dev/dashboard) account and
copy your token. The free plan allows 15,000 requests a month, and each tracked fund uses
about 350.

Pick one of the options below:

| Option | Best for |
|---|---|
| [Docker](#docker) | Linux and Windows, or anyone already using Docker |
| [OrbStack](#orbstack-mac) | Mac, with lower resource use than Docker Desktop |
| [Podman](#podman) | Anyone who prefers not to use Docker |
| [Without containers](#without-containers) | Anyone who wants everything installed directly (coming soon) |

All of them use PostgreSQL. The day-to-day `make` commands apply to the container options.

### Docker

Requires [Docker](https://docs.docker.com/get-docker/) with Compose, and `make`.

```bash
git clone https://github.com/Alancfops/me-avisa-AI.git
cd me-avisa-AI
make install   # creates .env, builds the images and prepares the database
```

Add your token to `.env`:

```dotenv
BRAPI_TOKEN=your_token
```

Then start the app and open <http://127.0.0.1:8000>:

```bash
make start
```

The first time, load the search catalog, the CVM reports and the B3 closing prices. After
that, they update on their own once a day. The yearly B3 history downloads files of about
90 MB and can take a few minutes:

```bash
docker compose exec web php artisan fiis:sync-catalog
docker compose exec web php artisan fiis:import-reports
docker compose exec web php artisan fiis:import-exchange-closes
docker compose exec web php artisan fiis:import-exchange-history
```

Without `make`, which is common on Windows, use Compose directly:

```bash
cp .env.example .env
docker compose up -d --build
```

On Linux, if your user's UID isn't 1000, set `APP_UID=$(id -u)` before these commands, or
the app can't write to `.env`.

### OrbStack (Mac)

[OrbStack](https://orbstack.dev) provides the `docker` and `docker compose` commands,
so the steps are the same as in [Docker](#docker). Check OrbStack's license page if
you use it professionally.

```bash
brew install orbstack
open -a OrbStack
```

### Podman

Requires [Podman](https://podman.io/docs/installation) with Compose support. On a Mac,
start the virtual machine first:

```bash
brew install podman podman-compose
podman machine init
podman machine start
```

Install and start the app, passing the Podman command:

```bash
git clone https://github.com/Alancfops/me-avisa-AI.git
cd me-avisa-AI
make install COMPOSE="podman compose"
make start COMPOSE="podman compose"
```

In the first-load commands, replace `docker compose` with `podman compose`.

With Podman, the app may not come back on its own when the computer boots. After
booting, run `podman machine start` if you're on a Mac, then `make start COMPOSE="podman compose"`.

### Without containers

> **Not available yet.** The `make native-*` commands don't exist yet; this section shows
> how this option will work.

Requires PHP 8.4 (with `pdo_pgsql`, `bcmath` and `zip`), Composer, Node 22, PostgreSQL 17 and
`make`. On a Mac, with [Homebrew](https://brew.sh):

```bash
brew install php composer node@22 postgresql@17
brew services start postgresql@17
createdb me_avisa_ai
```

Install and start the app:

```bash
git clone https://github.com/Alancfops/me-avisa-AI.git
cd me-avisa-AI
make native-install   # creates .env, installs dependencies and prepares the database
make native-start     # starts the website, scheduler and queue together
```

Set `BRAPI_TOKEN` and the database credentials in `.env`. With this option, the app
doesn't start with the computer by default.

## Telegram bot

With the bot, every alert on the website also reaches your Telegram, and you can check
favorites, portfolio and funds with commands, without opening the app.

**Why does each person create their own bot?** The app installed on your computer is
what reads and answers the messages. One bot for everyone would need a server on the
internet holding everyone's data, and Me Avisa Aí runs only on your machine. Creating a
bot is free and takes about 2 minutes.

### 1. Create the bot

1. In Telegram, open [@BotFather](https://t.me/BotFather) and send `/newbot`.
2. When it asks for the **name**, send `Me Avisa Aí`.
3. When it asks for the **username**, make one up ending in `bot`. It must be unique in
   all of Telegram, so put something of yours in the middle, like `meavisaai_maria_bot`.
4. @BotFather answers with the **token**, a line like `123456789:AAH...`. Copy it.

### 2. Give the token to the app

Open the app's **Telegram** tab (<http://127.0.0.1:8000/telegram>), paste the token and
tap **Salvar token**. The app checks the token with Telegram and stores it encrypted in
the database. In a few seconds the bot starts working, with no restart.

The app also gives the bot the standard name, description and command menu (what shows
up when you type `/` in the chat).

> Rather use a file? Put `TELEGRAM_BOT_TOKEN=your_token` in `.env` and run `make start`.
> The `.env` token takes precedence over the one from the tab.

### 3. Connect your chat

The same tab shows a **QR code** and the **Abrir no Telegram** button:

- **on the phone:** point the camera at the QR code and tap **Start** in Telegram;
- **on the computer:** tap **Abrir no Telegram** and then **Start**.

The bot answers "✅ Conectado" and the tab switches to **Conectado** by itself. Use
**Enviar mensagem de teste** to check.

The code inside the link proves that whoever sent `/start` is the person with the app
open. It lasts 15 minutes and works once. Once connected, the bot ignores every other
chat.

### Commands

| Command | Answer |
|---|---|
| `/alertas` | Favorites that met a rule today |
| `/ver CÓDIGO` | Price, P/B, DY and distribution of a fund (the B3 ticker, ending in 11) |
| `/favoritos` | Your favorites with their price |
| `/carteira` | Portfolio value and estimated income |
| `/ajuda` | The command list |

The "Ver no app" links in the messages open only on the computer running the app. On
the phone, use the commands.

### Disconnect, switch bots and security

- **Disconnect:** **Desconectar** button on the tab. Alerts stay on the website. To come
  back, connect again with the QR code.
- **Switch bots:** **Usar outro bot** on the tab, then paste the new token.
- **Leaked token:** send `/revoke` to @BotFather, pick the bot and paste the new token on
  the tab (or in `.env`). Whoever has the token controls the bot, so don't share it.
- The app fetches messages from Telegram; nothing on your computer is exposed to the
  internet.

### If the bot doesn't answer

| What you see | What to do |
|---|---|
| No answer at all | Check the app is running (`make start`). Without the app, the bot is silent. |
| "O Telegram recusou o token" | The token was revoked or mistyped. Copy it again from @BotFather. |
| "O Telegram não respondeu agora" | No internet or Telegram is down. The bot recovers by itself. |
| "Este bot ainda não está conectado" | Create the code on the tab and connect with the QR code. |

The **Saúde** tab shows the bot's last successful send or read and its last error.

## Starting with the computer

With Docker (Linux), Docker Desktop or OrbStack, the app comes back when the computer
starts: Docker starts with the system and brings the app's services back, unless you
stopped them with `make stop`. Podman is different (see below).

While the app is stopped, no alerts go out, neither on the website nor on Telegram.
When it starts again, it catches up on the closing prices and reports it missed.

### Turn only the app off and on

| I want to | Command |
|---|---|
| Stop the app now and keep it from coming back when the computer starts | `make stop` |
| Turn it on again (and start with the computer again) | `make start` |

This is the simplest way: Docker still starts with the system, but the app stays off
until you run `make start`.

### Keep Docker from starting with the computer

So that nothing from Docker opens by itself:

| Where | Turn off | Turn back on |
|---|---|---|
| Linux (Docker Engine) | `sudo systemctl disable docker.service docker.socket` | `sudo systemctl enable --now docker.service docker.socket` |
| Docker Desktop (Windows, Mac, Linux) | **Settings → General**, uncheck **Start Docker Desktop when you sign in** | Check the same option |
| OrbStack (Mac) | **Settings → General**, uncheck **Start at login** | Check the same option |

With Docker off at boot, open Docker (or run `sudo systemctl start docker`) and then
`make start` when you want to use the app.

### Podman

Podman has no service that brings containers back by itself, so the app does **not**
start with the computer. After turning the machine on:

```bash
podman machine start              # Mac only
make start COMPOSE="podman compose"
```

To stop: `make stop COMPOSE="podman compose"`.

## Commands

| Command | What it does |
|---|---|
| `make` | Lists the commands |
| `make install` | Creates `.env` without overwriting an existing one, builds the images and prepares the database |
| `make start` / `make stop` | Starts or stops the app |
| `make restart` / `make logs` | Restarts or follows the logs |
| `make test` | Runs the tests on a separate database, without touching your data |
| `make lint` / `make format` | Checks or fixes formatting |
| `make migrate` | Updates the database |
| `make shell` | Opens a terminal in a tools container |
| `make check` | Runs the backend CI checks |
| `make backtest TICKER=KNRI11` | Counts the alerts past prices would have triggered, without saving anything; it does not say whether buying would have been profitable |

## Architecture

A modular Laravel monolith with pragmatic Clean Architecture. The alert rules don't
depend on the framework, the database or the APIs, so they can be tested on their own.

```text
app/Modules/Fiis/
├── Domain/          alert and anti-spam rules, plain PHP
├── Application/     use cases and interfaces
├── Infrastructure/  PostgreSQL, brapi, CVM, B3
└── Http/            controllers and Inertia responses

app/Modules/Telegram/  bot: messages, commands and alert delivery
```

Architecture tests keep these layers from mixing.

**Stack:** Laravel, React (Inertia), TypeScript, Tailwind v4, shadcn/ui, PostgreSQL and
Docker Compose.

**Data:** quotes for favorites from [brapi](https://brapi.dev); net asset value, dividend
yield and portfolio composition from the
[CVM monthly FII report](https://dados.cvm.gov.br/dataset/fii-doc-inf_mensal); daily and
historical closing prices from
[B3 (COTAHIST)](https://www.b3.com.br/pt_br/market-data-e-indices/servicos-de-dados/market-data/historico/mercado-a-vista/cotacoes-historicas/).

## Upcoming modules

1. **FIIs:** favorites, market data, rules and on-site alerts *(working, with a minimal interface)*
2. **Telegram bot:** alerts and commands *(working)*
3. MCP server, to manage favorites by chatting with Claude
4. Stocks and ETFs

## Documentation

Most project documents are in Portuguese.

- [FII module design](docs/superpowers/specs/2026-10-29-modulo-fiis-design.md)
- Product rules: [FIIs](docs/fiis/), [stocks](docs/acoes/), [ETFs](docs/etfs/) and [bot](docs/bot/)
- [Visual prototype](docs/prototipo-lovable/)
- [What came from the previous version](docs/legado.md)

## License

[MIT](LICENSE)
