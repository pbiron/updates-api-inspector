<?php
/**
 * Jump links template.
 *
 * @package updates-api-inspector
 * @subpackage templates
 * @since 0.2.0
 */

namespace SHC\Updates_API_Inspector;

defined( 'ABSPATH' ) || die;

$inspector       = Updates_API_Inspector::get_instance();
$current_section = $inspector->current_section;
$links           = $inspector->get_sections();
$links           = array_merge( array( 'top' => __( 'Back to top', 'updates-api-inspector' ) ), $links );
?>
<nav class='jump-links'>
	<ul>
<?php
foreach ( $links as $anchor => $text ) {
	if ( $current_section !== $anchor ) {
		?>
		<li><a href='#<?php echo esc_attr( $anchor ); ?>'><?php echo esc_html( $text ); ?></a></li>
		<?php
	} else {
		?>
		<li><?php echo esc_html( $text ); ?></li>
		<?php
	}
}
?>
	</ul>
</nav>
