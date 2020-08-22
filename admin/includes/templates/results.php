<?php
/**
 * Results template.
 *
 * @package updates-api-inspector
 * @subpackage templates
 * @since 0.2.0
 */

use SHC\Updates_API_Inspector\Updates_API_Inspector;

defined( 'ABSPATH' ) || die;

$inspector = Updates_API_Inspector::get_instance();
?>
<div id='result'>
	<div class='preface'>
	<?php
	$inspector->current_section = 'top';
	$inspector->load_template( 'jump-links' );
	$inspector->load_template( 'description/preface' );
	?>
	</div>
	<?php
	foreach ( array_keys( $inspector->get_sections() ) as $section ) {
		$inspector->current_section = $section;
		$inspector->load_template( 'section' );
	}
	?>
</div>
