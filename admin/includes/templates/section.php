<?php
/**
 * Section template.
 *
 * @package updates-api-inspector
 * @subpackage templates
 * @since 0.2.0
 */

namespace SHC\Updates_API_Inspector;

defined( 'ABSPATH' ) || die;

$inspector = Updates_API_Inspector::get_instance();
$section   = $inspector->current_section;
$variable  = $inspector->{$section};
$sections  = $inspector->sections;
?>
<section id='<?php echo esc_attr( $section ); ?>'>
	<h3><?php echo esc_html( $sections[ $section ] ); ?></h3>
	<?php $inspector->load_template( 'jump-links' ); ?>
	<div class='description'>
		<?php $inspector->load_template( "description/{$section}" ); ?>
	</div>

	<form>
		<textarea rows='25' readonly><?php echo esc_html( WPCS::pretty_print( $variable ) ); ?></textarea>
	</form>
</section>
