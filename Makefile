# Docker por padrão. Com a palavra podman no fim, o Podman: make install podman.
# COMPOSE="..." na linha de comando escolhe qualquer outro e vale mais que os dois.
ifeq ($(origin COMPOSE),undefined)
ifneq ($(filter podman,$(MAKECMDGOALS)),)
COMPOSE := $(shell podman compose version >/dev/null 2>&1 && echo podman compose || echo podman-compose)
else
COMPOSE := docker compose
endif
endif

# O UID do dono do projeto vira o do usuário app na imagem. Com root (id 0) não há como
# criar outro usuário com esse UID: a imagem usa 1000, e o app roda como root.
ifeq ($(shell id -u),0)
export APP_UID := 1000
export APP_USER := root
else
export APP_UID := $(shell id -u)
endif

# No Podman e no Docker sem root, o root do contêiner é você no computador, e o UID do app
# vira outro. Por isso o app roda como root lá: só assim grava o .env e os arquivos montados.
ifeq ($(APP_USER),)
ifneq ($(findstring podman,$(COMPOSE)),)
export APP_USER := $(shell podman info --format '{{.Host.Security.Rootless}}' 2>/dev/null | grep -qx true && echo root)
else
export APP_USER := $(shell docker info --format '{{.SecurityOptions}}' 2>/dev/null | grep -q rootless && echo root)
endif
endif

# O tools fica num profile. O docker compose ativa o profile de um serviço chamado no run;
# o podman-compose não, e diria "missing services [tools]".
TOOLS = $(COMPOSE) --profile tools run --rm tools

.DEFAULT_GOAL := help
.PHONY: help install start load stop restart logs test lint format migrate shell check backtest backup restore mcp-config connect autostart autostart-off podman

help: ## Lista os comandos
	@awk 'BEGIN {FS = ":.*## "} /^[a-z-]+:.*## / {printf "  make %-13s %s\n", $$1, $$2}' $(MAKEFILE_LIST)
	@echo
	@echo "  Com Podman, acrescente podman no fim: make install podman, make start podman..."

# Só marca o Podman (veja o COMPOSE acima). Sozinho, mostra a ajuda.
podman:
	@$(if $(filter-out podman,$(MAKECMDGOALS)),:,$(MAKE) --no-print-directory help)

# Todos os serviços do app usam a mesma imagem: construir só a do web. Um build sem serviço,
# ou um up que ainda não acha a imagem, faz o podman-compose construí-la uma vez por serviço,
# todas ao mesmo tempo, e isso esgota a memória da máquina virtual do Podman no Mac.
install: ## Cria o .env sem sobrescrever um existente, constrói a imagem e prepara o banco
	@test -f .env || cp .env.example .env
	$(COMPOSE) build web
	# O entrypoint do web gera a APP_KEY e migra.
	$(COMPOSE) run --rm web true

start: ## Sobe o app
	$(COMPOSE) build web
	$(COMPOSE) up -d

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
	$(TOOLS) php artisan test

lint: ## Confere a formatação
	$(TOOLS) vendor/bin/pint --test

format: ## Corrige a formatação
	$(TOOLS) vendor/bin/pint

migrate: ## Atualiza o banco
	# O entrypoint do web migra: é o único lugar que faz isso.
	$(COMPOSE) run --rm web true

shell: ## Abre um terminal no contêiner do app
	$(TOOLS) bash

check: lint ## Roda as checagens de backend do CI
	$(TOOLS) vendor/bin/phpstan analyse --no-progress --memory-limit=1G
	$(TOOLS) php artisan test

backtest: ## Simula os avisos sobre preços passados, sem gravar nada: make backtest TICKER=KNRI11
	@test -n "$(TICKER)" || { echo "Informe o fundo: make backtest TICKER=KNRI11"; exit 1; }
	$(TOOLS) php artisan fiis:backtest $(TICKER)

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
# O podman-compose não tem --project-directory, e o podman e o podman-compose se acham pelo
# PATH (que podem estar em pastas diferentes). Com Podman, o command é um sh que entra na
# pasta (o Compose lê o override de lá), põe na frente o PATH do terminal que rodou o make e
# roda o resto. Pasta e PATH vão como argumentos ($0 e $1), fora do script.
mcp-config: export MCP_DIR := $(CURDIR)
mcp-config: export MCP_CMD := $(COMPOSE)
mcp-config: ## Imprime o JSON do servidor MCP para colar à mão
	@# Caminho e comando chegam pelo ambiente, e não entre aspas: a pasta pode ter ' no nome.
	@BIN="$$(command -v "$${MCP_CMD%% *}" || echo "$${MCP_CMD%% *}")" \
	SH='cd "$$0" && PATH="$$1:$$PATH" && shift && exec "$$@"' \
	awk 'function q(v) { gsub(/\\/, "\\\\", v); gsub(/"/, "\\\"", v); return "\"" v "\"" } \
	BEGIN { n = split(ENVIRON["MCP_CMD"], c, " "); c[1] = ENVIRON["BIN"]; rest = ""; \
		for (i = 2; i <= n; i++) rest = rest q(c[i]) ", "; \
		if (ENVIRON["MCP_CMD"] ~ /podman/) { \
			cmd = q("/bin/sh"); \
			args = q("-c") ", " q(ENVIRON["SH"]) ", " q(ENVIRON["MCP_DIR"]) ", " q(ENVIRON["PATH"]) ", " q(c[1]) ", " rest; \
		} else { \
			cmd = q(c[1]); \
			args = rest q("--project-directory") ", " q(ENVIRON["MCP_DIR"]) ", "; \
		} \
		printf "{\n  \"mcpServers\": {\n    \"me-avisa-ai\": {\n      \"command\": %s,\n      \"args\": [%s\"exec\", \"-T\", \"web\", \"php\", \"artisan\", \"mcp:start\", \"fiis\"]\n    }\n  }\n}\n", cmd, args }'

# CLIENT só vale na linha de comando: um CLIENT exportado no shell não pula o menu.
connect: export CONNECT_COMPOSE := $(COMPOSE)
connect: export CONNECT_CLIENT := $(if $(filter command line,$(origin CLIENT)),$(CLIENT))
connect: ## Liga uma IA (Claude, Cursor, Antigravity) ao app: make connect [CLIENT=cursor]
	@bin/connect

# O Docker religa o app sozinho ao ligar o computador; o Podman não tem esse serviço.
# Um serviço do systemd do usuário faz o up -d no boot, e o linger o roda antes do login.
autostart: export AUTOSTART_COMPOSE := $(COMPOSE)
autostart: ## Podman no Linux: liga o app junto com o computador (make autostart podman)
	@bin/autostart on

autostart-off: ## Podman no Linux: não liga mais o app com o computador
	@bin/autostart off
