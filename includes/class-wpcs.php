<?php
/**
 * WPCS Pretty Printer.
 *
 * @since 0.2.0
 *
 * @package updates-api-inspector
 */

namespace SHC\Updates_API_Inspector;

defined( 'ABSPATH' ) || die;

/**
 * Class to pretty print a variable, approximating WPCS.
 *
 * @since 0.2.0
 */
class WPCS {
	/**
	 * Pretty print a variable's value, approximating WPCS.
	 *
	 * @since 0.1.0
	 * @since 0.2.0 Moved here from the Plugin class. Also, now correctly handles stdClass in PHP < 7.3
	 *              and other first class objects and other corner cases.
	 *
	 * @param mixed $variable The variable to be pretty printed.
	 * @return string
	 */
	public static function pretty_print( $variable ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		$str = var_export( $variable, true );

		if ( is_null( $variable ) ) {
			// Special case the null variable.  Null as a value in arrays/objects will be handled below.
			$str = 'null';
		}

		// perform a series of regex replacements to "regularize" the
		// output of var_export().
		$str = preg_replace(
			array(
				'/=>\s+/',      // this includes newlines and leading whitespace on the next line.
				'/array\s+\(/', // for some arrays, var_export() adds the extra whitespace, others it doesn't.
				'/\d+ =>\s+/',  // strip numeric indexes from arrays.
				'/\(\s+\)/',    // ensure empty arrays appear on 1 line.
				'/\(array\(/',  // Ensure opening parenthesis of a multi-line function call is the last content on the line as in WPCS.
				'/\)\)/',       // Ensure closing parenthesis of a multi-line function call is the last content on the line as in WPCS.
				'/  NULL,/',
				'/ => NULL,/',
			),
			array(
				'=> ',
				'array(',
				'',
				'()',
				"(\narray(",
				")\n)",
				'  null,',
				' => null,',
			),
			$str
		);

		// Replace leading spaces with tabs and align '=>' ala WPCS using a little state machine.
		// @todo the state machine is pretty brittle, especially for '=>' alignment!!
		$lines                  = array_map( 'rtrim', explode( "\n", $str ) );
		$indent                 = 0;
		$last_char_on_prev_line = null;
		$array_stack            = array();

		foreach ( $lines as $i => &$line ) {
			$last_char_on_line = substr( $line, -1 );
			$line              = ltrim( $line );

			if ( '(' === $last_char_on_prev_line ) {
				// previous line was start of an array or object,
				// so increase indentation.
				$indent++;

				$array_stack[ $indent ][] = $i;
			} elseif ( '),' === $line || ')' === $line ) {
				// end of an array or object.
				// align '=>' based on the longest key in the array/object.
				// first, find the max length of keys.
				$max_length = 0;
				foreach ( $array_stack[ $indent ] as $k ) {
					if ( ! preg_match( '/ =>/', $lines[ $k ] ) ) {
						// This item in the array is numeric indexed, skip it.
						continue;
					}
					$key        = preg_replace( '/^\s+\'([^\']+)\'\s+=>.*$/U', '$1', $lines[ $k ] );
					$max_length = max( $max_length, strlen( $key ) );
				}

				if ( 0 < $max_length ) {
					// now that we know the max length key, do the actually alignment.
					foreach ( $array_stack[ $indent ] as $k ) {
						if ( ! preg_match( '/ =>/', $lines[ $k ] ) ) {
							// This item in the array is numeric indexed, skip it.
							continue;
						}
						$key         = preg_replace( '/^\s+\'([^\']+)\'\s+=>.*$/U', '$1', $lines[ $k ] );
						$padding     = str_repeat( ' ', $max_length - strlen( $key ) + 1 );
						$lines[ $k ] = preg_replace( '/^(\s+\'[^\']+\')(\s+)(=>.*$)/U', "\$1{$padding}\$3", $lines[ $k ] );
					}
				}

				// pop the array context off the stack.
				$array_stack[ $indent ] = array();

				// previous line was the end of an array or object,
				// so decrease indentation.
				$indent--;
			} else {
				$array_stack[ $indent ][] = $i;
			}

			$line                   = str_repeat( "\t", $indent ) . $line;
			$last_char_on_prev_line = $last_char_on_line;
		}

		return implode( "\n", $lines );
	}
}
