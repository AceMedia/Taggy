# Taggy developer guide

The `taggy_search` authenticated AJAX action requires the `taggy_search` nonce, `edit_posts` and the taxonomy's `assign_terms` capability. It accepts 3–100 characters and returns an array of safe title strings. Missing credentials or service failures return a controlled message, without upstream URLs or credentials.

`taggy_movie_search($query)` performs the server-side service call. It assumes the caller has already authorised the operation; do not expose it through an unprotected endpoint. Responses are cached per site for five minutes, keyed by the query and a hash of the configured key. The per-user request counter is a best-effort quota guard, not an atomic billing guarantee.

`get_taggy_api_key()` is retained and reads the site's `taggy_api_key` option. Older versions copied updates into a network option too; this release stops that copy and does not read the historical network value. Existing editor behaviour already read the per-site option. Review any external integrations calling the old helper before upgrading.

The editor uses core taxonomy REST operations to find or create a tag and updates the edited post's `tags` property. WordPress enforces term creation permissions. The post must be saved to persist the selection. External titles are rendered as React text, never HTML. There are no custom extension filters in this release.

## Reproducible checks

`tests/search.php` uses the WordPress `pre_http_request` filter to return fictional movie titles, including an HTML-injection payload. It removes temporary users and cache entries and restores the key afterwards. Only use a disposable site. Live OMDb credentials are not required for this regression test. The 0.421.0 submission used mocked provider responses; a live provider check with a dedicated demo key remains outstanding.
