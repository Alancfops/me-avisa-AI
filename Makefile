COMPOSE ?= docker compose
export APP_UID := $(shell id -u)

.DEFAULT_GOAL := help
.PHONY: help install start load stop restart logs test lint format migrate shell check backtest backup restore mcp-config connect

help: ## Lista os comandos
	@awk 'BEGIN {FS = ":.*## "} /^[a-z-]+:.*## / {printf "  make %-10s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

install: ## Cria o .env sem sobrescrever um existente, constrói as imagens e prepara o banco
	@test -f .env || cp .env.example .env
	$(COMPOSE) build
	# O entrypoint do web gera a APP_KEY e migra.
	$(COMPOSE) run --rm web true

start: ## Sobe o app
	$(COMPOSE) up -d --build

load: ## Primeira carga: catálogo, informes da CVM e fechamentos da B3 (o app precisa estar no ar)
	$(COMPOSE) exec web php artisan fiis:sync-catalog
	$(COMPOSE) exec web php artisan fiis:import-reports
	$(COMPOSE) exec web php artisan fiis:import-exchange-closes
	# Arquivos anuais de ~90 MB: pode levar alguns minutos.
	$(COMPOSE) exec web php artisan fiis:import-exchange-history

stop: ## Para o app
	$(COMPOSE) stop

restart: ## Reinicia o app
	$(COMPOSE) restart

logs: ## Acompanha os logs
	$(COMPOSE) logs -f

test: ## Roda os testes
	$(COMPOSE) run --rm tools php artisan test

lint: ## Confere a formatação
	$(COMPOSE) run --rm tools vendor/bin/pint --test

format: ## Corrige a formatação
	$(COMPOSE) run --rm tools vendor/bin/pint

migrate: ## Atualiza o banco
	# O entrypoint do web migra: é o único lugar que faz isso.
	$(COMPOSE) run --rm web true

shell: ## Abre um terminal no contêiner do app
	$(COMPOSE) run --rm tools bash

check: lint ## Roda as checagens de backend do CI
	$(COMPOSE) run --rm tools vendor/bin/phpstan analyse --no-progress --memory-limit=1G
	$(COMPOSE) run --rm tools php artisan test

backtest: ## Simula os avisos sobre preços passados, sem gravar nada: make backtest TICKER=KNRI11
	@test -n "$(TICKER)" || { echo "Informe o fundo: make backtest TICKER=KNRI11"; exit 1; }
	$(COMPOSE) run --rm tools php artisan fiis:backtest $(TICKER)

# O pg_dump roda dentro do contêiner do banco, com o usuário e o banco que ele já conhece.
# --clean faz o arquivo trocar as tabelas ao ser restaurado. O backup só vale se terminar com a
# linha final do pg_dump: o resultado do pipe é o do gzip, que não vê o erro do banco.
backup: ## Salva uma cópia do banco em backups/ (o app precisa estar no ar)
	@mkdir -p backups
	@file="backups/me-avisa-ai-$$(date +%Y-%m-%d-%H%M%S).sql.gz"; \
	if $(COMPOSE) exec -T postgres sh -c 'pg_dump --clean --if-exists --no-owner -U "$$POSTGRES_USER" "$$POSTGRES_DB"' | gzip > "$$file" \
		&& gunzip -c "$$file" | tail -n 5 | grep -q "PostgreSQL database dump complete"; then \
		echo "Backup salvo em $$file"; \
	else \
		rm -f "$$file"; echo "O backup falhou (o arquivo incompleto foi descartado). O app está no ar? Rode make start."; exit 1; \
	fi

restore: ## Volta o banco para um backup, depois de salvar o atual: make restore FILE=backups/...
	@test -n "$(FILE)" || { echo "Informe o arquivo: make restore FILE=backups/me-avisa-ai-....sql.gz"; exit 1; }
	@gunzip -c "$(FILE)" 2>/dev/null | tail -n 5 | grep -q "PostgreSQL database dump complete" || { echo "$(FILE) não é um backup válido."; exit 1; }
	@printf "Os dados atuais serão trocados pelos de $(FILE). Antes, uma cópia deles vai para backups/. Continuar? [s/N] "; \
	read answer; [ "$$answer" = s ] || [ "$$answer" = S ] || { echo "Nada mudou."; exit 1; }
	@$(MAKE) --no-print-directory backup COMPOSE="$(COMPOSE)"
	$(COMPOSE) stop web scheduler worker bot
	gunzip -c "$(FILE)" | $(COMPOSE) exec -T postgres sh -c 'psql -q -v ON_ERROR_STOP=1 -U "$$POSTGRES_USER" -d "$$POSTGRES_DB"'
	$(COMPOSE) up -d
	@echo "Banco restaurado de $(FILE)."

# $(COMPOSE) pode ter mais de uma palavra ("podman compose"): a primeira é o command e o resto abre o args.
# --project-directory (e não -f) faz o Compose ler também o compose.override.yaml.
# O command leva o caminho completo: no Mac, apps abertos pelo Dock não herdam o PATH do terminal.
mcp-config: export MCP_DIR := $(CURDIR)
mcp-config: export MCP_CMD := $(COMPOSE)
mcp-config: ## Imprime o JSON do servidor MCP para colar à mão
	@# Caminho e comando chegam pelo ambiente, e não entre aspas: a pasta pode ter ' no nome.
	@BIN="$$(command -v "$${MCP_CMD%% *}" || echo "$${MCP_CMD%% *}")" \
	awk 'function q(v) { gsub(/\\/, "\\\\", v); gsub(/"/, "\\\"", v); return "\"" v "\"" } \
	BEGIN { n = split(ENVIRON["MCP_CMD"], c, " "); c[1] = ENVIRON["BIN"]; args = ""; \
		for (i = 2; i <= n; i++) args = args q(c[i]) ", "; \
		args = args q("--project-directory") ", " q(ENVIRON["MCP_DIR"]); \
		printf "{\n  \"mcpServers\": {\n    \"me-avisa-ai\": {\n      \"command\": %s,\n      \"args\": [%s, \"exec\", \"-T\", \"web\", \"php\", \"artisan\", \"mcp:start\", \"fiis\"]\n    }\n  }\n}\n", q(c[1]), args }'

# CLIENT só vale na linha de comando: um CLIENT exportado no shell não pula o menu.
connect: export CONNECT_COMPOSE := $(COMPOSE)
connect: export CONNECT_CLIENT := $(if $(filter command line,$(origin CLIENT)),$(CLIENT))
connect: ## Liga uma IA (Claude, Cursor, Antigravity) ao app: make connect [CLIENT=cursor]
	@bin/connect
