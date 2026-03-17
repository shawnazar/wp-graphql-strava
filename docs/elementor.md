# Elementor Widget

A native Elementor widget for displaying Strava activities. Only loaded when Elementor is active.

## Adding the Widget

1. Open the Elementor editor on any page
2. Search for **"Strava Activities"** in the widget panel
3. Drag it onto your page

## Settings

| Setting | Options | Description |
|---|---|---|
| Display | Activity List, Single Activity, Route Map, Stats, Latest, Heatmap, Year in Review | Which shortcode to render |
| Count | 1–200 | Number of activities (activity list only) |
| Activity Type | Text field | Filter by type, e.g. "Ride" |

## How It Works

The widget renders the corresponding shortcode server-side, so you see a live preview in the Elementor editor. It supports the same features as the shortcodes — SVG maps, dark mode, activity photos.

## Requirements

- [Elementor](https://elementor.com/) must be installed and active
- The widget registers automatically — no additional setup needed
