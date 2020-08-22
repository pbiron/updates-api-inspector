<?php
/**
 * Updates API Inspector screen.
 *
 * @package updates-api-inspector
 *
 * @since 0.2.0
 */

namespace SHC\Updates_API_Inspector;

defined( 'ABSPATH' ) || die;

if ( ! current_user_can( Updates_API_Inspector::CAPABILITY ) ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to inspect the Updates API for this site.', 'updates-api-inspector' ) );
}

$current_type = isset( $_REQUEST['type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['type'] ) ) : '';
if ( $current_type ) {
	// having the type be part of the action means we don't have to do a separate
	// check that type is one the "allowed" types.
	check_admin_referer( "updates-api-inspector_{$current_type}" );
}

$screen = get_current_screen();

$help_tabs = array(
	array(
		'title'   => __( 'General', 'updates-api-inspector' ),
		'id'      => 'general',
		'content' => '<p>' . __( 'This is a very preliminary release of this plugin, released in this early state because of confusion about the meaning of some fields in the <code>update_plugins</code> and <code>update_themes</code> site transients and the need for plugins/themes not hosted in the .org repo to properly populate the <code>no_update</code> fields therein for the Auto-updates UI in WordPress 5.5.0 to properly work for such plugins/themes.', 'updates-api-inspector' ) . '</p>' .
			'<p>' . __( 'Since there is no official documentation on the Updates API, it is hoped that this inspector will at least give developers a sense of the values in those fields for both .org plugins/themes and other externally hosted plugins/themes.', 'updates-api-inspector' ) . '</p>' .
			'<p>' . __( 'In future releases I hope to expand this help information to provide more context about the Updates API, in general, as well as provide more functionality.', 'updates-api-inspector' ) . '</p>',
	),
);
foreach ( $help_tabs as $help_tab ) {
	$screen->add_help_tab( $help_tab );
}

$sidebar =
	'<p><strong>' . __( 'For more information:', 'updates-api-inspector' ) . '</strong></p>' .
	'<p>' . __( '<a href="https://make.wordpress.org/core/2020/07/15/controlling-plugin-and-theme-auto-updates-ui-in-wordpress-5-5/">Controlling Plugin and Theme auto-updates UI in WordPress 5.5</a>' ) . '</p>' .
	'<p>' . __( '<a href="https://make.wordpress.org/core/2020/07/30/recommended-usage-of-the-updates-api-to-support-the-auto-updates-ui-for-plugins-and-themes-in-wordpress-5-5">Recommended usage of the Updates API to support the auto-updates UI for Plugins and Themes in WordPress 5.5</a>' ) . '</p>';

$screen->set_help_sidebar( $sidebar );

wp_enqueue_style( 'updates-api-inspector' );

// now that the help has been set, load the rest of admin-header.
require_once ABSPATH . 'wp-admin/admin-header.php';

// this is analogous to the $list_table global in core screens that use
// list tables.  We setup the basic "structure" of the screen here
// and then let that instance do all the work of displaying it.
$inspector = Updates_API_Inspector::get_instance( $current_type );
?>
<div class='wrap updates-api-inspector'>
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<p><?php esc_html_e( 'This tool will allow you to inspect requests/responses from the Updates API and the site transients core sets as a result.', 'updates-api-inspector' ); ?></p>

	<?php $inspector->load_template( 'update-types-navigation' ); ?>

	<?php
	if ( ! $current_type ) {
		?>
		<p>
		<?php esc_html_e( 'Simply click on the tab for the request type you would like to inspect.', 'updates-api-inspector' ); ?>
		</p>
		<?php
	} else {
		// Run the update check.
		$inspector->check_updates();

		// Display the results.
		$inspector->load_template( 'results' );
	}
	?>
</div>
