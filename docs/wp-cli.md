# WP-CLI Commands

Manage Strava activities from the command line.

## Commands

### `wp strava sync`

Sync activities from Strava. Fetches fresh data and updates the cache.

```bash
wp strava sync           # Sync using cached data if available
wp strava sync --force   # Clear cache first, then fetch fresh data
```

**Output:**
```
Cache cleared.
Success: 42 activities synced. Last sync: 2026-03-16 14:30:00
```

### `wp strava status`

Show connection status and sync information.

```bash
wp strava status
```

**Output:**
```
Connected:  yes
Token expires: 2026-04-15 08:00:00
Last sync:  2026-03-16 14:30:00
Cached activities: 42
```

## Use Cases

- **Cron automation:** Schedule syncs outside WordPress cron with `wp strava sync --force`
- **Debugging:** Check connection status after credential changes
- **CI/CD:** Verify plugin health in deployment scripts
- **Bulk operations:** Clear and rebuild cache after settings changes
