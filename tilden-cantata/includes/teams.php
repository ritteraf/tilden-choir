<?php
defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * Teams and documents.
 *
 * Teams: the volunteer teams (Marketing, Staging…), each with a description and a list of
 * tasks for the season. A task has a due date; each team member ticks it off for themselves,
 * so the director sees who has done it and teammates see each other's ticks.
 *
 * Documents: managed on their own page for the current cantata, aimed at groups or teams.
 * ===================================================================== */

/** The teams as [ slug => [ 'label', 'desc' ] ]. The slug never changes, so renaming a team keeps its people. */
function tcc_teams(): array {
	$stored = get_option( 'tcc_teams' );
	if ( is_array( $stored ) ) {
		return $stored;
	}
	/* first run: the old one-per-line list from Settings */
	$out = [];
	$raw = tcc_option( 'roles', function_exists( 'tcc_default_roles' ) ? tcc_default_roles() : '' );
	foreach ( array_filter( array_map( 'trim', explode( "\n", (string) $raw ) ) ) as $line ) {
		$bits  = preg_split( '/\s+(?:—|–|-)\s+|:\s+/u', $line, 2 );
		$label = trim( $bits[0] );
		if ( $label !== '' ) {
			$out[ sanitize_title( $label ) ] = [ 'label' => $label, 'desc' => trim( $bits[1] ?? '' ) ];
		}
	}
	return $out;
}

/* keep the list once it exists, so the old Settings text can go without losing a renamed team */
add_action( 'init', function () {
	if ( ! is_array( get_option( 'tcc_teams' ) ) ) {
		update_option( 'tcc_teams', tcc_teams(), false );
	}
} );

/** This season's tasks, soonest first: [ [ 'id', 'team', 'text', 'details', 'due' ] ]. */
function tcc_tasks( int $cantata_id ): array {
	$tasks = array_values( array_filter( (array) get_post_meta( $cantata_id, '_tcc_tasks', true ), 'is_array' ) );
	usort( $tasks, fn( $a, $b ) => [ $a['due'] ?: '9999', $a['text'] ] <=> [ $b['due'] ?: '9999', $b['text'] ] );
	return $tasks;
}

/** Who has ticked each task: [ task id => [ person id => when ] ]. */
function tcc_task_done( int $cantata_id ): array {
	return (array) get_post_meta( $cantata_id, '_tcc_task_done', true );
}

/** People on this season, by team slug. */
function tcc_team_members( int $cantata_id ): array {
	$out = [];
	$ids = get_posts( [ 'post_type' => TCC_PERSON, 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'title', 'order' => 'ASC',
		'meta_query' => [ [ 'key' => '_tcc_seasons', 'value' => sprintf( 'i:%d;', $cantata_id ), 'compare' => 'LIKE' ] ] ] );
	foreach ( $ids as $id ) {
		$role = (string) get_post_meta( $id, '_tcc_role', true );
		if ( $role !== '' ) {
			$out[ $role ][] = (int) $id;
		}
	}
	return $out;
}

/** Tasks for the Backstage pages, with who has done each. */
function tcc_tasks_data( int $cantata_id ): array {
	$done = tcc_task_done( $cantata_id );
	$out  = [];
	foreach ( tcc_tasks( $cantata_id ) as $t ) {
		$by = [];
		foreach ( (array) ( $done[ $t['id'] ] ?? [] ) as $pid => $when ) {
			$by[] = [ 'id' => (int) $pid, 'name' => get_the_title( (int) $pid ) ];
		}
		$out[] = [ 'id' => $t['id'], 'team' => $t['team'], 'text' => $t['text'], 'details' => $t['details'] ?? '', 'due' => $t['due'] ?? '', 'done' => $by ];
	}
	return $out;
}

/* ---------- a member ticks a task off (for themselves only) ---------- */

