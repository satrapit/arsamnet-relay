#!/usr/bin/env bash
# Read-only host checks; no secrets, web requests or destructive actions.
set -eu
echo "=== PHP ==="
php -v | head -n 2
echo "=== Extensions ==="
php -m
echo "=== Composer ==="
if command -v composer >/dev/null 2>&1; then composer --version; else echo "Composer unavailable"; fi
echo "=== Node (build-time only) ==="
if command -v node >/dev/null 2>&1; then node --version; else echo "Node unavailable on host; okay for prebuilt assets"; fi
echo "=== MySQL CLI ==="
if command -v mysql >/dev/null 2>&1; then mysql --version; else echo "MySQL CLI unavailable; not proof of DB connectivity"; fi
echo "=== Writable paths ==="
for path in storage bootstrap/cache; do
    if [ -d "$path" ] && [ -w "$path" ]; then echo "$path writable"; else echo "$path NOT writable or missing"; fi
done
echo "No DB connectivity, Cron, DNS, firewall or TLS assertions are made by this script."
