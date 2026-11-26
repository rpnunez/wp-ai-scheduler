#!/usr/bin/env bash
# Cloud Agent install: Docker engine + cached Compose images. Idempotent.
# Runs from /agent. Leaves dockerd stopped so a snapshot has no live daemon.
set -euo pipefail

[ ! -d '/agent/repos/wp-ai-scheduler' ] || cd '/agent/repos/wp-ai-scheduler' || exit $?

export DEBIAN_FRONTEND=noninteractive
APT_OPTS=(-y -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold)

sudo DEBIAN_FRONTEND=noninteractive apt-get update
sudo DEBIAN_FRONTEND=noninteractive apt-get "${APT_OPTS[@]}" install ca-certificates curl gnupg fuse-overlayfs iptables iproute2 procps

if ! command -v docker >/dev/null 2>&1 || ! docker compose version >/dev/null 2>&1; then
	sudo install -m 0755 -d /etc/apt/keyrings
	sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
	sudo chmod a+r /etc/apt/keyrings/docker.asc
	# shellcheck disable=SC1091
	. /etc/os-release
	echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu ${VERSION_CODENAME} stable" \
		| sudo tee /etc/apt/sources.list.d/docker.list >/dev/null
	sudo DEBIAN_FRONTEND=noninteractive apt-get update
	sudo DEBIAN_FRONTEND=noninteractive apt-get "${APT_OPTS[@]}" install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi

if ! getent group docker >/dev/null; then
	sudo groupadd docker
fi
sudo usermod -aG docker "$(id -un)" || true

if [ -x /usr/sbin/iptables-legacy ]; then
	sudo update-alternatives --set iptables /usr/sbin/iptables-legacy
	sudo update-alternatives --set ip6tables /usr/sbin/ip6tables-legacy
fi

sudo mkdir -p /etc/docker
sudo tee /etc/docker/daemon.json >/dev/null <<'EOF'
{
  "storage-driver": "fuse-overlayfs",
  "firewall-backend": "iptables",
  "iptables": true,
  "ip6tables": false,
  "features": {
    "containerd-snapshotter": false
  }
}
EOF

start_dockerd() {
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
	echo "dockerd failed to start; last log lines:" >&2
	sudo tail -n 120 /tmp/dockerd.log >&2 || true
	return 1
}

stop_dockerd() {
	local pid
	pid="$(pgrep -x dockerd | head -n 1 || true)"
	if [ -z "${pid}" ]; then
		sudo rm -f /var/run/docker.pid /var/run/docker.sock
		return 0
	fi
	sudo kill "${pid}" 2>/dev/null || true
	local i
	for i in $(seq 1 40); do
		if ! sudo kill -0 "${pid}" 2>/dev/null; then
			break
		fi
		sleep 1
	done
	sudo rm -f /var/run/docker.pid /var/run/docker.sock
}

start_dockerd

if [ -f docker-compose.yml ]; then
	docker compose build
	docker compose pull --ignore-buildable
	if docker image inspect wp-ai-scheduler-web:latest >/dev/null 2>&1; then
		docker run --rm --entrypoint composer \
			-e COMPOSER_HOME=/tmp/composer \
			-v "$PWD/ai-post-scheduler:/app" \
			-w /app \
			wp-ai-scheduler-web:latest \
			install --no-interaction --prefer-dist --no-progress
	fi
fi

docker compose down --remove-orphans >/dev/null 2>&1 || true
stop_dockerd
echo "install complete"
