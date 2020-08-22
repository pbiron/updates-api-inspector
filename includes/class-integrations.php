<?php
/**
 * Integrations class.
 *
 * @since 0.2.0
 *
 * @package updates-api-inspector
 */

namespace SHC\Updates_API_Inspector;

defined( 'ABSPATH' ) || die;

/**
 * Class that manages integrations with other plugins and/or themes.
 *
 * @since 0.2.0
 */
class Integrations extends Singleton {
	/**
	 * Perform initialization tasks.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 *
	 * @action init
	 *
	 * @todo add integrations for other cap/user management plugins.
	 */
	public function init() {
		$plugin_integrations = array(
			'user-role-editor/user-role-editor.php',
		);

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( $plugin_integrations as $plugin ) {
			$class = implode( '_', array_map( 'ucfirst', explode( '-', dirname( $plugin ) ) ) );
			$class = sprintf( '%s\\%s_Integration', __NAMESPACE__, $class );

			// The class_exists() check is just to make sure we didn't accidentially
			// name the integration class incorrectly or something...or removed the
			// integration class and forgot to remove it from here.
			if ( is_plugin_active( $plugin ) && class_exists( $class ) ) {
				$class::get_instance();
			}
		}

		return;
	}
}
