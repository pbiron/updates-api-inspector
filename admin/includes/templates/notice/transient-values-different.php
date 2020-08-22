<?php
/**
 * Admin notice template to let the user now the transite value "as set" is different than "as read".
 *
 * @package updates-api-inspector
 * @subpackage templates
 * @since 0.2.0
 */

namespace SHC\Updates_API_Inspector;

defined( 'ABSPATH' ) || die;

$inspector = Updates_API_Inspector::get_instance();
$strings   = $inspector->strings;

// Must use loose comparison here otherwise PHP will test that they
// are the same object which is not what we want to test.
// A Yoda Condition it is, but figure that out phpcs can not!
// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison, WordPress.PHP.YodaConditions.NotYoda
if ( $inspector->transient_as_read == $inspector->transient_as_set ) {
	return;
}

$other_section = 'transient_as_read' === $inspector->current_section
	? 'transient_as_set'
	: 'transient_as_read';
$sections      = $inspector->sections;
?>
<div class='notice notice-info inline'>
	<p>
		<?php
		printf(
			/* translators: link to another section of this page */
			esc_html__( 'The transient value here is different than it\'s value in %s.', 'updates-api-inspector' ),
			sprintf(
				'<a href="#%s">%s</a>',
				esc_attr( $other_section ),
				esc_html( $sections[ $other_section ] )
			)
		);
		echo ' ';
		printf(
			/* translators: 1: link to code reference, 2: link to code reference */
			esc_html__( 'There are a number of different ways this could happen, but it often is the result of something hooking into %1$s rather that %2$s.', 'updates-api-inspector' ),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$strings['code_reference'][ 'site_transient_update_' . $inspector->type ],
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$strings['code_reference'][ 'pre_set_site_transient_update_' . $inspector->type ]
		);
		?>
	</p>
</div>
