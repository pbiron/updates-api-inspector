<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

/**
 * Test the pretty printer..
 *
 * @since 0.2.0
 *
 * @package updates-api-inspector
 */

use SHC\Updates_API_Inspector\Plugin;
use SHC\Updates_API_Inspector\Updates_API_Inspector;
use SHC\Updates_API_Inspector\WPCS;

/**
 * Test that the pretty printer produces output that passes WPCS.
 *
 * @group pretty-print
 *
 * @since 0.2.0
 */
class Test_Pretty_Print extends Updates_API_Inspector_UnitTestCase {
	/**
	 * Test that primitive types are pretty printed correctly.
	 *
	 * @since 0.2.0
	 */
	public function test_primitive_types() {
		$actual = WPCS::pretty_print( true );
		$this->assertWPCS( $actual );

		$actual = WPCS::pretty_print( false );
		$this->assertWPCS( $actual );

		$actual = WPCS::pretty_print( null );
		$this->assertWPCS( $actual );

		$actual = WPCS::pretty_print( 'this is a test' );
		$this->assertWPCS( $actual );

		$actual = WPCS::pretty_print( 1 );
		$this->assertWPCS( $actual );

		$actual = WPCS::pretty_print( 2.45 );
		$this->assertWPCS( $actual );
	}

	/**
	 * Test that arrays are pretty printed correctly.
	 *
	 * @since 0.2.0
	 */
	public function test_arrays() {
		// Numeric Indexed array.
		$iarray = array(
			'this',
			'is',
			'a',
			'test',
			true,
			false,
			null,
		);
		$actual = WPCS::pretty_print( $iarray );
		$this->assertWPCS( $actual );

		// Associative array.
		$aarray = array(
			'this' => 'that',
			'is'   => true,
			'a'    => false,
			'test' => null,
		);
		$actual = WPCS::pretty_print( $aarray );
		$this->assertWPCS( $actual );

		// Mixed indexed/associative array, associative first.
		$marray = $aarray + $iarray;
		$actual = WPCS::pretty_print( $marray );
		$this->assertWPCS( $actual );

		// Mixed indexed/associative array, indexed first.
		$marray = $iarray + $aarray;
		$actual = WPCS::pretty_print( $marray );
		$this->assertWPCS( $actual );

		// Mixed indexed/associative array, interspersed.
		// We use a temp var just in case we add other checks later and want to
		// still use the original $iarray and $aarray variables.
		$tmp = $aarray;
		array_splice( $tmp, 2, 0, $iarray );
		$marray = $tmp;
		unset( $tmp );
		$actual = WPCS::pretty_print( $marray );
		$this->assertWPCS( $actual );
	}

	/**
	 * Test that objects are pretty printed correctly.
	 *
	 * @since 0.2.0
	 */
	public function test_objects() {
		// StdClass.
		$object = (object) array(
			'this' => 'is',
			'a'    => array(
				'test'      => 'of',
				'the'       => 'emergency',
				'broadcast' => (object) array(
					'system' => null,
				),
			),
		);
		$actual = WPCS::pretty_print( $object );
		$this->assertWPCS( $actual );

		// First-class object.
		$object = Updates_API_Inspector::get_instance( 'core' );
		$actual = WPCS::pretty_print( $object );
		$this->assertWPCS( $actual );

		// WP_Error, another first-class object.
		$object = new WP_Error( 'test', 'This is a test', array( 'foo' => 'bar' ) );
		$actual = WPCS::pretty_print( $object );
		$this->assertWPCS( $actual );
	}

	/**
	 * Asserts that a string will pass WPCS checks (when wrapped in an appropriate "driver" PHP file).
	 *
	 * @since 0.2.0
	 *
	 * @param string $actual The pretty printed value.
	 *
	 * @todo Figure out if there is a way to directly call phpcs instead of
	 *       doing through the command-line.
	 */
	protected function assertWPCS( $actual ) {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			WP_Filesystem();
		}

		$file = wp_tempnam( 'uai' );
		$wp_filesystem->put_contents( $file, "<?php\n\$var = {$actual};\n" );

		// Set the coding standard on the command line so phpcs doesn't use this plugins phpcs.xml config file.
		$cmd = sprintf(
			'%s/vendor/bin/phpcs -s --standard=WordPress --exclude=Squiz.Commenting.FileComment,WordPress.Files.FileName %s',
			dirname( Plugin::FILE ),
			$file
		);

		$exit_code = 0;
		// Prevent phpcs from producing output.
		ob_start();
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_system
		system( $cmd, $exit_code );
		ob_get_clean();

		$wp_filesystem->delete( $file );

		$this->assertSame( 0, $exit_code, 'Pretty print: WPCS check failed' );
	}
}
