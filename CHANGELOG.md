# Changelog

All notable changes to **World Inspire Video Gallery for Gumlet** are documented in this file.

## [1.0.0] - 2026-11-14

### Added
- Multi-adapter storefront video gallery management for WooCommerce + WoodMart, standard WooCommerce themes, and SureCart (`sc_product`).
- File-less Gumlet CDN attachment mapping (`video.gumlet.io/<workspace>/<asset>/main.mp4`) and direct browser-to-Gumlet S3 upload sessions (`POST /wp-json/world-inspire-video-gallery/v1/gumlet/upload-session`).
- Privacy-Enhanced Embeds enabled by default (`youtube-nocookie.com` for YouTube, `dnt=1` for Vimeo) with zero telemetry and zero tracking beacons.
- WordPress Settings API page (**Settings > External Video Gallery**) supporting `WIVG_GUMLET_API_KEY` `wp-config.php` constant isolation.
- Zero-CLI admin hub (**Media > Video Gallery**) with URL-synced tabs, visual slot reordering, probe & preview, asset inventory, and storefront hygiene pruning.
- Enqueued admin/frontend stylesheets and scripts (`admin/css/admin.css`, `admin/js/admin.js`, `public/css/frontend.css`), clean `uninstall.php`, and zero-dependency PHP mock E2E test suite.
