<?php
/**
 * Admin notice template to let the user know about when to hook `auto_update_{$type}`.
 *
 * @package updates-api-inspector
 * @subpackage templates
 * @since 0.2.0
 */

namespace SHC\Updates_API_Inspector;

defined( 'ABSPATH' ) || die;

$inspector = Updates_API_Inspector::get_instance();

?>
<div class='notice notice-warning inline'>
	<p>
	<?php
		echo '<strong>' . esc_html__( 'Important:', 'updates-api-inspector' ) . '</strong>';
		echo ' ';
		printf(
			/* translators: cron job hook */
			esc_html__( 'Auto-updates happen during the %s WP-Cron job. ', 'updates-api-inspector' ),
			'<code>wp_version_check</code>'
		);
		echo ' ';
		printf(
			/* translators: 1: filter name 2: action name 3: action name */
			esc_html__( 'Therefore, the %1$s filter must be added on %2$s (and not, say, %3$s).', 'updates-api-inspector' ),
			'<code>auto_update_' . esc_attr( $inspector->type ) . '</code>',
			'<code>init</code>',
			'<code>admin_init</code>'
		);
		?>
	</p>
</div>
