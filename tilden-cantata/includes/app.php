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

/** The five Backstage tabs, with which one this page belongs to. Empty when the page has no tab bar. */
function tcc_tabs_config(): array {
	$qid = get_queried_object_id();
	if ( ! $qid || has_block( 'tilden/practice', $qid ) || post_password_required( $qid ) ) {
		return [];
	}
	$cur  = tcc_current_id();
	$hubp = $cur ? (int) tcc_meta( $cur, 'members' ) : 0;
	$hub  = $hubp ? get_permalink( $hubp ) : home_url( '/backstage/' );
	$find = function ( string $slug ) {
		$p = get_page_by_path( $slug );
		return $p && $p->post_status === 'publish' ? $p : null;
	};
	$prac  = $find( 'practice' );
	$sched = $find( 'schedule' );
	$slug  = (string) get_post_field( 'post_name', $qid );
	$on    = '';
	if ( ( $hubp && $qid === $hubp ) || $slug === 'backstage' ) {
		$on = 'home';
	} elseif ( $sched && $qid === $sched->ID ) {
		$on = 'schedule';
	} elseif ( in_array( $slug, [ 'soprano', 'alto', 'tenor', 'bass', 'accompaniment' ], true ) ) {
		$on = 'practice';
	}
	return [
		'on'   => $on,
		'tabs' => [
			'home'     => [ 'Home', $hub ],
			'practice' => [ 'Practice', $prac ? get_permalink( $prac ) : $hub ],
			'schedule' => [ 'Schedule', $sched ? get_permalink( $sched ) : $hub ],
			'team'     => [ 'Team', $hub . '#team' ],
			'docs'     => [ 'Docs', $hub . '#docs' ],
		],
	];
}

function tcc_tab_icon( string $k ): string {
	$p = [
		'home'     => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>',
		'practice' => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
		'schedule' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
		'team'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
		'docs'     => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M9 13h6M9 17h6"/>',
	];
	return '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ( $p[ $k ] ?? '' ) . '</svg>';
}

/* Backstage pages carry the tab bar, so the sub-pages' own "← Backstage" links step aside. */
add_filter( 'body_class', function ( array $c ) {
	if ( function_exists( 'tcc_is_members_request' ) && tcc_is_members_request() && tcc_tabs_config() ) {
		$c[] = 'tcc-tabbed';
	}
	return $c;
} );

/** The tab bar: along the bottom on phones, across the top on computers. */
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'tcc_is_members_request' ) || ! tcc_is_members_request() ) {
		return;
	}
	$cfg = tcc_tabs_config();
	if ( ! $cfg ) {
		return;
	}
	echo '<nav class="tcc-tabs" aria-label="Backstage"><span class="tcc-tabs__brand">Backstage</span><div class="tcc-tabs__row">';
	foreach ( $cfg['tabs'] as $k => $t ) {
		$cur = $cfg['on'] === $k ? ' aria-current="page"' : '';
		echo '<a class="tcc-tabs__a" data-tab="' . esc_attr( $k ) . '" href="' . esc_url( $t[1] ) . '"' . $cur . '>' . tcc_tab_icon( $k ) . '<span>' . esc_html( $t[0] ) . '</span></a>';
	}
	echo '</div></nav>';
	?>