add_action( 'rest_api_init', function () {
	register_rest_route( 'tcc/v1', '/taskdone', [ 'methods' => 'POST', 'permission_callback' => fn( WP_REST_Request $r ) => (bool) tcc_rest_cantata( $r ), 'callback' => function ( WP_REST_Request $r ) {
		$cantata = tcc_rest_cantata( $r );
		$who     = (int) tcc_who();
		$id      = sanitize_key( (string) $r->get_param( 'task' ) );
		$task    = null;
		foreach ( tcc_tasks( $cantata ) as $t ) {
			if ( $t['id'] === $id ) { $task = $t; break; }
		}
		if ( ! $who || ! $task ) {
			return new WP_Error( 'bad', 'Unknown task', [ 'status' => 400 ] );
		}
		if ( (string) get_post_meta( $who, '_tcc_role', true ) !== $task['team'] ) {
			return new WP_Error( 'team', 'Not on this team', [ 'status' => 403 ] );
		}
		$done = tcc_task_done( $cantata );
		if ( $r->get_param( 'done' ) ) {
			$done[ $id ][ $who ] = current_time( 'mysql' );
		} else {
			unset( $done[ $id ][ $who ] );
			if ( empty( $done[ $id ] ) ) { unset( $done[ $id ] ); }
		}
		update_post_meta( $cantata, '_tcc_task_done', $done );
		return [ 'ok' => true ];
	} ] );
} );

/* ---------- documents: who a document can be for ---------- */

/** Audiences a document can be aimed at: the groups, then the teams. */
function tcc_doc_audiences(): array {
	$out = function_exists( 'tcc_attendee_types' ) ? tcc_attendee_types() : [];
	foreach ( tcc_teams() as $k => $t ) {
		$out[ 'role:' . $k ] = preg_match( '/team$/i', $t['label'] ) ? $t['label'] : $t['label'] . ' team';
	}
	return $out;
}

add_action( 'init', function () {
	register_post_meta( 'post', '_tcc_docs', [ 'type' => 'array', 'single' => true, 'show_in_rest' => false, 'auth_callback' => fn() => current_user_can( 'edit_posts' ) ] );
} );

/* ---------- admin: Cantata → Teams and Cantata → Documents ---------- */

add_action( 'admin_menu', function () {
	add_submenu_page( 'tcc-menu', 'Teams', 'Teams', 'edit_posts', 'tcc-teams', 'tcc_render_teams_page' );
	add_submenu_page( 'tcc-menu', 'Documents', 'Documents', 'edit_posts', 'tcc-documents', 'tcc_render_documents_page' );
}, 10 );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( str_ends_with( (string) $hook, 'tcc-documents' ) ) {
		wp_enqueue_media();
		wp_add_inline_script( 'media-editor', file_get_contents( TCC_DIR . 'assets/picker.js' ) );
	}
} );

function tcc_admin_cantata_line( int $cantata ): string {
	return $cantata ? '<p class="description" style="margin:-4px 0 18px">' . esc_html( tcc_year( $cantata ) . ' · ' . get_the_title( $cantata ) ) . '</p>' : '';
}

