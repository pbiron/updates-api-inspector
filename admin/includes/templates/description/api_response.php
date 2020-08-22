<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
/**
 * Section description template.
 *
 * This template displays **before** the update-type-specific description template
 * for the `api_response` section.
 *
 * @package updates-api-inspector
 * @subpackage templates
 * @since 0.2.0
 */

use SHC\Updates_API_Inspector\Updates_API_Inspector;

defined( 'ABSPATH' ) || die;

$inspector = Updates_API_Inspector::get_instance();
$strings   = $inspector->strings;
?>
<p>
	<?php esc_html_e( 'This is the response from the API.', 'updates-api-inspector' ); ?>
</p
<?php
if ( $strings['only_dotorg'] ) {
	?>
<p>
	<?php echo esc_html( $strings['only_dotorg'] ); ?>
</p>
	<?php
}
?>
<p>
	<?php echo esc_html( $strings['not_documented']['api_response'] ); ?>
</p>
