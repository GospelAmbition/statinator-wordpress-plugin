# Gospel Ambition Analytics

WordPress plugin that connects WordPress-based Gospel Ambition projects to the [Statinator](../tracking-server) analytics server.

Thin wrapper — API key stored in `wp-config.php`, no admin UI.

## What it does

1. **Enqueues the frontend tracking script** on every page with the configured `data-project` and `data-storage` attributes.
2. **Provides `go_analytics_track()`** — a global PHP function for sending server-side events to the Statinator API (non-blocking).
3. **Auto-tracks login and registration** via `wp_login` and `user_register`, sending an `email_hash` for cross-project user linking.
4. **Fires the `go_analytics_event` action** so other plugins can listen for analytics events.

## Installation

1. Copy this directory into `wp-content/plugins/` (or symlink it).
2. Activate **Gospel Ambition Analytics** from the WordPress plugins screen.
3. Add configuration to `wp-config.php` (see below).

## Configuration

Define these constants in `wp-config.php`:

```php
define('GO_ANALYTICS_PROJECT_ID', 'prayer_global');
define('GO_ANALYTICS_API_KEY', 'uuid-from-statinator-admin');
define('GO_ANALYTICS_ENDPOINT', 'https://statinator.prayer.global');
define('GO_ANALYTICS_STORAGE_MODE', 'local'); // optional, defaults to 'session'
define('GO_ANALYTICS_HASH_KEY', '_gs_vid');   // optional, localStorage key for the visitor UUID (default: _gs_vid)
```

`GO_ANALYTICS_HASH_KEY` sets the localStorage key name the frontend tracking script uses to store its persistent visitor UUID. It only applies when `GO_ANALYTICS_STORAGE_MODE` is `'local'` (or `'cookie+local'`); in `'session'` mode the visitor hash is derived server-side and the key is unused. Override the default when migrating from a legacy storage key or when multiple Statinator-tracked sites share an origin and shouldn't share visitor IDs.

If `GO_ANALYTICS_PROJECT_ID` or `GO_ANALYTICS_ENDPOINT` is missing the frontend script is not enqueued. If `GO_ANALYTICS_API_KEY` is also missing, server-side tracking calls silently no-op.

## Usage

Send a server-side event from anywhere in your theme/plugin code:

```php
// Event with metadata and a numeric value
go_analytics_track('global_lap_completed', ['lap_number' => $lap_number], $lap_number);

// Event with metadata only
go_analytics_track('campaign_created', ['campaign_id' => $post_id]);

// Event with no metadata
go_analytics_track('newsletter_signup');
```

The function attaches `hostname`, `language` (Polylang-aware, falls back to WP locale), and a SHA-256 `user_hash` of the logged-in user's email when available. The `anonymous_hash` value can be supplied via the `go_analytics_anonymous_hash` filter.

### Listening for events

```php
add_action('go_analytics_event', function ($event_type, $metadata, $value) {
    // ...
}, 10, 3);
```

## Requirements

- WordPress 5.0+
- PHP 7.4+

## Verification

1. Activate the plugin on a local WordPress site with constants pointing at a local Statinator.
2. View any page — confirm the tracking script tag is in the page source with correct `data-project` / `data-storage` attributes.
3. Log in — a `user_login` event with `email_hash` should appear in the Statinator database.
4. Register a user — `user_registered` event with `email_hash` should appear.
5. Call `go_analytics_track('test_event', ['key' => 'value'])` — event should appear.
