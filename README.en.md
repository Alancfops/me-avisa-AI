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
- **Telegram bot:** gets the alerts and answers commands such as `/alertas` and `/ver CÓDIGO`.
- **Ceiling price:** the highest price at which the fund yields the annual rate you pick,
  with a button to use it as the target price.

Quotes update every 30 minutes, from 10:00 to 18:00 Brasília time, on weekdays. Alerts
appear on the website and, if you connect the bot, on Telegram. The full rules, in Portuguese, are in
[`docs/fiis/alert.md`](docs/fiis/alert.md).

**This app provides information, not investment advice.** An alert means a rule was
met, not that an asset is cheap or should be bought.

## Installation

Each installation serves one person, with their own keys, and is reachable only from
their computer. There is no login, so don't expose the app to the internet.

The recommended path uses containers (Docker, OrbStack or Podman): you don't install PHP,
Node or a database, only the container program.

| Your computer | Use | Status |
|---|---|---|
| Mac (Intel or Apple Silicon) | [OrbStack](https://orbstack.dev) (lighter) or [Docker Desktop](https://docs.docker.com/desktop/setup/install/mac-install/) | Not tested yet: tell us if something breaks |
| Linux | [Docker Engine](https://docs.docker.com/engine/install/) | Tested |
| Windows | [Docker Desktop](https://docs.docker.com/desktop/setup/install/windows-install/) with WSL 2 | Not tested yet |
| Any, without Docker | [Podman](#podman) | Not tested yet |

### 1. Install what's missing

**Mac**

1. Open **Terminal** (Applications → Utilities).
2. Install Apple's command line tools, which bring `git` and `make`:

   ```bash
   xcode-select --install
   ```

   If it says "already installed", you're set.
3. Install a container program. With [Homebrew](https://brew.sh):

   ```bash
   brew install orbstack     # or: brew install --cask docker
   open -a OrbStack          # or: open -a Docker
   ```

   Without Homebrew, download the installer from [OrbStack](https://orbstack.dev) or
   [Docker Desktop](https://docs.docker.com/desktop/setup/install/mac-install/). Open it
   once and wait until it finishes starting. OrbStack is free for personal use; check
   the license for work use.

**Linux**

Install [Docker Engine](https://docs.docker.com/engine/install/) with the Compose plugin,
plus `git` and `make` (on Ubuntu: `sudo apt install git make`). To run Docker without
`sudo`, follow the [post-install steps](https://docs.docker.com/engine/install/linux-postinstall/).

**Windows**

Install [Docker Desktop](https://docs.docker.com/desktop/setup/install/windows-install/)
with WSL 2. Open the Ubuntu (WSL) terminal, install `git` and `make`
(`sudo apt install git make`) and follow the next steps inside it.

To check, run `docker compose version`. It should print a version.

### 2. Download and install the app

```bash
git clone https://github.com/Alancfops/me-avisa-AI.git
cd me-avisa-AI
make install
```

`make install` creates the `.env` settings file, builds the images and prepares
the database. The first time takes a few minutes.

### 3. Start the app

```bash
make start
```

Open <http://127.0.0.1:8000> in the browser.

### 4. Paste the brapi key

brapi provides the quotes. Create a free account at <https://brapi.dev/dashboard> and
copy the token from the dashboard. The free plan gives 15,000 requests a month, and each
favorite uses about 350.

In the app, open **Configurações**, paste the token in **Chave da brapi** and tap
**Salvar chave**. The app checks the key with brapi and stores it encrypted; no file to
open. While the key is missing, a notice at the top of the site links to that field.

> Rather use a file? Put `BRAPI_TOKEN=your_token` in `.env` and run `make start`. The
> `.env` key takes precedence over the one from the site.

### 5. Run the first load

It loads the fund list, the CVM reports and the B3 closing prices. The yearly B3 history
downloads files of about 90 MB, so it can take a few minutes:

```bash
make load
```

After that everything updates by itself: quotes every 30 minutes during trading hours
and the rest once a day.

### 6. Check

- Open the **Saúde** tab: every source should show the time of its last read.
- Favorite a FII in **Todos os FIIs** and set when you want to be alerted.
- To get the alerts on your phone, follow [Telegram bot](#telegram-bot).

### Update, stop and uninstall

| I want to | Command |
|---|---|
| Update to the newest version | `git pull`, then `make start` (the database updates by itself) |
| Stop the app | `make stop` |
| See what's going on | `make logs` (exit with Ctrl+C) |
| Uninstall and **delete all data** | `docker compose down -v` and delete the `me-avisa-AI` folder |

### If something goes wrong

| Problem | What to do |
|---|---|
| `make: command not found` | Mac: `xcode-select --install`. Linux/WSL: `sudo apt install make` |
| `Cannot connect to the Docker daemon` | Open OrbStack or Docker Desktop and wait for it to start |
| Port 8000 is already in use | Close the other program using port 8000 |
| On Linux, permission error on `.env` | Run `make install` again: it uses your user (`APP_UID`) |
| A source with an error on Saúde | Normal right after the computer starts or without internet; the app retries by itself |

Without `make`, use Compose directly: `cp .env.example .env`, `docker compose up -d --build`
and, for the first load, the commands of the `load` rule in the `Makefile`, each prefixed
with `docker compose exec web`.

### Podman

Requires [Podman](https://podman.io/docs/installation) with Compose support. On Mac:

```bash
brew install podman podman-compose
podman machine init
podman machine start
```

Follow steps 2 to 6 above, adding `COMPOSE="podman compose"` to every `make`:

```bash
make install COMPOSE="podman compose"
make start COMPOSE="podman compose"
make load COMPOSE="podman compose"
```

With Podman, the app does not come back by itself when the computer starts (see
[Starting with the computer](#starting-with-the-computer)).

### Without containers

> **Not available yet.** The `make native-*` commands don't exist yet; this section shows
> how this option will work.

Requires PHP 8.4 (with `pdo_pgsql`, `bcmath` and `zip`), Composer, Node 22, PostgreSQL 17
and `make`. On Mac, with [Homebrew](https://brew.sh):

```bash
brew install php composer node@22 postgresql@17
brew services start postgresql@17
createdb me_avisa_ai
```

Install and start the app:

```bash
git clone https://github.com/Alancfops/me-avisa-AI.git
cd me-avisa-AI
make native-install   # creates .env, installs the dependencies and prepares the database
make native-start     # starts the website, the scheduler and the queue together
```

Set `BRAPI_TOKEN` and the database access in `.env`. In this option, the app does not
start with the computer by default.

## Telegram bot

With the bot, every alert on the website also reaches your Telegram, and you can check
favorites, portfolio and funds with commands, without opening the app.

**Why does each person create their own bot?** The app installed on your computer is
what reads and answers the messages. One bot for everyone would need a server on the
internet holding everyone's data, and Me Avisa Aí runs only on your machine. Creating a
bot is free and takes about 2 minutes.

### 1. Create the bot

Do it in Telegram **on the computer** (the app or <https://web.telegram.org>): that way
the token is already on the computer, ready to paste into the app.

| Step | You do | @BotFather answers |
|---|---|---|
| 1 | Open [@BotFather](https://t.me/BotFather) (the official one, with the blue check), tap **Start** and send `/newbot` | *Alright, a new bot. How are we going to call it?* |
| 2 | Send the name: `Me Avisa Aí` | *Now let's choose a username for your bot. It must end in `bot`.* |
| 3 | Send a username ending in `bot`, with something of yours in the middle, like `meavisaai_maria_bot` | If taken: *Sorry, this username is already taken.* Try another |
| 4 | — | *Done! Congratulations on your new bot.* and, below *Use this token to access the HTTP API:*, the **token**, a line like `123456789:AAH...` |

Tap the token to copy it.

### 2. Give the token to the app

Open the app's **Telegram** tab (<http://127.0.0.1:8000/telegram>), paste the token and
tap **Salvar token**. No file to open and no terminal needed: the app checks the token with Telegram and stores it encrypted in
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
| `make load` | First load: funds, CVM reports and B3 closing prices |
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
- [Telegram bot design](docs/superpowers/specs/2026-11-04-bot-telegram-design.md)
- Product rules: [FIIs](docs/fiis/), [stocks](docs/acoes/), [ETFs](docs/etfs/) and [bot](docs/bot/)
- [Visual prototype](docs/prototipo-lovable/)
- [What came from the previous version](docs/legado.md)

## License

[MIT](LICENSE)
