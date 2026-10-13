<?php
defined( 'ABSPATH' ) || exit;

/** Is the current request a singers-only page (flagged, or simply password-protected)? */
function tcc_is_members_request(): bool {
	if ( ! is_singular() ) {
		return false;
	}
	return tcc_is_members_page( get_queried_object_id() );
}

/** A page is members-only when it is flagged, password-protected, or uses the theme's "Backstage page" template. */
function tcc_is_members_page( int $id ): bool {
	if ( ! $id ) {
		return false;
	}
	return (bool) get_post_meta( $id, '_tcc_members', true ) || (bool) get_post( $id )->post_password || get_page_template_slug( $id ) === 'page-backstage';
}

/**
 * One password for all of Backstage. A members-only page with no password of its own (the practice
 * page, say) is served only once the hub's password has been entered on this device; otherwise the
 * visitor is sent to the hub, which asks for it.
 */
add_action( 'template_redirect', function () {
	if ( ! is_singular() ) {
		return;
	}
	$id = get_queried_object_id();
	if ( ! tcc_is_members_page( $id ) || get_post( $id )->post_password ) {
		return;
	}
	$cur = tcc_current_id();
	$hub = $cur ? (int) tcc_meta( $cur, 'members' ) : 0;
	if ( ! $hub || $hub === $id || ! get_post( $hub )->post_password ) {
		return;
	}
	if ( post_password_required( $hub ) ) {
		wp_safe_redirect( get_permalink( $hub ), 302 );
		exit;
	}
}, 2 );

/** Remember the page password for a whole season instead of WordPress's 10 days. */
add_filter( 'post_password_expires', fn() => time() + 180 * DAY_IN_SECONDS );

/** Keep singers' pages out of search engines. */
add_filter( 'wp_robots', function ( array $robots ) {
	if ( tcc_is_members_request() ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
	}
	return $robots;
} );

/**
 * Keep singers' pages out of any page cache. This covers WordPress and most caching plugins;
 * SiteGround's Dynamic Cache additionally needs the page URL added to its exclusion list
 * (Site Tools → Speed → Caching → Dynamic Cache → Exclude URLs).
 */
add_action( 'template_redirect', function () {
	if ( tcc_is_members_request() ) {
		nocache_headers();
		header( 'Cache-Control: private, no-cache, no-store, must-revalidate' );
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
	}
}, 1 );

/** Ask SiteGround Optimizer (if present) to skip these URLs too. */
add_filter( 'sgo_dynamic_cache_exclude', function ( array $urls ) {
	$pages = get_posts( [
		'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids',
		'meta_key' => '_tcc_members', 'meta_value' => '1',
	] );
	foreach ( $pages as $id ) {
		$urls[] = get_permalink( $id );
	}
	return $urls;
} );

/**
 * The password page for Backstage: a kind word about what's behind it and how to get in (ask
 * anyone taking part, or the director), instead of WordPress's bare "This content is
 * password-protected". A password that didn't work gets a gentle note.
 */
