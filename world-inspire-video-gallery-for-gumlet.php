<?php
/**
 * Plugin Name:       World Inspire Video Gallery for Gumlet
 * Plugin URI:        https://github.com/vecyang1/world-inspire-video-gallery-for-gumlet
 * Description:       Privacy-first product video gallery manager supporting Gumlet CDN direct MP4 uploads, YouTube (youtube-nocookie), and Vimeo across WooCommerce and SureCart with zero local video storage.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            World Inspire
 * Author URI:        https://github.com/vecyang1
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       world-inspire-video-gallery-for-gumlet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'World_Inspire_Video_Gallery' ) ) {
	class World_Inspire_Video_Gallery {
		const VERSION            = '1.0.0';
		const REST_NAMESPACE     = 'world-inspire-video-gallery/v1';
		const ADMIN_SLUG         = 'world-inspire-video-gallery';
		const SETTINGS_SLUG      = 'world-inspire-video-gallery-settings';
		const OPT_SETTINGS       = 'wivg_settings';
		const SETTINGS_GROUP     = 'wivg_settings_group';
		const META_EXT_URL       = '_wivg_external_video_url';
		const META_GUMLET_ASSET  = '_wivg_gumlet_asset_id';
		const META_POSTER_ID     = '_wivg_poster_attachment_id';
		const META_YT_POSTER_FOR = '_wivg_youtube_poster_for';
		const META_STD_WC_VIDEOS = '_wivg_product_video_gallery';
		const GUMLET_HOST        = 'video.gumlet.io';

		/**
		 * Register plugin hooks.
		 */
		public static function init() {
			add_filter( 'wp_get_attachment_url', array( __CLASS__, 'filter_attachment_url' ), 99, 2 );
			add_filter( 'get_attached_file', array( __CLASS__, 'filter_attached_file' ), 99, 2 );
			add_filter( 'wp_prepare_attachment_for_js', array( __CLASS__, 'filter_prepare_attachment_js' ), 20, 2 );
			add_filter( 'woocommerce_single_product_image_thumbnail_html', array( __CLASS__, 'filter_wc_standard_gallery_slide' ), 20, 2 );
			add_filter( 'manage_media_columns', array( __CLASS__, 'add_media_column' ) );
			add_action( 'manage_media_custom_column', array( __CLASS__, 'render_media_column' ), 10, 2 );
			add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
			add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ), 99 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend_assets' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( __CLASS__, 'add_plugin_action_links' ) );
			add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		}

		/**
		 * Enqueue admin stylesheets and scripts strictly on plugin admin pages.
		 *
		 * @param string $hook_suffix Current admin page hook suffix.
		 */
		public static function enqueue_admin_assets( $hook_suffix ) {
			$media_hook    = 'media_page_' . self::ADMIN_SLUG;
			$settings_hook = 'settings_page_' . self::SETTINGS_SLUG;

			if ( $media_hook !== $hook_suffix && $settings_hook !== $hook_suffix ) {
				return;
			}

			$cfg = self::get_settings();

			wp_enqueue_style(
				'wivg-admin',
				plugins_url( 'admin/css/admin.css', __FILE__ ),
				array(),
				self::VERSION
			);
			wp_add_inline_style(
				'wivg-admin',
				'.wivg-wrap{--wivg-accent:' . esc_attr( $cfg['accent_color'] ) . ';}'
			);

			if ( $media_hook === $hook_suffix ) {
				$audit = self::build_audit_report();
				wp_enqueue_script(
					'wivg-admin',
					plugins_url( 'admin/js/admin.js', __FILE__ ),
					array(),
					self::VERSION,
					true
				);
				wp_localize_script(
					'wivg-admin',
					'wivgAdminData',
					array(
						'restBase'    => esc_url_raw( rest_url( self::REST_NAMESPACE ) ),
						'nonce'       => wp_create_nonce( 'wp_rest' ),
						'defaultSlot' => max( 1, (int) $cfg['default_slot'] ),
						'audit'       => $audit,
						'i18n'        => array(
							'noProducts'        => __( 'No matching products found.', 'world-inspire-video-gallery-for-gumlet' ),
							'videosActive'      => __( 'Video(s) Active', 'world-inspire-video-gallery-for-gumlet' ),
							'missingVideo'      => __( 'Missing Video', 'world-inspire-video-gallery-for-gumlet' ),
							'hasVideo'          => __( 'Has Video', 'world-inspire-video-gallery-for-gumlet' ),
							'auto'              => __( 'Auto', 'world-inspire-video-gallery-for-gumlet' ),
							'imageSlide'        => __( 'Image Slide', 'world-inspire-video-gallery-for-gumlet' ),
							'moveLeft'          => __( 'Move Left', 'world-inspire-video-gallery-for-gumlet' ),
							'moveRight'         => __( 'Move Right', 'world-inspire-video-gallery-for-gumlet' ),
							'detach'            => __( 'Detach', 'world-inspire-video-gallery-for-gumlet' ),
							'slotPrefix'        => __( 'Slot #', 'world-inspire-video-gallery-for-gumlet' ),
							'mainLabel'         => __( 'Main', 'world-inspire-video-gallery-for-gumlet' ),
							'viewPdp'           => __( 'View Storefront Product', 'world-inspire-video-gallery-for-gumlet' ),
							'inlinePlaceholder' => __( 'YouTube URL/ID or Gumlet/MP4', 'world-inspire-video-gallery-for-gumlet' ),
							'targetSlot'        => __( 'Target Slot #', 'world-inspire-video-gallery-for-gumlet' ),
							'insertAtSlot'      => __( 'Insert at Slot', 'world-inspire-video-gallery-for-gumlet' ),
							'noAssets'          => __( 'No video attachments registered in WP Media Library yet.', 'world-inspire-video-gallery-for-gumlet' ),
							'colId'             => __( 'ID', 'world-inspire-video-gallery-for-gumlet' ),
							'colTitle'          => __( 'Title', 'world-inspire-video-gallery-for-gumlet' ),
							'colHost'           => __( 'Host / Size', 'world-inspire-video-gallery-for-gumlet' ),
							'colDims'           => __( 'Dimensions', 'world-inspire-video-gallery-for-gumlet' ),
							'colUrl'            => __( 'URL', 'world-inspire-video-gallery-for-gumlet' ),
							'allHealthy'        => __( 'All published products have verified gallery videos and zero postmeta stub clutter.', 'world-inspire-video-gallery-for-gumlet' ),
							'enterVideoFirst'   => __( 'Enter a YouTube URL/ID, Gumlet link, or MP4 ID first.', 'world-inspire-video-gallery-for-gumlet' ),
							'selectMp4'         => __( 'Select an MP4 file first.', 'world-inspire-video-gallery-for-gumlet' ),
							'requestingSession' => __( 'Requesting pre-signed Gumlet upload session...', 'world-inspire-video-gallery-for-gumlet' ),
							'saved'             => __( 'Saved.', 'world-inspire-video-gallery-for-gumlet' ),
						),
					)
				);
			}
		}

		/**
		 * Register lightweight frontend gallery CSS (enqueued lazily only when a video slide renders).
		 */
		public static function enqueue_frontend_assets() {
			if ( 'woocommerce_standard' !== self::detect_adapter() ) {
				return;
			}
			wp_register_style(
				'wivg-frontend',
				plugins_url( 'public/css/frontend.css', __FILE__ ),
				array(),
				self::VERSION
			);
		}

		/**
		 * Add quick Settings and Video Gallery links on the Plugins list screen.
		 *
		 * @param array $links Existing action links.
		 * @return array
		 */
		public static function add_plugin_action_links( $links ) {
			if ( ! is_array( $links ) ) {
				$links = array();
			}
			$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=' . self::SETTINGS_SLUG ) ) . '">'
				. esc_html__( 'Settings', 'world-inspire-video-gallery-for-gumlet' )
				. '</a>';
			$hub_link      = '<a href="' . esc_url( admin_url( 'upload.php?page=' . self::ADMIN_SLUG ) ) . '">'
				. esc_html__( 'Video Gallery', 'world-inspire-video-gallery-for-gumlet' )
				. '</a>';
			array_unshift( $links, $settings_link, $hub_link );
			return $links;
		}

		/**
		 * Detect active commerce/gallery adapter for the current WordPress site.
		 *
		 * @return string 'woocommerce_woodmart' | 'surecart' | 'woocommerce_standard'
		 */
		public static function detect_adapter() {
			if ( function_exists( 'post_type_exists' ) && post_type_exists( 'sc_product' ) && ! class_exists( 'WooCommerce' ) ) {
				return 'surecart';
			}
			if ( function_exists( 'woodmart_get_opt' ) || defined( 'WOODMART_THEME_DIR' ) || ( function_exists( 'get_template' ) && 'woodmart' === strtolower( (string) get_template() ) ) ) {
				return 'woocommerce_woodmart';
			}
			if ( function_exists( 'post_type_exists' ) && post_type_exists( 'sc_product' ) ) {
				return 'surecart';
			}
			return 'woocommerce_standard';
		}

		/**
		 * Resolve normalized plugin configuration from WordPress options.
		 *
		 * @return array
		 */
		public static function get_settings() {
			$host = '';
			if ( function_exists( 'home_url' ) ) {
				$parts = wp_parse_url( home_url() );
				$host  = isset( $parts['host'] ) ? strtolower( preg_replace( '/^www\./', '', (string) $parts['host'] ) ) : '';
			}

			$defaults = array(
				'brand_name'          => function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'name' ) : __( 'Storefront', 'world-inspire-video-gallery-for-gumlet' ),
				'domain'              => $host ? $host : 'localhost',
				'accent_color'        => '#FF5500',
				'youtube_channel_url' => '',
				'default_slot'        => 2,
				'privacy_mode'        => 1,
				'gumlet_workspace_id' => '',
				'gumlet_api_key'      => '',
			);

			$saved = function_exists( 'get_option' ) ? get_option( self::OPT_SETTINGS, array() ) : array();
			if ( ! is_array( $saved ) ) {
				$saved = array();
			}

			$merged = array_merge(
				$defaults,
				array_filter(
					$saved,
					function ( $v ) {
						return null !== $v && '' !== $v;
					}
				)
			);

			$color                         = function_exists( 'sanitize_hex_color' ) ? sanitize_hex_color( (string) ( $merged['accent_color'] ?? '' ) ) : '';
			$merged['accent_color']        = $color ? $color : '#FF5500';
			$merged['brand_name']          = '' !== trim( (string) $merged['brand_name'] ) ? (string) $merged['brand_name'] : $defaults['brand_name'];
			$merged['default_slot']        = max( 1, min( 20, (int) ( $merged['default_slot'] ?? 2 ) ) );
			$merged['privacy_mode']        = array_key_exists( 'privacy_mode', $saved ) ? (int) ( ! empty( $saved['privacy_mode'] ) ) : 1;
			$merged['gumlet_workspace_id'] = self::is_valid_hex24( (string) ( $merged['gumlet_workspace_id'] ?? '' ) )
				? strtolower( trim( (string) $merged['gumlet_workspace_id'] ) )
				: '';
			$merged['youtube_channel_url'] = ! empty( $merged['youtube_channel_url'] ) ? esc_url_raw( (string) $merged['youtube_channel_url'] ) : '';
			$merged['adapter']             = self::detect_adapter();

			return $merged;
		}

		/**
		 * Retrieve configured Gumlet API key (supports wp-config.php WIVG_GUMLET_API_KEY override).
		 *
		 * @return string
		 */
		public static function get_gumlet_api_key() {
			if ( defined( 'WIVG_GUMLET_API_KEY' ) && is_string( WIVG_GUMLET_API_KEY ) && '' !== trim( WIVG_GUMLET_API_KEY ) ) {
				return trim( WIVG_GUMLET_API_KEY );
			}
			$saved = function_exists( 'get_option' ) ? get_option( self::OPT_SETTINGS, array() ) : array();
			if ( is_array( $saved ) && ! empty( $saved['gumlet_api_key'] ) ) {
				return trim( (string) $saved['gumlet_api_key'] );
			}
			return '';
		}

		/**
		 * Register WordPress Settings API options, sections, and fields.
		 */
		public static function register_settings() {
			register_setting(
				self::SETTINGS_GROUP,
				self::OPT_SETTINGS,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
					'default'           => array(),
				)
			);

			add_settings_section(
				'wivg_gumlet_section',
				__( 'Gumlet CDN Credentials', 'world-inspire-video-gallery-for-gumlet' ),
				array( __CLASS__, 'render_gumlet_section_intro' ),
				self::SETTINGS_SLUG
			);

			add_settings_field(
				'gumlet_workspace_id',
				__( 'Gumlet Workspace ID', 'world-inspire-video-gallery-for-gumlet' ),
				array( __CLASS__, 'render_field_gumlet_workspace_id' ),
				self::SETTINGS_SLUG,
				'wivg_gumlet_section',
				array( 'label_for' => 'wivg_gumlet_workspace_id' )
			);

			add_settings_field(
				'gumlet_api_key',
				__( 'Gumlet API Key', 'world-inspire-video-gallery-for-gumlet' ),
				array( __CLASS__, 'render_field_gumlet_api_key' ),
				self::SETTINGS_SLUG,
				'wivg_gumlet_section',
				array( 'label_for' => 'wivg_gumlet_api_key' )
			);

			add_settings_section(
				'wivg_gallery_section',
				__( 'Gallery & Privacy Preferences', 'world-inspire-video-gallery-for-gumlet' ),
				array( __CLASS__, 'render_gallery_section_intro' ),
				self::SETTINGS_SLUG
			);

			add_settings_field(
				'privacy_mode',
				__( 'Privacy-Enhanced Embeds', 'world-inspire-video-gallery-for-gumlet' ),
				array( __CLASS__, 'render_field_privacy_mode' ),
				self::SETTINGS_SLUG,
				'wivg_gallery_section',
				array( 'label_for' => 'wivg_privacy_mode' )
			);

			add_settings_field(
				'brand_name',
				__( 'Storefront / Gallery Label', 'world-inspire-video-gallery-for-gumlet' ),
				array( __CLASS__, 'render_field_brand_name' ),
				self::SETTINGS_SLUG,
				'wivg_gallery_section',
				array( 'label_for' => 'wivg_brand_name' )
			);

			add_settings_field(
				'accent_color',
				__( 'Admin Accent Color', 'world-inspire-video-gallery-for-gumlet' ),
				array( __CLASS__, 'render_field_accent_color' ),
				self::SETTINGS_SLUG,
				'wivg_gallery_section',
				array( 'label_for' => 'wivg_accent_color' )
			);

			add_settings_field(
				'default_slot',
				__( 'Default Video Slot #', 'world-inspire-video-gallery-for-gumlet' ),
				array( __CLASS__, 'render_field_default_slot' ),
				self::SETTINGS_SLUG,
				'wivg_gallery_section',
				array( 'label_for' => 'wivg_default_slot' )
			);

			add_settings_field(
				'youtube_channel_url',
				__( 'YouTube Channel URL (Optional)', 'world-inspire-video-gallery-for-gumlet' ),
				array( __CLASS__, 'render_field_youtube_channel_url' ),
				self::SETTINGS_SLUG,
				'wivg_gallery_section',
				array( 'label_for' => 'wivg_youtube_channel_url' )
			);
		}

		/**
		 * Sanitize settings payload from Settings API form submission.
		 *
		 * @param mixed $input Raw submitted settings array.
		 * @return array
		 */
		public static function sanitize_settings( $input ) {
			if ( ! is_array( $input ) ) {
				$input = array();
			}

			$existing = get_option( self::OPT_SETTINGS, array() );
			if ( ! is_array( $existing ) ) {
				$existing = array();
			}

			$clean = array();

			$ws = isset( $input['gumlet_workspace_id'] ) ? strtolower( trim( sanitize_text_field( (string) $input['gumlet_workspace_id'] ) ) ) : '';
			if ( '' === $ws ) {
				$clean['gumlet_workspace_id'] = '';
			} elseif ( self::is_valid_hex24( $ws ) ) {
				$clean['gumlet_workspace_id'] = $ws;
			} else {
				$clean['gumlet_workspace_id'] = isset( $existing['gumlet_workspace_id'] ) && self::is_valid_hex24( (string) $existing['gumlet_workspace_id'] )
					? strtolower( (string) $existing['gumlet_workspace_id'] )
					: '';
				if ( function_exists( 'add_settings_error' ) ) {
					add_settings_error(
						self::OPT_SETTINGS,
						'wivg_invalid_workspace_id',
						__( 'Gumlet Workspace ID must be a 24-character hexadecimal ID.', 'world-inspire-video-gallery-for-gumlet' ),
						'error'
					);
				}
			}

			if ( ! empty( $input['clear_gumlet_api_key'] ) ) {
				$clean['gumlet_api_key'] = '';
			} elseif ( isset( $input['gumlet_api_key'] ) && '' !== trim( (string) $input['gumlet_api_key'] ) ) {
				$clean['gumlet_api_key'] = trim( sanitize_text_field( (string) $input['gumlet_api_key'] ) );
			} else {
				$clean['gumlet_api_key'] = isset( $existing['gumlet_api_key'] ) ? (string) $existing['gumlet_api_key'] : '';
			}

			$clean['privacy_mode'] = ! empty( $input['privacy_mode'] ) ? 1 : 0;

			$clean['brand_name'] = isset( $input['brand_name'] )
				? sanitize_text_field( trim( (string) $input['brand_name'] ) )
				: '';

			$accent                = isset( $input['accent_color'] ) ? sanitize_hex_color( (string) $input['accent_color'] ) : '';
			$clean['accent_color'] = $accent ? $accent : '#FF5500';

			$slot                  = isset( $input['default_slot'] ) ? absint( $input['default_slot'] ) : 2;
			$clean['default_slot'] = max( 1, min( 20, $slot ) );

			$yt_url                       = isset( $input['youtube_channel_url'] ) ? trim( (string) $input['youtube_channel_url'] ) : '';
			$clean['youtube_channel_url'] = '' !== $yt_url ? esc_url_raw( $yt_url ) : '';

			return $clean;
		}

		public static function render_gumlet_section_intro() {
			echo '<p class="description">'
				. esc_html__( 'Configure your optional Gumlet workspace (collection) ID and API key for direct browser-to-Gumlet MP4 uploads and asset URL resolution.', 'world-inspire-video-gallery-for-gumlet' )
				. '</p>';
		}

		public static function render_gallery_section_intro() {
			echo '<p class="description">'
				. esc_html__( 'Customize privacy-enhanced embed behavior, default gallery slot placement, and admin interface preferences.', 'world-inspire-video-gallery-for-gumlet' )
				. '</p>';
		}

		public static function render_field_gumlet_workspace_id() {
			$cfg = self::get_settings();
			printf(
				'<input type="text" id="wivg_gumlet_workspace_id" name="%1$s[gumlet_workspace_id]" value="%2$s" class="regular-text code" placeholder="%3$s" autocomplete="off" />',
				esc_attr( self::OPT_SETTINGS ),
				esc_attr( $cfg['gumlet_workspace_id'] ),
				esc_attr__( '24-character hex workspace/collection ID', 'world-inspire-video-gallery-for-gumlet' )
			);
			echo '<p class="description">'
				. esc_html__( 'Found in your Gumlet Video Dashboard under Collections / Workspaces (24-character hex string).', 'world-inspire-video-gallery-for-gumlet' )
				. '</p>';
		}

		public static function render_field_gumlet_api_key() {
			$has_const = defined( 'WIVG_GUMLET_API_KEY' ) && is_string( WIVG_GUMLET_API_KEY ) && '' !== trim( WIVG_GUMLET_API_KEY );
			$has_key   = '' !== self::get_gumlet_api_key();

			if ( $has_const ) {
				echo '<input type="password" id="wivg_gumlet_api_key" class="regular-text" value="" disabled="disabled" placeholder="' . esc_attr__( 'Defined via WIVG_GUMLET_API_KEY constant', 'world-inspire-video-gallery-for-gumlet' ) . '" />';
				echo '<p class="description">' . esc_html__( 'API key is currently overridden by WIVG_GUMLET_API_KEY in wp-config.php.', 'world-inspire-video-gallery-for-gumlet' ) . '</p>';
				return;
			}

			printf(
				'<input type="password" id="wivg_gumlet_api_key" name="%1$s[gumlet_api_key]" value="" class="regular-text" placeholder="%2$s" autocomplete="new-password" />',
				esc_attr( self::OPT_SETTINGS ),
				$has_key ? esc_attr__( 'Saved (leave blank to keep existing key)', 'world-inspire-video-gallery-for-gumlet' ) : esc_attr__( 'Enter your Gumlet API key', 'world-inspire-video-gallery-for-gumlet' )
			);

			if ( $has_key ) {
				printf(
					'<p class="description"><label><input type="checkbox" name="%1$s[clear_gumlet_api_key]" value="1" /> %2$s</label></p>',
					esc_attr( self::OPT_SETTINGS ),
					esc_html__( 'Remove saved Gumlet API key', 'world-inspire-video-gallery-for-gumlet' )
				);
			} else {
				echo '<p class="description">'
					. esc_html__( 'Optional. Required only for direct MP4 uploads to Gumlet and asset status polling.', 'world-inspire-video-gallery-for-gumlet' )
					. '</p>';
			}
		}

		public static function render_field_privacy_mode() {
			$cfg = self::get_settings();
			printf(
				'<label><input type="checkbox" id="wivg_privacy_mode" name="%1$s[privacy_mode]" value="1" %2$s /> %3$s</label>',
				esc_attr( self::OPT_SETTINGS ),
				checked( 1, (int) $cfg['privacy_mode'], false ),
				esc_html__( 'Use youtube-nocookie.com for YouTube embeds and DNT (Do Not Track) for Vimeo embeds', 'world-inspire-video-gallery-for-gumlet' )
			);
			echo '<p class="description">'
				. esc_html__( 'Recommended for GDPR privacy compliance. Prevents tracking cookies from being set before a visitor plays an embedded video.', 'world-inspire-video-gallery-for-gumlet' )
				. '</p>';
		}

		public static function render_field_brand_name() {
			$cfg = self::get_settings();
			printf(
				'<input type="text" id="wivg_brand_name" name="%1$s[brand_name]" value="%2$s" class="regular-text" />',
				esc_attr( self::OPT_SETTINGS ),
				esc_attr( $cfg['brand_name'] )
			);
		}

		public static function render_field_accent_color() {
			$cfg = self::get_settings();
			printf(
				'<input type="text" id="wivg_accent_color" name="%1$s[accent_color]" value="%2$s" class="small-text code" placeholder="#FF5500" />',
				esc_attr( self::OPT_SETTINGS ),
				esc_attr( $cfg['accent_color'] )
			);
			echo '<p class="description">' . esc_html__( 'Hex color used for highlights in the Video Gallery admin screen (e.g. #FF5500).', 'world-inspire-video-gallery-for-gumlet' ) . '</p>';
		}

		public static function render_field_default_slot() {
			$cfg = self::get_settings();
			printf(
				'<input type="number" id="wivg_default_slot" name="%1$s[default_slot]" value="%2$d" class="small-text" min="1" max="20" />',
				esc_attr( self::OPT_SETTINGS ),
				(int) $cfg['default_slot']
			);
			echo '<p class="description">' . esc_html__( '1-based gallery slot where newly inserted videos are placed by default (Slot #2 preserves Slot #1 as the main product photo).', 'world-inspire-video-gallery-for-gumlet' ) . '</p>';
		}

		public static function render_field_youtube_channel_url() {
			$cfg = self::get_settings();
			printf(
				'<input type="url" id="wivg_youtube_channel_url" name="%1$s[youtube_channel_url]" value="%2$s" class="regular-text" placeholder="https://www.youtube.com/@yourchannel" />',
				esc_attr( self::OPT_SETTINGS ),
				esc_attr( $cfg['youtube_channel_url'] )
			);
		}

		/**
		 * Render the WordPress Settings API options page (Settings -> External Video Gallery).
		 */
		public static function render_settings_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'world-inspire-video-gallery-for-gumlet' ) );
			}
			?>
			<div class="wrap">
				<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
				<p>
					<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'upload.php?page=' . self::ADMIN_SLUG ) ); ?>">
						<?php esc_html_e( 'Open Media Video Gallery Hub', 'world-inspire-video-gallery-for-gumlet' ); ?>
					</a>
				</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
					<?php
					settings_fields( self::SETTINGS_GROUP );
					do_settings_sections( self::SETTINGS_SLUG );
					submit_button( __( 'Save Settings', 'world-inspire-video-gallery-for-gumlet' ) );
					?>
				</form>
			</div>
			<?php
		}

		/**
		 * Call Gumlet REST v1 API using the configured API key.
		 *
		 * @param string     $method HTTP method.
		 * @param string     $path   Endpoint path starting with /.
		 * @param array|null $body   Optional JSON body payload.
		 * @return array|WP_Error
		 */
		public static function gumlet_request( $method, $path, $body = null ) {
			$api_key = self::get_gumlet_api_key();
			if ( '' === $api_key ) {
				return new WP_Error(
					'wivg_gumlet_key_missing',
					__( 'Gumlet API Key is not configured yet. Save it in Settings -> External Video Gallery.', 'world-inspire-video-gallery-for-gumlet' ),
					array( 'status' => 400 )
				);
			}
			$args = array(
				'method'  => strtoupper( (string) $method ),
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'User-Agent'    => 'World-Inspire-Video-Gallery/' . self::VERSION,
				),
			);
			if ( null !== $body ) {
				$args['body'] = wp_json_encode( $body );
			}
			$resp = wp_safe_remote_request( 'https://api.gumlet.com/v1' . $path, $args );
			if ( is_wp_error( $resp ) ) {
				return $resp;
			}
			$code = (int) wp_remote_retrieve_response_code( $resp );
			$raw  = (string) wp_remote_retrieve_body( $resp );
			$json = json_decode( $raw, true );
			if ( $code < 200 || $code >= 300 ) {
				$msg = is_array( $json ) && ! empty( $json['error']['message'] ) ? (string) $json['error']['message'] : substr( $raw, 0, 240 );
				return new WP_Error(
					'wivg_gumlet_api_error',
					sprintf(
						/* translators: 1: HTTP status code, 2: error message */
						__( 'Gumlet API HTTP %1$d: %2$s', 'world-inspire-video-gallery-for-gumlet' ),
						$code,
						$msg
					),
					array( 'status' => $code >= 400 ? $code : 502 )
				);
			}
			return is_array( $json ) ? $json : array();
		}

		public static function is_valid_hex24( $val ) {
			return is_string( $val ) && 1 === preg_match( '/^[a-f0-9]{24}$/i', trim( $val ) );
		}

		public static function is_valid_gumlet_mp4_url( $url ) {
			if ( ! is_string( $url ) || '' === $url ) {
				return false;
			}
			$parts = wp_parse_url( $url );
			if ( ! is_array( $parts ) || ( $parts['scheme'] ?? '' ) !== 'https' || strtolower( (string) ( $parts['host'] ?? '' ) ) !== self::GUMLET_HOST ) {
				return false;
			}
			if ( isset( $parts['query'] ) || isset( $parts['fragment'] ) || isset( $parts['user'] ) || isset( $parts['port'] ) ) {
				return false;
			}
			return 1 === preg_match( '#^/[a-f0-9]{24}/[a-f0-9]{24}/main\.mp4$#i', (string) ( $parts['path'] ?? '' ) );
		}

		public static function is_valid_youtube_id( $id ) {
			return is_string( $id ) && 1 === preg_match( '/^[A-Za-z0-9_-]{11}$/', trim( $id ) );
		}

		/**
		 * Retrieve external Gumlet URL from attachment meta.
		 *
		 * @param int $attachment_id Attachment post ID.
		 * @return string
		 */
		public static function get_attachment_external_url( $attachment_id ) {
			$url = (string) get_post_meta( (int) $attachment_id, self::META_EXT_URL, true );
			if ( self::is_valid_gumlet_mp4_url( $url ) ) {
				return $url;
			}
			return '';
		}

		/**
		 * Retrieve Gumlet asset ID from attachment meta.
		 *
		 * @param int $attachment_id Attachment post ID.
		 * @return string
		 */
		public static function get_attachment_gumlet_asset_id( $attachment_id ) {
			$aid = (string) get_post_meta( (int) $attachment_id, self::META_GUMLET_ASSET, true );
			if ( self::is_valid_hex24( $aid ) ) {
				return strtolower( $aid );
			}
			return '';
		}

		/**
		 * Universal parser for YouTube URLs/Shorts/IDs, Gumlet URLs/Asset IDs, Vimeo URLs, and WP MP4 attachments.
		 *
		 * @param string $raw_input          Input URL, ID, or "#attachment_id".
		 * @param string $fallback_workspace Optional 24-hex Gumlet workspace ID.
		 * @return array
		 */
		public static function parse_video_input( $raw_input, $fallback_workspace = '' ) {
			$raw        = trim( (string) $raw_input );
			$cfg        = self::get_settings();
			$ws         = self::is_valid_hex24( $fallback_workspace )
				? strtolower( trim( $fallback_workspace ) )
				: strtolower( (string) ( $cfg['gumlet_workspace_id'] ?? '' ) );
			$embed_host = ! empty( $cfg['privacy_mode'] ) ? 'www.youtube-nocookie.com' : 'www.youtube.com';

			if ( '' === $raw ) {
				return array(
					'ok'          => false,
					'source_type' => '',
					'error'       => __( 'Please provide a YouTube URL/ID, Gumlet URL/Asset ID, or WP Media attachment ID.', 'world-inspire-video-gallery-for-gumlet' ),
				);
			}

			// 1. Explicit WP Attachment ID (e.g. "#2097", "wp:2097", or pure digits)
			if ( preg_match( '/^(?:#|wp:|attachment:)?(\d+)$/i', $raw, $m ) ) {
				$att_id = (int) $m[1];
				$att    = function_exists( 'get_post' ) ? get_post( $att_id ) : null;
				$url    = function_exists( 'wp_get_attachment_url' ) ? (string) wp_get_attachment_url( $att_id ) : '';
				$ext    = self::get_attachment_external_url( $att_id );
				if ( $ext ) {
					$asset_id  = self::get_attachment_gumlet_asset_id( $att_id );
					$ext_parts = wp_parse_url( $ext );
					$ext_ws    = $ws;
					if ( is_array( $ext_parts ) && preg_match( '#^/([a-f0-9]{24})/#i', (string) ( $ext_parts['path'] ?? '' ), $wm ) ) {
						$ext_ws = strtolower( $wm[1] );
					}
					return array(
						'ok'            => true,
						'source_type'   => 'gumlet',
						'attachment_id' => $att_id,
						'asset_id'      => $asset_id,
						'workspace_id'  => $ext_ws,
						'url'           => $ext,
						'title'         => $att ? (string) $att->post_title : '',
						'error'         => '',
					);
				}
				if ( $att && 'attachment' === ( $att->post_type ?? '' ) ) {
					$mime = (string) ( $att->post_mime_type ?? '' );
					if ( '' !== $mime && 0 !== strpos( $mime, 'video/' ) ) {
						return array(
							'ok'          => false,
							'source_type' => '',
							'error'       => sprintf(
								/* translators: 1: attachment ID, 2: MIME type */
								__( 'Attachment #%1$d is not a video attachment (mime: %2$s).', 'world-inspire-video-gallery-for-gumlet' ),
								$att_id,
								$mime
							),
						);
					}
				}
				return array(
					'ok'            => true,
					'source_type'   => 'mp4',
					'attachment_id' => $att_id,
					'url'           => $url,
					'title'         => $att ? (string) $att->post_title : '',
					'error'         => '',
				);
			}

			// 2. Raw 24-hex Gumlet Asset ID
			if ( self::is_valid_hex24( $raw ) ) {
				if ( ! self::is_valid_hex24( $ws ) ) {
					return array(
						'ok'          => false,
						'source_type' => 'gumlet',
						'error'       => __( 'Gumlet Workspace ID is not configured. Save it in Settings -> External Video Gallery or paste the full https://video.gumlet.io/<workspace>/<asset>/main.mp4 URL.', 'world-inspire-video-gallery-for-gumlet' ),
					);
				}
				$asset_id = strtolower( $raw );
				return array(
					'ok'           => true,
					'source_type'  => 'gumlet',
					'asset_id'     => $asset_id,
					'workspace_id' => $ws,
					'url'          => sprintf( 'https://%s/%s/%s/main.mp4', self::GUMLET_HOST, $ws, $asset_id ),
					'poster_url'   => '',
					'error'        => '',
				);
			}

			// 3. Raw 11-char YouTube Video ID
			if ( self::is_valid_youtube_id( $raw ) ) {
				return array(
					'ok'          => true,
					'source_type' => 'youtube',
					'video_id'    => $raw,
					'is_shorts'   => false,
					'url'         => 'https://www.youtube.com/watch?v=' . $raw,
					'embed_url'   => 'https://' . $embed_host . '/embed/' . $raw,
					'poster_url'  => 'https://i.ytimg.com/vi/' . $raw . '/hqdefault.jpg',
					'error'       => '',
				);
			}

			$parts = wp_parse_url( $raw );
			if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
				return array(
					'ok'          => false,
					'source_type' => '',
					'error'       => __( 'Unrecognized video input. Paste a YouTube link/ID, Gumlet link/ID, or MP4 attachment.', 'world-inspire-video-gallery-for-gumlet' ),
				);
			}

			$host = strtolower( preg_replace( '/^www\./', '', (string) $parts['host'] ) );
			$path = (string) ( $parts['path'] ?? '' );

			// 4. YouTube URLs (youtube.com/watch?v=..., youtube.com/shorts/..., youtu.be/..., youtube.com/embed/...)
			if ( in_array( $host, array( 'youtube.com', 'm.youtube.com', 'youtu.be', 'youtube-nocookie.com' ), true ) ) {
				$vid       = '';
				$is_shorts = false;
				if ( 'youtu.be' === $host ) {
					if ( preg_match( '#^/([A-Za-z0-9_-]{11})#', $path, $m ) ) {
						$vid = $m[1];
					}
				} elseif ( preg_match( '#^/shorts/([A-Za-z0-9_-]{11})#', $path, $m ) ) {
					$vid       = $m[1];
					$is_shorts = true;
				} elseif ( preg_match( '#^/embed/([A-Za-z0-9_-]{11})#', $path, $m ) ) {
					$vid = $m[1];
				} elseif ( ! empty( $parts['query'] ) ) {
					parse_str( (string) $parts['query'], $q );
					if ( ! empty( $q['v'] ) && self::is_valid_youtube_id( $q['v'] ) ) {
						$vid = $q['v'];
					}
				}
				if ( self::is_valid_youtube_id( $vid ) ) {
					return array(
						'ok'          => true,
						'source_type' => 'youtube',
						'video_id'    => $vid,
						'is_shorts'   => $is_shorts,
						'url'         => 'https://www.youtube.com/watch?v=' . $vid,
						'shorts_url'  => 'https://www.youtube.com/shorts/' . $vid,
						'embed_url'   => 'https://' . $embed_host . '/embed/' . $vid,
						'poster_url'  => 'https://i.ytimg.com/vi/' . $vid . '/hqdefault.jpg',
						'error'       => '',
					);
				}
				return array(
					'ok'          => false,
					'source_type' => 'youtube',
					'error'       => __( 'Could not extract a valid 11-character YouTube Video ID from URL.', 'world-inspire-video-gallery-for-gumlet' ),
				);
			}

			// 5. Gumlet CDN / Embed / Dashboard URLs
			if ( self::GUMLET_HOST === $host ) {
				if ( preg_match( '#^/([a-f0-9]{24})/([a-f0-9]{24})(?:/.*)?$#i', $path, $m ) ) {
					$w = strtolower( $m[1] );
					$a = strtolower( $m[2] );
					return array(
						'ok'           => true,
						'source_type'  => 'gumlet',
						'workspace_id' => $w,
						'asset_id'     => $a,
						'url'          => sprintf( 'https://%s/%s/%s/main.mp4', self::GUMLET_HOST, $w, $a ),
						'error'        => '',
					);
				}
				return array(
					'ok'          => false,
					'source_type' => 'gumlet',
					'error'       => __( 'Gumlet CDN URL must match https://video.gumlet.io/<workspace>/<asset>/main.mp4.', 'world-inspire-video-gallery-for-gumlet' ),
				);
			}

			if ( in_array( $host, array( 'play.gumlet.io', 'app.gumlet.com' ), true ) ) {
				if ( preg_match_all( '/([a-f0-9]{24})/i', $path, $matches ) && ! empty( $matches[1] ) ) {
					$ids       = array_map( 'strtolower', $matches[1] );
					$asset_id  = end( $ids );
					$parsed_ws = count( $ids ) >= 2 ? $ids[0] : $ws;
					if ( ! self::is_valid_hex24( $parsed_ws ) ) {
						return array(
							'ok'          => false,
							'source_type' => 'gumlet',
							'error'       => __( 'Gumlet Workspace ID is not configured. Save it in Settings -> External Video Gallery or paste the full https://video.gumlet.io/<workspace>/<asset>/main.mp4 URL.', 'world-inspire-video-gallery-for-gumlet' ),
						);
					}
					return array(
						'ok'           => true,
						'source_type'  => 'gumlet',
						'workspace_id' => $parsed_ws,
						'asset_id'     => $asset_id,
						'url'          => sprintf( 'https://%s/%s/%s/main.mp4', self::GUMLET_HOST, $parsed_ws, $asset_id ),
						'error'        => '',
					);
				}
			}

			// 6. Vimeo URLs
			if ( 'vimeo.com' === $host || 'player.vimeo.com' === $host ) {
				if ( preg_match( '#/(\d+)#', $path, $m ) ) {
					return array(
						'ok'          => true,
						'source_type' => 'vimeo',
						'video_id'    => $m[1],
						'url'         => 'https://vimeo.com/' . $m[1],
						'error'       => '',
					);
				}
			}

			// 7. Direct MP4 URL (on current site or external HTTPS MP4)
			if ( preg_match( '/\.mp4$/i', $path ) && ( $parts['scheme'] ?? '' ) === 'https' ) {
				$att_id = function_exists( 'attachment_url_to_postid' ) ? (int) attachment_url_to_postid( $raw ) : 0;
				return array(
					'ok'            => true,
					'source_type'   => 'mp4',
					'attachment_id' => $att_id,
					'url'           => esc_url_raw( $raw ),
					'error'         => '',
				);
			}

			return array(
				'ok'          => false,
				'source_type' => '',
				'error'       => __( 'Unsupported video host. Accepted: YouTube (watch/Shorts/ID), Gumlet (video.gumlet.io/play.gumlet.io/ID), Vimeo, or HTTPS .mp4.', 'world-inspire-video-gallery-for-gumlet' ),
			);
		}

		/**
		 * Map Gumlet external video URLs on wp_get_attachment_url.
		 */
		public static function filter_attachment_url( $url, $attachment_id ) {
			$ext = self::get_attachment_external_url( $attachment_id );
			return '' !== $ext ? $ext : $url;
		}

		/**
		 * Ensure file-less Gumlet video attachments pass wp_attachment_is('video', $id).
		 */
		public static function filter_attached_file( $file, $attachment_id ) {
			if ( ! empty( $file ) ) {
				return $file;
			}
			$ext = self::get_attachment_external_url( $attachment_id );
			if ( '' !== $ext ) {
				$aid = self::get_attachment_gumlet_asset_id( $attachment_id ) ?: ( 'att-' . (int) $attachment_id );
				return 'gumlet-cdn/' . $aid . '/main.mp4';
			}
			return $file;
		}

		/**
		 * Populate filename and poster thumbnail in WP Media modal for file-less Gumlet attachments.
		 */
		public static function filter_prepare_attachment_js( $response, $attachment ) {
			if ( ! is_array( $response ) || ! is_object( $attachment ) ) {
				return $response;
			}
			$ext = self::get_attachment_external_url( $attachment->ID );
			if ( '' === $ext ) {
				return $response;
			}
			if ( empty( $response['filename'] ) ) {
				$slug                 = ! empty( $attachment->post_name ) ? $attachment->post_name : ( 'gumlet-' . $attachment->ID );
				$response['filename'] = $slug . '.mp4';
			}
			$poster_id = (int) get_post_meta( $attachment->ID, self::META_POSTER_ID, true );
			if ( $poster_id <= 0 && function_exists( 'get_post_thumbnail_id' ) ) {
				$poster_id = (int) get_post_thumbnail_id( $attachment->ID );
			}
			if ( $poster_id > 0 && function_exists( 'wp_get_attachment_image_src' ) ) {
				$img = wp_get_attachment_image_src( $poster_id, 'medium' );
				if ( is_array( $img ) && ! empty( $img[0] ) ) {
					$response['image'] = array(
						'src'    => $img[0],
						'width'  => (int) ( $img[1] ?? 320 ),
						'height' => (int) ( $img[2] ?? 320 ),
					);
					$response['thumb'] = $response['image'];
				}
			}
			return $response;
		}

		/**
		 * Render inline video player on standard WooCommerce themes
		 * when WoodMart's native gallery video module is not present.
		 */
		public static function filter_wc_standard_gallery_slide( $html, $attachment_id ) {
			if ( 'woocommerce_standard' !== self::detect_adapter() ) {
				return $html;
			}
			global $product;
			$pid = ( is_object( $product ) && method_exists( $product, 'get_id' ) )
				? (int) $product->get_id()
				: ( function_exists( 'get_the_ID' ) ? (int) get_the_ID() : 0 );
			if ( $pid <= 0 ) {
				return $html;
			}
			$wm_meta = get_post_meta( $pid, 'woodmart_wc_video_gallery', true );
			if ( ! is_array( $wm_meta ) ) {
				$wm_meta = get_post_meta( $pid, self::META_STD_WC_VIDEOS, true );
			}
			if ( ! is_array( $wm_meta ) ) {
				return $html;
			}
			$aid   = (int) $attachment_id;
			$entry = $wm_meta[ $aid ] ?? ( $wm_meta[ (string) $aid ] ?? null );
			if ( ! self::is_active_woodmart_video_entry( $entry ) ) {
				return $html;
			}

			$cfg        = self::get_settings();
			$privacy    = ! empty( $cfg['privacy_mode'] );
			$vtype      = strtolower( (string) ( $entry['video_type'] ?? 'mp4' ) );
			$autoplay   = '1' === (string) ( $entry['autoplay'] ?? '1' );
			$muted      = $autoplay || 'mute' === (string) ( $entry['audio_status'] ?? 'mute' );
			$poster_url = function_exists( 'wp_get_attachment_image_url' )
				? (string) wp_get_attachment_image_url( $aid, 'woocommerce_single' )
				: '';

			$player_html = '';
			if ( 'youtube' === $vtype ) {
				$parsed = self::parse_video_input( (string) ( $entry['youtube_url'] ?? '' ) );
				if ( ! empty( $parsed['ok'] ) && ! empty( $parsed['video_id'] ) ) {
					$vid         = rawurlencode( $parsed['video_id'] );
					$yt_host     = $privacy ? 'www.youtube-nocookie.com' : 'www.youtube.com';
					$qs          = 'rel=0&playsinline=1&loop=1&playlist=' . $vid . ( $autoplay ? '&autoplay=1&mute=1' : '' );
					$player_html = '<div class="wivg-wc-video-wrap wivg-wc-youtube">'
						. '<iframe class="wivg-wc-gallery-iframe" src="' . esc_url( 'https://' . $yt_host . '/embed/' . $vid . '?' . $qs ) . '" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen loading="lazy"></iframe>'
						. '</div>';
				}
			} elseif ( 'vimeo' === $vtype ) {
				$parsed = self::parse_video_input( (string) ( $entry['vimeo_url'] ?? '' ) );
				if ( ! empty( $parsed['ok'] ) && ! empty( $parsed['video_id'] ) ) {
					$vid         = rawurlencode( $parsed['video_id'] );
					$qs          = 'loop=1&autopause=0' . ( $privacy ? '&dnt=1' : '' ) . ( $autoplay ? '&autoplay=1&muted=1' : '' );
					$player_html = '<div class="wivg-wc-video-wrap wivg-wc-vimeo">'
						. '<iframe class="wivg-wc-gallery-iframe" src="' . esc_url( 'https://player.vimeo.com/video/' . $vid . '?' . $qs ) . '" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy"></iframe>'
						. '</div>';
				}
			} else {
				$vid_att_id = (int) ( $entry['upload_video_id'] ?? 0 );
				$vid_url    = (string) ( $entry['upload_video_url'] ?? '' );
				if ( $vid_att_id > 0 && '' === $vid_url && function_exists( 'wp_get_attachment_url' ) ) {
					$vid_url = (string) wp_get_attachment_url( $vid_att_id );
				}
				if ( '' !== $vid_url ) {
					$attrs = 'class="wivg-wc-gallery-video" src="' . esc_url( $vid_url ) . '" playsinline loop controls';
					if ( $poster_url ) {
						$attrs .= ' poster="' . esc_url( $poster_url ) . '"';
					}
					if ( $autoplay ) {
						$attrs .= ' autoplay';
					}
					if ( $muted ) {
						$attrs .= ' muted';
					}
					$player_html = '<div class="wivg-wc-video-wrap wivg-wc-mp4">'
						. '<video ' . $attrs . '></video>'
						. '</div>';
				}
			}

			if ( '' === $player_html ) {
				return $html;
			}
			if ( function_exists( 'wp_enqueue_style' ) ) {
				wp_enqueue_style( 'wivg-frontend' );
			}
			if ( preg_match( '#^(<div[^>]*woocommerce-product-gallery__image[^>]*>).*(</div>)\s*$#is', (string) $html, $m ) ) {
				$open_div = str_replace( 'woocommerce-product-gallery__image', 'woocommerce-product-gallery__image wivg-has-video', $m[1] );
				return $open_div . $player_html . $m[2];
			}
			return $player_html;
		}

		public static function add_media_column( $columns ) {
			if ( is_array( $columns ) && ! isset( $columns['wivg_video_host'] ) ) {
				$columns['wivg_video_host'] = esc_html__( 'Video Host', 'world-inspire-video-gallery-for-gumlet' );
			}
			return $columns;
		}

		public static function render_media_column( $column_name, $post_id ) {
			if ( 'wivg_video_host' !== $column_name ) {
				return;
			}
			$mime = (string) get_post_mime_type( $post_id );
			if ( 0 !== strpos( $mime, 'video/' ) ) {
				echo '<span class="wivg-badge-muted">&mdash;</span>';
				return;
			}
			$ext = self::get_attachment_external_url( $post_id );
			if ( '' !== $ext ) {
				echo '<span class="wivg-badge-ok">'
					. esc_html__( 'Gumlet CDN', 'world-inspire-video-gallery-for-gumlet' )
					. '</span>';
			} else {
				$file = get_attached_file( $post_id );
				$mb   = ( $file && false === strpos( $file, 'gumlet-cdn/' ) && file_exists( $file ) && is_readable( $file ) )
					? number_format( filesize( $file ) / 1048576, 1 ) . ' MB'
					: __( 'Local MP4', 'world-inspire-video-gallery-for-gumlet' );
				echo '<a class="wivg-badge-warn" href="' . esc_url( admin_url( 'upload.php?page=' . self::ADMIN_SLUG . '&tab=inventory' ) ) . '">'
					. esc_html(
						sprintf(
							/* translators: %s: file size or label */
							__( 'Local (%s)', 'world-inspire-video-gallery-for-gumlet' ),
							$mb
						)
					)
					. '</a>';
			}
		}

		/**
		 * Find existing WP attachment bound to a Gumlet asset ID.
		 *
		 * @param string $asset_id 24-hex Gumlet asset ID.
		 * @return int
		 */
		public static function find_gumlet_attachment( $asset_id ) {
			$asset_id = strtolower( trim( (string) $asset_id ) );
			if ( ! self::is_valid_hex24( $asset_id ) ) {
				return 0;
			}
			$ids = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'any',
					'posts_per_page' => 2,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded lookup by unique asset ID.
					'meta_key'       => self::META_GUMLET_ASSET,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded lookup by unique asset ID.
					'meta_value'     => $asset_id,
				)
			);
			if ( ! empty( $ids ) ) {
				return (int) $ids[0];
			}
			return 0;
		}

		/**
		 * Create or update a file-less Gumlet video/mp4 attachment in WordPress.
		 */
		public static function upsert_gumlet_attachment( $asset_id, $url, $title, $width = 0, $height = 0, $poster_id = 0, $target_attachment_id = 0 ) {
			$asset_id = strtolower( trim( (string) $asset_id ) );
			if ( ! self::is_valid_hex24( $asset_id ) || ! self::is_valid_gumlet_mp4_url( $url ) ) {
				return new WP_Error(
					'wivg_invalid_gumlet',
					__( 'Valid 24-hex Gumlet asset_id and https://video.gumlet.io/.../main.mp4 URL are required.', 'world-inspire-video-gallery-for-gumlet' ),
					array( 'status' => 422 )
				);
			}
			if ( false === stripos( $url, '/' . $asset_id . '/main.mp4' ) ) {
				return new WP_Error(
					'wivg_gumlet_mismatch',
					__( 'URL does not match the provided Gumlet asset_id.', 'world-inspire-video-gallery-for-gumlet' ),
					array( 'status' => 422 )
				);
			}

			$id      = self::find_gumlet_attachment( $asset_id );
			$created = false;
			if ( 0 === $id && $target_attachment_id > 0 ) {
				$target = get_post( (int) $target_attachment_id );
				if ( $target && 'attachment' === $target->post_type ) {
					$id = (int) $target_attachment_id;
				}
			}

			if ( 0 === $id ) {
				$id = wp_insert_attachment(
					array(
						'post_title'     => sanitize_text_field( $title ?: ( 'Gumlet Video ' . substr( $asset_id, 0, 8 ) ) ),
						'post_mime_type' => 'video/mp4',
						'post_status'    => 'inherit',
						'guid'           => esc_url_raw( $url ),
					),
					'gumlet-cdn/' . $asset_id . '/main.mp4',
					0,
					true
				);
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				$created = true;
			} elseif ( '' !== trim( (string) $title ) ) {
				wp_update_post(
					array(
						'ID'         => $id,
						'post_title' => sanitize_text_field( $title ),
					)
				);
			}

			update_post_meta( $id, self::META_GUMLET_ASSET, $asset_id );
			update_post_meta( $id, self::META_EXT_URL, esc_url_raw( $url ) );
			update_post_meta( $id, '_wp_attached_file', 'gumlet-cdn/' . $asset_id . '/main.mp4' );

			if ( $poster_id > 0 ) {
				update_post_meta( $id, self::META_POSTER_ID, (int) $poster_id );
				set_post_thumbnail( $id, (int) $poster_id );
			}
			if ( $width > 0 && $height > 0 ) {
				wp_update_attachment_metadata(
					$id,
					array(
						'width'      => (int) $width,
						'height'     => (int) $height,
						'mime_type'  => 'video/mp4',
						'fileformat' => 'mp4',
					)
				);
			}

			return array(
				'ok'          => true,
				'source_type' => 'gumlet',
				'id'          => (int) $id,
				'asset_id'    => $asset_id,
				'url'         => wp_get_attachment_url( $id ),
				'created'     => $created,
			);
		}

		/**
		 * Find or sideload a YouTube video's high-resolution thumbnail as a WP image attachment
		 * so products with only 1 photo can have a dedicated Slot #2 video slide without overwriting Slot #1.
		 */
		public static function ensure_youtube_poster_attachment( $video_id, $custom_title = '' ) {
			if ( ! self::is_valid_youtube_id( $video_id ) ) {
				return new WP_Error( 'wivg_invalid_yt', __( 'Invalid YouTube Video ID.', 'world-inspire-video-gallery-for-gumlet' ), array( 'status' => 422 ) );
			}
			$existing = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_mime_type' => 'image',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Bounded lookup by unique YouTube video ID.
					'meta_key'       => self::META_YT_POSTER_FOR,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Bounded lookup by unique YouTube video ID.
					'meta_value'     => $video_id,
				)
			);
			if ( ! empty( $existing ) ) {
				$aid = (int) $existing[0];
				return array(
					'ok'                   => true,
					'source_type'          => 'youtube',
					'video_id'             => $video_id,
					'id'                   => $aid,
					'poster_attachment_id' => $aid,
					'url'                  => (string) wp_get_attachment_url( $aid ),
					'poster_url'           => (string) wp_get_attachment_url( $aid ),
					'created'              => false,
				);
			}

			$candidates = array(
				'https://i.ytimg.com/vi/' . rawurlencode( $video_id ) . '/maxresdefault.jpg',
				'https://i.ytimg.com/vi/' . rawurlencode( $video_id ) . '/hqdefault.jpg',
			);
			$img_bytes  = '';
			foreach ( $candidates as $candidate_url ) {
				$resp = wp_safe_remote_get( $candidate_url, array( 'timeout' => 12 ) );
				if ( ! is_wp_error( $resp ) && 200 === (int) wp_remote_retrieve_response_code( $resp ) ) {
					$body = (string) wp_remote_retrieve_body( $resp );
					if ( strlen( $body ) > 1000 ) {
						$img_bytes = $body;
						break;
					}
				}
			}
			if ( '' === $img_bytes ) {
				return new WP_Error(
					'wivg_yt_thumb_failed',
					sprintf(
						/* translators: %s: YouTube video ID */
						__( 'Failed to download YouTube thumbnail for %s.', 'world-inspire-video-gallery-for-gumlet' ),
						$video_id
					),
					array( 'status' => 502 )
				);
			}

			$filename = sanitize_file_name( 'yt-poster-' . $video_id . '.jpg' );
			$upload   = wp_upload_bits( $filename, null, $img_bytes );
			if ( ! empty( $upload['error'] ) ) {
				return new WP_Error( 'wivg_upload_bits_failed', (string) $upload['error'], array( 'status' => 500 ) );
			}

			require_once ABSPATH . 'wp-admin/includes/image.php';
			$title  = sanitize_text_field( $custom_title ?: ( 'Product Video Preview (' . $video_id . ')' ) );
			$att_id = wp_insert_attachment(
				array(
					'post_mime_type' => 'image/jpeg',
					'post_title'     => $title,
					'post_content'   => '',
					'post_status'    => 'inherit',
				),
				$upload['file']
			);
			if ( is_wp_error( $att_id ) ) {
				return $att_id;
			}
			$attach_data = wp_generate_attachment_metadata( $att_id, $upload['file'] );
			wp_update_attachment_metadata( $att_id, $attach_data );
			update_post_meta( $att_id, self::META_YT_POSTER_FOR, $video_id );
			update_post_meta( $att_id, '_wp_attachment_image_alt', $title );

			return array(
				'ok'                   => true,
				'source_type'          => 'youtube',
				'video_id'             => $video_id,
				'id'                   => (int) $att_id,
				'poster_attachment_id' => (int) $att_id,
				'url'                  => (string) wp_get_attachment_url( $att_id ),
				'poster_url'           => (string) wp_get_attachment_url( $att_id ),
				'created'              => true,
			);
		}

		/**
		 * Check whether a WoodMart video settings entry actually has an active video (not an empty default stub).
		 */
		public static function is_active_woodmart_video_entry( $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['video_type'] ) ) {
				return false;
			}
			$vtype = strtolower( (string) $entry['video_type'] );
			if ( 'youtube' === $vtype ) {
				$yt = trim( (string) ( $entry['youtube_url'] ?? '' ) );
				return '' !== $yt && ( false !== stripos( $yt, 'youtube.com/' ) || false !== stripos( $yt, 'youtu.be/' ) || false !== stripos( $yt, 'youtube-nocookie.com/' ) );
			}
			if ( 'vimeo' === $vtype ) {
				$vm = trim( (string) ( $entry['vimeo_url'] ?? '' ) );
				return '' !== $vm && false !== stripos( $vm, 'vimeo.com/' );
			}
			if ( 'mp4' === $vtype || 'upload' === $vtype || 'custom' === $vtype ) {
				$uid = (int) ( $entry['upload_video_id'] ?? 0 );
				$url = trim( (string) ( $entry['upload_video_url'] ?? ( $entry['custom_url'] ?? '' ) ) );
				return $uid > 0 || '' !== $url;
			}
			return false;
		}

		/**
		 * Resolve product post object by ID or slug across WooCommerce (`product`) or SureCart (`sc_product`).
		 */
		public static function resolve_product( $product_ref ) {
			$adapter   = self::detect_adapter();
			$post_type = ( 'surecart' === $adapter ) ? 'sc_product' : 'product';

			if ( is_numeric( $product_ref ) && (int) $product_ref > 0 ) {
				$p = get_post( (int) $product_ref );
				if ( $p && $p->post_type === $post_type ) {
					return $p;
				}
			}
			$slug = sanitize_title( (string) $product_ref );
			if ( '' !== $slug ) {
				$posts = get_posts(
					array(
						'post_type'      => $post_type,
						'name'           => $slug,
						'post_status'    => 'any',
						'posts_per_page' => 1,
						'no_found_rows'  => true,
					)
				);
				if ( ! empty( $posts ) ) {
					return $posts[0];
				}
			}
			return null;
		}

		/**
		 * Inspect a single product's ordered gallery slots (1-based) and bound videos.
		 */
		public static function inspect_product( $post ) {
			if ( is_numeric( $post ) ) {
				$post = get_post( (int) $post );
			}
			if ( ! $post || ! is_object( $post ) ) {
				return null;
			}
			$adapter = self::detect_adapter();
			$pid     = (int) $post->ID;

			if ( 'surecart' === $adapter ) {
				$mp  = json_decode( wp_json_encode( get_post_meta( $pid, 'product', true ) ), true );
				$gal = $mp['metadata']['gallery_ids'] ?? array();
				if ( is_string( $gal ) ) {
					$gal = json_decode( $gal, true );
				}
				if ( ! is_array( $gal ) ) {
					$gal = array();
				}
				if ( empty( $gal ) ) {
					$fallback_thumb = (int) get_post_thumbnail_id( $pid );
					if ( $fallback_thumb > 0 ) {
						$gal = array( array( 'id' => $fallback_thumb ) );
					}
				}
				$slots       = array();
				$active_vids = array();
				$slot_num    = 0;
				foreach ( $gal as $item ) {
					$aid = is_array( $item ) ? (int) ( $item['id'] ?? 0 ) : (int) $item;
					if ( $aid <= 0 ) {
						continue;
					}
					$slot_num++;
					$mime    = (string) get_post_mime_type( $aid );
					$is_vid  = ( 0 === strpos( $mime, 'video/' ) );
					$ext_url = self::get_attachment_external_url( $aid );
					$url     = $ext_url ?: (string) wp_get_attachment_url( $aid );
					$thumb   = $is_vid
						? (string) wp_get_attachment_image_url( (int) get_post_meta( $aid, self::META_POSTER_ID, true ) ?: (int) get_post_thumbnail_id( $aid ), 'thumbnail' )
						: (string) wp_get_attachment_image_url( $aid, 'thumbnail' );

					$video_info = null;
					if ( $is_vid ) {
						$video_info    = array(
							'has_video'       => true,
							'source_type'     => '' !== $ext_url ? 'gumlet' : 'mp4',
							'video_url'       => $url,
							'attachment_id'   => $aid,
							'gumlet_asset_id' => self::get_attachment_gumlet_asset_id( $aid ),
							'autoplay'        => true,
							'audio_status'    => 'mute',
						);
						$active_vids[] = array_merge(
							array(
								'slot'                => $slot_num,
								'slide_attachment_id' => $aid,
							),
							$video_info
						);
					}
					$slots[] = array(
						'slot'          => $slot_num,
						'role'          => 1 === $slot_num ? 'main_thumb' : 'gallery',
						'attachment_id' => $aid,
						'mime_type'     => $mime,
						'thumb_url'     => $thumb ?: $url,
						'full_url'      => $url,
						'has_video'     => (bool) $video_info,
						'video'         => $video_info,
					);
				}

				$sku   = (string) ( $mp['metadata']['sku'] ?? ( $mp['sku'] ?? get_post_meta( $pid, 'sku', true ) ) );
				$sc_id = (string) ( get_post_meta( $pid, 'sc_id', true ) ?: ( $mp['id'] ?? '' ) );
				return array(
					'id'                  => $pid,
					'sku'                 => $sku,
					'title'               => (string) $post->post_title,
					'slug'                => (string) $post->post_name,
					'status'              => (string) $post->post_status,
					'url'                 => (string) get_permalink( $pid ),
					'edit_url'            => $sc_id ? admin_url( 'admin.php?page=sc-products&action=edit&id=' . rawurlencode( $sc_id ) ) : admin_url( 'admin.php?page=sc-products' ),
					'adapter'             => $adapter,
					'slot_count'          => count( $slots ),
					'video_count'         => count( $active_vids ),
					'has_video'           => ! empty( $active_vids ),
					'slots'               => $slots,
					'videos'              => $active_vids,
					'empty_stub_count'    => 0,
					'orphaned_slot_count' => 0,
				);
			}

			// WooCommerce + WoodMart or WooCommerce Standard
			$thumb_id    = (int) get_post_thumbnail_id( $pid );
			$gal_raw     = (string) get_post_meta( $pid, '_product_image_gallery', true );
			$gal_ids     = array_values( array_filter( array_map( 'intval', explode( ',', $gal_raw ) ) ) );
			$ordered_ids = array();
			if ( $thumb_id > 0 ) {
				$ordered_ids[] = $thumb_id;
			}
			foreach ( $gal_ids as $gid ) {
				if ( $gid > 0 && ! in_array( $gid, $ordered_ids, true ) ) {
					$ordered_ids[] = $gid;
				}
			}

			$wm_meta = get_post_meta( $pid, 'woodmart_wc_video_gallery', true );
			if ( ! is_array( $wm_meta ) ) {
				$wm_meta = array();
			}

			$empty_stub_count    = 0;
			$orphaned_slot_count = 0;
			foreach ( $wm_meta as $k => $entry ) {
				$kid = (int) $k;
				if ( ! self::is_active_woodmart_video_entry( $entry ) ) {
					$empty_stub_count++;
				} elseif ( ! in_array( $kid, $ordered_ids, true ) ) {
					$orphaned_slot_count++;
				}
			}

			$slots       = array();
			$active_vids = array();
			$slot_num    = 0;

			foreach ( $ordered_ids as $aid ) {
				$slot_num++;
				$thumb_url  = (string) wp_get_attachment_image_url( $aid, 'woocommerce_gallery_thumbnail' );
				$full_url   = (string) wp_get_attachment_image_url( $aid, 'full' );
				$entry      = $wm_meta[ $aid ] ?? ( $wm_meta[ (string) $aid ] ?? null );
				$video_info = null;

				if ( self::is_active_woodmart_video_entry( $entry ) ) {
					$vtype = strtolower( (string) ( $entry['video_type'] ?? 'mp4' ) );
					if ( 'youtube' === $vtype ) {
						$yt_url     = (string) ( $entry['youtube_url'] ?? '' );
						$parsed     = self::parse_video_input( $yt_url );
						$video_info = array(
							'has_video'     => true,
							'source_type'   => 'youtube',
							'video_type'    => 'youtube',
							'video_id'      => $parsed['video_id'] ?? '',
							'video_url'     => $yt_url,
							'embed_url'     => $parsed['embed_url'] ?? '',
							'poster_url'    => $parsed['poster_url'] ?? $thumb_url,
							'autoplay'      => '1' === (string) ( $entry['autoplay'] ?? '0' ),
							'audio_status'  => (string) ( $entry['audio_status'] ?? 'mute' ),
							'video_size'    => (string) ( $entry['video_size'] ?? 'contain' ),
							'video_control' => (string) ( $entry['video_control'] ?? 'theme' ),
						);
					} elseif ( 'vimeo' === $vtype ) {
						$vm_url     = (string) ( $entry['vimeo_url'] ?? '' );
						$video_info = array(
							'has_video'     => true,
							'source_type'   => 'vimeo',
							'video_type'    => 'vimeo',
							'video_url'     => $vm_url,
							'autoplay'      => '1' === (string) ( $entry['autoplay'] ?? '0' ),
							'audio_status'  => (string) ( $entry['audio_status'] ?? 'mute' ),
							'video_size'    => (string) ( $entry['video_size'] ?? 'contain' ),
							'video_control' => (string) ( $entry['video_control'] ?? 'theme' ),
						);
					} else {
						$vid_att_id = (int) ( $entry['upload_video_id'] ?? 0 );
						$vid_url    = (string) ( $entry['upload_video_url'] ?? '' );
						if ( $vid_att_id > 0 && '' === $vid_url ) {
							$vid_url = (string) wp_get_attachment_url( $vid_att_id );
						}
						$is_gumlet  = self::is_valid_gumlet_mp4_url( $vid_url ) || ( $vid_att_id > 0 && '' !== self::get_attachment_external_url( $vid_att_id ) );
						$video_info = array(
							'has_video'           => true,
							'source_type'         => $is_gumlet ? 'gumlet' : 'mp4',
							'video_type'          => 'mp4',
							'video_attachment_id' => $vid_att_id,
							'gumlet_asset_id'     => $vid_att_id > 0 ? self::get_attachment_gumlet_asset_id( $vid_att_id ) : '',
							'video_url'           => $vid_url,
							'autoplay'            => '1' === (string) ( $entry['autoplay'] ?? '0' ),
							'audio_status'        => (string) ( $entry['audio_status'] ?? 'mute' ),
							'video_size'          => (string) ( $entry['video_size'] ?? 'contain' ),
							'video_control'       => (string) ( $entry['video_control'] ?? 'theme' ),
						);
					}
					$active_vids[] = array_merge(
						array(
							'slot'                => $slot_num,
							'slide_attachment_id' => $aid,
						),
						$video_info
					);
				}

				$slots[] = array(
					'slot'          => $slot_num,
					'role'          => 1 === $slot_num ? 'main_thumb' : 'gallery',
					'attachment_id' => $aid,
					'mime_type'     => (string) get_post_mime_type( $aid ),
					'thumb_url'     => $thumb_url ?: $full_url,
					'full_url'      => $full_url,
					'has_video'     => null !== $video_info,
					'video'         => $video_info,
				);
			}

			$sku = (string) get_post_meta( $pid, '_sku', true );
			return array(
				'id'                  => $pid,
				'sku'                 => $sku,
				'title'               => (string) $post->post_title,
				'slug'                => (string) $post->post_name,
				'status'              => (string) $post->post_status,
				'url'                 => (string) get_permalink( $pid ),
				'edit_url'            => (string) get_edit_post_link( $pid, 'raw' ),
				'adapter'             => $adapter,
				'thumb_id'            => $thumb_id,
				'gallery_ids'         => $gal_ids,
				'slot_count'          => count( $slots ),
				'video_count'         => count( $active_vids ),
				'has_video'           => ! empty( $active_vids ),
				'slots'               => $slots,
				'videos'              => $active_vids,
				'empty_stub_count'    => $empty_stub_count,
				'orphaned_slot_count' => $orphaned_slot_count,
			);
		}

		/**
		 * Build full store video & product gallery audit report.
		 * Never includes API keys or personal user data.
		 */
		public static function build_audit_report() {
			$cfg       = self::get_settings();
			$adapter   = $cfg['adapter'];
			$post_type = ( 'surecart' === $adapter ) ? 'sc_product' : 'product';

			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => array( 'publish', 'draft', 'private' ),
					'posts_per_page' => -1,
					'orderby'        => 'ID',
					'order'          => 'DESC',
					'no_found_rows'  => true,
				)
			);

			$products             = array();
			$products_with_video  = 0;
			$products_missing     = 0;
			$total_video_bindings = 0;
			$youtube_bindings     = 0;
			$gumlet_bindings      = 0;
			$mp4_bindings         = 0;
			$total_empty_stubs    = 0;
			$total_orphaned_slots = 0;
			$attached_video_ids   = array();

			foreach ( $posts as $p ) {
				$item = self::inspect_product( $p );
				if ( ! $item ) {
					continue;
				}
				$products[] = $item;
				if ( 'publish' === $item['status'] ) {
					if ( $item['has_video'] ) {
						$products_with_video++;
					} else {
						$products_missing++;
					}
				}
				$total_empty_stubs    += (int) $item['empty_stub_count'];
				$total_orphaned_slots += (int) $item['orphaned_slot_count'];
				foreach ( $item['videos'] as $v ) {
					$total_video_bindings++;
					if ( 'youtube' === ( $v['source_type'] ?? '' ) ) {
						$youtube_bindings++;
					} elseif ( 'gumlet' === ( $v['source_type'] ?? '' ) ) {
						$gumlet_bindings++;
					} else {
						$mp4_bindings++;
					}
					$vaid = (int) ( $v['video_attachment_id'] ?? ( $v['attachment_id'] ?? 0 ) );
					if ( $vaid > 0 ) {
						$attached_video_ids[ $vaid ] = $item['id'];
					}
				}
			}

			$video_posts = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_mime_type' => 'video',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'orderby'        => 'ID',
					'order'          => 'DESC',
					'no_found_rows'  => true,
				)
			);

			$gumlet_assets     = array();
			$local_mp4_assets  = array();
			$total_local_bytes = 0;

			foreach ( $video_posts as $vp ) {
				$vid     = (int) $vp->ID;
				$ext_url = self::get_attachment_external_url( $vid );
				$meta    = wp_get_attachment_metadata( $vid );
				$width   = is_array( $meta ) ? (int) ( $meta['width'] ?? 0 ) : 0;
				$height  = is_array( $meta ) ? (int) ( $meta['height'] ?? 0 ) : 0;
				$length  = is_array( $meta ) ? (string) ( $meta['length_formatted'] ?? ( $meta['length'] ?? '' ) ) : '';

				if ( '' !== $ext_url ) {
					$gumlet_assets[] = array(
						'id'                => $vid,
						'title'             => (string) $vp->post_title,
						'asset_id'          => self::get_attachment_gumlet_asset_id( $vid ),
						'url'               => $ext_url,
						'width'             => $width,
						'height'            => $height,
						'duration'          => $length,
						'linked_product_id' => $attached_video_ids[ $vid ] ?? ( (int) $vp->post_parent ?: 0 ),
						'date'              => (string) $vp->post_date,
					);
				} else {
					$file        = get_attached_file( $vid );
					$real_exists = $file && false === strpos( $file, 'gumlet-cdn/' ) && file_exists( $file ) && is_readable( $file );
					$size        = $real_exists ? (int) filesize( $file ) : 0;
					$total_local_bytes += $size;
					$local_mp4_assets[] = array(
						'id'                => $vid,
						'title'             => (string) $vp->post_title,
						'filename'          => $file ? basename( $file ) : '',
						'file_ready'        => (bool) $real_exists,
						'bytes'             => $size,
						'url'               => (string) wp_get_attachment_url( $vid ),
						'width'             => $width,
						'height'            => $height,
						'duration'          => $length,
						'linked_product_id' => $attached_video_ids[ $vid ] ?? ( (int) $vp->post_parent ?: 0 ),
						'is_bound'          => isset( $attached_video_ids[ $vid ] ),
						'date'              => (string) $vp->post_date,
					);
				}
			}

			$pub_total    = $products_with_video + $products_missing;
			$coverage_pct = $pub_total > 0 ? round( ( $products_with_video / $pub_total ) * 100, 1 ) : 100.0;

			$issues = array();
			if ( $products_missing > 0 ) {
				$issues[] = array(
					'code'     => 'PRODUCTS_MISSING_VIDEO',
					'severity' => 'warning',
					'count'    => $products_missing,
					'message'  => sprintf(
						/* translators: %d: number of products */
						__( '%d published product(s) do not have a gallery video attached yet.', 'world-inspire-video-gallery-for-gumlet' ),
						$products_missing
					),
				);
			}
			if ( $total_empty_stubs > 0 ) {
				$issues[] = array(
					'code'     => 'WOODMART_EMPTY_VIDEO_STUBS',
					'severity' => 'info',
					'count'    => $total_empty_stubs,
					'message'  => sprintf(
						/* translators: %d: number of empty stub entries */
						__( '%d empty default WoodMart video stub entries found in postmeta (can be pruned cleanly).', 'world-inspire-video-gallery-for-gumlet' ),
						$total_empty_stubs
					),
				);
			}
			if ( $total_orphaned_slots > 0 ) {
				$issues[] = array(
					'code'     => 'ORPHANED_VIDEO_SLOTS',
					'severity' => 'warning',
					'count'    => $total_orphaned_slots,
					'message'  => sprintf(
						/* translators: %d: number of orphaned bindings */
						__( '%d video binding(s) reference image IDs no longer in the product gallery.', 'world-inspire-video-gallery-for-gumlet' ),
						$total_orphaned_slots
					),
				);
			}

			return array(
				'ok'        => true,
				'version'   => self::VERSION,
				'timestamp' => gmdate( 'c' ),
				'site'      => array(
					'brand_name'          => $cfg['brand_name'],
					'domain'              => $cfg['domain'],
					'adapter'             => $adapter,
					'accent_color'        => $cfg['accent_color'],
					'default_slot'        => $cfg['default_slot'],
					'privacy_mode'        => (int) $cfg['privacy_mode'],
					'youtube_channel_url' => $cfg['youtube_channel_url'],
					'gumlet_workspace_id' => $cfg['gumlet_workspace_id'],
					'gumlet_configured'   => '' !== self::get_gumlet_api_key(),
				),
				'summary'   => array(
					'published_products'     => $pub_total,
					'products_with_video'    => $products_with_video,
					'products_missing_video' => $products_missing,
					'coverage_pct'           => $coverage_pct,
					'total_video_bindings'   => $total_video_bindings,
					'youtube_bindings'       => $youtube_bindings,
					'gumlet_bindings'        => $gumlet_bindings,
					'mp4_bindings'           => $mp4_bindings,
					'gumlet_asset_count'     => count( $gumlet_assets ),
					'local_mp4_count'        => count( $local_mp4_assets ),
					'local_mp4_bytes'        => $total_local_bytes,
					'empty_stub_count'       => $total_empty_stubs,
					'orphaned_slot_count'    => $total_orphaned_slots,
				),
				'issues'    => $issues,
				'products'  => $products,
				'assets'    => array(
					'gumlet'    => $gumlet_assets,
					'local_mp4' => $local_mp4_assets,
				),
			);
		}

		/**
		 * Flush WordPress object cache and optional page cache after gallery mutations.
		 */
		public static function flush_product_cache( $product_id = 0 ) {
			if ( $product_id > 0 ) {
				clean_post_cache( (int) $product_id );
				if ( function_exists( 'wc_delete_product_transients' ) ) {
					wc_delete_product_transients( (int) $product_id );
				}
			}
			if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
				if ( $product_id > 0 && function_exists( 'get_permalink' ) ) {
					$plink = get_permalink( (int) $product_id );
					if ( $plink ) {
						sg_cachepress_purge_cache( $plink );
					}
				}
				sg_cachepress_purge_cache();
			}
		}

		/**
		 * Insert / bind a video into a product's gallery at a target 1-based slot position.
		 *
		 * @param array $params Insert parameters.
		 * @return array|WP_Error
		 */
		public static function insert_product_video( $params ) {
			$cfg      = self::get_settings();
			$prod_ref = $params['product_id'] ?? ( $params['product'] ?? '' );
			$post     = self::resolve_product( $prod_ref );
			if ( ! $post ) {
				return new WP_Error(
					'wivg_product_not_found',
					sprintf(
						/* translators: %s: product reference */
						__( 'Product "%s" not found.', 'world-inspire-video-gallery-for-gumlet' ),
						sanitize_text_field( (string) $prod_ref )
					),
					array( 'status' => 404 )
				);
			}
			$pid    = (int) $post->ID;
			$parsed = self::parse_video_input( (string) ( $params['video'] ?? '' ) );
			if ( ! $parsed['ok'] ) {
				return new WP_Error( 'wivg_invalid_video', $parsed['error'], array( 'status' => 422 ) );
			}

			$adapter      = self::detect_adapter();
			$target_slot  = max( 1, (int) ( $params['slot'] ?? $cfg['default_slot'] ) );
			$mode         = strtolower( (string) ( $params['mode'] ?? 'auto' ) );
			$autoplay     = isset( $params['autoplay'] ) ? ( (bool) $params['autoplay'] && '0' !== (string) $params['autoplay'] ? '1' : '0' ) : '1';
			$audio_status = isset( $params['audio_status'] ) ? ( in_array( $params['audio_status'], array( 'mute', 'unmute' ), true ) ? $params['audio_status'] : 'mute' ) : 'mute';
			$video_size   = isset( $params['video_size'] ) && in_array( $params['video_size'], array( 'contain', 'cover' ), true ) ? $params['video_size'] : 'contain';
			$video_ctrl   = isset( $params['video_control'] ) && in_array( $params['video_control'], array( 'theme', 'native' ), true ) ? $params['video_control'] : 'theme';

			if ( 'surecart' === $adapter ) {
				$vid_att_id = (int) ( $parsed['attachment_id'] ?? 0 );
				if ( 'gumlet' === $parsed['source_type'] && $vid_att_id <= 0 ) {
					$up = self::upsert_gumlet_attachment(
						$parsed['asset_id'],
						$parsed['url'],
						(string) ( $params['title'] ?? ( $post->post_title . ' Video' ) )
					);
					if ( is_wp_error( $up ) ) {
						return $up;
					}
					$vid_att_id = (int) $up['id'];
				}
				if ( $vid_att_id <= 0 ) {
					return new WP_Error(
						'wivg_surecart_requires_attachment',
						__( 'SureCart product galleries require a Gumlet or MP4 attachment ID.', 'world-inspire-video-gallery-for-gumlet' ),
						array( 'status' => 422 )
					);
				}
				$mp = json_decode( wp_json_encode( get_post_meta( $pid, 'product', true ) ), true );
				if ( ! is_array( $mp ) ) {
					return new WP_Error( 'wivg_surecart_meta_missing', __( 'SureCart product metadata not found.', 'world-inspire-video-gallery-for-gumlet' ), array( 'status' => 500 ) );
				}
				$gal = $mp['metadata']['gallery_ids'] ?? array();
				if ( is_string( $gal ) ) {
					$gal = json_decode( $gal, true );
				}
				if ( ! is_array( $gal ) ) {
					$gal = array();
				}
				if ( empty( $gal ) ) {
					$fallback_thumb = (int) get_post_thumbnail_id( $pid );
					if ( $fallback_thumb > 0 ) {
						$gal = array( array( 'id' => $fallback_thumb ) );
					}
				}
				$filtered = array();
				foreach ( $gal as $gitem ) {
					$gid = is_array( $gitem ) ? (int) ( $gitem['id'] ?? 0 ) : (int) $gitem;
					if ( $gid > 0 && $gid !== $vid_att_id ) {
						$filtered[] = is_array( $gitem ) ? $gitem : array( 'id' => $gid );
					}
				}
				$insert_idx = min( count( $filtered ), max( 0, $target_slot - 1 ) );
				array_splice( $filtered, $insert_idx, 0, array( array( 'id' => $vid_att_id ) ) );
				if ( ! isset( $mp['metadata'] ) || ! is_array( $mp['metadata'] ) ) {
					$mp['metadata'] = array();
				}
				$mp['metadata']['gallery_ids'] = wp_json_encode( array_values( $filtered ) );
				update_post_meta( $pid, 'product', $mp );
				if ( empty( $params['skip_cache_flush'] ) ) {
					self::flush_product_cache( $pid );
				}
				return array(
					'ok'                  => true,
					'action'              => 'inserted_surecart_gallery_video',
					'bound_attachment_id' => $vid_att_id,
					'product'             => empty( $params['skip_inspect'] ) ? self::inspect_product( $pid ) : array( 'id' => $pid ),
				);
			}

			// WooCommerce + WoodMart or WooCommerce Standard adapter
			$thumb_id = (int) get_post_thumbnail_id( $pid );
			$gal_raw  = (string) get_post_meta( $pid, '_product_image_gallery', true );
			$gal_ids  = array_values( array_filter( array_map( 'intval', explode( ',', $gal_raw ) ) ) );

			if ( ! empty( $params['gallery_append_ids'] ) ) {
				$extra = is_array( $params['gallery_append_ids'] )
					? array_map( 'intval', $params['gallery_append_ids'] )
					: array_map( 'intval', explode( ',', (string) $params['gallery_append_ids'] ) );
				foreach ( $extra as $eid ) {
					if ( $eid > 0 && $eid !== $thumb_id && ! in_array( $eid, $gal_ids, true ) ) {
						$gal_ids[] = $eid;
					}
				}
			}

			$unique_gal_ids = array();
			foreach ( $gal_ids as $gid ) {
				if ( $gid > 0 && $gid !== $thumb_id && ! in_array( $gid, $unique_gal_ids, true ) ) {
					$unique_gal_ids[] = $gid;
				}
			}

			if ( $thumb_id <= 0 && ! empty( $unique_gal_ids ) ) {
				$thumb_id = (int) array_shift( $unique_gal_ids );
				set_post_thumbnail( $pid, $thumb_id );
			}

			$ordered_ids = array();
			if ( $thumb_id > 0 ) {
				$ordered_ids[] = $thumb_id;
			}
			foreach ( $unique_gal_ids as $gid ) {
				$ordered_ids[] = $gid;
			}

			$explicit_slide_id = isset( $params['slide_attachment_id'] ) ? (int) $params['slide_attachment_id'] : 0;
			$target_slide_id   = 0;

			if ( $explicit_slide_id > 0 ) {
				$target_slide_id = $explicit_slide_id;
				if ( $thumb_id <= 0 ) {
					$thumb_id = $target_slide_id;
					set_post_thumbnail( $pid, $thumb_id );
				} elseif ( 1 === $target_slot && 'insert_slide' === $mode && $target_slide_id !== $thumb_id ) {
					array_unshift( $unique_gal_ids, $thumb_id );
					$unique_gal_ids = array_values( array_diff( $unique_gal_ids, array( $target_slide_id ) ) );
					$thumb_id       = $target_slide_id;
					set_post_thumbnail( $pid, $thumb_id );
				} elseif ( $target_slide_id !== $thumb_id && ! in_array( $target_slide_id, $unique_gal_ids, true ) ) {
					$ins_pos = max( 0, min( count( $unique_gal_ids ), $target_slot - 2 ) );
					array_splice( $unique_gal_ids, $ins_pos, 0, array( $target_slide_id ) );
				}
			} elseif ( 'insert_slide' === $mode || empty( $ordered_ids ) || ( $target_slot >= 2 && count( $ordered_ids ) < $target_slot ) ) {
				$new_slide_id = 0;
				if ( 'youtube' === $parsed['source_type'] ) {
					$poster = self::ensure_youtube_poster_attachment( $parsed['video_id'], $post->post_title . ' Video Preview' );
					if ( is_wp_error( $poster ) ) {
						return $poster;
					}
					$new_slide_id = (int) $poster['id'];
				} elseif ( ! empty( $params['poster_id'] ) ) {
					$new_slide_id = (int) $params['poster_id'];
				} elseif ( ! empty( $parsed['attachment_id'] ) ) {
					$att_poster = (int) get_post_meta( (int) $parsed['attachment_id'], self::META_POSTER_ID, true );
					if ( $att_poster <= 0 && function_exists( 'get_post_thumbnail_id' ) ) {
						$att_poster = (int) get_post_thumbnail_id( (int) $parsed['attachment_id'] );
					}
					if ( $att_poster > 0 ) {
						$new_slide_id = $att_poster;
					}
				}

				if ( $new_slide_id > 0 ) {
					$target_slide_id = $new_slide_id;
					if ( $thumb_id <= 0 ) {
						$thumb_id       = $target_slide_id;
						$unique_gal_ids = array_values( array_diff( $unique_gal_ids, array( $thumb_id ) ) );
						set_post_thumbnail( $pid, $thumb_id );
					} elseif ( 1 === $target_slot && 'insert_slide' === $mode && $target_slide_id !== $thumb_id ) {
						array_unshift( $unique_gal_ids, $thumb_id );
						$unique_gal_ids = array_values( array_diff( $unique_gal_ids, array( $target_slide_id ) ) );
						$thumb_id       = $target_slide_id;
						set_post_thumbnail( $pid, $thumb_id );
					} elseif ( $target_slide_id !== $thumb_id && ! in_array( $target_slide_id, $unique_gal_ids, true ) ) {
						$ins_pos = max( 0, min( count( $unique_gal_ids ), $target_slot - 2 ) );
						array_splice( $unique_gal_ids, $ins_pos, 0, array( $target_slide_id ) );
					}
				} elseif ( ! empty( $unique_gal_ids ) ) {
					$idx             = min( count( $unique_gal_ids ) - 1, max( 0, $target_slot - 2 ) );
					$target_slide_id = (int) $unique_gal_ids[ $idx ];
				} else {
					$target_slide_id = $thumb_id;
				}
			} else {
				$idx             = min( count( $ordered_ids ) - 1, max( 0, $target_slot - 1 ) );
				$target_slide_id = isset( $ordered_ids[ $idx ] ) ? (int) $ordered_ids[ $idx ] : 0;
			}

			if ( $target_slide_id <= 0 ) {
				return new WP_Error(
					'wivg_no_target_slide',
					__( 'Product has no gallery image slide to bind the video to.', 'world-inspire-video-gallery-for-gumlet' ),
					array( 'status' => 422 )
				);
			}

			if ( $thumb_id <= 0 && $target_slide_id > 0 ) {
				$thumb_id       = $target_slide_id;
				$unique_gal_ids = array_values( array_diff( $unique_gal_ids, array( $thumb_id ) ) );
				set_post_thumbnail( $pid, $thumb_id );
			}

			update_post_meta( $pid, '_product_image_gallery', implode( ',', $unique_gal_ids ) );

			$wm_entry = array(
				'video_type'       => 'mp4',
				'upload_video_id'  => '',
				'upload_video_url' => '',
				'youtube_url'      => '',
				'vimeo_url'        => '',
				'autoplay'         => $autoplay,
				'video_size'       => $video_size,
				'video_control'    => $video_ctrl,
				'hide_gallery_img' => '0',
				'hide_information' => '0',
				'audio_status'     => $audio_status,
			);

			if ( 'youtube' === $parsed['source_type'] ) {
				$wm_entry['video_type']  = 'youtube';
				$wm_entry['youtube_url'] = $parsed['url'];
			} elseif ( 'vimeo' === $parsed['source_type'] ) {
				$wm_entry['video_type'] = 'vimeo';
				$wm_entry['vimeo_url']  = $parsed['url'];
			} elseif ( 'gumlet' === $parsed['source_type'] ) {
				$vid_att_id = (int) ( $parsed['attachment_id'] ?? 0 );
				if ( $vid_att_id <= 0 ) {
					$up = self::upsert_gumlet_attachment(
						$parsed['asset_id'],
						$parsed['url'],
						(string) ( $params['title'] ?? ( $post->post_title . ' Video' ) )
					);
					if ( is_wp_error( $up ) ) {
						return $up;
					}
					$vid_att_id = (int) $up['id'];
				}
				$wm_entry['video_type']       = 'mp4';
				$wm_entry['upload_video_id']  = (string) $vid_att_id;
				$wm_entry['upload_video_url'] = $parsed['url'];
			} else {
				$vid_att_id = (int) ( $parsed['attachment_id'] ?? 0 );
				$vid_url    = (string) ( $parsed['url'] ?? '' );
				if ( $vid_att_id > 0 && '' === $vid_url ) {
					$vid_url = (string) wp_get_attachment_url( $vid_att_id );
				}
				$wm_entry['video_type']       = 'mp4';
				$wm_entry['upload_video_id']  = $vid_att_id > 0 ? (string) $vid_att_id : '';
				$wm_entry['upload_video_url'] = $vid_url;
			}

			$wm_meta = get_post_meta( $pid, 'woodmart_wc_video_gallery', true );
			if ( ! is_array( $wm_meta ) ) {
				$wm_meta = array();
			}
			$all_valid_slides = array_merge( $thumb_id > 0 ? array( $thumb_id ) : array(), $unique_gal_ids );
			$cleaned_meta     = array();
			foreach ( $wm_meta as $k => $existing_entry ) {
				$kid = (int) $k;
				if ( in_array( $kid, $all_valid_slides, true ) && self::is_active_woodmart_video_entry( $existing_entry ) ) {
					$cleaned_meta[ $kid ] = $existing_entry;
				}
			}
			$cleaned_meta[ $target_slide_id ] = $wm_entry;
			update_post_meta( $pid, 'woodmart_wc_video_gallery', $cleaned_meta );

			if ( empty( $params['skip_cache_flush'] ) ) {
				self::flush_product_cache( $pid );
			}
			return array(
				'ok'                  => true,
				'action'              => 'inserted_woodmart_gallery_video',
				'bound_attachment_id' => $target_slide_id,
				'video_entry'         => $wm_entry,
				'product'             => empty( $params['skip_inspect'] ) ? self::inspect_product( $pid ) : array( 'id' => $pid ),
			);
		}

		/**
		 * Arrange / reorder gallery slots, move a video between slots, update playback flags, or remove videos.
		 */
		public static function arrange_product_gallery( $params ) {
			$prod_ref = $params['product_id'] ?? ( $params['product'] ?? '' );
			$post     = self::resolve_product( $prod_ref );
			if ( ! $post ) {
				return new WP_Error(
					'wivg_product_not_found',
					sprintf(
						/* translators: %s: product reference */
						__( 'Product "%s" not found.', 'world-inspire-video-gallery-for-gumlet' ),
						sanitize_text_field( (string) $prod_ref )
					),
					array( 'status' => 404 )
				);
			}
			$pid     = (int) $post->ID;
			$adapter = self::detect_adapter();

			if ( 'surecart' === $adapter ) {
				$mp = json_decode( wp_json_encode( get_post_meta( $pid, 'product', true ) ), true );
				if ( ! is_array( $mp ) ) {
					$mp = array( 'metadata' => array() );
				}
				$gal = $mp['metadata']['gallery_ids'] ?? array();
				if ( is_string( $gal ) ) {
					$gal = json_decode( $gal, true );
				}
				if ( ! is_array( $gal ) ) {
					$gal = array();
				}
				$ordered = array();
				foreach ( $gal as $gitem ) {
					$gid = is_array( $gitem ) ? (int) ( $gitem['id'] ?? 0 ) : (int) $gitem;
					if ( $gid > 0 && ! in_array( $gid, $ordered, true ) ) {
						$ordered[] = $gid;
					}
				}
				if ( empty( $ordered ) ) {
					$fallback_thumb = (int) get_post_thumbnail_id( $pid );
					if ( $fallback_thumb > 0 ) {
						$ordered[] = $fallback_thumb;
					}
				}

				if ( ! empty( $params['slot_order'] ) ) {
					$new_order = is_array( $params['slot_order'] )
						? array_values( array_unique( array_filter( array_map( 'intval', $params['slot_order'] ) ) ) )
						: array_values( array_unique( array_filter( array_map( 'intval', explode( ',', (string) $params['slot_order'] ) ) ) ) );
					if ( ! empty( $new_order ) ) {
						$ordered = $new_order;
					}
				}

				if ( ! empty( $params['move_video_from_slot'] ) && ! empty( $params['move_video_to_slot'] ) ) {
					$from_idx = (int) $params['move_video_from_slot'] - 1;
					$to_idx   = (int) $params['move_video_to_slot'] - 1;
					if ( isset( $ordered[ $from_idx ] ) && $from_idx !== $to_idx ) {
						$moved = $ordered[ $from_idx ];
						array_splice( $ordered, $from_idx, 1 );
						$ins_idx = max( 0, min( count( $ordered ), $to_idx ) );
						array_splice( $ordered, $ins_idx, 0, array( $moved ) );
					}
				}

				if ( ! empty( $params['clear_all_videos'] ) ) {
					$ordered = array_values(
						array_filter(
							$ordered,
							function ( $aid ) {
								return 0 !== strpos( (string) get_post_mime_type( $aid ), 'video/' );
							}
						)
					);
				} elseif ( ! empty( $params['remove_slot'] ) ) {
					$rm_idx = (int) $params['remove_slot'] - 1;
					if ( isset( $ordered[ $rm_idx ] ) ) {
						array_splice( $ordered, $rm_idx, 1 );
					}
				} elseif ( ! empty( $params['remove_attachment_id'] ) ) {
					$rm_aid  = (int) $params['remove_attachment_id'];
					$ordered = array_values( array_diff( $ordered, array( $rm_aid ) ) );
				}

				$new_gal = array();
				foreach ( $ordered as $aid ) {
					$new_gal[] = array( 'id' => (int) $aid );
				}
				if ( ! isset( $mp['metadata'] ) || ! is_array( $mp['metadata'] ) ) {
					$mp['metadata'] = array();
				}
				$mp['metadata']['gallery_ids'] = wp_json_encode( $new_gal );
				update_post_meta( $pid, 'product', $mp );
				if ( empty( $params['skip_cache_flush'] ) ) {
					self::flush_product_cache( $pid );
				}
				return array(
					'ok'      => true,
					'action'  => 'arranged_surecart_gallery',
					'product' => empty( $params['skip_inspect'] ) ? self::inspect_product( $pid ) : array( 'id' => $pid ),
				);
			}

			$thumb_id = (int) get_post_thumbnail_id( $pid );
			$gal_raw  = (string) get_post_meta( $pid, '_product_image_gallery', true );
			$gal_ids  = array_values( array_filter( array_map( 'intval', explode( ',', $gal_raw ) ) ) );
			$ordered  = array();
			if ( $thumb_id > 0 ) {
				$ordered[] = $thumb_id;
			}
			foreach ( $gal_ids as $gid ) {
				if ( $gid > 0 && ! in_array( $gid, $ordered, true ) ) {
					$ordered[] = $gid;
				}
			}

			$wm_meta = get_post_meta( $pid, 'woodmart_wc_video_gallery', true );
			if ( ! is_array( $wm_meta ) ) {
				$wm_meta = array();
			}

			if ( ! empty( $params['slot_order'] ) ) {
				$new_order = is_array( $params['slot_order'] )
					? array_values( array_unique( array_filter( array_map( 'intval', $params['slot_order'] ) ) ) )
					: array_values( array_unique( array_filter( array_map( 'intval', explode( ',', (string) $params['slot_order'] ) ) ) ) );
				if ( ! empty( $new_order ) ) {
					$ordered  = $new_order;
					$thumb_id = $ordered[0];
					set_post_thumbnail( $pid, $thumb_id );
					update_post_meta( $pid, '_product_image_gallery', implode( ',', array_slice( $ordered, 1 ) ) );
				}
			} else {
				update_post_meta( $pid, '_product_image_gallery', implode( ',', array_slice( $ordered, 1 ) ) );
			}

			if ( ! empty( $params['move_video_from_slot'] ) && ! empty( $params['move_video_to_slot'] ) ) {
				$from_idx = (int) $params['move_video_from_slot'] - 1;
				$to_idx   = (int) $params['move_video_to_slot'] - 1;
				if ( isset( $ordered[ $from_idx ], $ordered[ $to_idx ] ) && $from_idx !== $to_idx ) {
					$from_aid = $ordered[ $from_idx ];
					$to_aid   = $ordered[ $to_idx ];
					$entry    = $wm_meta[ $from_aid ] ?? ( $wm_meta[ (string) $from_aid ] ?? null );
					if ( $entry && self::is_active_woodmart_video_entry( $entry ) ) {
						$wm_meta[ $to_aid ] = $entry;
						unset( $wm_meta[ $from_aid ], $wm_meta[ (string) $from_aid ] );
					}
				}
			}

			if ( ! empty( $params['clear_all_videos'] ) ) {
				$wm_meta = array();
			} elseif ( ! empty( $params['remove_slot'] ) ) {
				$rm_idx = (int) $params['remove_slot'] - 1;
				if ( isset( $ordered[ $rm_idx ] ) ) {
					$rm_aid = $ordered[ $rm_idx ];
					unset( $wm_meta[ $rm_aid ], $wm_meta[ (string) $rm_aid ] );
				}
			} elseif ( ! empty( $params['remove_attachment_id'] ) ) {
				$rm_aid = (int) $params['remove_attachment_id'];
				unset( $wm_meta[ $rm_aid ], $wm_meta[ (string) $rm_aid ] );
			}

			if ( ! empty( $params['update_slot'] ) ) {
				$up_idx = (int) $params['update_slot'] - 1;
				if ( isset( $ordered[ $up_idx ] ) ) {
					$up_aid = $ordered[ $up_idx ];
					$entry  = $wm_meta[ $up_aid ] ?? ( $wm_meta[ (string) $up_aid ] ?? null );
					if ( is_array( $entry ) ) {
						if ( isset( $params['autoplay'] ) ) {
							$entry['autoplay'] = ( (bool) $params['autoplay'] && '0' !== (string) $params['autoplay'] ) ? '1' : '0';
						}
						if ( isset( $params['audio_status'] ) && in_array( $params['audio_status'], array( 'mute', 'unmute' ), true ) ) {
							$entry['audio_status'] = $params['audio_status'];
						}
						if ( isset( $params['video_size'] ) && in_array( $params['video_size'], array( 'contain', 'cover' ), true ) ) {
							$entry['video_size'] = $params['video_size'];
						}
						if ( isset( $params['video_control'] ) && in_array( $params['video_control'], array( 'theme', 'native' ), true ) ) {
							$entry['video_control'] = $params['video_control'];
						}
						$wm_meta[ $up_aid ] = $entry;
					}
				}
			}

			$cleaned = array();
			foreach ( $wm_meta as $k => $entry ) {
				$kid = (int) $k;
				if ( in_array( $kid, $ordered, true ) && self::is_active_woodmart_video_entry( $entry ) ) {
					$cleaned[ $kid ] = $entry;
				}
			}
			if ( empty( $cleaned ) ) {
				delete_post_meta( $pid, 'woodmart_wc_video_gallery' );
			} else {
				update_post_meta( $pid, 'woodmart_wc_video_gallery', $cleaned );
			}

			if ( empty( $params['skip_cache_flush'] ) ) {
				self::flush_product_cache( $pid );
			}
			return array(
				'ok'      => true,
				'action'  => 'arranged_woodmart_gallery',
				'product' => empty( $params['skip_inspect'] ) ? self::inspect_product( $pid ) : array( 'id' => $pid ),
			);
		}

		/**
		 * Prune empty default WoodMart stub entries across all products on the site.
		 */
		public static function prune_all_empty_stubs( $flush = true ) {
			$posts            = get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			$cleaned_products = 0;
			$removed_entries  = 0;
			foreach ( $posts as $pid ) {
				$pid     = (int) $pid;
				$wm_meta = get_post_meta( $pid, 'woodmart_wc_video_gallery', true );
				if ( ! is_array( $wm_meta ) || empty( $wm_meta ) ) {
					continue;
				}
				$thumb_id = (int) get_post_thumbnail_id( $pid );
				$gal_raw  = (string) get_post_meta( $pid, '_product_image_gallery', true );
				$gal_ids  = array_values( array_filter( array_map( 'intval', explode( ',', $gal_raw ) ) ) );
				$valid    = array_unique( array_filter( array_merge( array( $thumb_id ), $gal_ids ) ) );

				$kept = array();
				$diff = 0;
				foreach ( $wm_meta as $k => $entry ) {
					$kid = (int) $k;
					if ( in_array( $kid, $valid, true ) && self::is_active_woodmart_video_entry( $entry ) ) {
						$kept[ $kid ] = $entry;
					} else {
						$diff++;
					}
				}
				if ( $diff > 0 ) {
					$cleaned_products++;
					$removed_entries += $diff;
					if ( empty( $kept ) ) {
						delete_post_meta( $pid, 'woodmart_wc_video_gallery' );
					} else {
						update_post_meta( $pid, 'woodmart_wc_video_gallery', $kept );
					}
				}
			}
			if ( $flush ) {
				self::flush_product_cache( 0 );
			}
			return array(
				'ok'               => true,
				'cleaned_products' => $cleaned_products,
				'removed_entries'  => $removed_entries,
			);
		}

		/**
		 * Live probe a parsed video (YouTube oEmbed or Gumlet/MP4 Range request).
		 */
		public static function probe_video_input( $raw_input ) {
			$parsed = self::parse_video_input( $raw_input );
			if ( ! $parsed['ok'] ) {
				return new WP_Error( 'wivg_invalid_input', $parsed['error'], array( 'status' => 422 ) );
			}

			if ( 'youtube' === $parsed['source_type'] ) {
				$oembed_url = 'https://www.youtube.com/oembed?url=' . rawurlencode( $parsed['url'] ) . '&format=json';
				$resp       = wp_safe_remote_get( $oembed_url, array( 'timeout' => 10 ) );
				$reachable  = false;
				$code       = 0;
				$meta       = array();
				if ( ! is_wp_error( $resp ) ) {
					$code      = (int) wp_remote_retrieve_response_code( $resp );
					$reachable = ( 200 === $code );
					$data      = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
					if ( is_array( $data ) ) {
						$meta = array(
							'title'         => (string) ( $data['title'] ?? '' ),
							'author_name'   => (string) ( $data['author_name'] ?? '' ),
							'author_url'    => (string) ( $data['author_url'] ?? '' ),
							'thumbnail_url' => (string) ( $data['thumbnail_url'] ?? $parsed['poster_url'] ),
						);
					}
				}
				return array_merge(
					$parsed,
					array(
						'playback' => array(
							'reachable'   => $reachable,
							'http_status' => $code,
							'error'       => $reachable ? '' : sprintf( 'YouTube oEmbed returned HTTP %d', $code ),
						),
						'metadata' => $meta,
					)
				);
			}

			// Gumlet or MP4 HTTP Range probe
			$url  = (string) ( $parsed['url'] ?? '' );
			$resp = wp_safe_remote_get(
				$url,
				array(
					'timeout' => 12,
					'headers' => array( 'Range' => 'bytes=0-1' ),
				)
			);
			if ( is_wp_error( $resp ) ) {
				return array_merge(
					$parsed,
					array(
						'playback' => array(
							'reachable'   => false,
							'http_status' => 0,
							'bytes'       => 0,
							'error'       => $resp->get_error_message(),
						),
					)
				);
			}
			$code  = (int) wp_remote_retrieve_response_code( $resp );
			$range = (string) wp_remote_retrieve_header( $resp, 'content-range' );
			$total = (int) wp_remote_retrieve_header( $resp, 'content-length' );
			if ( preg_match( '#/(\d+)$#', $range, $m ) ) {
				$total = (int) $m[1];
			}
			$ok = in_array( $code, array( 200, 206 ), true );
			return array_merge(
				$parsed,
				array(
					'existing_attachment' => 'gumlet' === $parsed['source_type'] ? self::find_gumlet_attachment( $parsed['asset_id'] ) : ( $parsed['attachment_id'] ?? 0 ),
					'playback'            => array(
						'reachable'   => $ok,
						'http_status' => $code,
						'bytes'       => $total,
						'error'       => $ok ? '' : sprintf( 'HTTP %d', $code ),
					),
				)
			);
		}

		/**
		 * Register admin pages: Media -> Video Gallery and Settings -> External Video Gallery.
		 */
		public static function register_admin_menu() {
			$cfg = self::get_settings();
			add_media_page(
				sprintf(
					/* translators: %s: site or brand label */
					__( '%s — External Video Gallery', 'world-inspire-video-gallery-for-gumlet' ),
					$cfg['brand_name']
				),
				__( 'Video Gallery', 'world-inspire-video-gallery-for-gumlet' ),
				'upload_files',
				self::ADMIN_SLUG,
				array( __CLASS__, 'render_admin_page' )
			);

			add_options_page(
				__( 'External Video Gallery Settings', 'world-inspire-video-gallery-for-gumlet' ),
				__( 'External Video Gallery', 'world-inspire-video-gallery-for-gumlet' ),
				'manage_options',
				self::SETTINGS_SLUG,
				array( __CLASS__, 'render_settings_page' )
			);
		}

		/**
		 * Register REST API routes under world-inspire-video-gallery/v1.
		 */
		public static function register_rest_routes() {
			$can_upload = function () {
				return current_user_can( 'upload_files' );
			};
			$can_manage = function () {
				return current_user_can( 'manage_options' );
			};

			// 1. Minimal Public Health Check (zero sensitive config or PII exposed)
			register_rest_route(
				self::REST_NAMESPACE,
				'/status',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => '__return_true',
					'callback'            => function () {
						return new WP_REST_Response(
							array(
								'ok'      => true,
								'enabled' => true,
								'version' => self::VERSION,
							),
							200
						);
					},
				)
			);

			// 2. Authenticated Full Storefront Video & Product Gallery Audit
			register_rest_route(
				self::REST_NAMESPACE,
				'/audit',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => $can_upload,
					'callback'            => function () {
						return new WP_REST_Response( self::build_audit_report(), 200 );
					},
				)
			);

			// 3. Probe any YouTube, Gumlet, or MP4 input
			register_rest_route(
				self::REST_NAMESPACE,
				'/probe',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => $can_upload,
					'callback'            => function ( $request ) {
						$res = self::probe_video_input( sanitize_text_field( (string) $request->get_param( 'input' ) ) );
						if ( is_wp_error( $res ) ) {
							return $res;
						}
						return new WP_REST_Response( $res, 200 );
					},
				)
			);

			// 4. Create / Register Video Asset (Gumlet file-less MP4 or YouTube Poster slide)
			register_rest_route(
				self::REST_NAMESPACE,
				'/create',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => $can_upload,
					'callback'            => function ( $request ) {
						$input  = sanitize_text_field( (string) ( $request->get_param( 'input' ) ?: $request->get_param( 'url' ) ?: $request->get_param( 'asset_id' ) ) );
						$title  = sanitize_text_field( (string) $request->get_param( 'title' ) );
						$parsed = self::parse_video_input( $input );
						if ( ! $parsed['ok'] ) {
							return new WP_Error( 'wivg_invalid_input', $parsed['error'], array( 'status' => 422 ) );
						}
						if ( 'gumlet' === $parsed['source_type'] ) {
							$res = self::upsert_gumlet_attachment(
								$parsed['asset_id'],
								$parsed['url'],
								$title,
								absint( $request->get_param( 'width' ) ),
								absint( $request->get_param( 'height' ) ),
								absint( $request->get_param( 'poster_id' ) )
							);
							if ( is_wp_error( $res ) ) {
								return $res;
							}
							return new WP_REST_Response( array_merge( array( 'ok' => true, 'source_type' => 'gumlet' ), $res ), 200 );
						}
						if ( 'youtube' === $parsed['source_type'] ) {
							$poster = self::ensure_youtube_poster_attachment( $parsed['video_id'], $title );
							if ( is_wp_error( $poster ) ) {
								return $poster;
							}
							return new WP_REST_Response(
								array(
									'ok'                   => true,
									'source_type'          => 'youtube',
									'video_id'             => $parsed['video_id'],
									'url'                  => $parsed['url'],
									'poster_attachment_id' => $poster['id'],
									'poster_url'           => $poster['url'],
									'created'              => $poster['created'],
								),
								200
							);
						}
						return new WP_REST_Response( array_merge( array( 'ok' => true ), $parsed ), 200 );
					},
				)
			);

			// 5. Insert / Bind Video into Product Gallery Slot
			register_rest_route(
				self::REST_NAMESPACE,
				'/insert',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => $can_upload,
					'callback'            => function ( $request ) {
						$res = self::insert_product_video( $request->get_params() );
						if ( is_wp_error( $res ) ) {
							return $res;
						}
						return new WP_REST_Response( $res, 200 );
					},
				)
			);

			// 6. Arrange / Reorder Product Gallery Slots or Update/Remove Videos
			register_rest_route(
				self::REST_NAMESPACE,
				'/arrange',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => $can_upload,
					'callback'            => function ( $request ) {
						if ( $request->get_param( 'prune_all_stubs' ) ) {
							return new WP_REST_Response( self::prune_all_empty_stubs(), 200 );
						}
						$res = self::arrange_product_gallery( $request->get_params() );
						if ( is_wp_error( $res ) ) {
							return $res;
						}
						return new WP_REST_Response( $res, 200 );
					},
				)
			);

			// 7. Atomic Batch Endpoint
			register_rest_route(
				self::REST_NAMESPACE,
				'/batch',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => $can_upload,
					'callback'            => function ( $request ) {
						$ops = $request->get_param( 'operations' );
						if ( ! is_array( $ops ) ) {
							return new WP_Error( 'wivg_invalid_batch', __( 'operations must be an array.', 'world-inspire-video-gallery-for-gumlet' ), array( 'status' => 422 ) );
						}
						$results = array();
						foreach ( $ops as $idx => $op ) {
							if ( ! is_array( $op ) ) {
								continue;
							}
							$kind                   = strtolower( (string) ( $op['op'] ?? 'insert' ) );
							$op['skip_cache_flush'] = true;
							$op['skip_inspect']     = true;
							if ( 'prune_stubs' === $kind ) {
								$r = self::prune_all_empty_stubs( false );
							} elseif ( 'arrange' === $kind || 'remove' === $kind ) {
								$r = self::arrange_product_gallery( $op );
							} else {
								$r = self::insert_product_video( $op );
							}
							if ( is_wp_error( $r ) ) {
								$results[] = array(
									'index' => $idx,
									'ok'    => false,
									'error' => $r->get_error_message(),
								);
							} else {
								$results[] = array_merge( array( 'index' => $idx ), $r );
							}
						}
						self::flush_product_cache( 0 );
						return new WP_REST_Response(
							array(
								'ok'      => true,
								'count'   => count( $results ),
								'results' => $results,
							),
							200
						);
					},
				)
			);

			// 8. Save Plugin Settings via REST (shares sanitizer with Settings API)
			register_rest_route(
				self::REST_NAMESPACE,
				'/settings',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => $can_manage,
					'callback'            => function ( $request ) {
						$existing = get_option( self::OPT_SETTINGS, array() );
						if ( ! is_array( $existing ) ) {
							$existing = array();
						}
						$payload = $existing;
						foreach ( array( 'brand_name', 'accent_color', 'youtube_channel_url', 'gumlet_workspace_id', 'gumlet_api_key', 'default_slot', 'privacy_mode' ) as $field ) {
							$val = $request->get_param( $field );
							if ( null !== $val ) {
								$payload[ $field ] = $val;
							}
						}
						if ( ! empty( $payload['gumlet_workspace_id'] ) && ! self::is_valid_hex24( (string) $payload['gumlet_workspace_id'] ) ) {
							return new WP_Error(
								'wivg_invalid_workspace_id',
								__( 'Gumlet Workspace ID must be a 24-character hexadecimal string.', 'world-inspire-video-gallery-for-gumlet' ),
								array( 'status' => 422 )
							);
						}
						$sanitized = self::sanitize_settings( $payload );
						update_option( self::OPT_SETTINGS, $sanitized, false );
						$cfg = self::get_settings();
						unset( $cfg['gumlet_api_key'] );
						$cfg['gumlet_configured'] = '' !== self::get_gumlet_api_key();
						return new WP_REST_Response(
							array(
								'ok'   => true,
								'site' => $cfg,
							),
							200
						);
					},
				)
			);

			// 9. Create direct browser-to-Gumlet pre-signed S3 upload session (0 video bytes hit PHP)
			register_rest_route(
				self::REST_NAMESPACE,
				'/gumlet/upload-session',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => $can_upload,
					'callback'            => function ( $request ) {
						$cfg          = self::get_settings();
						$workspace_id = (string) ( $cfg['gumlet_workspace_id'] ?? '' );
						if ( ! self::is_valid_hex24( $workspace_id ) ) {
							return new WP_Error(
								'wivg_gumlet_workspace_missing',
								__( 'Gumlet Workspace ID is not configured. Save it in Settings -> External Video Gallery.', 'world-inspire-video-gallery-for-gumlet' ),
								array( 'status' => 400 )
							);
						}
						$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
						$resp  = self::gumlet_request(
							'POST',
							'/video/assets/upload',
							array(
								'collection_id' => $workspace_id,
								'format'        => 'MP4',
								'title'         => '' !== $title ? $title : ( $cfg['brand_name'] . ' Product Video' ),
								'tag'           => array( 'world-inspire-video-gallery', 'wp-admin-upload' ),
							)
						);
						if ( is_wp_error( $resp ) ) {
							return $resp;
						}
						$asset_id   = (string) ( $resp['asset_id'] ?? '' );
						$upload_url = (string) ( $resp['upload_url'] ?? '' );
						if ( ! self::is_valid_hex24( $asset_id ) || '' === $upload_url ) {
							return new WP_Error(
								'wivg_gumlet_session_invalid',
								__( 'Gumlet did not return a valid asset_id / upload_url.', 'world-inspire-video-gallery-for-gumlet' ),
								array( 'status' => 502 )
							);
						}
						return new WP_REST_Response(
							array(
								'ok'           => true,
								'asset_id'     => strtolower( $asset_id ),
								'upload_url'   => esc_url_raw( $upload_url ),
								'workspace_id' => $workspace_id,
								'url'          => sprintf( 'https://%s/%s/%s/main.mp4', self::GUMLET_HOST, $workspace_id, strtolower( $asset_id ) ),
							),
							200
						);
					},
				)
			);

			// 10. Poll Gumlet asset processing status and optionally auto-register in WP Media
			register_rest_route(
				self::REST_NAMESPACE,
				'/gumlet/status',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => $can_upload,
					'callback'            => function ( $request ) {
						$asset_id = strtolower( trim( sanitize_text_field( (string) $request->get_param( 'asset_id' ) ) ) );
						if ( ! self::is_valid_hex24( $asset_id ) ) {
							return new WP_Error( 'wivg_invalid_asset_id', __( 'asset_id must be a 24-character hex string.', 'world-inspire-video-gallery-for-gumlet' ), array( 'status' => 422 ) );
						}
						$resp = self::gumlet_request( 'GET', '/video/assets/' . $asset_id );
						if ( is_wp_error( $resp ) ) {
							return $resp;
						}
						$cfg = self::get_settings();
						$ws  = (string) ( $cfg['gumlet_workspace_id'] ?? '' );
						if ( ! self::is_valid_hex24( $ws ) && ! empty( $resp['collection_id'] ) && self::is_valid_hex24( (string) $resp['collection_id'] ) ) {
							$ws = strtolower( (string) $resp['collection_id'] );
						}
						if ( ! self::is_valid_hex24( $ws ) ) {
							return new WP_Error(
								'wivg_gumlet_workspace_missing',
								__( 'Gumlet Workspace ID is not configured. Save it in Settings -> External Video Gallery.', 'world-inspire-video-gallery-for-gumlet' ),
								array( 'status' => 400 )
							);
						}
						$status = strtolower( (string) ( $resp['status'] ?? 'processing' ) );
						$url    = sprintf( 'https://%s/%s/%s/main.mp4', self::GUMLET_HOST, $ws, $asset_id );
						$out    = array(
							'ok'       => true,
							'asset_id' => $asset_id,
							'status'   => $status,
							'ready'    => in_array( $status, array( 'ready', 'stream-ready' ), true ),
							'url'      => $url,
						);
						if ( $out['ready'] && $request->get_param( 'auto_register' ) ) {
							$inp = is_array( $resp['input'] ?? null ) ? $resp['input'] : array();
							$up  = self::upsert_gumlet_attachment(
								$asset_id,
								$url,
								sanitize_text_field( (string) ( $request->get_param( 'title' ) ?: ( $inp['title'] ?? '' ) ) ),
								(int) ( $inp['width'] ?? 0 ),
								(int) ( $inp['height'] ?? 0 )
							);
							if ( ! is_wp_error( $up ) ) {
								$out['attachment'] = $up;
							}
						}
						return new WP_REST_Response( $out, 200 );
					},
				)
			);
		}

		/**
		 * Render Zero-CLI WordPress Admin UI (Media -> Video Gallery) with URL-synced tabs.
		 */
		public static function render_admin_page() {
			if ( ! current_user_can( 'upload_files' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'world-inspire-video-gallery-for-gumlet' ) );
			}
			$audit        = self::build_audit_report();
			$site         = $audit['site'];
			$summary      = $audit['summary'];
			$rest_base    = esc_url_raw( rest_url( self::REST_NAMESPACE ) );
			$can_manage   = current_user_can( 'manage_options' );
			$local_mb     = number_format( ( $summary['local_mp4_bytes'] ?? 0 ) / 1048576, 1 );
			$default_slot = max( 1, (int) ( $site['default_slot'] ?? 2 ) );
			$settings_url = admin_url( 'options-general.php?page=' . self::SETTINGS_SLUG );
			?>
			<div class="wrap wivg-wrap">
				<div class="wivg-header">
					<div>
						<h1>
							<span class="dashicons dashicons-format-video" aria-hidden="true"></span>
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: storefront or site label */
									__( '%s — External Video Gallery', 'world-inspire-video-gallery-for-gumlet' ),
									$site['brand_name']
								)
							);
							?>
						</h1>
						<p class="wivg-subtitle"><?php esc_html_e( 'Arrange product gallery slots, insert Gumlet CDN or privacy-enhanced YouTube videos at Slot #2, audit storefront video coverage, and manage zero-byte external video assets from one control surface.', 'world-inspire-video-gallery-for-gumlet' ); ?></p>
					</div>
					<div class="wivg-header-actions">
						<span class="wivg-status wivg-status-ok">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: commerce adapter identifier */
									__( 'Adapter: %s', 'world-inspire-video-gallery-for-gumlet' ),
									$site['adapter']
								)
							);
							?>
						</span>
						<?php if ( $can_manage ) : ?>
						<a class="wivg-btn wivg-btn-sm" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Plugin Settings', 'world-inspire-video-gallery-for-gumlet' ); ?></a>
						<?php endif; ?>
						<a class="wivg-btn wivg-btn-sm" href="<?php echo esc_url( $rest_base . '/audit' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'REST Audit JSON', 'world-inspire-video-gallery-for-gumlet' ); ?></a>
					</div>
				</div>

				<div class="wivg-kpis">
					<div class="wivg-kpi" data-jump-tab="arrange" data-jump-filter="all">
						<div class="wivg-kpi-label"><?php esc_html_e( 'Video Coverage', 'world-inspire-video-gallery-for-gumlet' ); ?></div>
						<div class="wivg-kpi-val" id="wivgKpiCoverage"><?php echo esc_html( (string) $summary['coverage_pct'] ); ?>%</div>
						<div class="wivg-kpi-sub"><span id="wivgKpiWithVid"><?php echo (int) $summary['products_with_video']; ?></span> / <span id="wivgKpiTotalProd"><?php echo (int) $summary['published_products']; ?></span> <?php esc_html_e( 'products', 'world-inspire-video-gallery-for-gumlet' ); ?></div>
					</div>
					<div class="wivg-kpi" data-jump-tab="arrange" data-jump-filter="missing">
						<div class="wivg-kpi-label"><?php esc_html_e( 'Missing Video SKUs', 'world-inspire-video-gallery-for-gumlet' ); ?></div>
						<div class="wivg-kpi-val" id="wivgKpiMissing"><?php echo (int) $summary['products_missing_video']; ?></div>
						<div class="wivg-kpi-sub"><?php esc_html_e( 'Click to filter & assign', 'world-inspire-video-gallery-for-gumlet' ); ?></div>
					</div>
					<div class="wivg-kpi" data-jump-tab="inventory">
						<div class="wivg-kpi-label"><?php esc_html_e( 'Active Video Bindings', 'world-inspire-video-gallery-for-gumlet' ); ?></div>
						<div class="wivg-kpi-val" id="wivgKpiBindings"><?php echo (int) $summary['total_video_bindings']; ?></div>
						<div class="wivg-kpi-sub">
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: YouTube count, 2: Gumlet/MP4 count */
									__( 'YouTube: %1$d · Gumlet/MP4: %2$d', 'world-inspire-video-gallery-for-gumlet' ),
									(int) $summary['youtube_bindings'],
									(int) ( $summary['gumlet_bindings'] + $summary['mp4_bindings'] )
								)
							);
							?>
						</div>
					</div>
					<div class="wivg-kpi" data-jump-tab="audit">
						<div class="wivg-kpi-label"><?php esc_html_e( 'Local MP4 Disk', 'world-inspire-video-gallery-for-gumlet' ); ?></div>
						<div class="wivg-kpi-val">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: local MP4 file count */
									__( '%d files', 'world-inspire-video-gallery-for-gumlet' ),
									(int) $summary['local_mp4_count']
								)
							);
							?>
						</div>
						<div class="wivg-kpi-sub"><?php echo esc_html( $local_mb ); ?> MB · <?php esc_html_e( 'Stubs:', 'world-inspire-video-gallery-for-gumlet' ); ?> <span id="wivgKpiStubs"><?php echo (int) $summary['empty_stub_count']; ?></span></div>
					</div>
				</div>

				<nav class="wivg-nav-tabs" aria-label="<?php esc_attr_e( 'Video Gallery Sections', 'world-inspire-video-gallery-for-gumlet' ); ?>">
					<a class="wivg-nav-tab is-active" href="<?php echo esc_url( admin_url( 'upload.php?page=' . self::ADMIN_SLUG . '&tab=arrange' ) ); ?>" data-tab="arrange"><?php esc_html_e( '1. Arrange & Insert Product Videos', 'world-inspire-video-gallery-for-gumlet' ); ?></a>
					<a class="wivg-nav-tab" href="<?php echo esc_url( admin_url( 'upload.php?page=' . self::ADMIN_SLUG . '&tab=create' ) ); ?>" data-tab="create"><?php esc_html_e( '2. Probe & Register Video', 'world-inspire-video-gallery-for-gumlet' ); ?></a>
					<a class="wivg-nav-tab" href="<?php echo esc_url( admin_url( 'upload.php?page=' . self::ADMIN_SLUG . '&tab=inventory' ) ); ?>" data-tab="inventory"><?php esc_html_e( '3. Video Asset Inventory', 'world-inspire-video-gallery-for-gumlet' ); ?></a>
					<a class="wivg-nav-tab" href="<?php echo esc_url( admin_url( 'upload.php?page=' . self::ADMIN_SLUG . '&tab=audit' ) ); ?>" data-tab="audit"><?php esc_html_e( '4. Storefront Audit & Hygiene', 'world-inspire-video-gallery-for-gumlet' ); ?></a>
				</nav>

				<!-- TAB 1: ARRANGE & INSERT -->
				<section id="wivg-panel-arrange" class="wivg-panel is-active">
					<div class="wivg-card">
						<div class="wivg-toolbar">
							<div class="wivg-toolbar-group" id="wivgProdFilters">
								<button type="button" class="wivg-btn wivg-btn-sm is-active" data-filter="all"><?php esc_html_e( 'All Products', 'world-inspire-video-gallery-for-gumlet' ); ?></button>
								<button type="button" class="wivg-btn wivg-btn-sm" data-filter="with_video"><?php esc_html_e( 'Has Video', 'world-inspire-video-gallery-for-gumlet' ); ?></button>
								<button type="button" class="wivg-btn wivg-btn-sm" data-filter="missing"><?php esc_html_e( 'Missing Video', 'world-inspire-video-gallery-for-gumlet' ); ?></button>
							</div>
							<div class="wivg-toolbar-group">
								<input type="search" id="wivgProdSearch" class="wivg-input wivg-input-search" placeholder="<?php esc_attr_e( 'Filter by title, ID, or slug...', 'world-inspire-video-gallery-for-gumlet' ); ?>">
								<button type="button" id="wivgRefreshAuditBtn" class="wivg-btn wivg-btn-sm"><?php esc_html_e( 'Refresh', 'world-inspire-video-gallery-for-gumlet' ); ?></button>
							</div>
						</div>
						<div id="wivgProductList"></div>
					</div>
				</section>

				<!-- TAB 2: PROBE & REGISTER -->
				<section id="wivg-panel-create" class="wivg-panel">
					<div class="wivg-grid-2">
						<div class="wivg-card">
							<h2><?php esc_html_e( 'Probe & Quick-Insert Video (YouTube / Gumlet / MP4)', 'world-inspire-video-gallery-for-gumlet' ); ?></h2>
							<p class="wivg-subtitle"><?php esc_html_e( 'Paste any YouTube watch/Shorts link or 11-char ID, Gumlet CDN URL or 24-hex Asset ID, or WP Media attachment ID.', 'world-inspire-video-gallery-for-gumlet' ); ?></p>
							<div class="wivg-field">
								<label for="wivgProbeInput" class="wivg-label"><?php esc_html_e( 'Video URL or ID', 'world-inspire-video-gallery-for-gumlet' ); ?></label>
								<input type="text" id="wivgProbeInput" class="wivg-input" placeholder="https://www.youtube.com/watch?v=... or https://video.gumlet.io/...">
							</div>
							<div class="wivg-form-row">
								<div>
									<label for="wivgQuickProductSelect" class="wivg-label"><?php esc_html_e( 'Target Product (Optional for Quick-Insert)', 'world-inspire-video-gallery-for-gumlet' ); ?></label>
									<select id="wivgQuickProductSelect" class="wivg-input"></select>
								</div>
								<div>
									<label for="wivgQuickSlotInput" class="wivg-label"><?php esc_html_e( 'Target Slot #', 'world-inspire-video-gallery-for-gumlet' ); ?></label>
									<input type="number" id="wivgQuickSlotInput" class="wivg-input" value="<?php echo (int) $default_slot; ?>" min="1" max="20">
								</div>
							</div>
							<div class="wivg-toolbar-group">
								<button type="button" id="wivgProbeBtn" class="wivg-btn"><?php esc_html_e( 'Probe & Preview', 'world-inspire-video-gallery-for-gumlet' ); ?></button>
								<button type="button" id="wivgCreateAssetBtn" class="wivg-btn"><?php esc_html_e( 'Register in Media Library', 'world-inspire-video-gallery-for-gumlet' ); ?></button>
								<button type="button" id="wivgQuickInsertBtn" class="wivg-btn wivg-btn-primary"><?php esc_html_e( 'Insert into Selected Product', 'world-inspire-video-gallery-for-gumlet' ); ?></button>
							</div>
							<div id="wivgProbeOutput" class="wivg-field"></div>

							<div class="wivg-divider">
								<h3><?php esc_html_e( 'Direct MP4 Upload to Gumlet CDN (0-Byte PHP S3 Stream)', 'world-inspire-video-gallery-for-gumlet' ); ?></h3>
								<div class="wivg-toolbar-group">
									<input type="file" id="wivgDirectFile" accept="video/mp4,video/quicktime,video/webm">
									<button type="button" id="wivgDirectUploadBtn" class="wivg-btn wivg-btn-sm"><?php esc_html_e( 'Upload MP4 to Gumlet', 'world-inspire-video-gallery-for-gumlet' ); ?></button>
								</div>
								<div id="wivgDirectUploadStatus" class="wivg-subtitle"></div>
							</div>
						</div>

						<div class="wivg-card">
							<div class="wivg-toolbar">
								<h2><?php esc_html_e( 'Gumlet & Gallery Settings', 'world-inspire-video-gallery-for-gumlet' ); ?></h2>
								<?php if ( $can_manage ) : ?>
								<a class="wivg-btn wivg-btn-sm" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'WP Settings Page', 'world-inspire-video-gallery-for-gumlet' ); ?></a>
								<?php endif; ?>
							</div>
							<div class="wivg-field">
								<label for="wivgSetBrand" class="wivg-label"><?php esc_html_e( 'Storefront / Gallery Label', 'world-inspire-video-gallery-for-gumlet' ); ?></label>
								<input type="text" id="wivgSetBrand" class="wivg-input" value="<?php echo esc_attr( $site['brand_name'] ); ?>" <?php disabled( ! $can_manage ); ?>>
							</div>
							<div class="wivg-field">
								<label for="wivgSetYtChannel" class="wivg-label"><?php esc_html_e( 'YouTube Channel URL (Optional)', 'world-inspire-video-gallery-for-gumlet' ); ?></label>
								<input type="url" id="wivgSetYtChannel" class="wivg-input" value="<?php echo esc_attr( $site['youtube_channel_url'] ); ?>" <?php disabled( ! $can_manage ); ?>>
							</div>
							<div class="wivg-form-row">
								<div>
									<label for="wivgSetGumletWs" class="wivg-label"><?php esc_html_e( 'Gumlet Workspace ID (24-hex)', 'world-inspire-video-gallery-for-gumlet' ); ?></label>
									<input type="text" id="wivgSetGumletWs" class="wivg-input" value="<?php echo esc_attr( $site['gumlet_workspace_id'] ); ?>" <?php disabled( ! $can_manage ); ?>>
								</div>
								<div>
									<label for="wivgSetDefaultSlot" class="wivg-label"><?php esc_html_e( 'Default Video Slot #', 'world-inspire-video-gallery-for-gumlet' ); ?></label>
									<input type="number" id="wivgSetDefaultSlot" class="wivg-input" value="<?php echo (int) $default_slot; ?>" min="1" max="20" <?php disabled( ! $can_manage ); ?>>
								</div>
							</div>
							<div class="wivg-field">
								<label>
									<input type="checkbox" id="wivgSetPrivacyMode" value="1" <?php checked( 1, (int) ( $site['privacy_mode'] ?? 1 ) ); ?> <?php disabled( ! $can_manage ); ?>>
									<?php esc_html_e( 'Privacy-Enhanced Mode (youtube-nocookie.com & Vimeo DNT)', 'world-inspire-video-gallery-for-gumlet' ); ?>
								</label>
							</div>
							<div class="wivg-field">
								<label for="wivgSetGumletKey" class="wivg-label">
									<?php esc_html_e( 'Gumlet API Key', 'world-inspire-video-gallery-for-gumlet' ); ?>
									<?php if ( ! empty( $site['gumlet_configured'] ) ) : ?>
										<span class="wivg-status wivg-status-ok"><?php esc_html_e( 'Configured', 'world-inspire-video-gallery-for-gumlet' ); ?></span>
									<?php else : ?>
										<span class="wivg-status wivg-status-warn"><?php esc_html_e( 'Optional (for Direct Upload)', 'world-inspire-video-gallery-for-gumlet' ); ?></span>
									<?php endif; ?>
								</label>
								<input type="password" id="wivgSetGumletKey" class="wivg-input" placeholder="<?php esc_attr_e( 'Leave blank to keep existing key', 'world-inspire-video-gallery-for-gumlet' ); ?>" autocomplete="new-password" <?php disabled( ! $can_manage ); ?>>
							</div>
							<?php if ( $can_manage ) : ?>
							<button type="button" id="wivgSaveSettingsBtn" class="wivg-btn"><?php esc_html_e( 'Save Settings', 'world-inspire-video-gallery-for-gumlet' ); ?></button>
							<span id="wivgSetStatus" class="wivg-subtitle"></span>
							<?php endif; ?>
						</div>
					</div>
				</section>

				<!-- TAB 3: INVENTORY -->
				<section id="wivg-panel-inventory" class="wivg-panel">
					<div class="wivg-card">
						<h2><?php esc_html_e( 'WordPress Media & Gumlet CDN Video Assets', 'world-inspire-video-gallery-for-gumlet' ); ?></h2>
						<div id="wivgAssetTableWrap"></div>
					</div>
				</section>

				<!-- TAB 4: AUDIT & HYGIENE -->
				<section id="wivg-panel-audit" class="wivg-panel">
					<div class="wivg-card">
						<div class="wivg-toolbar">
							<h2><?php esc_html_e( 'Storefront Video Health & Postmeta Hygiene', 'world-inspire-video-gallery-for-gumlet' ); ?></h2>
							<button type="button" id="wivgPruneStubsBtn" class="wivg-btn wivg-btn-primary wivg-btn-sm"><?php esc_html_e( 'Clean Empty WoodMart Video Stubs', 'world-inspire-video-gallery-for-gumlet' ); ?></button>
						</div>
						<div id="wivgAuditIssuesWrap"></div>
					</div>
				</section>
			</div>
			<?php
		}
	}

	World_Inspire_Video_Gallery::init();
}
