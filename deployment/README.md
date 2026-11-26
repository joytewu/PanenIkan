# PanenIkan production security deployment

These files are server configuration templates, not application routes. Review
each step on the Ubuntu host. Do not copy private keys or database passwords
into this repository.

## J. Backup and recovery

The backup scripts require root so they can use the local MySQL `auth_socket`
account. `backup.sh` packages the database and private harvest-photo directory,
checks the generated archive, stores it under `/var/backups/panenikan` with
mode 0700 (archives are mode 0600), and retains local archives for 30 days.

After deploying the updated scripts, run one manual backup and verify its
contents before enabling the schedule:

```sh
sudo /bin/bash /var/www/html/scripts/backup.sh
sudo ls -lh /var/backups/panenikan
LATEST_BACKUP="$(ls -1t /var/backups/panenikan/panenikan-*.tar.gz | head -n 1)"
sudo tar -tzf "$LATEST_BACKUP" | sed -n '1,20p'
```

Only after that succeeds, install the daily 02:00 cron job and confirm cron is
active:

```sh
sudo install -o root -g root -m 0644 \
  /var/www/html/scripts/panenikan-backup.cron \
  /etc/cron.d/panenikan-backup
sudo systemctl enable --now cron
sudo systemctl is-active cron
sudo cat /etc/cron.d/panenikan-backup
```

Test a restore into an isolated database and a new, empty upload directory.
The restore script prompts for the target database and requires typing its
name again before importing:

```sh
LATEST_BACKUP="$(ls -1t /var/backups/panenikan/panenikan-*.tar.gz | head -n 1)"
RESTORE_UPLOAD_DIR="/var/tmp/panenikan-restore-test-$(date +%Y%m%d%H%M%S)/uploads"
sudo env PANENIKAN_RESTORE_UPLOAD_DIR="$RESTORE_UPLOAD_DIR" \
  /bin/bash /var/www/html/scripts/restore.sh "$LATEST_BACKUP"
```

At the prompts, enter `panenikan_restore_test` twice; do not choose the live
`panenikan` database for the recovery test. Then verify tables and restored
photos before recording J2 as tested:

```sh
sudo mysql -NBe "SHOW TABLES FROM panenikan_restore_test;"
sudo find "$RESTORE_UPLOAD_DIR" -type f | wc -l
```

The remote destination must be a different host or independent backup service.
For the current rsync implementation, set `PANENIKAN_BACKUP_REMOTE` in the
private PHP configuration to a dedicated target such as
`backupuser@BACKUP_HOST:/srv/backups/panenikan/`, configure a restricted SSH
key for the cron root account, then run a manual backup and verify the remote
copy. Do not set it to a directory on this application server. Until a remote
destination is configured and tested, J3 remains incomplete.

## SSH migration to port 9090 with public-key authentication

The server currently reports port 22 and password authentication enabled.
Migrate in two stages so the existing SSH session and port 22 remain usable
until port 9090 has been tested. First inspect existing port directives and
confirm the provider firewall permits TCP 9090:

```sh
sudo grep -RniE '^[[:space:]]*Port[[:space:]]+' \
  /etc/ssh/sshd_config /etc/ssh/sshd_config.d
sudo ss -ltnp | grep -E ':(22|9090)[[:space:]]'
```

Install the temporary drop-in under a name that takes precedence over cloud
defaults. It only adds the second listener:

```sh
sudo install -D -o root -g root -m 0644 \
  /var/www/html/deployment/ssh/00-panenikan-stage.conf \
  /etc/ssh/sshd_config.d/00-panenikan.conf
sudo sshd -t
sudo sshd -T | grep -E '^(port|pubkeyauthentication|passwordauthentication|permitrootlogin|authenticationmethods) '
sudo systemctl reload ssh
```

Keep the current session open. Verify the effective output includes both ports
22 and 9090, then verify a key login on port 9090 from a second terminal. Do
not continue unless that connection succeeds. Next install the final drop-in:

