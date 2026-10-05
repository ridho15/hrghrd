#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

port="${HRD_PORT:-8000}"
if ! [[ "$port" =~ ^[0-9]+$ ]] || (( port < 1 || port > 65535 )); then
  echo 'HRD_PORT harus berupa nomor port 1–65535.' >&2
  exit 1
fi
if ! command -v docker >/dev/null || ! docker info >/dev/null 2>&1; then
  echo 'Docker harus terpasang dan berjalan untuk menjalankan demo lokal.' >&2
  exit 1
fi

uid="$(id -u)"; gid="$(id -g)"
common=(--rm --user "$uid:$gid" --volume "$PWD:/app" --workdir /app)
if [[ -n "${CODEX_PROXY_CERT:-}" && -r "$CODEX_PROXY_CERT" ]]; then
  common+=(--volume "$CODEX_PROXY_CERT:/run/proxy-ca.pem:ro")
  php_ca=(-e SSL_CERT_FILE=/run/proxy-ca.pem -e COMPOSER_CAFILE=/run/proxy-ca.pem)
  node_ca=(-e NODE_EXTRA_CA_CERTS=/run/proxy-ca.pem)
else
  php_ca=(); node_ca=()
fi

if [[ ! -f .env ]]; then cp .env.example .env; fi
new_database=0
if [[ ! -f database/database.sqlite ]]; then touch database/database.sqlite; new_database=1; fi

docker run "${common[@]}" "${php_ca[@]}" -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer composer:2 composer install --no-dev --no-interaction --prefer-dist
if grep -q '^APP_KEY=$' .env; then
  docker run "${common[@]}" composer:2 php artisan key:generate --no-ansi
fi
docker run "${common[@]}" "${node_ca[@]}" -e HOME=/tmp node:24-bookworm-slim sh -lc 'npm ci --ignore-scripts --no-audit --no-fund && npm run build'
docker run "${common[@]}" composer:2 php artisan migrate --force --no-ansi
if (( new_database )); then
  docker run "${common[@]}" composer:2 php artisan db:seed --force --no-ansi
fi

echo "Buka http://localhost:$port/login di browser komputer ini. Tekan Ctrl+C untuk berhenti."
exec docker run "${common[@]}" --publish "127.0.0.1:$port:8000" composer:2 php artisan serve --host=0.0.0.0 --port=8000
