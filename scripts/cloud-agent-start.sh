#!/usr/bin/env bash
# Cloud Agent start: launch dockerd, then the repo's dev stack.
# Safe to rerun. Exits after WordPress answers; dockerd keeps running.
set -euo pipefail

[ ! -d '/agent/repos/wp-ai-scheduler' ] || cd '/agent/repos/wp-ai-scheduler' || exit $?

ensure_dockerd() {
	local i
	for i in $(seq 1 90); do
		if [ -S /var/run/docker.sock ]; then
			sudo chmod 666 /var/run/docker.sock 2>/dev/null || true
		fi
		if docker info >/dev/null 2>&1; then
			return 0
		fi
		if [ "$i" -eq 1 ] && [ ! -S /var/run/docker.sock ]; then
			sudo rm -f /var/run/docker.pid
			sudo setsid dockerd >/tmp/dockerd.log 2>&1 < /dev/null &
		fi
		sleep 1
	done
	echo "Docker daemon did not start. See /tmp/dockerd.log" >&2
	sudo tail -n 80 /tmp/dockerd.log >&2 || true
	return 1
}

ensure_dockerd

WP_PORT="$(grep -hE '^WP_PORT=' .env 2>/dev/null | tail -1 | cut -d= -f2- | tr -d '\r' || true)"
WP_PORT="${WP_PORT:-8080}"
if docker compose ps --status running --format '{{.Service}}' 2>/dev/null | grep -qx web \
	&& curl -fsS -o /dev/null --max-time 8 "http://127.0.0.1:${WP_PORT}/wp-login.php"; then
	echo "WordPress already responding on port ${WP_PORT}"
	exit 0
fi

if [ -z "${GOOGLE_API_KEY:-}" ]; then
	export AIPS_SKIP_API_KEY_CHECK=1
fi

exec ./start-dev.sh