<script>
(function () {
	var d = document, nav = d.querySelector( '.tcc-tabs' ); if ( ! nav ) { return; }
	/* on computers the bar sits under the site header (when it shows), otherwise at the very top */
	var head = d.querySelector( '.wp-site-blocks > header, header.wp-block-template-part' );
	if ( head && head.parentNode ) { head.parentNode.insertBefore( nav, head.nextSibling ); } else { d.body.insertBefore( nav, d.body.firstChild ); }
	var links = nav.querySelectorAll( '.tcc-tabs__a' );
	function here( a ) { var u = new URL( a.href, location.href ); return u.pathname.replace( /\/+$/, '' ) === location.pathname.replace( /\/+$/, '' ); }
	/* the hub's own tabs (Home, Team, Docs) are one page: mark the one in the address */
	window.tccTabs = function () {
		var t = d.documentElement.getAttribute( 'data-tcc-tab' ); if ( ! t ) { return; }
		links.forEach( function ( a ) {
			var k = a.getAttribute( 'data-tab' );
			if ( k === 'home' || k === 'team' || k === 'docs' ) { if ( k === t ) { a.setAttribute( 'aria-current', 'page' ); } else { a.removeAttribute( 'aria-current' ); } }
		} );
	};
	window.tccTabs();
	nav.addEventListener( 'click', function ( e ) {
		var a = e.target.closest( '.tcc-tabs__a' ); if ( ! a || ! here( a ) || e.metaKey || e.ctrlKey ) { return; }
		var hash = new URL( a.href, location.href ).hash;
		e.preventDefault();
		if ( hash ) { if ( location.hash !== hash ) { location.hash = hash; } else { window.scrollTo( 0, 0 ); } return; }
		if ( location.hash ) { location.hash = ''; history.replaceState( null, '', location.pathname + location.search ); } else { window.scrollTo( 0, 0 ); }
	} );
})();
</script>
	<?php
}, 5 );

/**
 * Opened from the home-screen icon, Backstage behaves like an app: no site header or footer
 * (nothing that leads back out to the public site; the tab bar is the way around) and any link
 * that leads outside Backstage opens in the browser instead of inside the app.
 */
add_action( 'wp', function () {
	if ( ! function_exists( 'tcc_is_members_request' ) || ! tcc_is_members_request() ) {
		return;
	}
	add_action( 'wp_head', function () {
		$cfg = [ 'members' => tcc_members_paths() ];
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
	function on() { de.classList.add( 'tcc-standalone' ); }
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
.tcc-tabbed .tcc-phead__back { display: none !important; }
.tcc-tabs { position: fixed; z-index: 60; left: 0; right: 0; bottom: 0; display: block; padding: 4px max(4px, env(safe-area-inset-right)) max(8px, env(safe-area-inset-bottom)) max(4px, env(safe-area-inset-left)); background: rgba(255,255,255,.97); border-top: 1px solid #dedbd3; font-family: 'Libre Franklin', system-ui, sans-serif; -webkit-backdrop-filter: blur(12px); backdrop-filter: blur(12px); }
.tcc-tabs__brand { display: none; }
.tcc-tabs__row { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); max-width: 560px; margin: 0 auto; }
.tcc-tabs__a { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; min-height: 54px; border-radius: 14px; color: #5f5d58 !important; font-size: 12px; font-weight: 600; line-height: 1.1; text-decoration: none !important; -webkit-tap-highlight-color: transparent; }
.tcc-tabs__a[aria-current="page"] { color: #9c2b1a !important; font-weight: 700; }
.tcc-tabs__a:active { background: #f3f1ec; }
.tcc-tabs__a:focus-visible { outline: 3px solid #9c2b1a; outline-offset: -3px; }
body.tcc-tabbed { padding-bottom: calc(72px + env(safe-area-inset-bottom)); }
@media (min-width: 900px) {
	.tcc-tabs { position: sticky; top: 0; bottom: auto; display: flex; align-items: center; gap: 28px; min-height: 68px; padding: 0 max(32px, env(safe-area-inset-right)) 0 max(32px, env(safe-area-inset-left)); border-top: 0; border-bottom: 1px solid #dedbd3; }
	.tcc-tabs__brand { display: block; font-family: 'Zilla Slab', Georgia, serif; font-size: 22px; font-weight: 700; color: #9c2b1a; }
	.tcc-tabs__row { display: flex; gap: 4px; max-width: none; margin: 0; }
	.tcc-tabs__a { flex-direction: row; gap: 8px; min-height: 44px; padding: 0 16px; border-radius: 22px; color: #3d3c39 !important; font-size: 15px; }
	.tcc-tabs__a svg { width: 18px; height: 18px; }
	.tcc-tabs__a[aria-current="page"] { background: #f6e3df; }
	.tcc-tabs__a:not([aria-current]):hover { background: #f3f1ec; }
	body.tcc-tabbed { padding-bottom: 0; }
}
.admin-bar .tcc-tabs { top: 32px; }
@media (max-width: 899px) { .admin-bar .tcc-tabs { top: auto; } }
@media print { .tcc-tabs { display: none; } }
</style>
		<?php
	}, 1 );
} );
