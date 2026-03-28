# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with
code in this repository.

## Project Overview

YesWiki is a PHP collaborative wiki platform with AGPL-3.0 license. It uses
Symfony 5.x components (DependencyInjection, Routing, HttpKernel, Security,
Console, EventDispatcher) with Twig templating and Vue 3.5 for interactive
frontend components.

**Requirements:** PHP 7.3+ (up to 8.4), MySQL 8+/MariaDB

## Common Commands

```bash
# Install dependencies
make install                    # Both composer and yarn
composer install                # PHP dependencies only
yarn install                    # Node.js dependencies only

# Linting (run before committing)
make lint                       # All linters
make lint-php                   # PHP-CS-Fixer (PSR-12 + Symfony)
make lint-js                    # ESLint
make lint-other                 # Prettier (CSS, JSON, YAML, MD)

# Testing
make test                                              # All unit tests
./vendor/bin/phpunit --stderr tests                    # Core tests
./vendor/bin/phpunit --stderr tests/path/to/TestFile.php  # Single test file
./vendor/bin/phpunit --stderr --filter testMethodName tests  # Single test method

# Docker development (recommended)
cd docker && docker compose up              # Start dev environment
docker compose exec yeswiki-app composer install  # Update PHP deps in container
```

**Docker services:** localhost:8085 (app), localhost:8086 (phpMyAdmin), localhost:1080 (mailcatcher)

## Architecture

### Request Flow

`index.php` → `YesWikiLoader` → `Wiki` class → Symfony routing → Controller dispatch

### Core Structure

```
includes/
├── YesWiki.php          # Main Wiki class (~2100 lines)
├── YesWikiInit.php      # Initialization, routing, DI setup
├── services/            # 29 core services (PageManager, UserManager, AclService, DbService, etc.)
├── controllers/         # 10 controllers (ApiController, AuthController, PageController, etc.)
├── commands/            # CLI commands (yeswicli)
└── migrations/          # Database migrations (timestamp-based)

handlers/                # Page request handlers (show, edit, delete, revisions)
actions/                 # Wiki action markup (37+ actions)
formatters/              # Text formatters (wakka, code, action, raw)
tools/                   # Extensions (bazar, contact, login, security, tags, etc.)
templates/               # Twig templates
javascripts/             # Frontend JS/Vue code
```

### Extension System (tools/)

Each tool can contain: `controllers/`, `services/`, `handlers/`, `actions/`, `fields/`, `migrations/`, `lang/`, `templates/`, `config.yaml`

Extensions are loaded dynamically and registered in the DI container. Custom tools go in `custom/tools/` to survive updates.

### Key Services

- `PageManager` - Page CRUD & versioning
- `UserManager` - Authentication & user management
- `AclService` - Access control lists
- `DbService` - Database abstraction
- `TemplateEngine` - Twig rendering
- `Performer` - Action/formatter execution
- `AssetsManager` - CSS/JS asset management

### Frontend

- Vue 3.5 components in `javascripts/`
- Global variables: `wiki`, `Vue`, `_t` (translations), `toastMessage`
- Bootstrap 3, jQuery 3.5, Leaflet for maps

## Testing

PHPUnit tests mirror source structure:

- `tests/includes/` for core
- `tests/tools/{toolname}/` for extensions

Test base class: `YesWiki\Test\Core\YesWikiTestCase`

- Use `$this->getWiki()` to get a Wiki instance in tests

E2E tests (Playwright) in `tests/e2e/` - run via Docker test compose file.

## Code Style

**PHP:** PSR-12 + Symfony conventions via php-cs-fixer

- Short array syntax, single quotes, no Yoda conditions
- Post-increment style, trailing commas in multiline arrays

**JavaScript:** ESLint with Airbnb base

- No semicolons, single quotes, no trailing commas
- Max line length: 104 characters
- File extensions required in imports

## Database

MySQL with timestamp-based migrations in `includes/migrations/` and `tools/*/migrations/`. Uses prepared statements for SQL injection prevention.

## Configuration

- `wakka.config.php` - Main config (generated on install)
- `includes/services.yaml` - Symfony DI container config
- `tools/*/config.yaml` - Extension service configs
