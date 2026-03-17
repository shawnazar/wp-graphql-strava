# REST API

A public REST endpoint for accessing Strava activities without GraphQL.

## Endpoint

```
GET /wp-json/wpgraphql-strava/v1/activities
```

## Parameters

| Parameter | Type | Default | Description |
|---|---|---|---|
| `count` | integer | `0` (all) | Number of activities to return (max 200) |
| `offset` | integer | `0` | Skip this many activities |
| `type` | string | — | Filter by activity type (e.g. `Ride`, `Run`) |
| `user_id` | integer | `0` | WordPress user ID for multi-athlete (0 = global) |

## Example Request

```bash
curl https://yoursite.com/wp-json/wpgraphql-strava/v1/activities?count=5&type=Ride
```

## Example Response

```json
[
  {
    "title": "Morning Ride",
    "distance": 25.4,
    "duration": "1h 16m",
    "date": "2026-03-15T08:30:00Z",
    "type": "Ride",
    "unit": "mi",
    "speedUnit": "mph",
    "svgMap": "<svg ...>",
    "stravaUrl": "https://www.strava.com/activities/123",
    "averageSpeed": 12.44,
    "maxSpeed": 27.6,
    "elevationGain": 312.5,
    "poweredByStrava": "Powered by Strava"
  }
]
```

## Response Headers

| Header | Description |
|---|---|
| `X-WP-Total` | Total number of activities before pagination |

## Authentication

The endpoint is public (no authentication required) — same as the GraphQL query. Activity data is cached server-side and doesn't expose private credentials.
