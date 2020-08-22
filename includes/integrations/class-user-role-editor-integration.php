<?php
/**
 * User Role Editor Integration class.
 *
 * @since 0.2.0
 *
 * @package updates-api-inspector
 */

namespace SHC\Updates_API_Inspector;

defined( 'ABSPATH' ) || die;

/**
 * Class to provide integration with User Role Editor.
 *
 * This allows our inspector cap to be listed in the "Custom capabilities" section of
 * {@link https://wordpress.org/plugins/user-role-editor/ User Role Editor}'s admin screen
 * and makes it easier for site admins to assign the cap to specific roles/users.
 *
 * @since 0.2.0
 */
class User_Role_Editor_Integration extends Singleton {
	/**
	 * The URE group our custom caps should appear in.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	const GROUP = 'updates-api-inspector';

	/**
	 * Add hooks.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	protected function add_hooks() {
		parent::add_hooks();

		add_filter( 'ure_built_in_wp_caps', array( $this, 'ure_caps' ) );
		add_filter( 'ure_capabilities_groups_tree', array( $this, 'ure_groups' ) );

		return;
	}

	/**
	 * Registers our capability group for the User Role Editor plugin.
	 *
	 * @since 0.2.0
	 *
	 * @param array[] $groups Array of existing groups.
	 * @return array[] Updated array of groups.
	 *
	 * @filter ure_capabilities_groups_tree
	 */
	public function ure_groups( $groups ) {
		$groups[ self::GROUP ] = array(
			'caption' => esc_html__( 'Updates API Inspector', 'updates-api-inspector' ),
			'parent'  => 'custom',
			'level'   => 2,
		);

		return $groups;
	}

	/**
	 * Register our capability for the User Role Editor plugin.
	 *
	 * @since 0.2.0
	 *
	 * @param array[] $caps Array of existing capabilities.
	 * @return array[] Updated array of capabilities.
	 *
	 * @filter ure_built_in_wp_caps
	 */
	public function ure_caps( $caps ) {
		$custom_caps = array(
			Updates_API_Inspector::CAPABILITY,
		);

		foreach ( $custom_caps as $cap ) {
			$caps[ $cap ] = array( 'custom', self::GROUP );
		}

		return $caps;
	}
}
