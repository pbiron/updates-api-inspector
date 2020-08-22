<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase
/**
 * Section description template.
 *
 * This template displays description of the `transient_as_read` section for the `core` update type.
 *
 * @package updates-api-inspector
 * @subpackage templates
 * @since 0.2.0
 */

namespace SHC\Updates_API_Inspector;

defined( 'ABSPATH' ) || die;

$inspector = Updates_API_Inspector::get_instance();
$strings   = $inspector->strings;
$field     = 'core' === $inspector->type ? 'updates' : 'response';
switch ( $inspector->type ) {
	case 'core':
		$filter = 'auto_update_core';
		break;
	case 'plugins':
		$filter   = 'auto_update_plugin';
		$php_type = esc_html_x( 'objects', 'PHP data type', 'updates-api-inspector' );
		break;
	case 'themes':
		$filter   = 'auto_update_theme';
		$php_type = esc_html_x( 'arrays', 'PHP data type', 'updates-api-inspector' );
		break;
}
?>
<p>
	<?php
		printf(
			esc_html( $strings['function_call'] ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			"<code>get_site_transient( 'update_{$inspector->type}' )</code>"
		);
		?>
</p>
<p>
	<?php
		printf(
			esc_html( $strings['updates_available_field'] ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			"<code>{$field}</code>"
		);
		echo ' ';
		printf(
			esc_html( $strings['auto_update_filter'] ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$strings['code_reference'][ $filter ]
		);
		?>
</p>
<?php
$inspector->load_template( 'notice/auto-update-wp-cron' );

if ( in_array( $inspector->type, array( 'plugins', 'themes' ), true ) ) {
	?>
<p>
	<?php
		printf(
			esc_html( $strings['externally_hosted'] ),
			'<code>response</code>',
			'<code>no_update</code>',
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			"<strong>{$php_type}</strong>"
		);
	?>
</p>
	<?php
}

$inspector->load_template( 'notice/no_update-must-be-populated' );
$inspector->load_template( 'notice/transient-values-different' );
?>
<p>
	<?php
		echo esc_html( $strings['not_documented']['transient'] );
	?>
</p>

