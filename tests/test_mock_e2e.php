<?php
/**
 * Zero-dependency mock E2E test harness for World Inspire Video Gallery for Gumlet.
 *
 * Uses strictly RFC 2606/6761 example.com placeholders and stubs WordPress APIs
 * to verify all adapters, filters, Settings API sanitization, REST gates, and privacy mode.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

$GLOBALS['wivg_test_filters']  = array();
$GLOBALS['wivg_test_actions']  = array();
$GLOBALS['wivg_test_posts']    = array();
$GLOBALS['wivg_test_meta']     = array();
$GLOBALS['wivg_test_options']  = array();
$GLOBALS['wivg_test_rest']     = array();
$GLOBALS['wivg_test_styles']   = array();
$GLOBALS['wivg_test_scripts']  = array();
$GLOBALS['wivg_test_settings'] = array();
$GLOBALS['wivg_test_template'] = 'woodmart';
$GLOBALS['wivg_test_sc']       = false;
$GLOBALS['wivg_test_caps']     = array(
	'upload_files'   => true,
	'manage_options' => true,
);

class WP_Error {
	public $code;
	public $message;
	public $data;
	public function __construct( $code = '', $message = '', $data = array() ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->message;
	}
}

class WP_REST_Server {
	const READABLE  = 'GET';
	const CREATABLE = 'POST';
}

class WP_REST_Response {
	public $data;
	public $status;
	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}
}

class WP_REST_Request_Mock {
	private $params;
	public function __construct( $params = array() ) {
		$this->params = $params;
	}
	public function get_param( $key ) {
		return $this->params[ $key ] ?? null;
	}
	public function get_params() {
		return $this->params;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) {
	$GLOBALS['wivg_test_filters'][ $hook ] = $cb;
}
function add_action( $hook, $cb, $prio = 10, $args = 1 ) {
	$GLOBALS['wivg_test_actions'][ $hook ] = $cb;
}
function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}
function plugins_url( $path = '', $plugin = '' ) {
	return 'https://example.com/wp-content/plugins/world-inspire-video-gallery-for-gumlet/' . ltrim( $path, '/' );
}
function wp_parse_url( $url ) {
	return parse_url( $url );
}
function home_url( $path = '' ) {
	return 'https://example.com' . $path;
}
function admin_url( $path = '' ) {
	return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
}
function rest_url( $path = '' ) {
	return 'https://example.com/wp-json/' . ltrim( $path, '/' );
}
function get_bloginfo( $show = '' ) {
	return 'Example Storefront';
}
function get_template() {
	return $GLOBALS['wivg_test_template'];
}
function post_type_exists( $pt ) {
	return 'sc_product' === $pt ? $GLOBALS['wivg_test_sc'] : true;
}
function get_option( $k, $default = false ) {
	return $GLOBALS['wivg_test_options'][ $k ] ?? $default;
}
function update_option( $k, $v, $autoload = null ) {
	$GLOBALS['wivg_test_options'][ $k ] = $v;
	return true;
}
function sanitize_text_field( $s ) {
	return trim( strip_tags( (string) $s ) );
}
function sanitize_hex_color( $color ) {
	$color = trim( (string) $color );
	return 1 === preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $color ) ? $color : '';
}
function sanitize_title( $s ) {
	return strtolower( preg_replace( '/[^a-zA-Z0-9_-]+/', '-', trim( (string) $s ) ) );
}
function esc_url_raw( $u ) {
	return filter_var( (string) $u, FILTER_SANITIZE_URL ) ?: '';
}
function esc_url( $u ) {
	return htmlspecialchars( (string) $u, ENT_QUOTES, 'UTF-8' );
}
function esc_html( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}
function __( $text, $domain = 'default' ) {
	return $text;
}
function esc_html__( $text, $domain = 'default' ) {
	return esc_html( $text );
}
function esc_attr__( $text, $domain = 'default' ) {
	return esc_attr( $text );
}
function current_user_can( $cap ) {
	return ! empty( $GLOBALS['wivg_test_caps'][ $cap ] );
}
function register_setting( $group, $name, $args = array() ) {
	$GLOBALS['wivg_test_settings'][ $name ] = $args;
}
function add_settings_section( $id, $title, $cb, $page ) {}
function add_settings_field( $id, $title, $cb, $page, $section, $args = array() ) {}
function add_settings_error( $setting, $code, $message, $type = 'error' ) {
	$GLOBALS['wivg_test_settings_errors'][] = array( $setting, $code, $message, $type );
}
function register_rest_route( $ns, $route, $args ) {
	$GLOBALS['wivg_test_rest'][ $ns . $route ] = $args;
}
function wp_create_nonce( $action = -1 ) {
	return 'nonce_' . $action;
}
function wp_register_style( $handle, $src = '', $deps = array(), $ver = false ) {
	$GLOBALS['wivg_test_styles'][ $handle . '_registered' ] = $src;
}
function wp_enqueue_style( $handle, $src = '', $deps = array(), $ver = false ) {
	$GLOBALS['wivg_test_styles'][ $handle ] = $src ?: ( $GLOBALS['wivg_test_styles'][ $handle . '_registered' ] ?? 'enqueued' );
}
function wp_add_inline_style( $handle, $data ) {
	$GLOBALS['wivg_test_styles'][ $handle . '_inline' ] = $data;
}
function wp_enqueue_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
	$GLOBALS['wivg_test_scripts'][ $handle ] = $src;
}
function wp_localize_script( $handle, $obj, $data ) {
	$GLOBALS['wivg_test_scripts'][ $handle . '_l10n' ] = array( $obj, $data );
}
function is_product() {
	return true;
}
function get_the_ID() {
	return 101;
}
function absint( $n ) {
	return abs( (int) $n );
}
function wp_json_encode( $d ) {
	return json_encode( $d );
}
function clean_post_cache( $id ) {}
function get_permalink( $id ) {
	$p = $GLOBALS['wivg_test_posts'][ $id ] ?? null;
	return $p ? ( 'https://example.com/product/' . $p->post_name . '/' ) : '';
}
function get_edit_post_link( $id, $context = 'display' ) {
	return 'https://example.com/wp-admin/post.php?post=' . (int) $id . '&action=edit';
}
function get_post( $id ) {
	return $GLOBALS['wivg_test_posts'][ (int) $id ] ?? null;
}
function get_posts( $args = array() ) {
	$pt  = $args['post_type'] ?? 'post';
	$out = array();
	foreach ( $GLOBALS['wivg_test_posts'] as $id => $p ) {
		if ( $p->post_type !== $pt ) {
			continue;
		}
		if ( ! empty( $args['name'] ) && $p->post_name !== $args['name'] ) {
			continue;
		}
		if ( ! empty( $args['meta_key'] ) ) {
			$mv = $GLOBALS['wivg_test_meta'][ $id ][ $args['meta_key'] ] ?? '';
			if ( (string) $mv !== (string) ( $args['meta_value'] ?? '' ) ) {
				continue;
			}
		}
		$out[] = ( ! empty( $args['fields'] ) && 'ids' === $args['fields'] ) ? $id : $p;
	}
	return $out;
}
function get_post_meta( $id, $key, $single = false ) {
	return $GLOBALS['wivg_test_meta'][ (int) $id ][ $key ] ?? ( $single ? '' : array() );
}
function update_post_meta( $id, $key, $val ) {
	$GLOBALS['wivg_test_meta'][ (int) $id ][ $key ] = $val;
	return true;
}
function delete_post_meta( $id, $key ) {
	unset( $GLOBALS['wivg_test_meta'][ (int) $id ][ $key ] );
	return true;
}
function get_post_thumbnail_id( $id ) {
	return (int) ( $GLOBALS['wivg_test_meta'][ (int) $id ]['_thumbnail_id'] ?? 0 );
}
function set_post_thumbnail( $id, $tid ) {
	$GLOBALS['wivg_test_meta'][ (int) $id ]['_thumbnail_id'] = (int) $tid;
	return true;
}
function get_post_mime_type( $id ) {
	$p = $GLOBALS['wivg_test_posts'][ (int) $id ] ?? null;
	return $p ? ( $p->post_mime_type ?? 'image/jpeg' ) : 'image/jpeg';
}
function wp_get_attachment_url( $id ) {
	$u = 'https://example.com/wp-content/uploads/item-' . (int) $id . '.jpg';
	if ( isset( $GLOBALS['wivg_test_filters']['wp_get_attachment_url'] ) ) {
		return call_user_func( $GLOBALS['wivg_test_filters']['wp_get_attachment_url'], $u, (int) $id );
	}
	return $u;
}
function get_attached_file( $id ) {
	$f = (string) ( $GLOBALS['wivg_test_meta'][ (int) $id ]['_wp_attached_file'] ?? '' );
	if ( isset( $GLOBALS['wivg_test_filters']['get_attached_file'] ) ) {
		return call_user_func( $GLOBALS['wivg_test_filters']['get_attached_file'], $f, (int) $id );
	}
	return $f;
}
function wp_get_attachment_image_url( $id, $size = 'thumbnail' ) {
	return 'https://example.com/wp-content/uploads/img-' . (int) $id . '.jpg';
}
function wp_get_attachment_image_src( $id, $size = 'thumbnail' ) {
	return array( 'https://example.com/wp-content/uploads/img-' . (int) $id . '.jpg', 320, 320 );
}
function wp_get_attachment_metadata( $id ) {
	return $GLOBALS['wivg_test_meta'][ (int) $id ]['_wp_attachment_metadata'] ?? array(
		'width'  => 1280,
		'height' => 720,
	);
}
function wp_update_attachment_metadata( $id, $data ) {
	$GLOBALS['wivg_test_meta'][ (int) $id ]['_wp_attachment_metadata'] = $data;
	return true;
}
function wp_insert_attachment( $args, $file = '', $parent = 0, $wp_error = false ) {
	static $next_id = 9000;
	$next_id++;
	$p                 = new stdClass();
	$p->ID             = $next_id;
	$p->post_type      = 'attachment';
	$p->post_title     = $args['post_title'] ?? '';
	$p->post_name      = sanitize_title( $p->post_title );
	$p->post_mime_type = $args['post_mime_type'] ?? 'video/mp4';
	$p->post_parent    = (int) $parent;
	$p->post_date      = '2026-11-14 00:00:00';
	$GLOBALS['wivg_test_posts'][ $next_id ] = $p;
	return $next_id;
}
function wp_update_post( $args ) {
	$id = (int) $args['ID'];
	if ( isset( $GLOBALS['wivg_test_posts'][ $id ], $args['post_title'] ) ) {
		$GLOBALS['wivg_test_posts'][ $id ]->post_title = $args['post_title'];
	}
	return $id;
}

require_once __DIR__ . '/../world-inspire-video-gallery-for-gumlet.php';

$assertions = 0;
function wivg_assert( $cond, $msg ) {
	global $assertions;
	$assertions++;
	if ( ! $cond ) {
		fwrite( STDERR, "FAIL: {$msg}\n" );
		exit( 1 );
	}
}

// Seed sample products & media
$p1                 = new stdClass();
$p1->ID             = 101;
$p1->post_type      = 'product';
$p1->post_title     = 'Example Trail Boots';
$p1->post_name      = 'example-trail-boots';
$p1->post_status    = 'publish';
$p1->post_date      = '2026-11-14 00:00:00';
$GLOBALS['wivg_test_posts'][101] = $p1;
$GLOBALS['wivg_test_meta'][101]  = array(
	'_thumbnail_id'           => 201,
	'_product_image_gallery'  => '202,203',
	'_sku'                    => 'EX-001',
	'woodmart_wc_video_gallery' => array(
		202 => array( 'video_type' => 'mp4', 'upload_video_id' => '' ), // empty stub
		999 => array( 'video_type' => 'youtube', 'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ), // orphan
	),
);

foreach ( array( 201, 202, 203 ) as $img_id ) {
	$img                 = new stdClass();
	$img->ID             = $img_id;
	$img->post_type      = 'attachment';
	$img->post_title     = 'Slide ' . $img_id;
	$img->post_name      = 'slide-' . $img_id;
	$img->post_mime_type = 'image/jpeg';
	$img->post_parent    = 101;
	$img->post_date      = '2026-11-14 00:00:00';
	$GLOBALS['wivg_test_posts'][ $img_id ] = $img;
}

// 1. Settings API registration & sanitization
World_Inspire_Video_Gallery::register_settings();
wivg_assert( isset( $GLOBALS['wivg_test_settings']['wivg_settings'] ), 'Settings API option registered' );

$ws_id    = 'a1b2c3d4e5f60718293a4b5c';
$asset_id = '11223344556677889900aabb';
$san1     = World_Inspire_Video_Gallery::sanitize_settings(
	array(
		'gumlet_workspace_id' => strtoupper( $ws_id ),
		'gumlet_api_key'      => 'secret-key-123',
		'brand_name'          => 'Demo Shop',
		'accent_color'        => '#00A8B5',
		'default_slot'        => 3,
		'privacy_mode'        => 1,
	)
);
update_option( 'wivg_settings', $san1 );
wivg_assert( $san1['gumlet_workspace_id'] === $ws_id, 'Workspace ID lowercased & validated' );
wivg_assert( 'secret-key-123' === $san1['gumlet_api_key'], 'API key saved' );

// Blank API key preserves saved key; invalid workspace keeps previous
$san2 = World_Inspire_Video_Gallery::sanitize_settings(
	array(
		'gumlet_workspace_id' => 'not-hex',
		'gumlet_api_key'      => '',
		'privacy_mode'        => 1,
	)
);
wivg_assert( $san2['gumlet_workspace_id'] === $ws_id, 'Invalid workspace preserves previous valid ID' );
wivg_assert( 'secret-key-123' === $san2['gumlet_api_key'], 'Blank API key preserves existing key' );

// 2. Parser: YouTube (privacy-enhanced nocookie URL), Shorts, Gumlet asset ID, Gumlet full URL, Vimeo, MP4
$yt = World_Inspire_Video_Gallery::parse_video_input( 'https://www.youtube.com/shorts/AbCdEf12345' );
wivg_assert( $yt['ok'] && 'youtube' === $yt['source_type'] && true === $yt['is_shorts'], 'YouTube Shorts parsed' );
wivg_assert( false !== strpos( $yt['embed_url'], 'youtube-nocookie.com/embed/AbCdEf12345' ), 'Privacy-enhanced youtube-nocookie embed URL generated by default' );

$g_id = World_Inspire_Video_Gallery::parse_video_input( $asset_id );
$expected_gumlet = "https://video.gumlet.io/{$ws_id}/{$asset_id}/main.mp4";
wivg_assert( $g_id['ok'] && $g_id['url'] === $expected_gumlet, 'Gumlet raw 24-hex asset ID resolves with configured workspace' );

$vm = World_Inspire_Video_Gallery::parse_video_input( 'https://vimeo.com/123456789' );
wivg_assert( $vm['ok'] && 'vimeo' === $vm['source_type'] && '123456789' === $vm['video_id'], 'Vimeo URL parsed' );

// 3. File-less Gumlet attachment upsert + WP Media filters
$up = World_Inspire_Video_Gallery::upsert_gumlet_attachment( $asset_id, $expected_gumlet, 'Example Product Demo', 1080, 1920, 201 );
wivg_assert( $up['ok'] && $up['created'] && $up['url'] === $expected_gumlet, 'Gumlet attachment created & filtered URL matches CDN' );
$att_id = $up['id'];
wivg_assert( get_attached_file( $att_id ) === "gumlet-cdn/{$asset_id}/main.mp4", 'Attached file filter resolves virtual path' );

$js_prep = World_Inspire_Video_Gallery::filter_prepare_attachment_js( array(), get_post( $att_id ) );
wivg_assert( ! empty( $js_prep['filename'] ) && ! empty( $js_prep['thumb']['src'] ), 'Media modal JS metadata populated' );

// 4. Insert video into WooCommerce product Slot #2 + verify pruning of empty stub & orphan
$ins = World_Inspire_Video_Gallery::insert_product_video(
	array(
		'product_id' => 101,
		'video'      => $expected_gumlet,
		'slot'       => 2,
	)
);
wivg_assert( $ins['ok'] && 202 === $ins['bound_attachment_id'], 'Inserted Gumlet video into Slot #2' );
wivg_assert( 0 === $ins['product']['empty_stub_count'] && 0 === $ins['product']['orphaned_slot_count'], 'Empty stub & orphan auto-pruned' );
wivg_assert( 1 === $ins['product']['video_count'] && 'gumlet' === $ins['product']['videos'][0]['source_type'], 'Product inspection reports active Gumlet binding' );

// 5. Standard WooCommerce fallback slide filter renders HTML5 video + nocookie/DNT iframe
$GLOBALS['wivg_test_template'] = 'twentytwentyfive';
$product                       = new class {
	public function get_id() {
		return 101;
	}
};
$slide_html = World_Inspire_Video_Gallery::filter_wc_standard_gallery_slide(
	'<div class="woocommerce-product-gallery__image"><img src="x.jpg"></div>',
	202
);
wivg_assert( false !== strpos( $slide_html, '<video class="wivg-wc-gallery-video"' ) && false !== strpos( $slide_html, $expected_gumlet ), 'Standard WC gallery slide renders Gumlet MP4 video player' );

// Test YouTube privacy-enhanced slide rendering on slot 3
World_Inspire_Video_Gallery::insert_product_video(
	array(
		'product_id' => 101,
		'video'      => 'https://www.youtube.com/watch?v=AbCdEf12345',
		'slot'       => 3,
	)
);
$yt_slide_html = World_Inspire_Video_Gallery::filter_wc_standard_gallery_slide(
	'<div class="woocommerce-product-gallery__image"><img src="y.jpg"></div>',
	203
);
wivg_assert( false !== strpos( $yt_slide_html, 'https://www.youtube-nocookie.com/embed/AbCdEf12345' ), 'Standard WC YouTube slide uses youtube-nocookie.com' );

// 6. Arrange: move slot 2 -> 3 and detach slot 3
$arr = World_Inspire_Video_Gallery::arrange_product_gallery(
	array(
		'product_id'           => 101,
		'move_video_from_slot' => 2,
		'move_video_to_slot'   => 3,
	)
);
wivg_assert( $arr['ok'] && 3 === $arr['product']['videos'][0]['slot'], 'Moved video binding from Slot 2 to Slot 3' );

$rm = World_Inspire_Video_Gallery::arrange_product_gallery(
	array(
		'product_id'  => 101,
		'remove_slot' => 3,
	)
);
wivg_assert( $rm['ok'] && 0 === $rm['product']['video_count'], 'Detached video from Slot 3' );

// 7. SureCart adapter insert & arrange
$GLOBALS['wivg_test_sc']       = true;
$sc_prod                       = new stdClass();
$sc_prod->ID                   = 501;
$sc_prod->post_type            = 'sc_product';
$sc_prod->post_title           = 'Example SureCart Item';
$sc_prod->post_name            = 'example-surecart-item';
$sc_prod->post_status          = 'publish';
$sc_prod->post_date            = '2026-11-14 00:00:00';
$GLOBALS['wivg_test_posts'][501] = $sc_prod;
$GLOBALS['wivg_test_meta'][501]  = array(
	'product' => array(
		'id'       => 'sc_123',
		'metadata' => array(
			'sku'         => 'SC-123',
			'gallery_ids' => json_encode( array( array( 'id' => 201 ), array( 'id' => 202 ) ) ),
		),
	),
);
$sc_ins = World_Inspire_Video_Gallery::insert_product_video(
	array(
		'product_id' => 501,
		'video'      => $expected_gumlet,
		'slot'       => 2,
	)
);
wivg_assert( $sc_ins['ok'] && 2 === $sc_ins['product']['slots'][1]['slot'] && 'gumlet' === $sc_ins['product']['slots'][1]['video']['source_type'], 'SureCart gallery inserts Gumlet video at Slot #2' );

// 8. REST API routes & minimal public /status check
$GLOBALS['wivg_test_template'] = 'woodmart';
$GLOBALS['wivg_test_sc']       = false;
World_Inspire_Video_Gallery::register_rest_routes();
$status_route = $GLOBALS['wivg_test_rest']['world-inspire-video-gallery/v1/status'] ?? null;
wivg_assert( is_array( $status_route ), '/status route registered' );
$status_resp = call_user_func( $status_route['callback'] );
wivg_assert( isset( $status_resp->data['enabled'] ) && ! isset( $status_resp->data['site'] ), 'Public /status exposes minimal health only (no config leak)' );

$audit_route = $GLOBALS['wivg_test_rest']['world-inspire-video-gallery/v1/audit'] ?? null;
$GLOBALS['wivg_test_caps']['upload_files'] = false;
wivg_assert( false === call_user_func( $audit_route['permission_callback'] ), '/audit blocks unauthenticated users' );
$GLOBALS['wivg_test_caps']['upload_files'] = true;
wivg_assert( true === call_user_func( $audit_route['permission_callback'] ), '/audit allows upload_files capability' );

// 9. Asset enqueuing only on target admin screens
World_Inspire_Video_Gallery::enqueue_admin_assets( 'edit.php' );
wivg_assert( empty( $GLOBALS['wivg_test_scripts']['wivg-admin'] ), 'Admin scripts do not load on unrelated screens' );
World_Inspire_Video_Gallery::enqueue_admin_assets( 'media_page_world-inspire-video-gallery' );
wivg_assert( ! empty( $GLOBALS['wivg_test_scripts']['wivg-admin'] ) && ! empty( $GLOBALS['wivg_test_styles']['wivg-admin'] ), 'Admin CSS & JS enqueue cleanly on plugin screen' );

echo "All {$assertions} mock E2E assertions passed!\n";
