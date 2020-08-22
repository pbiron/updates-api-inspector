<?php
/**
 * Base class for all unit tests.
 *
 * @since 0.2.0
 *
 * @package updates-api-inspector
 */

use SHC\Updates_API_Inspector\Update_Checker;

/**
 * Base class for all unit tests.
 *
 * @since 0.2.0
 */
class Updates_API_Inspector_UnitTestCase extends WP_UnitTestCase {
	/**
	 * Reset the Update_Checker singleton to an initial state between tests.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		$checker = Update_Checker::get_instance();
		$checker->do_check( '' );

		return;
	}
}
