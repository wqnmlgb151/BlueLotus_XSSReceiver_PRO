# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

BlueLotus_XSSReceiver_PRO is a file-based XSS data receiving and analysis platform for CTF/learning/authorized SRC testing. Originally by firesunCN/BlueLotus team, upgraded for PHP 8.x compatibility. No database — all storage is file-based.

## Setup

Requires PHP >= 8.0. No Composer, no PHPUnit tests, no CI/CD, no build step — PHPMailer and JS libraries are bundled raw; development happens directly on source files.

```bash
cp config-sample.php config.php

# Generate password hash (see README "手动安装")
php -r '$salt="!KTMdg#^^I6Z!deIVR#SgpAI6qTN7oVl";$key="你的密码";$key=md5($salt.$key.$salt);$key=md5($salt.$key.$salt);$key=md5($salt.$key.$salt);echo $key;'

chmod 777 -R data/ template/ myjs/
```

## Architecture

### Entry Points (Root-level flat PHP files)

| File | Role |
|------|------|
| `index.php` | XSS data capture — receives all GET/POST/Cookie/Headers from XSS payloads |
| `admin.php` | Admin SPA dashboard (jQWidgets grid, JS editor, XSSOR tool) |
| `api.php` | JSON API: record CRUD, search, stats, export, JS template/MyJS management |
| `login.php` | Challenge-response login with IP-based rate limiting (bans after 5 wrong attempts) |
| `install.php` | Web-based setup wizard |
| `keepsession.php` | Cron-triggered session keeper with `flock` single-instance locking |
| `change_encrypt_pass.php` | CLI tool for re-encrypting data (disabled by default — starts with `exit()`) |

### Infrastructure Files

| File | Role |
|------|------|
| `config.php` | Copied from `config-sample.php`; defines all constants (PASS, paths, encryption, mail, projects) |
| `load.php` | Bootstrap: loads `config.php` then `app/autoload.php`. Used by all entry points except `install.php` and `change_encrypt_pass.php` |
| `auth.php` | Session enforcement — validates login state, IP/UA binding (gated by `ADMIN_IP_CHECK_ENABLE`), session expiry; required by `admin.php` and `api.php` |
| `waf.php` | Security headers: CSP, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `X-XSS-Protection`; included by `login.php` and `auth.php` |

### Layered Architecture under `app/`

```
app/
├── autoload.php          # PSR-4 autoloader (App\ namespace)
├── Config/
│   └── AppConfig.php     # Typed wrapper around config.php constants
├── Helper/
│   ├── Crypto.php        # AES-CTR / RC4 encrypt/decrypt (with base64 fallback)
│   ├── IPLocator.php     # IP to geo-location via qqwry.dat (pure IP database)
│   ├── Request.php       # getRealIP, getAllHeaders, collectXssInfo, base64 detection
│   └── XssFilter.php     # htmlspecialchars-based XSS filtering (string + recursive array)
├── Service/
│   └── XssRecordService.php  # Business logic (CRUD, search, stats, export)
└── Storage/
    ├── StorageInterface.php  # Interface: save/load/delete/clearAll/listIds/listAll
    └── FileStorage.php       # File-based implementation
```

**Important:** `app/Service/` and `app/Storage/` are unwired migration scaffolds — not instantiated by any runtime code. The live data path is `dio.php` (procedural functions called directly by `index.php` and `api.php`). `app/Helper/` classes are the only wired layer (via `functions.php` wrappers), and `AppConfig::projects()` is called at `api.php:285`.

### Bridge Layer

`functions.php` wraps `app\Helper\*` classes as procedural functions (`encrypt()`, `decrypt()`, `getRealIP()`, `stripStr()`, etc.) for backward compatibility with legacy code in `dio.php`, `index.php`, `login.php`.

`dio.php` is the actual data I/O layer — all XSS record file operations, JS file operations, and forbidden IP list management. The newer `app/Storage/FileStorage.php` partially duplicates this logic but is not wired in.

### Data Flow

1. Victim browser sends XSS payload data to `index.php?a=xxx&b=xxxx&proj=project_id`
2. `index.php` collects GET/POST/Cookie/HTTP headers, detects base64 encoding, applies XSS filtering
3. Project identified via `proj` or `token` GET parameter (falls back to `'default'`)
4. Record saved — see Storage Format below
5. Admin views/export data via `admin.php` AJAX calls to `api.php`
6. `api.php` reads files, applies project/keyword filtering, returns JSON

### Storage Format

- XSS records: `data/{unix_timestamp}.php` — `<?php exit();?>ENCRYPTED_JSON`
- JS descriptions: `{template|myjs}/{name}.desc` — encrypted text
- JS content: `{template|myjs}/{name}.js` — plain JS
- Forbidden IP list: `data/forbiddenIPList.dat` — encrypted JSON
- KeepSession lock: `data/check.pid` — `flock` mutex

### Authentication

- Challenge-response via `auth.php`: server generates random `firesunCheck` → client runs triple MD5 hash (salt + PASS + challenge + salt) in JS → server verifies from `PASS` constant
- Session bound to User-Agent (always) and IP (gated by `ADMIN_IP_CHECK_ENABLE`); violations force logout
- CSRF token via `X-CSRF-Token` header on all jQuery AJAX requests, with `$_SERVER['HTTP_REFERER']` fallback
- IP banned after 5 wrong password attempts (counter > 5 triggers ban); correct password resets the counter. Delete `data/forbiddenIPList.dat` to unban

### Project System (PRO feature)

Multi-project support configured in `config.php` `PROJECTS` array:
```php
define('PROJECTS', [
    'default' => ['name' => '默认项目', 'token' => null],
    'src_a'   => ['name' => 'SRC测试-A厂商', 'token' => 'customtoken123'],
]);
```

## Key Technical Notes

- `define('IN_XSS_PLATFORM', true)` must be set before including any other file — guards all includes against direct access.
- Data encryption uses AES-CTR or RC4 (configurable). RC4 is the default for performance. `base64_encode` is used as fallback when encryption is disabled.
- The bundled `qqwry.dat` (10MB) is the pure IP geo-location database used by `IPLocator.php`.
- Frontend uses jQWidgets (CC BY-NC 3.0 license — non-commercial use only) with custom modifications (diffs in `diff/` directory).
- JS editor is Ace editor with js_beautify and jsmin for formatting/minification.
- `src/`, `diff/`, `guide/` are source/reference material only — safe to delete in production.
- `.htaccess` restricts web access to `data/` (deny all) and limits `template/`/`myjs/` to `.js` files only.

## Version Numbering

x.y.z: x = major architecture change, y = feature addition, z = bug fix.
