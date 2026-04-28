# Backend (Rails API)

API REST do **me_avisaAI**; dados em **PostgreSQL** (Supabase em produção). Módulos de domínio: `app/services/fiis`, `acoes`, `ets` (espelho de `docs/`).

## Requisitos

- **Ruby** 4.x (ver `.ruby-version`) — instalador RubyInstaller+Devkit em `D:\Ruby\...` conforme a tua máquina.
- **PostgreSQL** local ou só `DATABASE_URL` do Supabase.
- **MSYS2 / Devkit** completo para gems nativas (`psych`, `pg`, etc.):

```powershell
D:\Ruby\ruby-4.0.3-x64\bin\ridk.cmd install 1
# Depois, no shell ridk (ucrt64), instalar libyaml se o psych falhar:
# pacman -S --needed mingw-w64-ucrt-x86_64-libyaml
```

## Setup

```powershell
cd backend
copy .env.example .env
# Editar .env com DATABASE_URL ou usar Postgres local (user/password em database.yml quando sem DATABASE_URL)

bundle install
rails db:create db:migrate
rails s -p 3000
```

- Health Rails: `GET http://localhost:3000/up`
- Health API JSON: `GET http://localhost:3000/api/v1/health`

## Bot Telegram

Ver pasta **`bot/`** — processo separado que consome endpoints internos do Rails (ver `docs/bot/` e `.cursor/skills/telegram-bot/`).
