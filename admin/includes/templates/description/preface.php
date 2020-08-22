<?php
/**
 * Section description template.
 *
 * This template displays **before** the update-type-specific description template
 * for the `preface` section.
 *
 * @package updates-api-inspector
 * @subpackage templates
 * @since 0.2.0
 */

namespace SHC\Updates_API_Inspector;

defined( 'ABSPATH' ) || die;

$inspector = Updates_API_Inspector::get_instance();
$strings   = $inspector->strings;
?>
<p>
	<?php
		echo esc_html( $strings['preface'] );
	?>
</p>
