#!/usr/bin/env bash
# Local dev DB snapshot / restore for retab-stores (XAMPP MariaDB on 127.0.0.1:3307).
#
# Backups land in .db-backups/ (gitignored — real data must never be committed).
#
#   scripts/db-local.sh backup            # timestamped snapshot
#   scripts/db-local.sh restore <file>    # restore a snapshot (⚠️ overwrites the DB)
#   scripts/db-local.sh list              # list snapshots, newest first
#
# Production uses MySQL 8, whose caching_sha2_password the XAMPP MariaDB client
# cannot auth against — dump prod from TablePlus or on the Railway container, not
# with this script.
set -euo pipefail

DB="retab-stores"
HOST="127.0.0.1"; PORT="3307"; USER="root"          # local dev creds (no password)
BIN="/c/xampp/mysql/bin"
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/.db-backups"
mkdir -p "$DIR"

case "${1:-}" in
  backup)
    out="$DIR/${DB}_local_$(date +%Y%m%d-%H%M%S).sql"
    "$BIN/mysqldump.exe" -h "$HOST" -P "$PORT" -u "$USER" \
      --single-transaction --routines --triggers --events \
      --add-drop-table --databases "$DB" > "$out"
    echo "Saved $out ($(wc -c < "$out") bytes, $(grep -c 'CREATE TABLE' "$out") tables)"
    ;;
  restore)
    file="${2:?usage: db-local.sh restore <file>}"
    [ -f "$file" ] || { echo "No such file: $file" >&2; exit 1; }
    echo "⚠️  This OVERWRITES the '$DB' database with $file"
    read -r -p "Type the database name to confirm: " ans
    [ "$ans" = "$DB" ] || { echo "Aborted."; exit 1; }
    "$BIN/mysql.exe" -h "$HOST" -P "$PORT" -u "$USER" < "$file"
    echo "Restored $DB from $file"
    ;;
  list)
    ls -1t "$DIR"/*.sql 2>/dev/null || echo "No snapshots yet."
    ;;
  *)
    echo "usage: scripts/db-local.sh {backup|restore <file>|list}" >&2; exit 1;;
esac
