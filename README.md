English · [简体中文](./README.zh-CN.md)

# 陌生的你 — Ai Wei's Poetry Collection

A personal poetry collection website (2021–2026) by a Chinese poet, built as a minimal static-style site with a pure PHP backend and vanilla HTML/CSS/JS — no frameworks, no database (JSON files only). It collects modern poems and imitation-style works written between 2021 and 2026, as an online showcase with visitor interaction. Features poem browsing, random poem, dark mode, a guestbook, per-poem likes, visitor analytics, and a password-protected admin panel for publishing new work.

## Overview

A personal poetry website with poem display, sorting, random browsing, dark mode, visitor guestbook, access statistics, likes, and more.

- **Author**: Ai Wei (艾苇，炜)
- **Time span**: 2021 — 2026
- **Deployment**: PHP + static frontend pages

## Features

| Feature | Description |
|------|------|
| 📝 **Poem display** | Browse all poems sorted by time |
| 🔀 **Random poem** | Random browsing |
| 🌓 **Dark mode** | Dark/light theme toggle |
| 💬 **Guestbook** | Visitor guestbook (PHP backend) |
| ❤️ **Likes** | Independent like counter per poem |
| 📊 **Access statistics** | Visitor tracking (requires the PHP backend) |
| 🔍 **Log search** | Poem lookup |
| 👑 **Admin panel** | Admin entry at `/admin/` |

## Quick start

### Option 1: Frontend-only browsing (recommended)

Open `index.html` directly in a browser to read all poems (guestbook and similar features need the backend).

### Option 2: Full deployment (with backend)

Requires a PHP server environment:

```bash
# Use the PHP built-in server
php -S localhost:8080

# Or deploy behind Nginx / Apache
```

Admin credentials are not committed: on deployment, copy `admin/config.example.php` to `admin/config.php` and fill in the admin username and a password hash (generate one with `php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"`).

### Option 3: Docker / Alibaba Cloud

The site is deployed on an Alibaba Cloud ECS instance (Nginx + PHP environment) and accessed via a domain.

## Directory structure

```
poetry-site/
├── index.html              # Home page (poem display)
├── style.css               # All styles
├── script.js               # Frontend interaction logic
├── poems.js                # Poem data
├── about/                  # About page
├── admin/                  # Admin panel (with credentials template config.example.php)
├── check_access.php        # Access control
├── guestbook.php           # Guestbook
├── likes.php               # Like API
├── log_search.php          # Log search
└── track.php               # Access statistics
```

## Privacy

- All poems are original works
- Visitor data is used for statistics only and is never shared externally
