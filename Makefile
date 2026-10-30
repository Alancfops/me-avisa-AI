COMPOSE ?= docker compose
export APP_UID := $(shell id -u)

.DEFAULT_GOAL := help
.PHONY: help install start stop restart logs test lint format migrate shell check

help: ## Lista os comandos
	@awk 'BEGIN {FS = ":.*## "} /^[a-z-]+:.*## / {printf "  make %-10s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

install: ## Cria o .env sem sobrescrever um existente, constrói as imagens e prepara o banco
	@test -f .env || cp .env.example .env
	$(COMPOSE) build
	# O entrypoint do web gera a APP_KEY e migra.
	$(COMPOSE) run --rm web true

start: ## Sobe o app
	$(COMPOSE) up -d --build

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

check: lint ## Roda o que o CI confere
	$(COMPOSE) run --rm tools vendor/bin/phpstan analyse --no-progress --memory-limit=1G
	$(COMPOSE) run --rm tools php artisan test
