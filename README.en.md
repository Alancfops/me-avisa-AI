# Me Avisa Aí

[Português](README.md) · **English**

Tracks your favorite Brazilian real estate funds (FIIs) and tells you when one reaches
the price you set or drops more than usual. Instead of opening your brokerage app to
check prices, you look at one screen and see whether there's anything to analyze today.

> **Under construction.** There is no installable code yet: the commands below show how
> installation will work and will be checked with each release.

The name is Brazilian Portuguese for roughly "give me a heads-up". The "Aí" is not "AI".

## What it does

The FII module, currently in development, offers:

- **Favorites:** search by ticker or name.
- **Target price:** alerts you when the price reaches or falls below your value.
- **Buy range:** alerts you when the price enters a range.
- **Automatic rule:** alerts you when the price is 5% below its 30-session average and
  the P/B ratio is below the fund's own 12-month median.
- **Sharp drop:** alerts you when the share price falls more than 3% in a day.
- **Anti-spam:** avoids repeating the same alert without hiding a different one.

Quotes update every 30 minutes, from 10:00 to 18:00 Brasília time, on weekdays. Alerts
appear on the website. The full rules, in Portuguese, are in
[`docs/fiis/alert.md`](docs/fiis/alert.md).

**This app provides information, not investment advice.** An alert means a rule was
met, not that an asset is cheap or should be bought.

## Installation

Each installation serves one person, with their own keys, and is reachable only from
their computer. There is no login, so don't expose the app to the internet.

**Before you start:** create a free [brapi](https://brapi.dev/dashboard) account and
copy your token. The free plan allows 15,000 requests a month, about 350 per tracked fund.

Pick one of the options below:

| Option | Best for |
|---|---|
| [Docker](#docker) | Linux and Windows, or anyone already using Docker |
| [OrbStack](#orbstack-mac) | Mac, with lower resource use than Docker Desktop |
| [Podman](#podman) | Anyone who prefers not to use Docker |
| [Without containers](#without-containers) | Anyone who wants everything installed directly |

All of them use PostgreSQL and the same `make` commands day to day.

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

Without `make`, which is common on Windows, use Compose directly:

```bash
cp .env.example .env
docker compose up -d --build
```

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

With Podman, the app may not come back on its own when the computer boots. After
booting, run `podman machine start` if you're on a Mac, then `make start COMPOSE="podman compose"`.

### Without containers

Requires PHP 8.4 (with `pdo_pgsql` and `bcmath`), Composer, Node 22, PostgreSQL 17 and
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

## Commands

| Command | What it does |
|---|---|
| `make` | Lists the commands |
| `make install` | Creates `.env` without overwriting an existing one, builds the images and prepares the database |
| `make start` / `make stop` | Starts or stops the app |
| `make restart` / `make logs` | Restarts or follows the logs |
| `make test` | Runs the tests |
| `make lint` / `make format` | Checks or fixes formatting |
| `make migrate` | Updates the database |
| `make shell` | Opens a terminal in the app container |
| `make check` | Runs what CI checks |

## Architecture

A modular Laravel monolith with pragmatic Clean Architecture. The alert rules don't
depend on the framework, the database or the APIs, so they can be tested on their own.

```text
app/Modules/Fiis/
├── Domain/          alert and anti-spam rules, plain PHP
├── Application/     use cases and interfaces
├── Infrastructure/  PostgreSQL, brapi, CVM
└── Http/            controllers and Inertia responses
```

Architecture tests keep these layers from mixing.

**Stack:** Laravel, React (Inertia), TypeScript, Tailwind v4, shadcn/ui, PostgreSQL and
Docker Compose.

**Data:** quotes from [brapi](https://brapi.dev); net asset value and dividend yield
from the [CVM monthly FII report](https://dados.cvm.gov.br/dataset/fii-doc-inf_mensal).

## Upcoming modules

1. **FIIs:** favorites, market data, rules and on-site alerts *(under construction)*
2. Telegram bot
3. MCP server, to manage favorites by chatting with Claude
4. Stocks and ETFs

## Documentation

Most project documents are in Portuguese.

- [FII module design](docs/superpowers/specs/2026-10-29-modulo-fiis-design.md)
- Product rules: [FIIs](docs/fiis/), [stocks](docs/acoes/), [ETFs](docs/ets/) and [bot](docs/bot/)
- [Visual prototype](docs/prototipo-lovable/)
- [What came from the previous version](docs/legado.md)

## License

[MIT](LICENSE)
