# Privacy & GDPR

The plugin integrates with WordPress's built-in privacy tools for GDPR compliance.

## What Data is Stored

- **Strava API credentials** — Client ID, Client Secret, Access Token, Refresh Token (optionally encrypted)
- **Cached activities** — Title, distance, duration, date, location, route data (stored in transients)
- **Sync metadata** — Last sync timestamp, token expiry

## Personal Data Export

When a user requests a data export via **Tools → Export Personal Data**, the plugin exports:

- Activity titles
- Activity types
- Activity dates
- Distances with units
- Cities and countries

## Personal Data Erasure

When a user requests data erasure via **Tools → Erase Personal Data**, the plugin deletes:

- All cached activity data
- Access token
- Refresh token
- Token expiry timestamp
- Last sync timestamp

**Note:** This does not delete data from Strava — only the locally cached copy.

## Privacy Policy

The plugin automatically suggests privacy policy text via WordPress's privacy policy guide (**Settings → Privacy**). The suggested text covers:

- What data is collected from Strava
- How credentials are stored
- Link to Strava's privacy policy

## Credential Encryption

For additional security, enable at-rest encryption for stored credentials. See [Encryption](encryption.md).

## Third-Party Service

This plugin connects to the [Strava API](https://developers.strava.com/). See:

- [Strava Privacy Policy](https://www.strava.com/legal/privacy)
- [Strava API Agreement](https://www.strava.com/legal/api)
- [Strava Terms of Service](https://www.strava.com/legal/terms)
