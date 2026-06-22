# LiveCanvas AI Bridge Licensed Updater

## Goal

LiveCanvas AI Bridge updates are license-gated locally. The preferred delivery path is a LiveCanvas-controlled endpoint; while that endpoint is unavailable, the plugin may fall back to public GitHub release metadata after confirming that LiveCanvas is active and licensed on the local WordPress site.

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

`download_url` should be a short-lived signed URL when served by LiveCanvas. It can proxy a private GitHub release asset, S3 object, or other private storage, but it must not require WordPress to send custom headers during the core plugin install flow.

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
- The LiveCanvas endpoint is tried first.
- If the LiveCanvas endpoint is unavailable and the license was not rejected, the client falls back to the public GitHub latest release API.
- GitHub fallback accepts only stable tags like `v0.1.16` and the exact asset name `livecanvas-forge-ai.zip`.
- GitHub fallback does not run when the LiveCanvas endpoint returns `401` or `403`.
- Successful metadata is cached for 6 hours when an update is available.
- No-update and error metadata is cached for 10 minutes.
- GitHub fallback can be disabled with `LCFA_DISABLE_GITHUB_UPDATE_FALLBACK` or overridden with the `lcfa_allow_github_update_fallback` filter.

## Public GitHub Release Requirements

When using the fallback path:

1. The repository must be publicly reachable without GitHub authentication.
2. The latest release must not be a draft or prerelease.
3. The release tag must be a stable semantic version, for example `v0.1.16`.
4. The release must include an uploaded asset named exactly `livecanvas-forge-ai.zip`.
5. The plugin inside the zip must have the same version as the release tag.

## Minimal Release Checklist

1. Bump `Version` and `LCFA_VERSION` in `livecanvas-forge-ai.php`.
2. Run `bash scripts/build-dist.sh`.
3. Run `php tests/php/github_updater_phase1.php`.
4. Run `php tests/php/package_dist_phase1.php`.
5. Commit and push the release changes.
6. Create a stable GitHub release tag such as `v0.1.16`.
7. Upload `dist/livecanvas-forge-ai.zip` as `livecanvas-forge-ai.zip`.
8. Confirm the latest release API returns the new tag and asset.
9. On a licensed WordPress site with an older AI Bridge version, force `Dashboard > Updates > Check again`.
10. Confirm WordPress receives an update package URL for the new release.
