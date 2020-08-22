<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
/**
 * Admin notice template to let the user know that `no_update` must be populated for externally hosted plugis/themes
 * for the 5.5 auto-updates UI to work properly.
 *
 * @package updates-api-inspector
 * @subpackage templates
 * @since 0.2.0
 */

namespace SHC\Updates_API_Inspector;

defined( 'ABSPATH' ) || die;

$inspector = Updates_API_Inspector::get_instance();

if ( ! in_array( $inspector->type, array( 'plugins', 'themes' ), true ) ) {
	return;
}
?>
<div class='notice notice-warning inline'>
	<p>
	<?php
		echo '<strong>' . esc_html__( 'Important:', 'updates-api-inspector' ) . '</strong>';
		echo ' ';
		printf(
			/* translators: variable name */
			esc_html__( 'The Auto-updates UI, introduced in WordPress 5.5.0, will not work correctly for externally hosted plugins that do not populate %s with information about their theme!', 'updates-api-inspector' ),
			'<code>no_update</code>'
		);
		echo ' ';
		esc_html_e( 'For more information, see the sidebar in the Help tab on this screen.', 'updates-api-inspector' );
		?>
	</p>
</div>
