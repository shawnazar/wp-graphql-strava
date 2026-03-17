# GraphQL Strava Activities

A WordPress plugin that extends [WPGraphQL](https://www.wpgraphql.com/) with Strava activity data, server-side SVG route maps, and activity photos.

## Features

- **GraphQL API** — 23 fields with filtering, pagination, and multi-athlete support
- **REST API** — JSON endpoint for non-GraphQL frontends
- **SVG Route Maps** — Server-rendered inline SVG with dark mode support
- **Elevation Profiles** — SVG elevation chart alongside route maps
- **One-Click OAuth** — "Connect with Strava" button handles token exchange
- **8 Shortcodes** — Activity list, single card, map, stats, latest, heatmap, year review, trends
- **Gutenberg Block** — Native block editor support with live preview
- **Elementor Widget** — Native Elementor integration
- **WP-CLI** — `wp strava sync` and `wp strava status` commands
- **Webhooks** — Real-time Strava updates via webhook integration
- **Caching** — Object cache (Redis/Memcached) with transient fallback
- **Credential Encryption** — Optional AES-256-CBC at-rest encryption
- **GDPR** — Personal data export and erasure hooks
- **CSV Export** — Download activities from the admin page
- **Dark Mode** — SVG maps adapt to system preference automatically
- **Extensible** — 11 filters and 4 action hooks

## Quick Start

1. Install [WPGraphQL](https://www.wpgraphql.com/) 2.0+
2. Install and activate GraphQL Strava Activities
3. Create a [Strava API application](https://www.strava.com/settings/api)
4. Enter Client ID and Secret, click **"Connect with Strava"**
5. Query your activities:

```graphql
{
  stravaActivities(first: 5, type: "Ride") {
    title
    distance
    duration
    svgMap
    elevationProfileSvg
    stravaUrl
  }
}
```

## Requirements

- PHP 8.2+
- WordPress 6.0+ (tested up to 6.9)
- [WPGraphQL](https://www.wpgraphql.com/) 2.0+

## Documentation

Use the sidebar to navigate the full documentation, covering setup, shortcodes, GraphQL queries, REST API, webhooks, filters, and developer architecture.

## Links

- [GitHub Repository](https://github.com/shawnazar/graphql-strava-activities)
- [Report an Issue](https://github.com/shawnazar/graphql-strava-activities/issues)
- [Discussions](https://github.com/shawnazar/graphql-strava-activities/discussions)
