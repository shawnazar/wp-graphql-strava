# Strava Webhooks

Receive real-time activity notifications from Strava instead of waiting for cron sync.

## How It Works

1. Strava sends a POST request to your site when you create, update, or delete an activity
2. The plugin clears or updates the cache immediately
3. Your next page load or GraphQL query shows the latest data

## Webhook Endpoint

```
GET/POST /wp-json/wpgraphql-strava/v1/webhook
```

- **GET** — Strava sends this to verify your subscription (challenge/response)
- **POST** — Strava sends activity events here

## Setup

### 1. Set a Verify Token

Add to your `wp_options` (via WP-CLI or database):

```bash
wp option update wpgraphql_strava_webhook_verify_token "your-random-secret-string"
```

### 2. Create a Strava Webhook Subscription

```bash
curl -X POST https://www.strava.com/api/v3/push_subscriptions \
  -d client_id=YOUR_CLIENT_ID \
  -d client_secret=YOUR_CLIENT_SECRET \
  -d callback_url=https://yoursite.com/wp-json/wpgraphql-strava/v1/webhook \
  -d verify_token=your-random-secret-string
```

### 3. Verify

Strava will send a GET request to your callback URL with a challenge. The plugin responds automatically.

## Event Handling

| Event | Action |
|---|---|
| `create` | Cache cleared — fresh data fetched on next request |
| `update` | Cache cleared — fresh data fetched on next request |
| `delete` | Activity removed from cache without full re-sync |

## Hooks

```php
// Run custom code when a webhook event fires
add_action( 'wpgraphql_strava_webhook_event', function ( $type, $body ) {
    if ( 'create' === $type ) {
        // Notify on new activity
    }
}, 10, 2 );

// For external subscription systems (Pusher, Mercure, WebSocket)
add_action( 'wpgraphql_strava_subscription_event', function ( $event ) {
    // $event = [ 'type' => 'create', 'activityId' => 123, 'timestamp' => 1710000000 ]
} );
```

## Cron Fallback

Webhooks are optional. The plugin continues to sync via WordPress cron at your configured frequency even without webhooks.