function tcc_render_teams_page() {
	$cantata = tcc_current_id();
	if ( ! $cantata ) {
		echo '<div class="wrap"><h1>Teams</h1><p>Add a cantata first.</p></div>';
		return;
	}
	$teams   = tcc_teams();
	$tasks   = tcc_tasks( $cantata );
	$done    = tcc_task_done( $cantata );
	$members = tcc_team_members( $cantata );
	?>
	<div class="wrap tcc-teams-admin">
		<h1>Teams</h1>
		<?php echo tcc_admin_cantata_line( $cantata ); // phpcs:ignore ?>
		<?php if ( isset( $_GET['saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Saved.</p></div><?php endif; ?>
		<style>
			.tcc-teams-admin .tcc-team { max-width: 1040px; margin: 0 0 18px; padding: 16px 18px; background: #fff; border: 1px solid #dcdcde; border-radius: 6px; }
			.tcc-teams-admin .tcc-team__top { display: grid; grid-template-columns: 220px 1fr auto; gap: 10px; align-items: center; }
			.tcc-teams-admin .tcc-team__top input[type=text] { width: 100%; }
			.tcc-teams-admin .tcc-team__name { font-size: 16px; font-weight: 600; }
			.tcc-teams-admin .tcc-team__people { margin: 8px 0 12px; color: #50575e; }
			.tcc-teams-admin table { margin-top: 4px; }
			.tcc-teams-admin td { vertical-align: middle; }
			.tcc-teams-admin .tcc-done { color: #1d6b2f; }
			.tcc-teams-admin .tcc-done small { display: block; color: #50575e; }
			.tcc-teams-admin .tcc-late { color: #b32d2e; font-weight: 600; }
		</style>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tcc_save_teams">
			<?php wp_nonce_field( 'tcc_save_teams' ); ?>
			<?php foreach ( $teams as $slug => $t ) :
				$people = $members[ $slug ] ?? [];
				$mine   = array_values( array_filter( $tasks, fn( $x ) => $x['team'] === $slug ) );
				$mine[] = [ 'id' => '', 'text' => '', 'details' => '', 'due' => '' ];
				$mine[] = [ 'id' => '', 'text' => '', 'details' => '', 'due' => '' ];
				$base   = 'teams[' . esc_attr( $slug ) . ']';
				?>
				<section class="tcc-team">
					<div class="tcc-team__top">
						<input type="text" class="tcc-team__name" name="<?php echo $base; ?>[label]" value="<?php echo esc_attr( $t['label'] ); ?>" aria-label="Team name">
						<input type="text" name="<?php echo $base; ?>[desc]" value="<?php echo esc_attr( $t['desc'] ); ?>" placeholder="What the team does — members see this" aria-label="Description">
						<label><input type="checkbox" name="<?php echo $base; ?>[remove]" value="1"> Remove team</label>
					</div>
					<p class="tcc-team__people"><?php echo $people ? esc_html( count( $people ) . ( count( $people ) === 1 ? ' member: ' : ' members: ' ) . implode( ', ', array_map( 'get_the_title', $people ) ) ) : 'No members yet.'; ?></p>
					<table class="widefat striped">
						<thead><tr><th>Task</th><th>Details</th><th style="width:150px">Due</th><th style="width:220px">Done</th><th style="width:60px">Delete</th></tr></thead>
						<tbody>
						<?php foreach ( $mine as $i => $x ) :
							$tb   = $base . '[tasks][' . $i . ']';
							$by   = array_keys( (array) ( $done[ $x['id'] ] ?? [] ) );
							$late = $x['id'] && $x['due'] && $x['due'] < current_time( 'Y-m-d' ) && count( $by ) < count( $people );
							?>
							<tr>
								<td><input type="hidden" name="<?php echo $tb; ?>[id]" value="<?php echo esc_attr( $x['id'] ); ?>"><input type="text" class="large-text" name="<?php echo $tb; ?>[text]" value="<?php echo esc_attr( $x['text'] ); ?>" placeholder="<?php echo $x['id'] ? '' : 'Add a task'; ?>"></td>
								<td><input type="text" class="large-text" name="<?php echo $tb; ?>[details]" value="<?php echo esc_attr( $x['details'] ?? '' ); ?>" placeholder="Optional"></td>
								<td><input type="date" name="<?php echo $tb; ?>[due]" value="<?php echo esc_attr( $x['due'] ); ?>"><?php echo $late ? '<br><span class="tcc-late">Past due</span>' : ''; ?></td>
								<td><?php if ( $x['id'] ) : ?><span class="tcc-done"><?php echo esc_html( count( $by ) . ' of ' . count( $people ) ); ?></span><?php echo $by ? '<small>' . esc_html( implode( ', ', array_map( 'get_the_title', $by ) ) ) . '</small>' : ''; ?><?php endif; ?></td>
								<td><?php if ( $x['id'] ) : ?><input type="checkbox" name="<?php echo $tb; ?>[delete]" value="1" aria-label="Delete task"><?php endif; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</section>
			<?php endforeach; ?>
			<section class="tcc-team">
				<div class="tcc-team__top">
					<input type="text" name="newteam[label]" placeholder="New team name" aria-label="New team name">
					<input type="text" name="newteam[desc]" placeholder="What the team does" aria-label="New team description">
					<span></span>
				</div>
			</section>
			<?php submit_button( 'Save teams' ); ?>
		</form>
	</div>
	<?php
}

add_action( 'admin_post_tcc_save_teams', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'tcc_save_teams' ) ) {
		wp_die( 'Not allowed.' );
	}
	$cantata = tcc_current_id();
	$in      = wp_unslash( (array) ( $_POST['teams'] ?? [] ) );
	$old     = tcc_teams();
	$teams   = [];
	$tasks   = [];
	foreach ( $old as $slug => $t ) {
		$row = (array) ( $in[ $slug ] ?? [] );
		if ( ! empty( $row['remove'] ) ) {
			continue;
		}
		$label = sanitize_text_field( (string) ( $row['label'] ?? $t['label'] ) ) ?: $t['label'];
		$teams[ $slug ] = [ 'label' => $label, 'desc' => sanitize_text_field( (string) ( $row['desc'] ?? '' ) ) ];
		foreach ( (array) ( $row['tasks'] ?? [] ) as $x ) {
			$text = sanitize_text_field( (string) ( $x['text'] ?? '' ) );
			if ( $text === '' || ! empty( $x['delete'] ) ) {
				continue;
			}
			$due = preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) ( $x['due'] ?? '' ) ) ? $x['due'] : '';
			$id  = sanitize_key( (string) ( $x['id'] ?? '' ) ) ?: 't' . substr( md5( uniqid( '', true ) ), 0, 9 );
			$tasks[] = [ 'id' => $id, 'team' => $slug, 'text' => $text, 'details' => sanitize_text_field( (string) ( $x['details'] ?? '' ) ), 'due' => $due ];
		}
	}
	$new   = wp_unslash( (array) ( $_POST['newteam'] ?? [] ) );
	$label = sanitize_text_field( (string) ( $new['label'] ?? '' ) );
	if ( $label !== '' ) {
		$slug = sanitize_title( $label ) ?: 'team';
		$base = $slug;
		for ( $n = 2; isset( $teams[ $slug ] ) || isset( $old[ $slug ] ); $n++ ) { $slug = $base . '-' . $n; }
		$teams[ $slug ] = [ 'label' => $label, 'desc' => sanitize_text_field( (string) ( $new['desc'] ?? '' ) ) ];
	}
	update_option( 'tcc_teams', $teams, false );
	if ( $cantata ) {
		update_post_meta( $cantata, '_tcc_tasks', $tasks );
		/* ticks for tasks that are gone go with them */
		$done = array_intersect_key( tcc_task_done( $cantata ), array_flip( array_column( $tasks, 'id' ) ) );
		update_post_meta( $cantata, '_tcc_task_done', $done );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=tcc-teams&saved=1' ) );
	exit;
} );

function tcc_render_documents_page() {
	$cantata = tcc_current_id();
	echo '<div class="wrap"><h1>Documents</h1>';
	if ( ! $cantata ) {
		echo '<p>Add a cantata first.</p></div>';
		return;
	}
	echo tcc_admin_cantata_line( $cantata ); // phpcs:ignore
	if ( isset( $_GET['saved'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Saved.</p></div>';
	}
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tcc_save_documents">';
	wp_nonce_field( 'tcc_save_documents' );
	tcc_render_docs_control( $cantata );
	submit_button( 'Save documents' );
	echo '</form></div>';
}

add_action( 'admin_post_tcc_save_documents', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'tcc_save_documents' ) ) {
		wp_die( 'Not allowed.' );
	}
	$cantata = tcc_current_id();
	if ( $cantata ) {
		$docs = tcc_sanitize_docs( wp_unslash( $_POST['_tcc_docs'] ?? [] ) );
		if ( $docs ) {
			update_post_meta( $cantata, '_tcc_docs', $docs );
		} else {
			delete_post_meta( $cantata, '_tcc_docs' );
		}
	}
	wp_safe_redirect( admin_url( 'admin.php?page=tcc-documents&saved=1' ) );
	exit;
} );
