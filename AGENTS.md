# AGENTS.md

## What This Repo Is

A WordPress 7.0 plugin that registers [Dario](https://github.com/askalf/dario) as an AI provider via the Connectors API and AI Client. Dario is a local LLM router that proxies requests to Claude, GPT, and any OpenAI-compatible backend.

## Architecture

```
procyon-dario-provider.php              Entry point: autoloader, autoupdates, provider registration on init
src/
  autoload.php                     PSR-4 autoloader for Procyon\Dario\ namespace
  Provider/DarioProvider.php       Extends AbstractApiProvider (from wordpress/php-ai-client)
  Metadata/DarioModelMetadataDirectory.php  Curated model list (see "Things an Agent Might Get Wrong")
  Models/DarioTextGenerationModel.php       OpenAI-compatible /v1/chat/completions implementation
assets/images/dario.svg            Provider icon
vendor/                            Composer deps (plugin-update-checker)
```

## Key Conventions

- **Indent style**: tabs (WordPress coding standards, enforced in `.editorconfig`)
- **PHP**: 8.0+ (declared in plugin header `Requires PHP: 8.0`; the code uses `match` expressions)
- **Namespace**: `Procyon\Dario\` (PSR-4, loaded via custom autoloader — not Composer's)
- **WordPress AI Client interfaces**: `AbstractApiProvider`, `AbstractApiBasedModel`, `ModelMetadataDirectoryInterface`
- **No frontend build step**: PHP plugin with a Node sidecar script for Dario; no JS/CSS compilation

## Developer Commands

### One-time setup (any machine)
```bash
composer install
npm ci
```

### Boot the local dev WordPress (single command)
```bash
lando start          # Boots Lando + auto-installs WP, theme, and plugin
```
On first run this downloads the latest WordPress release (without bundled themes/plugins via `wp core download --skip-content`), installs core, installs the `twentytwentyfive` theme, and activates `procyon-dario-provider`. Idempotent on subsequent starts and on `lando rebuild -y`. The site is at http://wp-dario-test.lndo.site/ (admin/admin).

WordPress is downloaded only when `wordpress/` has no install, so an existing site keeps its version; to move it to the latest release, run `lando wp core update` (upgrades in place and keeps the database and files).

### Tests + checks (same scripts run locally and in CI)
```bash
npm run lint         # php -l on src/+tests/+scripts/, node --check on sidecar/*.mjs
npm run analyze      # PHPStan static analysis (level 5, WP stubs)
npm test             # PHP unit-style tests (host-side, fast)
npm run check:pcp    # Plugin Check via Lando — requires `lando start` first
npm run test:e2e     # Playwright browser tests against the Lando site — requires `lando start` first
npm run check        # everything (lint + analyze + test + check:pcp + test:e2e)
```
`npm run test:e2e` needs Chromium installed once: `npx playwright install chromium`. It uses `@playwright/test` with `@wordpress/e2e-test-utils-playwright` (global setup logs in and saves `artifacts/storage-states/admin.json`; specs use the `admin`, `requestUtils` and `page` fixtures). Specs live in `tests/e2e/specs/`; each resets the plugin's options and `~/.dario/backends/` file through `lando wp` (`tests/e2e/support/LandoSite.mjs`), so they are independent and repeatable. For the web user in the Lando container, `~/.dario/backends/` is `/var/www/.dario/backends/`. Global setup enables `WP_DEBUG_DISPLAY` on the Lando site so specs can detect PHP warnings in rendered pages.
CI runs the exact same `npm run check` scripts, one job per WordPress version (see `ci.yml` below). No CI-only assertions — if you can run `npm run check` locally, you have the same gate CI runs.

### Deploy plugin code changes into the running WP
```bash
lando deploy-plugin
```

### Plugin Check (PCP) — wp.org submission readiness
```bash
lando wp plugin install plugin-check --activate
lando wp plugin check procyon-dario-provider
```
CI runs this on every PR with the deferred-exception list applied. See [docs/plugin-check.md](docs/plugin-check.md) for the deferred items and which Jira tickets track them.

## Development Rules

### Red-Green-TDD

Red-Green TDD applies to code changes only, not documentation. Every code change follows it:

1. **RED**: Write a failing test first. Run it. See it fail.
2. **GREEN**: Write the minimum code to make the test pass.
3. **REFACTOR**: Clean up while keeping tests green.

No code without a test. No refactoring without green tests.

### Implementation and prototypes

- **Implementation is handed to a subagent**, not done in the main session.
- **`/prototype` needs Nick's approval** before it starts.

### Git & Commit Rules

- **NEVER commit directly to `main`.** Always create a feature branch and merge via PR.
- **NEVER use `git push` without asking first.**
- **NEVER add co-author credits or AI attribution to commit messages.**
- **Conventional commits** enforced via commitlint + husky:
  - `feat:`, `fix:`, `chore:`, `docs:`, `refactor:`, `test:`, `ci:` — standard prefixes
  - Commit messages are linted on `commit-msg` hook
  - PHP files are linted on `pre-commit` hook via lint-staged

### GitHub Actions Rules

- **Pin `procyon-creative/jira-action-man` to a specific stable tag** (currently `v1.0.0`; no moving `v1` major tag is published). Re-check on each `/jira-setup` run.
- **`.github/workflows/ci.yml`** runs on every PR + push to `main`, and on `workflow_dispatch`. Each leg runs the same `npm run check` a developer runs locally: lint → static analysis → unit tests → Lando boot → Plugin Check → Playwright e2e (Chromium). PRs and pushes test against the latest WordPress release only; `workflow_dispatch` runs and PRs from `bot/update-wp-versions` add a leg for the newest patch of readme.txt's `Requires at least`. A gate job named `npm run check` reports once all legs pass. No CI-only assertions; if `npm run check` passes locally, a leg passes.
- **`.github/workflows/update-wp-versions.yml`** (weekly + manual) runs `php scripts/wp-versions.php bump`: `Tested up to` becomes the latest WordPress release and `Requires at least` the previous major (7.1 → 7.0, 8.0 → 7.9) in readme.txt and the plugin header. It never downgrades. On a change it opens a PR from `bot/update-wp-versions` with `peter-evans/create-pull-request` and the default `GITHUB_TOKEN`, then starts CI on it via `workflow_dispatch`. This needs the repo setting "Allow GitHub Actions to create and approve pull requests" (Settings → Actions → General). Logic lives in `scripts/WordPressVersionRequirements.php` and `scripts/WordPressVersionBump.php` (dev tooling, namespace `Procyon\Dario\Tooling\`, loaded with `require_once`, not shipped in the zip); `scripts/wp-versions.php` is the CLI client.
- **Branch protection on `main`** requires the `npm run check` status check to pass before a PR can merge. Force-push and branch deletion are disabled. Re-apply via `gh api -X PUT --input docs/branch-protection.json repos/procyon-creative/wp-dario-provider/branches/main/protection` if it ever gets cleared.
- **`.github/workflows/jira.yml`** syncs ticket metadata to PRs and transitions tickets to `Done` on merge. See [docs/jira.md](docs/jira.md) for required secrets and board-column notes.
- **`.github/workflows/main.yml`** builds the release zip and attaches it to the GitHub release on `release: published` (and on `push: branches test` for workflow testing). The previous `push: tags v*` trigger was removed (WPD-28) because it raced `gh release create`.

## Version Release Process

Three places must have matching version bumps:
1. `readme.txt` — stable tag + `= 0.x.y =` Changelog header
2. `package.json` — version field
3. `procyon-dario-provider.php` — header comment `Version:` line

Then tag + create the release in one shot:

```bash
git tag v0.2.x
git push origin v0.2.x
gh release create v0.2.x --title "v0.2.x" --notes "..."
```

`gh release create` fires `release: published`, which runs `.github/workflows/main.yml` and uploads `procyon-dario-provider.zip` to the release. Don't rely on `push: tags` to do the build — that trigger was removed (WPD-28). Releases live at https://github.com/procyon-creative/wp-dario-provider/releases.

## Issue Tracking

- Jira board: [WPD project](https://procyoncreative.atlassian.net/jira/software/c/projects/WPD/boards/201)
- See [docs/jira.md](docs/jira.md) for ticket conventions, required secrets, and the workflow that syncs PRs ↔ tickets.

## Admin UX

The Settings → Dario AI Connector page renders sections in this order: **Status → Claude Authentication → Sidecar Settings → OpenAI Backend**. Claude auth is at position 2 because nothing else works without it.

When Claude is not authenticated and the user can `manage_options`, a site-wide admin notice fires on every admin page (suppressed on the settings page itself). The notice is `notice-error` for missing credentials, `notice-warning` for present-but-invalid credentials. Status is cached in the `procyon_dario_auth_status_cache` transient with a 60s TTL; the cache is busted when OAuth completes or credentials.json is imported.

In the Status table, the Claude auth row gets red text + warning dashicon when severity is `error`, orange + dashicon when `warning`. Severity is computed by `DarioSettingsPage::claudeStatusSeverity()` from the Dario `getStatus()` enum (`healthy`/`expiring`/`broken`/`none`).

Secret fields render as empty `password` inputs with `placeholder="*****"` whenever a value exists (stored or supplied via `DARIO_*` constant/env override). Stored bytes are never echoed back. Override-controlled fields render with the `disabled` attribute and a description naming the constant.

## Things an Agent Might Get Wrong

- **This is not a block/frontend plugin.** It has a Node sidecar runtime for Dario, but no `wp-scripts build` and no blocks.
- **The AI Client is bundled in WordPress 7.0+.** Do not install `wordpress/php-ai-client` as a Composer dependency — it will conflict with Core's bundled version.
- **Dario base URL is configurable.** Check `DARIO_BASE_URL` constant or env var before assuming `localhost:3456`. When you change how the base URL resolves, also update `DarioSidecar::allowedHostPort()` — that's what feeds the `http_request_host_is_external` and `http_allowed_safe_ports` filters. Without a matching whitelist, `wp_safe_remote_request` blocks the call (WPD-27).
- **Model list is curated, not fetched.** Dario does expose `/v1/models` now, but it only returns the Claude models its native subscription backend supports — GPT models pass through Dario's OpenAI-compat backend on demand. The hardcoded list in `DarioModelMetadataDirectory::DEFAULT_MODELS` always exposes both Claude AND GPT model IDs to consumers regardless of which backends the admin has configured. If we ever switch to dynamic fetching, GPT models would disappear from the picker until the admin runs `dario backend add openai` and the connector reads back the augmented list.
- **Custom autoloader, not Composer's.** The `src/autoload.php` handles PSR-4 for the `Procyon\Dario\` namespace. Composer's autoloader only handles `vendor/` dependencies.

## Agent skills

### Vendored skills

Skills are vendored in `.agents/skills/`, pinned in `skills-lock.json`, and symlinked into `.claude/skills/`: Matt Pocock's engineering flow plus WordPress's official `wordpress/agent-skills` (start with `wordpress-router`). Agents can invoke all of them except `/prototype`. See `docs/agents/skills.md`.

### Issue tracker

Jira, with `docs/jira.md` as the source of truth. See `docs/agents/issue-tracker.md`.

### Triage labels

Default five triage roles, applied as Jira labels. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: `CONTEXT.md` and `docs/adr/` at the repo root. See `docs/agents/domain.md`.
