<?php
/**
 * Section description template.
 *
 * This template displays description of the `request` section for the `plugins` update type.
 *
 * @package updates-api-inspector
 * @subpackage templates
 * @since 0.2.0
 */

namespace SHC\Updates_API_Inspector;

defined( 'ABSPATH' ) || die;

$inspector   = Updates_API_Inspector::get_instance();
$strings     = $inspector->strings;
$request_url = $inspector->request_url;
$function    = 'core' === $inspector->type ? 'wp_version_check' : 'wp_update_' . $inspector->type;
?>
<p>
	<?php
		printf(
			esc_html( $strings['value-of-in-call-in-function'] ),
			'<code>$options</code>',
			"<code>wp_remote_post( '" . esc_html( $request_url ) . "', \$options )</code>",
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$strings['code_reference'][ $function ]
		);
		?>
</p>
<p>
	<?php
		printf(
			/* translators: variable name */
			esc_html__( '%s is json encoded when the request is made but has been decoded here to make the output easier to read.', 'updates-api-inspector' ),
			'<code>body</code>'
		);
		?>
</p>
<?php
if ( 'core' === $inspector->type ) {
	?>
<p>
	<?php
		printf(
			/* translators: 1: variable name, 2 URL query string separator, 3 variable name */
			esc_html__( '%1$s is transmitted as part of the query string (the part after %2$s in the URL) but is displayed as part of %3$s here to make the output easier to read.', 'updates-api-inspector' ),
			'<code>query_string</code>',
			'<code>?</code>',
			'<code>$options</code>'
		);
	?>
</p>
	<?php
}
?>
