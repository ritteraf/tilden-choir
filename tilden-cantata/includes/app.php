<?php
defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * Backstage as a home-screen app: a web app manifest, the icons, and the
 * head tags phones look for. "Add to Home Screen" then gives singers a
 * Choir icon that opens Backstage full screen, like an app.
 * ===================================================================== */

/** The manifest, served at /?tcc_manifest=1 so no rewrite rules are needed. */
add_action( 'init', function () {
	if ( ! isset( $_GET['tcc_manifest'] ) ) {
		return;
	}
	$cur = tcc_current_id();
	$hub = $cur ? (int) tcc_meta( $cur, 'members' ) : 0;
	$start = $hub ? wp_make_link_relative( get_permalink( $hub ) ) : '/backstage/';
	$icons = TCC_URL . 'assets/icons/';
	nocache_headers();
	header( 'Content-Type: application/manifest+json; charset=utf-8' );
	echo wp_json_encode( [
		'id'               => $start,
		'name'             => get_bloginfo( 'name' ) . ' — Backstage',
		'short_name'       => 'Tilden Community Choir',
		'description'      => 'Rehearsals, practice tracks and notes for this year\'s cantata.',
		'start_url'        => $start,
		'scope'            => '/',
		'display'          => 'standalone',
		'orientation'      => 'any',
		'background_color' => '#ffffff',
		'theme_color'      => '#1f1f1f',
		'icons'            => [
			[ 'src' => $icons . 'icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ],
			[ 'src' => $icons . 'icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ],
			[ 'src' => $icons . 'icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ],
		],
	], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	exit;
}, 1 );

/** On Backstage pages: the manifest, the icon, full-screen hints, and a viewport that reaches the screen edges. */
add_action( 'wp', function () {
	if ( ! function_exists( 'tcc_is_members_request' ) || ! tcc_is_members_request() ) {
		return;
	}
	remove_action( 'wp_head', '_block_template_viewport_meta_tag', 0 );
	add_action( 'wp_head', function () {
		$icons = TCC_URL . 'assets/icons/';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">' . "\n";
		echo '<link rel="manifest" href="' . esc_url( home_url( '/?tcc_manifest=1' ) ) . '">' . "\n";
		echo '<link rel="apple-touch-icon" href="' . esc_url( $icons . 'apple-touch-icon.png' ) . '">' . "\n";
		echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-title" content="Tilden Community Choir">' . "\n";
		echo '<meta name="apple-mobile-web-app-status-bar-style" content="black">' . "\n";
		echo '<meta name="theme-color" content="#1f1f1f">' . "\n";
	}, 0 );
} );

/** Paths of every Backstage page (hub, schedule, parts, practice…), for the app's link handling. */
function tcc_members_paths(): array {
	$ids = get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 200, 'fields' => 'ids', 'no_found_rows' => true ] );
	$out = [];
	foreach ( $ids as $id ) {
		if ( tcc_is_members_page( (int) $id ) ) {
			$out[] = wp_make_link_relative( get_permalink( $id ) );
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * Opened from the home-screen icon, Backstage behaves like an app: no site header or footer
 * (nothing that leads back out to the public site), a slim "‹ Backstage" bar on the inner pages
 * so there is always a way home (iPhone apps have no back button), and any link that leads
 * outside Backstage opens in the browser instead of inside the app.
 */
add_action( 'wp', function () {
	if ( ! function_exists( 'tcc_is_members_request' ) || ! tcc_is_members_request() ) {
		return;
	}
	add_action( 'wp_head', function () {
		$cur  = tcc_current_id();
		$hub  = $cur ? (int) tcc_meta( $cur, 'members' ) : 0;
		$cfg  = [
			'hub'     => $hub ? wp_make_link_relative( get_permalink( $hub ) ) : '/backstage/',
			'isHub'   => $hub && get_queried_object_id() === $hub,
			'title'   => wp_strip_all_tags( get_the_title( get_queried_object_id() ) ),
			'noBar'   => has_block( 'tilden/practice', get_queried_object_id() ),
			'members' => tcc_members_paths(),
		];
		?>
<script>
(function (C) {
	var d = document, de = d.documentElement;
	function standalone() {
		try { if ( window.matchMedia( '(display-mode: standalone)' ).matches || window.matchMedia( '(display-mode: fullscreen)' ).matches ) { return true; } } catch ( e ) {}
		return window.navigator.standalone === true;
	}
	function norm( p ) { return ( p.replace( /\/+$/, '' ) || '/' ) + '/'; }
	var inside = C.members.map( norm );
	function bar() {
		if ( C.isHub || C.noBar || ! d.body || d.querySelector( '.tcc-appbar, .tcc-phead__back' ) ) { return; }
		var b = d.createElement( 'div' ); b.className = 'tcc-appbar';
		var a = d.createElement( 'a' ); a.className = 'tcc-appbar__back'; a.href = C.hub;
		a.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg><span>Backstage</span>';
		var t = d.createElement( 'span' ); t.className = 'tcc-appbar__title'; t.textContent = C.title;
		b.appendChild( a ); b.appendChild( t );
		d.body.insertBefore( b, d.body.firstChild );
	}
	function on() {
		de.classList.add( 'tcc-standalone' );
		if ( d.body ) { bar(); } else { d.addEventListener( 'DOMContentLoaded', bar ); }
	}
	/* Links that leave Backstage open in the browser, never inside the app. */
	d.addEventListener( 'click', function ( e ) {
		if ( ! de.classList.contains( 'tcc-standalone' ) || e.defaultPrevented ) { return; }
		var a = e.target.closest && e.target.closest( 'a[href]' );
		if ( ! a || a.target || a.hasAttribute( 'download' ) ) { return; }
		var u; try { u = new URL( a.href, location.href ); } catch ( x ) { return; }
		if ( ! /^https?:$/.test( u.protocol ) ) { return; }
		if ( u.origin === location.origin && ( inside.indexOf( norm( u.pathname ) ) > -1 || u.pathname.indexOf( '/wp-json/' ) === 0 ) ) { return; }
		e.preventDefault();
		window.open( u.href, '_blank', 'noopener' );
	} );
	window.tccStandalone = on;
	if ( standalone() ) { on(); }
})(<?php echo wp_json_encode( $cfg, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG ); ?>);
</script>
<style>
.tcc-standalone header.wp-block-template-part,
.tcc-standalone footer.wp-block-template-part,
.tcc-standalone .wp-site-blocks > header,
.tcc-standalone .wp-site-blocks > footer { display: none !important; }
.tcc-standalone body { padding-top: env(safe-area-inset-top); }
.tcc-appbar { position: sticky; top: 0; z-index: 50; display: flex; align-items: center; gap: 10px; min-height: 56px; padding: 4px max(12px, env(safe-area-inset-right)) 4px max(8px, env(safe-area-inset-left)); background: #1f1f1f; color: #fff; }
.tcc-appbar__back { display: inline-flex; align-items: center; gap: 4px; min-height: 48px; padding: 0 12px 0 6px; border-radius: 10px; color: #fff !important; text-decoration: none !important; font-size: 18px; font-weight: 600; }
.tcc-appbar__back:active { background: rgba(255,255,255,.12); }
.tcc-appbar__title { flex: 1; min-width: 0; text-align: right; color: rgba(255,255,255,.7); font-size: 16px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>
		<?php
	}, 1 );
} );
