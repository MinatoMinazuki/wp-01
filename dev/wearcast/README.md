# Wearcast

Current structure:

- `design/`
- `prototype/`
- `app/`
- `assets/`
- `actions/`
- `api/`
- `database/schema.sql`

Notes:

- app pages are plain PHP + jQuery, no router
- weather data is fetched from JMA JSON and cached in `storage/cache`
- if `config.php` is missing, the app falls back to session mode
