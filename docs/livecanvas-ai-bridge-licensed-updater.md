# LiveCanvas AI Bridge Licensed Updater

## Goal

LiveCanvas AI Bridge updates should be delivered through a LiveCanvas-controlled endpoint, not through public GitHub release metadata. The plugin remains license-gated locally and the LiveCanvas server verifies the same license before returning update metadata.

## Client Request

Endpoint:

```text
POST https://livecanvas.com/wp-json/livecanvas-ai-bridge/v1/update
```

Body:

```json
{
  "license_key": "LiveCanvas API key from lc_get_apikey() or lc_apikey",
  "plugin_slug": "livecanvas-forge-ai",
  "plugin_version": "0.1.13",
  "site_url": "https://example.com/",
  "wp_version": "6.8",
  "php_version": "8.2.0"
}
```

The license is sent in the HTTPS POST body, never in the update metadata URL. The plugin does not read the raw `lc_settings["license-code"]` value.

## Successful Response

```json
{
  "ok": true,
  "version": "0.1.14",
  "release_url": "https://livecanvas.com/ai-bridge/releases/0.1.14",
  "published_at": "2026-06-19T12:00:00Z",
  "download_url": "https://livecanvas.com/wp-json/livecanvas-ai-bridge/v1/download?token=short-lived-signed-token",
  "body": "Release notes",
  "requires": "6.0",
  "tested": "6.8",
  "requires_php": "7.4"
}
```

`download_url` should be a short-lived signed URL. It can proxy a private GitHub release asset, S3 object, or other private storage, but it must not require WordPress to send custom headers during the core plugin install flow.

## Failure Response

Use HTTP `401` or `403` for invalid or expired LiveCanvas licenses.

```json
{
  "ok": false,
  "code": "license_rejected",
  "message": "LiveCanvas license is not active."
}
```

Use `200` with `ok: false` for no update available only if the server wants to return a human-readable message. The client also treats same-version metadata as no update.

## Client Behavior

- Auto updates are checked only when LiveCanvas is active and `lc_get_apikey()` or `get_site_option("lc_apikey")` returns a non-empty value.
- Successful metadata is cached for 6 hours when an update is available.
- No-update and error metadata is cached for 10 minutes.
- GitHub fallback is disabled by default and is only available for development via `LCFA_ALLOW_GITHUB_UPDATE_FALLBACK` or the `lcfa_allow_github_update_fallback` filter.
