# Bot Telegram

Serviço que envia comandos ao **Rails** via HTTP (endpoints internos). **Não** duplicar regras de alerta aqui — ver `docs/bot/` e `.cursor/skills/telegram-bot/`.

## Setup (futuro)

```powershell
cd backend/bot
bundle install
ruby bot.rb
```

Variáveis típicas: `TELEGRAM_BOT_TOKEN`, `INTERNAL_API_BASE_URL`, `INTERNAL_API_TOKEN`, `SITE_URL`.
