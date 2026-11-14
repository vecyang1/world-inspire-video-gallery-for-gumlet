=== World Inspire Video Gallery for Gumlet ===
Contributors: hxsmyxh
Donate link: https://github.com/vecyang1/world-inspire-video-gallery-for-gumlet
Tags: video gallery, gumlet, woocommerce video, surecart, youtube nocookie
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-first external product video gallery manager for Gumlet CDN, YouTube, and Vimeo on WooCommerce and SureCart with zero local video storage.

== Description ==

**World Inspire Video Gallery for Gumlet** lets store owners attach, organize, and stream product videos from **Gumlet CDN**, **YouTube**, and **Vimeo** inside **WooCommerce** (including WoodMart and standard themes) and **SureCart** product galleries — without storing heavy MP4 files on your WordPress server.

Built for speed, privacy, and zero-CLI operation, every workflow works visually inside **Media > Video Gallery** and **Settings > External Video Gallery**.

### Key Features

* **Zero-Byte Local Video Storage (Gumlet CDN)**: Register file-less Gumlet CDN MP4 attachments in your WordPress Media Library or upload MP4 files directly from your browser to Gumlet S3 via pre-signed URLs without passing video bytes through PHP.
* **Privacy-Enhanced Embeds by Default**: Uses `youtube-nocookie.com` for YouTube embeds and `dnt=1` for Vimeo embeds so third-party tracking cookies are minimized until visitors interact with the player.
* **Zero Plugin Telemetry**: Does not collect, track, or transmit site usage metrics, analytics beacons, or visitor data to the plugin author or any external analytics server.
* **Multi-Adapter eCommerce Support**: Auto-detects **WooCommerce + WoodMart** (`woodmart_wc_video_gallery`), **Standard WooCommerce Themes** (inline responsive `<video>` and iframe gallery slide filter), and **SureCart** (`sc_product` gallery metadata).
* **Visual Slot Arranger (Slot #2 Default)**: Reorder product gallery slots, preserve Slot #1 as your primary product photo, move video bindings across slides, or detach videos with one click.
* **Universal Video Input Parser & Probe**: Paste any YouTube URL, YouTube Shorts URL, 11-character Video ID, Gumlet CDN/dashboard URL, 24-character Gumlet Asset ID, Vimeo URL, or Media Library attachment ID to probe playback reachability before attaching.
* **Storefront Coverage Audit & Hygiene**: Inspect published products missing gallery videos, identify local MP4 files consuming server disk, and clean empty WoodMart video stub entries in one click.
* **Authenticated REST API**: Manage audits, probes, asset registration, and slot ordering programmatically via `/wp-json/world-inspire-video-gallery/v1/*`.

### Disclaimer & Non-Affiliation

World Inspire Video Gallery for Gumlet is an independent open-source plugin and is not affiliated with, endorsed by, or sponsored by Gumlet, YouTube, Google, Vimeo, WooCommerce, WoodMart, or SureCart.

== Installation ==

1. Upload the `world-inspire-video-gallery-for-gumlet` folder to `/wp-content/plugins/`, or install the ZIP file via **Plugins > Add New > Upload Plugin**.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Navigate to **Settings > External Video Gallery** to configure your optional Gumlet Workspace ID, optional Gumlet API Key, default video slot, and privacy-enhanced embed preference.
4. Open **Media > Video Gallery** to probe video links, upload MP4s directly to Gumlet CDN, and arrange product gallery slots.

== Frequently Asked Questions ==

= Do I need a Gumlet account to use this plugin? =
No. You can immediately attach and arrange YouTube, YouTube Shorts, Vimeo, or HTTPS MP4 videos without configuring Gumlet credentials. A Gumlet Workspace ID and API Key are only needed when using Gumlet CDN hosting or direct browser-to-Gumlet MP4 uploads.

= Does this plugin store or upload videos on my WordPress web host? =
No. Gumlet CDN assets are stored as file-less WordPress Media attachments pointing directly to `https://video.gumlet.io/.../main.mp4`, and direct uploads stream from the administrator's browser straight to Gumlet's pre-signed upload URL.

= Does this plugin collect any user or site telemetry? =
No. The plugin contains zero telemetry, zero tracking pixels, and zero phone-home requests.

= Which WooCommerce themes and eCommerce plugins are supported? =
It natively integrates with WooCommerce + WoodMart, standard WooCommerce product galleries (rendering responsive HTML5 `<video>` and privacy-enhanced iframe players on matching slides), and SureCart products.

= Where can I find the open-source code and report issues? =
Source code and automated tests are hosted on GitHub at https://github.com/vecyang1/world-inspire-video-gallery-for-gumlet.

== External Services ==

This plugin does not connect to any external service automatically on frontend page visits unless an administrator explicitly attaches or probes an external video from Gumlet, YouTube, or Vimeo.

1. **Gumlet API & Video CDN (`https://api.gumlet.com/v1`, `https://video.gumlet.io`)**
   * **When used**: Only when an administrator configures a Gumlet API Key/Workspace ID and initiates a direct upload session, asset status check, or MP4 range probe in WP Admin, or when a storefront page plays an attached Gumlet MP4 video.
   * **Data sent**: Video file bytes (during direct upload), optional video title/collection ID, and asset ID. No visitor PII is sent by this plugin.
   * **Terms & Privacy**: https://www.gumlet.com/terms/ and https://www.gumlet.com/privacy/

2. **YouTube / Google (`https://www.youtube.com`, `https://www.youtube-nocookie.com`, `https://i.ytimg.com`)**
   * **When used**: When an administrator probes a YouTube URL (`oEmbed` metadata check), imports a poster preview image from `i.ytimg.com` into the local Media Library, or embeds a YouTube video on a product slide (using `youtube-nocookie.com` by default when Privacy-Enhanced Mode is enabled).
   * **Data sent**: The YouTube video URL/ID requested by the administrator or embedded for playback.
   * **Terms & Privacy**: https://www.youtube.com/t/terms and https://policies.google.com/privacy

3. **Vimeo (`https://vimeo.com`, `https://player.vimeo.com`)**
   * **When used**: Only when an administrator embeds a Vimeo URL on a product gallery slide (appending `dnt=1` when Privacy-Enhanced Mode is enabled).
   * **Data sent**: The Vimeo video ID embedded for playback.
   * **Terms & Privacy**: https://vimeo.com/terms and https://vimeo.com/privacy

== Screenshots ==

1. Product Gallery Arranger (`Media > Video Gallery`): Audit video coverage across products, filter missing SKUs, reorder slots, and insert YouTube or Gumlet videos at Slot #2.
2. Probe & Direct Upload (`Media > Video Gallery`): Test video URLs, sideload YouTube thumbnails locally, or upload MP4 files straight to Gumlet CDN with zero PHP disk usage.
3. Asset Inventory & Hygiene (`Media > Video Gallery`): Review registered Gumlet CDN assets alongside local MP4s and clean empty WoodMart video stub entries.
4. Plugin Settings (`Settings > External Video Gallery`): Configure Gumlet Workspace ID, API Key (with `WIVG_GUMLET_API_KEY` wp-config.php constant support), default slot, and Privacy-Enhanced Mode.

== Changelog ==

= 1.0.0 =
* Initial public open-source release on WordPress.org and GitHub.
* Multi-adapter support for WooCommerce + WoodMart, Standard WooCommerce Themes, and SureCart.
* File-less Gumlet CDN attachment engine with direct browser-to-Gumlet S3 pre-signed MP4 upload.
* Privacy-Enhanced Embeds enabled by default (`youtube-nocookie.com` and Vimeo `dnt=1`) with zero plugin telemetry.
* WordPress Settings API options screen, Media -> Video Gallery hub, and authenticated REST API endpoints.
