# Gitleaks Scan

Daily secret-leak scanning for a server-hosted PHP application (e.g. HumHub),
using [gitleaks](https://github.com/gitleaks/gitleaks) and a lightweight PHP
wrapper that emails an alert via SMTP when secrets are found. No system
packages, no mail server, no Composer dependencies required — just the
gitleaks binary and PHP CLI.

## How it works

- `scan.php` runs the `gitleaks` binary against a target directory.
- If secrets are found (or the scan errors), it sends an email with the
  findings over plain SMTP (STARTTLS), using only PHP's built-in socket
  functions — no PHPMailer, no `mail()`, no MTA needed.
- On a clean scan, nothing is emailed and the report file is deleted.
- Intended to run once a day via cron.

## 1. Clone this repo

```bash
git clone https://github.com/<you>/gitleaks-scan.git ~/gitleaks-scan
cd ~/gitleaks-scan
```

## 2. Download gitleaks

Check the [releases page](https://github.com/gitleaks/gitleaks/releases) for
the current version and the right architecture for your server, then:

```bash
wget https://github.com/gitleaks/gitleaks/releases/download/v8.30.1/gitleaks_8.30.1_linux_x64.tar.gz
tar -xvzf gitleaks_8.30.1_linux_x64.tar.gz gitleaks -C bin/
chmod +x bin/gitleaks
bin/gitleaks version
```

No root/sudo required — this just extracts a static binary into `bin/`.

## 3. Create your config file

```bash
cp config.example.php config.php
```

Edit `config.php` and fill in.

## 4. Tune `.config.toml` for your project

The included `.config.toml` extends gitleaks' default ruleset and adds an
allowlist for known false-positive paths (e.g. API documentation folders full
of example credentials). Update the `paths` list to match your own project's
docs/test/fixture directories as you find false positives:

```toml
[[allowlists]]
description = "Skip API documentation - full of example/placeholder credentials"
paths = [
  '''protected/modules/rest/docs/''',
]
```

To ignore one specific finding rather than a whole path, either:
- Add `// gitleaks:allow` (or the language-appropriate comment syntax) at the
  end of the offending line, or
- Add its `Fingerprint` (shown in the JSON report) to a `.gitleaksignore`
  file at the root of `scanDir`.

## 5. Test it manually

```bash
php scan.php
echo "Exit code: $?"
```

- Exit code `0` → no secrets found, no email sent.
- Exit code `1` → secrets found, alert email sent, report kept in `logs/`.
- Exit code `>1` → gitleaks itself errored, a failure email is sent.

Deliberately add a fake test secret somewhere in `scanDir` once to confirm
the email actually arrives before trusting this unattended.

## 6. Install the cron job

```bash
crontab -e
```

Add:

```cron
0 3 * * * /usr/bin/php /home/youruser/gitleaks-scan/scan.php >> /home/youruser/gitleaks-scan/logs/cron.log 2>&1
```

Adjust the PHP path if needed (`which php`) and the repo path to match where
you cloned it.

## Updating gitleaks

Repeat step 2 with a newer release tag whenever you want to upgrade the
scanner itself — `bin/gitleaks` is gitignored, so swapping the binary never
touches version control.

## Security notes

- `config.php` (real SMTP credentials) and `bin/gitleaks` (binary) are
  gitignored by design — only `config.example.php` and `.config.toml` are
  committed.
- The script uses `--redact` so emailed reports never contain raw secret
  values — only file paths, line numbers, and rule names. Always verify and
  rotate any real secret directly on the server, not from the email alone.
- Treat this repo itself as sensitive if you ever commit a previous report
  file by accident — check `git log` before making the repo public.
