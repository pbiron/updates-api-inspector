<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Plugin Name: Updates API Inspector
 * Description: Inspect various aspects of the WordPress Updates API
 * Author: Paul V. Biron/Sparrow Hawk Computing
 * Author URI: https://sparrowhawkcomputing.com/
 * Plugin URI: https://wordpress.org/plugins/updates-api-inspector//
 * GitHub Plugin URI: https://github.com/pbiron/updates-api-inspector/
 * Release Asset: true
 * Network: true
 * Version: 0.2.0-beta-1
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package updates-api-inspector
 */

namespace SHC\Updates_API_Inspector;

defined( 'ABSPATH' ) || die;

require __DIR__ . '/vendor/autoload.php';

/**
 * Our main plugin class.
 *
 * @since 0.1.0
 *
 * @todo do a11y audit
 */
class Plugin extends Singleton {
	/**
	 * Our version number.
	 *
	 * @since 0.1.1
	 *
	 * @var string
	 */
	const VERSION = '0.2.0-beta-1';

	/**
	 * The full path to the main plugin file.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	const FILE = __FILE__;

	/**
	 * Add hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function add_hooks() {
		parent::add_hooks();

		add_action(
			is_multisite() ? 'network_admin_menu' : 'admin_menu',
			array( __NAMESPACE__ . '\\Updates_API_Inspector', 'admin_menu' )
		);

		return;
	}

	/**
	 * Perform initialization tasks.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 *
	 * @action plugins_loaded
	 */
	public function plugins_loaded() {
		// load integrations with other plugins and/or themes.
		Integrations::get_instance();

		return;
	}
}

// Instantiate ourself.
Plugin::get_instance();
