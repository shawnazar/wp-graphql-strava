# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**GraphQL Strava Activities** is an open-source WordPress plugin that extends WPGraphQL
with Strava activity data. It provides server-side SVG route map generation, activity
photo fetching, and structured GraphQL types — no JavaScript map libraries required.

This is a standalone plugin extracted from the `shawnazar/shawnazar-me-ts` project.
It should work for any headless WordPress site using WPGraphQL, not just shawnazar.me.

## Tech Stack

| Concern | Tool |
|---|---|
| Language | PHP 8.0+ |
| WordPress | 6.0+ |
| Dependency | WPGraphQL 2.0+ |
| License | MIT |
| Plugin name | `GraphQL Strava Activities` |
| WordPress.org slug | `graphql-strava-activities` |
| Text domain | `graphql-strava-activities` |
| Function prefix | `wpgraphql_strava_` |
| Option prefix | `wpgraphql_strava_` |

## Workflow

Every task starts with a GitHub issue. Issues #1-#8 track the initial build:

| # | Title | Status |
|---|-------|--------|
| 1 | Initial plugin scaffold | Done |
| 2 | Strava API client | Done |
| 3 | Caching — transient storage, photo fetching | Done |
| 4 | Polyline decoder and SVG route map generator | Done |
| 5 | GraphQL schema — StravaActivity type and query | Done |
| 6 | Admin settings page | Done |
| 7 | Community docs — README, CONTRIBUTING, etc. | Open |
| 8 | WordPress.org plugin directory submission | Done |

### Branch & PR Process

1. **Start from a GitHub issue** — every piece of work maps to an issue.
2. **Create a feature branch from main:**
   - `feat/[issue-number]-[short-description]` for new features
   - `fix/[issue-number]-[short-description]` for bug fixes
3. **Commit with descriptive messages** that include `Closes #N` to auto-close the issue on merge.
4. **Push the branch and create a PR** via `gh pr create`, linked to the issue.
5. **Merge to main** after the PR is created.
6. **Document decisions** as comments on the GitHub issue when making architectural choices.

### Example

```bash
git checkout -b feat/9-pagination-support
# ... make changes ...
git commit -m "feat: add cursor-based pagination to stravaActivities query

Closes #9"
git push -u origin feat/9-pagination-support
gh pr create --title "Add cursor-based pagination" --body "Closes #9\n\n## Summary\n..."
```

## Architecture

```
wp-graphql-strava/
├── wp-graphql-strava.php       # Plugin bootstrap, dependency check, cron
├── includes/
│   ├── admin.php               # WP Admin settings + Getting Started guide page
│   ├── api.php                 # Strava API client (list activities, detail, token refresh)
│   ├── cache.php               # Transient caching with configurable TTL
│   ├── encryption.php          # Optional AES-256-CBC at-rest credential encryption
│   ├── graphql.php             # WPGraphQL type + query registration
│   ├── polyline.php            # Google encoded polyline decoder
│   └── svg.php                 # Polyline → SVG route map generator
├── .github/
│   ├── dependabot.yml          # Weekly Composer + Actions dependency updates
│   └── workflows/
│       └── deploy.yml          # Auto-deploy to WordPress.org SVN on tag push
├── LICENSE                     # MIT
├── readme.txt                  # WordPress.org plugin readme
├── composer.json               # WPCS dev dependencies, lint scripts
├── phpcs.xml.dist              # WordPress coding standards config
├── .editorconfig               # Consistent formatting across editors
├── .gitignore
└── .distignore                 # Files excluded from release zip
```

## GraphQL Schema

```graphql
type StravaActivity {
  title: String
  distance: Float          # In miles or km based on settings
  duration: String         # Formatted: "1h 16m"
  date: String             # ISO 8601
  svgMap: String           # Inline SVG markup
  stravaUrl: String        # Link to activity on Strava
  type: String             # Ride, Run, Walk, etc.
  photoUrl: String         # Primary activity photo URL
  unit: String             # "mi" or "km"
}

type Query {
  stravaActivities(first: Int, type: String): [StravaActivity]
}
```

## Admin Settings (under "Strava" top-level menu)
- **Credentials**: Client ID, Client Secret, Access Token, Refresh Token
- **SVG Customization**: stroke color (picker), stroke width
- **Display**: units (miles/km), sync frequency (hourly/twice daily/daily)
- **Sync**: resync button, last sync time, cached count, rate limit info
- **Getting Started**: submenu page with setup guide, GraphQL examples, hooks reference

## Filters & Hooks (extensibility)

All filters use `wpgraphql_strava_` prefix:
- `wpgraphql_strava_cache_ttl` — cache duration (default 12 hours)
- `wpgraphql_strava_svg_color` — SVG stroke color
- `wpgraphql_strava_svg_stroke_width` — SVG stroke width
- `wpgraphql_strava_svg_attributes` — extra SVG element attributes
- `wpgraphql_strava_activities` — filter processed activities before caching
- `wpgraphql_strava_activity_types` — allowed activity types

## Code Style

- PHP: 4 spaces indent, WordPress coding standards (WPCS 3.0)
- `declare(strict_types=1)` in every file
- PHPDoc on every function
- All user input sanitized, all output escaped
- Nonces on all forms
- Text domain: `graphql-strava-activities` for all translatable strings
- Lint: `composer lint` / `composer lint:fix`

## Security Rules

- Credentials optionally encrypted at rest (AES-256-CBC) via `GRAPHQL_STRAVA_ENCRYPTION_KEY` constant
- Sensitive options use `wpgraphql_strava_get_option()` / `wpgraphql_strava_update_option()` helpers
- All admin pages check `current_user_can('manage_options')`
- All form submissions verify nonces
- All option values sanitized on save
- No direct file access (ABSPATH check at top of every file)
- Rate limit API calls (200ms delay between detail calls)

## Publishing to WordPress.org

- Plugin slug: `graphql-strava-activities` (no trademark at the front)
- `readme.txt` includes Third-Party Service disclosure (Guideline 7)
- On git tag push, `.github/workflows/deploy.yml` auto-deploys to SVN
- SVN credentials stored as GitHub Secrets (`SVN_USERNAME`, `SVN_PASSWORD`)

## Relationship to shawnazar-me-ts

This plugin was extracted from `shawnazar/shawnazar-me-ts` (issue #41).
The parent project's `wordpress/plugins/shawnazar-site/` still has its own
Strava integration. Once this standalone plugin is stable, the parent
project should switch to using this plugin instead.

## Commands

```bash
# No build step needed — pure PHP plugin
composer install        # Install dev dependencies (WPCS)
composer lint           # Run PHP_CodeSniffer
composer lint:fix       # Auto-fix coding standard issues

# For development, mount in Docker:
# docker-compose volume: ./:/var/www/html/wp-content/plugins/wp-graphql-strava
```