```sh
sudo install -o root -g root -m 0644 \
  /var/www/html/deployment/ssh/00-panenikan-final.conf \
  /etc/ssh/sshd_config.d/00-panenikan.conf
```

If `sshd -T` still reports port 22, locate and remove the explicit `Port 22`
directive found in the initial inspection; do not reload until `sshd -t`
passes. Verify the effective configuration contains only port 9090,
`passwordauthentication no`, and `authenticationmethods publickey`; then
reload SSH and test a second key login again. Keep the original session open
until this succeeds. Only afterwards consider enabling UFW.

## UFW

The guarded script requires explicit confirmation of a tested key login,
provider firewall access, and a recovery console. It also verifies SSH is
public-key-only on port 9090 and that Fail2Ban's SSH jail is active. Install
and verify Fail2Ban below before enabling UFW. The script requires an explicit
allow-list containing 9090, 80, and 443; it does not guess whether other
services such as Jenkins should be reachable. Inspect `ss -ltnp` before
running it and include only intentionally reachable ports.

```sh
sudo env \
  PANENIKAN_KEY_LOGIN_VERIFIED=1 \
  PANENIKAN_PROVIDER_FIREWALL_CONFIRMED=1 \
  PANENIKAN_RECOVERY_CONFIRMED=1 \
  PANENIKAN_ALLOWED_TCP_PORTS=9090,80,443 \
  /bin/bash /var/www/html/deployment/ufw/enable-ufw.sh
```

If provider firewall or recovery-console access is not confirmed, do not enable
UFW yet. Verify afterwards using `sudo ufw status verbose`.

## Fail2Ban

Install the Fail2Ban package, install the SSH jail configuration, validate it,
and verify the active jail. The jail covers ports 22 and 9090 during migration;
keeping 22 in its list after the old SSH listener is removed is harmless.

```sh
sudo apt-get update
sudo apt-get install fail2ban
sudo install -D -o root -g root -m 0644 \
  /var/www/html/deployment/fail2ban/jail.d/panenikan.local \
  /etc/fail2ban/jail.d/panenikan.local
sudo fail2ban-client -t
sudo systemctl enable --now fail2ban
sudo fail2ban-client status sshd
```

## MySQL accounts and grants

The application connects as `panenikan_app`, not as database root. Keep the
administrative root account local for maintenance (prefer the operating
system's socket authentication); do not remove it until an alternate
administrative account and recovery path are tested. Check that no remote root
account exists:

```sh
sudo mysql -NBe "SELECT user,host,plugin FROM mysql.user WHERE user='root';"
sudo mysql -NBe "SHOW GRANTS FOR 'panenikan_app'@'localhost';"
```

After confirming a backup and moving any scheduled dump to a separate
read-only database account, the application's required data privileges can be
restricted with:

```sh
sudo mysql panenikan < /var/www/html/deployment/mysql/restrict_app_user.sql
sudo mysql -NBe "SHOW GRANTS FOR 'panenikan_app'@'localhost';"
```

The application account needs only SELECT, INSERT, UPDATE, and DELETE on the
application database; run schema migrations using an administrative account.
Do not run the grant script before separating backup credentials, because the
backup process may otherwise lose privileges.

## HTTP 403/404 and security headers

The application and Apache have generic 403/404 handlers. `.htaccess` sets
security headers for Apache responses, including static 403/404 pages. Enable
`mod_headers` and confirm Apache allows the root `.htaccess` directives before
reloading:

```sh
sudo a2enmod headers
sudo apache2ctl configtest
sudo systemctl reload apache2
curl -sSI https://www.devps.online/config/app.php
curl -sSI https://www.devps.online/no-such-page
curl -sSI https://www.devps.online
```

The PHP application also emits CSP, HSTS, nosniff, frame, referrer,
permissions, and cross-origin headers for PHP server configurations that do
not read `.htaccess`. An A+ grade is not inferable from source alone; run a
header scan against the deployed HTTPS domain and fix any reported gaps without
enabling `includeSubDomains` or `preload` until every subdomain is confirmed
HTTPS-ready.
