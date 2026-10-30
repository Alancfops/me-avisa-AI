#!/bin/sh
# Confere as garantias de segurança e de operação do Compose. Requer um .env.
set -eu

COMPOSE="${COMPOSE:-docker compose}"
IMAGE="me-avisa-ai:local"

fail() {
    echo "FALHOU: $1" >&2
    exit 1
}

# Lista os serviços cujo bloco, na configuração resolvida, tem uma linha com o padrão.
services_matching() {
    printf '%s\n' "$config" | awk -v pattern="$1" '
        /^services:/ { inside = 1; next }
        /^[^ ]/ { inside = 0 }
        inside && /^  [a-zA-Z0-9_-]+:$/ { service = $1; sub(":", "", service) }
        inside && index($0, pattern) { print service }
    ' | sort -u | tr '\n' ' ' | sed 's/ $//'
}

# Fica numa variável e nunca é impressa: a configuração resolvida traz a senha do banco.
config="$($COMPOSE config)"

[ "$(printf '%s\n' "$config" | grep -c 'published:')" = 1 ] \
    || fail "deve haver exatamente uma porta publicada"
[ "$(printf '%s\n' "$config" | grep -c 'host_ip: 127.0.0.1')" = 1 ] \
    || fail "a porta publicada deve usar host_ip 127.0.0.1"
[ "$(services_matching 'published:')" = web ] || fail "só o web publica porta"
echo "ok: porta publicada só em 127.0.0.1"

[ "$(services_matching 'entrypoint-web.sh')" = web ] || fail "só o web usa entrypoint-web.sh"
[ -z "$(services_matching 'migrate')" ] || fail "nenhum comando do Compose pode migrar"
[ "$(grep -rl 'migrate --force' docker --include='*' --exclude=smoke-test.sh)" = docker/entrypoint-web.sh ] \
    || fail "só o entrypoint-web.sh roda migrate --force"
echo "ok: só o web migra"

[ "$(services_matching 'restart: unless-stopped')" = "postgres scheduler web worker" ] \
    || fail "os quatro serviços precisam de restart: unless-stopped"
echo "ok: quatro serviços com restart: unless-stopped"

$COMPOSE build --quiet web
docker run --rm --entrypoint sh "$IMAGE" -c 'test ! -f /app/.env' \
    || fail "a imagem contém .env"
docker run --rm --entrypoint sh "$IMAGE" -c '! ls -A /app | grep -q "^\.env"' \
    || fail "a imagem contém arquivo .env.*"
echo "ok: imagem sem .env"

$COMPOSE up -d --wait
[ "$($COMPOSE port web 8000)" = "127.0.0.1:8000" ] || fail "o web não está só em 127.0.0.1:8000"
[ "$(curl -fsS -o /dev/null -w '%{http_code}' http://127.0.0.1:8000/up)" = 200 ] \
    || fail "/up não respondeu 200"
echo "ok: /up responde 200"

echo "smoke test passou"
