<?php
defined( 'ABSPATH' ) || exit;

function tcc_settings_fields(): array {
	return [
		'venue'          => [ 'Default venue', 'text', 'Used when a cantata post leaves its venue blank.' ],
		'address'        => [ 'Venue address', 'text', 'Street, town, state. Shown with the venue and used for the directions link.' ],
		'directions_url' => [ 'Directions link', 'url', 'Optional. A Google Maps link. Left blank, one is built from the address.' ],
		'join_url'       => [ 'Join page link', 'url', 'Where "Join the choir" buttons go. Defaults to /join/.' ],
		'emblem'         => [ 'Emblem image', 'image', 'The choir illustration shown faintly behind an entry that has no photo yet. Defaults to the theme\'s emblem.' ],
		'contact_to'     => [ 'Messages go to', 'email', 'Where the contact form sends email. A copy of every message is also kept under Cantata → Messages.' ],
		'contact_from'   => [ 'Send email from', 'email', 'Must be an address on this site\'s own domain or mail will be junked — e.g. alex@tildencommunitychoir.org. Replies go to the person who wrote, not here.' ],
	];
}

add_action( 'admin_init', function () {
	register_setting( 'tcc_settings_group', 'tcc_settings', [
		'type' => 'array',
		'sanitize_callback' => function ( $in ) {
			$out = [];
			foreach ( tcc_settings_fields() as $k => $f ) {
				$v = $in[ $k ] ?? '';
				$out[ $k ] = match ( $f[1] ) {
					'url'   => esc_url_raw( trim( (string) $v ) ),
					'email' => sanitize_email( (string) $v ),
					'image' => absint( $v ),
					'textarea' => sanitize_textarea_field( (string) $v ),
					default => sanitize_text_field( (string) $v ),
				};
			}
			delete_transient( 'tcc_stack' );
			return $out;
		},
	] );
} );

function tcc_render_settings_page() {
	?>
	<div class="wrap">
		<h1>Cantata settings</h1>
		<?php if ( isset( $_GET['tcc_test'] ) ) : ?>
			<div class="notice notice-<?php echo $_GET['tcc_test'] === 'ok' ? 'success' : 'error'; ?> is-dismissible"><p><?php echo $_GET['tcc_test'] === 'ok' ? 'Test sent. If it doesn\'t arrive within a few minutes, check the junk folder, then make sure "Send email from" is an address on this domain.' : 'The server refused to send. Install WP Mail SMTP and point it at the mailbox in "Send email from".'; ?></p></div>
		<?php endif; ?>
		<p>The home page changes on its own from the two dates on this year's cantata post: recruiting up to and including <em>Rehearsals start</em>, upcoming until the concert, live on the performance day, "just performed" for two weeks after, then the archive.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'tcc_settings_group' ); ?>
			<table class="form-table"><tbody>
			<?php foreach ( tcc_settings_fields() as $k => [ $label, $type, $desc ] ) :
				$val = tcc_option( $k ); ?>
				<tr><th scope="row"><label for="tcc_<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></label></th><td>
				<?php if ( $type === 'image' ) :
					$id = absint( $val ); $src = $id ? wp_get_attachment_image_url( $id, 'medium' ) : ''; ?>
					<div class="tcc-media" data-type="image">
						<input type="hidden" id="tcc_<?php echo esc_attr( $k ); ?>" name="tcc_settings[<?php echo esc_attr( $k ); ?>]" value="<?php echo $id; ?>">
						<img class="tcc-media__preview" src="<?php echo esc_url( $src ); ?>" style="max-width:120px;height:auto;display:<?php echo $src ? 'block' : 'none'; ?>;margin-bottom:6px;">
						<button type="button" class="button tcc-media__pick">Choose</button>
						<button type="button" class="button-link tcc-media__clear" style="<?php echo $id ? '' : 'display:none'; ?>">Remove</button>
					</div>
				<?php elseif ( $type === 'textarea' ) : ?>
					<textarea id="tcc_<?php echo esc_attr( $k ); ?>" name="tcc_settings[<?php echo esc_attr( $k ); ?>]" rows="5" class="regular-text"><?php echo esc_textarea( $val ?: ( $k === 'roles' && function_exists( 'tcc_default_roles' ) ? tcc_default_roles() : '' ) ); ?></textarea>
				<?php else : ?>
					<input type="<?php echo in_array( $type, [ 'url', 'email' ], true ) ? $type : 'text'; ?>" id="tcc_<?php echo esc_attr( $k ); ?>" name="tcc_settings[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $val ); ?>" class="regular-text" placeholder="<?php echo esc_attr( $k === 'contact_to' ? get_option( 'admin_email' ) : ( $k === 'contact_from' && function_exists( 'tcc_contact_from' ) ? tcc_contact_from() : '' ) ); ?>">
					<?php if ( $k === 'contact_from' && function_exists( 'tcc_contact_to' ) ) : ?>
						<a class="button" style="margin-left:8px" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tcc_test_mail' ), 'tcc_test_mail' ) ); ?>">Send a test to <?php echo esc_html( tcc_contact_to() ); ?></a>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( $desc ) : ?><p class="description"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
				</td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<?php submit_button(); ?>
		</form>
		<hr>
		<h2>Wording</h2>
		<p>The sentences the home page writes (the recruiting pitch, "How to watch", the countdown, the thank-you line, button labels) are edited on the page itself: <a href="<?php echo esc_url( admin_url( 'site-editor.php?postType=wp_template&postId=tilden-choir//front-page&canvas=edit' ) ); ?>">open the front page in the Site Editor</a>, click the cantata stack, and use the <strong>Wording</strong> panel on the right.</p>
		<hr>
		<h2>Backstage link</h2>
		<p>The header's Backstage button goes to <code><?php echo esc_html( home_url( '/backstage/' ) ); ?></code>, which always leads to the Backstage page set on this year's cantata post. (<code>/singers/</code> still works too.)</p>
	</div>
	<?php
}

/** Media picker script on the settings page too. */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( str_ends_with( (string) $hook, 'tcc-settings' ) ) {
		wp_enqueue_media();
		// Re-use the picker from meta.php by loading post.php's inline script logic.
		wp_add_inline_script( 'media-editor', file_get_contents( TCC_DIR . 'assets/picker.js' ) );
	}
} );
