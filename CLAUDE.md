# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

BlueLotus_XSSReceiver_PRO is a file-based XSS data receiving and analysis platform for CTF/learning/authorized SRC testing. Originally by firesunCN/BlueLotus team, upgraded for PHP 8.x compatibility. No database — all storage is file-based.

## Setup

Requires PHP >= 8.0. No Composer, no PHPUnit tests, no CI/CD, no build step — PHPMailer and JS libraries are bundled raw; development happens directly on source files.

```bash
cp config-sample.php config.php

# Generate password hash (see README "手动安装")
php -r 'require "app/autoload.php"; echo \App\Helper\PasswordHasher::hash("你的密码");'

chmod 777 -R data/ template/ myjs/
```

## Architecture

### Entry Points

| File | Role |
|------|------|
| `index.php` | XSS data capture — collects GET/POST/Cookie/Headers, delegates to `XssRecordService` |
| `admin.php` | Admin SPA dashboard (jQWidgets grid, JS editor, XSSOR tool) |
| `api.php` | Thin JSON API router — CSRF validation then delegates to controllers |
| `login.php` | Challenge-response login with IP-based rate limiting (bans after 5 wrong attempts) |
| `install.php` | Web-based setup wizard, uses PasswordHasher |
| `keepsession.php` | Cron-triggered session keeper with SSRF URL whitelist + `flock` locking |
| `change_encrypt_pass.php` | CLI tool for re-encrypting data (disabled by default — starts with `exit()`) |

### Infrastructure Files

| File | Role |
|------|------|
| `config.php` | Copied from `config-sample.php`; all constants (PASS, paths, encryption, mail, projects) |
| `load.php` | Bootstrap: loads `config.php` then `app/autoload.php` |
| `dio.php` | JS file operations + ForbiddenIP list I/O (XSS record CRUD moved to `app/Storage/`) |
| `auth.php` | Session enforcement: login state, IP/UA binding, forced logout on anomaly |
| `waf.php` | Security headers: CSP, X-Frame-Options, X-Content-Type-Options, X-XSS-Protection |

### Layered Architecture under `app/` (now fully wired)

```
app/
├── autoload.php              # PSR-4 autoloader (App\ namespace)
├── Config/
│   └── AppConfig.php         # Typed wrapper around config.php constants
├── Controller/
│   ├── XssRecordController.php   # /api.php?cmd= — record list/get/del/search/stats/export
│   ├── JsTemplateController.php  # /api.php?js_template_cmd= — template CRUD
│   ├── MyJsController.php        # /api.php?my_js_cmd= — my JS CRUD
│   └── ProjectController.php     # /api.php?project_cmd= — project list
├── Helper/
│   ├── Crypto.php            # AES-CTR / RC4 encrypt/decrypt (with base64 fallback)
│   ├── IPLocator.php         # IP to geo-location via qqwry.dat
│   ├── PasswordHasher.php    # Centralized salt + hash() + verifyChallenge()
│   ├── Request.php           # getRealIP, getAllHeaders, collectXssInfo, base64 detection
│   ├── UrlValidator.php      # SSRF protection: URL scheme/host whitelist for keepsession
│   └── XssFilter.php         # htmlspecialchars XSS filtering (string + recursive array)
├── Service/
│   └── XssRecordService.php  # Business logic: CRUD, search, stats, export — wired to controllers
└── Storage/
    ├── StorageInterface.php  # Interface: save/load/delete/clearAll/listIds/listAll
    └── FileStorage.php       # File-based implementation (id.php with <?php exit();?> prefix)
```

### Data Flow

1. Victim browser sends XSS payload data to `index.php?a=xxx&b=xxxx&proj=project_id`
2. `index.php` collects data (XssFilter, Request helpers), identifies project, calls `XssRecordService::saveRecord()`
3. Record saved via `FileStorage` as `data/{unix_timestamp}.php`
4. Admin views/export data via `admin.php` AJAX → `api.php` → Controller → Service → Storage
5. JS template/MyJS operations still go through dio.php procedural functions (called by controllers)

### Storage Format

- XSS records: `data/{unix_timestamp}.php` — `<?php exit();?>ENCRYPTED_JSON`
- JS descriptions: `{template|myjs}/{name}.desc` — encrypted text
- JS content: `{template|myjs}/{name}.js` — plain JS
- Forbidden IP list: `data/forbiddenIPList.dat` — encrypted JSON
- KeepSession lock: `data/check.pid` — `flock` mutex

### Authentication

- Challenge-response: server generates random `firesunCheck` → client hashes with salt + PASS via triple MD5 → server verifies via `PasswordHasher::verifyChallenge()` using `hash_equals`
- Salt in `PasswordHasher::SALT` shared with `static/js/login.min.js` (must stay in sync)
- Session bound to User-Agent (always) and IP (gated by `ADMIN_IP_CHECK_ENABLE`)
- CSRF: `X-CSRF-Token` header on all jQuery AJAX, with `HTTP_REFERER` fallback in api.php
- IP banned after 5 wrong password attempts; correct password resets counter

### Project System

Multi-project support in `config.php` `PROJECTS` array. Payloads specify via `?proj=src_a` or `?token=customtoken123`.

## Key Technical Notes

- `define('IN_XSS_PLATFORM', true)` guards all includes against direct access.
- Encryption: AES-CTR or RC4 (default). `base64_encode` fallback when disabled.
- `qqwry.dat` (10MB) is the pure IP geo-location database.
- Frontend: jQWidgets (CC BY-NC 3.0 — non-commercial only) with custom modifications in `diff/`.
- JS editor: Ace editor + js_beautify + jsmin.
- `src/`, `diff/`, `guide/` are source/reference — safe to delete in production.
- `.htaccess` restricts `data/` (deny all) and limits `template/`/`myjs/` to `.js` files only.
- `KEEP_SESSION_ALLOWED_HOSTS`: optional allowlist for keepsession redirect targets (URL whitelist via `UrlValidator`).
- `change_encrypt_pass.php` starts with `exit()` — must uncomment before use.

## Version Numbering

x.y.z: x = major architecture change, y = feature addition, z = bug fix. Changelog maintained in README.md.
