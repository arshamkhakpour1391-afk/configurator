# Production deployment checklist

## Files to upload

| Source in repo | Destination on server |
|----------------|----------------------|
| `theme/mobicare/` | `wp-content/themes/mobicare/` |
| `plugins/mobicare-core/` | `wp-content/plugins/mobicare-core/` |

Do **not** upload the whole git repo into the web root unless you intend to. Only theme + plugin are required.

## wp-config.php recommendations

```php
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISALLOW_FILE_EDIT', true );
define( 'FORCE_SSL_ADMIN', true );
```

## Permalinks

Settings → Permalinks → Post name → Save.

## Cron

Ensure WP-Cron or real server cron runs for scheduled sales and subscription-less Woo tasks.

## Caching

- Exclude cart, checkout, my-account from full-page cache
- Cache shop and product pages
- Use object cache if available (Redis)

## Backups

Daily database + `wp-content/uploads`.

## Updates

Test WooCommerce updates on staging first; theme/plugin use standard APIs to reduce break risk.
