#!/usr/bin/env bash
set -euo pipefail

SSH_PORT=9090

if [[ "${EUID:-$(id -u)}" -ne 0 ]]; then
    echo "Run this script as root." >&2
    exit 1
fi

if [[ "${PANENIKAN_KEY_LOGIN_VERIFIED:-}" != "1" ]]; then
    echo "Verify a second SSH login using the public key before continuing." >&2
    exit 1
fi

if [[ "${PANENIKAN_PROVIDER_FIREWALL_CONFIRMED:-}" != "1" ]]; then
    echo "Confirm TCP $SSH_PORT is allowed by the provider firewall first." >&2
    exit 1
fi

if [[ "${PANENIKAN_RECOVERY_CONFIRMED:-}" != "1" ]]; then
    echo "Confirm provider console or another recovery path is available." >&2
    exit 1
fi

if [[ -z "${PANENIKAN_ALLOWED_TCP_PORTS:-}" ]]; then
    echo "Set PANENIKAN_ALLOWED_TCP_PORTS to every required inbound TCP port." >&2
    echo "Required web/SSH ports: 80, 443, $SSH_PORT. Include other required services explicitly." >&2
    exit 1
fi

if ! command -v ufw >/dev/null 2>&1 || ! command -v sshd >/dev/null 2>&1; then
    echo "UFW and OpenSSH server must be installed." >&2
    exit 1
fi

if ! sshd -t; then
    echo "OpenSSH configuration is invalid; firewall changes aborted." >&2
    exit 1
fi

SSH_PORTS="$(sshd -T | awk '$1 == "port" { print $2 }')"
if ! grep -Fxq "$SSH_PORT" <<< "$SSH_PORTS" ||
    grep -Fxq "22" <<< "$SSH_PORTS"; then
    echo "OpenSSH must listen on $SSH_PORT and not on port 22 before UFW is enabled." >&2
    exit 1
fi

SSH_CONFIG="$(sshd -T)"
for setting in \
    "pubkeyauthentication yes" \
    "passwordauthentication no" \
    "kbdinteractiveauthentication no" \
    "authenticationmethods publickey"; do
    if ! grep -Fxq "$setting" <<< "$SSH_CONFIG"; then
        echo "OpenSSH must have '$setting' before UFW is enabled." >&2
        exit 1
    fi
done

if ! ss -ltn | awk -v port=":$SSH_PORT" '$4 ~ port "$" { found = 1 } END { exit !found }'; then
    echo "No TCP listener detected on SSH port $SSH_PORT; firewall changes aborted." >&2
    exit 1
fi

if ! command -v fail2ban-client >/dev/null 2>&1 ||
    ! systemctl is-active --quiet fail2ban ||
    ! fail2ban-client status sshd >/dev/null 2>&1; then
    echo "Fail2Ban and its active sshd jail are required before UFW is enabled." >&2
    exit 1
fi

IFS=',' read -r -a allowedPorts <<< "$PANENIKAN_ALLOWED_TCP_PORTS"
hasSsh=0
hasHttp=0
hasHttps=0

for port in "${allowedPorts[@]}"; do
    if [[ ! "$port" =~ ^[0-9]{1,5}$ ]] || (( port < 1 || port > 65535 )); then
        echo "Invalid TCP port in PANENIKAN_ALLOWED_TCP_PORTS: $port" >&2
        exit 1
    fi

    [[ "$port" == "$SSH_PORT" ]] && hasSsh=1
    [[ "$port" == "80" ]] && hasHttp=1
    [[ "$port" == "443" ]] && hasHttps=1
done

if (( !hasSsh || !hasHttp || !hasHttps )); then
    echo "Allowed ports must include 80, 443, and $SSH_PORT." >&2
    exit 1
fi

echo "Current listening TCP services:"
ss -ltnp
echo "Current UFW rules:"
ufw status verbose
echo "Applying only the explicitly listed inbound TCP ports."

for port in "${allowedPorts[@]}"; do
    ufw allow "$port/tcp"
done

ufw default deny incoming
ufw default allow outgoing
ufw --force enable
ufw status verbose
