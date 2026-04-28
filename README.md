# me_avisaAI

Monorepo: **Rails API** (`backend/`), **React + Vite + Tailwind** (`frontend/`), bot Telegram em `backend/bot/`. Documentação de produto em `docs/`. Regras e automação em `.cursor/`.

## Início rápido

1. **Ruby / Rails** — ver `backend/README.md` (`bundle install`, `ridk` + libyaml no Windows, Postgres ou `DATABASE_URL` Supabase).
2. **Backend:** `cd backend` → copiar `backend/.env.example` para `.env` → `rails db:create` → `rails s -p 3000`.
3. **Frontend:** `cd frontend` → `copy .env.example .env` → `npm install` → `npm run dev` (porta 5173).

- API health: `GET /api/v1/health`
- CORS: variável `FRONTEND_ORIGIN` (default `http://localhost:5173`).

## Repositório remoto

`https://github.com/Alancfops/me-avisa-AI.git` — fluxo Git: `.cursor/skills/github-workflow/SKILL.md`.
