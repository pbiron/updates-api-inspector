<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
/**
 * Section description template.
 *
 * This template displays description of the `transient_as_set` section for the `core` update type.
 *
 * @package updates-api-inspector
 * @subpackage templates
 * @since 0.2.0
 */

namespace SHC\Updates_API_Inspector;

defined( 'ABSPATH' ) || die;

$inspector = Updates_API_Inspector::get_instance();
$strings   = $inspector->strings;
$function  = 'core' === $inspector->type ? 'wp_version_check' : "wp_update_{$inspector->type}";
switch ( $inspector->type ) {
	case 'core':
		$variable = '$updates';
		break;
	case 'plugins':
		$variable = '$new_option';
		break;
	case 'themes':
		$variable = '$new_update';
		break;
}
?>
<p>
	<?php
		printf(
			esc_html( $strings['value-of-in-call-in-function'] ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			"<code>{$variable}</code>",
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			"<code>set_site_transient( 'update_{$inspector->type}', {$variable} )</code>",
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$strings['code_reference'][ $function ]
		);
		?>
</p>
<?php $inspector->load_template( 'notice/transient-values-different' ); ?>
<p>
	<?php
		echo esc_html( $strings['not_documented']['transient'] );
	?>
</p>