add_filter( 'the_password_form', function ( $output, $post = null ) {
	$post = get_post( $post );
	if ( ! $post || ! tcc_is_members_page( (int) $post->ID ) ) {
		return $output;
	}
	$cur     = tcc_current_id();
	$title   = $cur ? get_the_title( $cur ) : '';
	$find    = function ( string ...$slugs ) {
		foreach ( $slugs as $s ) {
			$p = get_page_by_path( $s );
			if ( $p && $p->post_status === 'publish' ) {
				return get_permalink( $p );
			}
		}
		return '';
	};
	$contact = $find( 'contact-the-director', 'contact' );
	$join    = $find( 'join-the-choir', 'join' );
	$id      = 'tcc-gate-pw-' . (int) $post->ID;
	/* the browser already sent a password for this site, and it isn't this page's */
	$wrong   = isset( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] );
	$lock    = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
	ob_start();
	?>
<section class="tcc-gate" aria-labelledby="<?php echo esc_attr( $id ); ?>-h">
	<div class="tcc-gate__card">
		<div class="tcc-gate__k"><?php echo $lock; // phpcs:ignore ?>Cast &amp; Crew</div>
		<h1 class="tcc-gate__h" id="<?php echo esc_attr( $id ); ?>-h">Welcome! This part is for the cast and crew.</h1>
		<p class="tcc-gate__p">Backstage is where the Tilden Community Choir gets ready<?php echo $title ? ' for <i>' . esc_html( $title ) . '</i>' : ''; ?>: the rehearsal schedule, practice tracks and sheet music. It’s kept behind one shared password, just to keep it among the people taking part.</p>
		<p class="tcc-gate__p"><b>Don’t have the password?</b> Ask anyone singing or helping with this year’s cantata — they can share it with you.<?php echo $contact ? ' Or <a href="' . esc_url( $contact ) . '">send the director a note</a> and you’ll get it back.' : ''; ?></p>
		<form class="tcc-gate__form" action="<?php echo esc_url( site_url( 'wp-login.php?action=postpass', 'login_post' ) ); ?>" method="post">
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( get_permalink( $post ) ); ?>">
			<?php if ( $wrong ) : ?>
			<p class="tcc-gate__wrong" role="alert">That password didn’t work. Check the spelling with whoever shared it — it may also have changed for this season.</p>
			<?php endif; ?>
			<div class="tcc-gate__label"><label for="<?php echo esc_attr( $id ); ?>">Password</label></div>
			<div class="tcc-gate__row">
				<input class="tcc-gate__input" name="post_password" id="<?php echo esc_attr( $id ); ?>" type="password" autocomplete="current-password" spellcheck="false" autocapitalize="off" required>
				<button class="tcc-gate__go" type="submit">Come in</button>
			</div>
		</form>
		<?php if ( $join ) : ?>
		<p class="tcc-gate__fine">Thinking about joining? <a href="<?php echo esc_url( $join ); ?>">Here’s how to sing with us</a> — no audition needed.</p>
		<?php endif; ?>
	</div>
</section>
<style>
.tcc-gate { padding-top: clamp(28px, 6vw, 64px); font-family: var(--wp--preset--font-family--libre-franklin, system-ui, sans-serif); }
.tcc-gate__card { box-sizing: border-box; max-width: 40rem; padding: clamp(22px, 4vw, 36px); border-radius: 24px; background: #f3f1ec; color: #1f1f1f; display: flex; flex-direction: column; gap: 14px; }
.tcc-gate__k { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; letter-spacing: 0.09em; text-transform: uppercase; color: #9c2b1a; }
.tcc-gate__h { margin: 0; font-family: var(--wp--preset--font-family--zilla-slab, Georgia, serif); font-size: clamp(28px, 5vw, 38px); line-height: 1.12; font-weight: 700; }
.tcc-gate__p { margin: 0; font-size: 17px; line-height: 1.55; color: #3d3c39; }
.tcc-gate a { color: #9c2b1a; font-weight: 600; text-underline-offset: 3px; }
.tcc-gate__form { display: flex; flex-direction: column; gap: 8px; margin-top: 6px; }
.tcc-gate__label { font-size: 15px; font-weight: 700; }
.tcc-gate__row { display: flex; gap: 10px; flex-wrap: wrap; }
.tcc-gate__input { flex: 1 1 220px; min-width: 0; min-height: 52px; box-sizing: border-box; padding: 0 16px; border: 1.5px solid #c8c5bd; border-radius: 14px; background: #fff; font: inherit; font-size: 17px; color: #1f1f1f; }
.tcc-gate__input:focus { outline: 3px solid rgba(156, 43, 26, 0.35); outline-offset: 1px; border-color: #9c2b1a; }
.tcc-gate__go { flex: 0 0 auto; min-height: 52px; padding: 0 26px; border: 0; border-radius: 26px; background: #9c2b1a; color: #fff; font: inherit; font-size: 17px; font-weight: 700; cursor: pointer; }
.tcc-gate__go:hover { background: #7a2214; }
.tcc-gate__go:focus-visible { outline: 3px solid #9c2b1a; outline-offset: 3px; }
.tcc-gate__wrong { margin: 0 0 4px; padding: 10px 14px; border-radius: 12px; background: #f6e3df; color: #7a2214; font-size: 15px; line-height: 1.45; }
.tcc-gate__fine { margin: 4px 0 0; font-size: 15px; color: #5f5d58; }
@media (max-width: 480px) { .tcc-gate__go { flex: 1 1 100%; } }
</style>
	<?php
	/* one line: the post content filters (wpautop) would otherwise pad the card with empty paragraphs */
	return trim( preg_replace( '/\s*\n\s*/', ' ', preg_replace( '/>\s+</', '><', ob_get_clean() ) ) );
}, 10, 2 );
