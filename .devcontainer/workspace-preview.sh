#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
set -a
if [[ -f .env.development.local ]]; then source .env.development.local; fi
set +a

if [[ -n ${DB_HOST:-} ]]; then
  exec php -d display_errors=0 -S 0.0.0.0:8080 -t . .devcontainer/preview-router.php
fi

# A socket-only database keeps preview data separate from deployed environments.
umask 077
state="$HOME/.local/state/roomiematch-preview/$(id -un)"
mkdir -p "$state"
socket="$state/mysql.sock"
if [[ ! -d "$state/data/mysql" ]]; then
  mariadb-install-db --no-defaults --datadir="$state/data" --auth-root-authentication-method=socket --auth-root-socket-user="$(id -un)" --skip-test-db
fi
mariadbd --no-defaults --datadir="$state/data" --socket="$socket" --pid-file="$state/db.pid" --skip-networking --log-error="$state/db.log" &
db_pid=$!
web_pid=''
trap 'if [[ -n $web_pid ]]; then kill "$web_pid" 2>/dev/null || true; fi; kill "$db_pid" 2>/dev/null || true; wait || true' EXIT
trap 'exit 0' INT TERM
client=(mariadb --no-defaults --socket="$socket")
for ((i=0;i<100;i++)); do
  "${client[@]}" -e 'SELECT 1' >/dev/null 2>&1 && break
  kill -0 "$db_pid" && sleep .1 || exit 1
done
if ! "${client[@]}" -D roommate_match -e 'SELECT 1' >/dev/null 2>&1; then
  "${client[@]}" -e 'CREATE DATABASE roommate_match CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci'
  "${client[@]}" roommate_match < database/schema.sql
  "${client[@]}" roommate_match < database/seed.sql
fi
export DB_HOST=localhost DB_USER="$(id -un)" DB_NAME=roommate_match DB_DRIVER=mysql DB_PASS='' STORE_IN_DB=0
php -d "pdo_mysql.default_socket=$socket" -d display_errors=0 -S 0.0.0.0:8080 -t . .devcontainer/preview-router.php &
web_pid=$!
wait -n "$db_pid" "$web_pid"
