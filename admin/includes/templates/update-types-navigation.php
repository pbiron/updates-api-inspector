<?php
/**
 * Update types navigation template.
 *
 * @package updates-api-inspector
 * @subpackage templates
 * @since 0.2.0
 */

use SHC\Updates_API_Inspector\Updates_API_Inspector;

defined( 'ABSPATH' ) || die;

?>
<nav role='tablist' class='nav-tab-wrapper wp-clearfix'>
<?php
$inspector = Updates_API_Inspector::get_instance();

foreach ( $inspector->types as $_type => $label ) {
	// having the type be part of the action means we don't have to do a separate
	// check that type is one the "allowed" types.
	$href = wp_nonce_url( add_query_arg( 'type', $_type ), "updates-api-inspector_{$_type}" );

	$class  = 'nav-tab';
	$class .= $_type === $inspector->type ? ' nav-tab-active' : '';
	?>
		<a href='<?php echo esc_url( $href ); ?>' role='tab' aria-selected='<?php echo ( $_type === $inspector->type ? 'true' : 'false' ); ?>' class='<?php echo esc_attr( $class ); ?>'><?php echo esc_html( $label ); ?></a>
	<?php
}
?>
</nav>
