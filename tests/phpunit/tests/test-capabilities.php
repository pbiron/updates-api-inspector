<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Test capabilities.
 *
 * @since 0.2.0
 *
 * @package updates-api-inspector
 */

use SHC\Updates_API_Inspector\Updates_API_Inspector;

/**
 * Test that the capabilities checks work.
 *
 * @group capabilities
 * @group multisite
 *
 * @since 0.2.0
 */
class Test_Capabilties extends Updates_API_Inspector_UnitTestCase {
	/**
	 * Various users.
	 *
	 * @since 0.2.0
	 *
	 * @var WP_User[]
	 */
	protected static $users = array();

	/**
	 * Set up, before each test is run.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function setUp() {
		parent::setUp();

		$this->flush_roles();

		// Ensure Upates_API_Insector::user_has_cap() gets hooked.
		do_action( is_multisite() ? 'network_admin_menu' : 'admin_menu' );

		return;
	}

	/**
	 * Test that a user's capabilities.
	 *
	 * @dataProvider users_data
	 *
	 * @since 0.2.0
	 *
	 * @param WP_User $user The user to test capabilities for.
	 * @param bool    $expected True if $user should have our cap, false otherwise.
	 */
	public function test_capabilities( $user, $expected ) {
		$actual = user_can( $user, Updates_API_Inspector::CAPABILITY );
		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider for test_capabilities().
	 *
	 * @since 0.2.0
	 *
	 * @return array[] {
	 *     Data.
	 *
	 *     @type WP_User $0 A User to test capabilities for.
	 *     @type bool    $1 True if $user should have our cap, false otherwise.
	 * }
	 */
	public function users_data() {
		// We have to create the users here (rather than in wpSetUpBeforeClass() method, which would
		// seem more logical),  because PHPUnit calls this data provider before calling wpSetUpBeforeClass().
		// Add some custom roles.
		$roles = array(
			'no_caps'       => array(),
			'has_meta_cap'  => array( is_multisite() ? 'manage_network_options' : 'manage_options' => true ),
			'developer'     => array( Updates_API_Inspector::CAPABILITY => true ),
			'non_developer' => array( Updates_API_Inspector::CAPABILITY => false ),
		);

		foreach ( $roles as $role => $caps ) {
			remove_role( $role );
			add_role( $role, $role, $caps );
		}

		$all_roles = array_merge( array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ), array_keys( $roles ) );
		// create users for each of the built-in roles...with the standard caps those roles have.
		foreach ( $all_roles as $role ) {
			self::$users[ $role ] = $this->factory->user->create_and_get( array( 'role' => $role ) );
		}

		// Add our cap to a user whose role normally wouldn't have it.
		self::$users['no_caps_granted'] = $this->factory->user->create_and_get( array( 'role' => 'no_caps' ) );
		self::$users['no_caps_granted']->add_cap( Updates_API_Inspector::CAPABILITY, true );

		// Add what our meta-cap maps to to a user whose role normally wouldn't have it.
		self::$users['subcriber_with_meta_cap'] = $this->factory->user->create_and_get( array( 'role' => 'subscriber' ) );
		self::$users['subcriber_with_meta_cap']->add_cap( is_multisite() ? 'manage_network_options' : 'manage_options', true );

		if ( is_multisite() ) {
			// Make a subscriber that is also a super admin.
			self::$users['superadmin'] = $this->factory->user->create_and_get( array( 'role' => 'subscriber' ) );
			grant_super_admin( self::$users['superadmin']->ID );
		} else {
			self::$users['superadmin'] = $this->factory->user->create_and_get( array( 'role' => 'administrator' ) );
		}

		// Revoke our cap from a user whose role normally have it.
		self::$users['developer_revoked'] = $this->factory->user->create_and_get( array( 'role' => 'developer' ) );
		self::$users['developer_revoked']->add_cap( Updates_API_Inspector::CAPABILITY, false );

		return array(
			// Users with built-in roles, and the caps that normally go with those roles.
			array( self::$users['administrator'], is_multisite() ? false : true ),
			array( self::$users['editor'], false ),
			array( self::$users['author'], false ),
			array( self::$users['contributor'], false ),
			array( self::$users['subscriber'], false ),
			// Users with custom roles.
			array( self::$users['no_caps'], false ),
			array( self::$users['has_meta_cap'], true ),
			array( self::$users['developer'], true ),
			array( self::$users['non_developer'], false ),
			// User that is a "superadmin": in non-multisite, this will just be an adminstrator.
			array( self::$users['superadmin'], true ),
			// Users with roles that normally would not have the cap, but it has been explicitly granted.
			array( self::$users['subcriber_with_meta_cap'], true ),
			array( self::$users['no_caps_granted'], true ),
			// User with a role that normally would have the cap, but it has been reovked.
			array( self::$users['developer_revoked'], false ),
		);
	}

	/**
	 * Flush user roles.
	 *
	 * We want to make sure we're testing against the DB, not just in-memory data.
	 * This will flush everything and reload it from the DB.
	 *
	 * @global WP_Roles $wp_roles Global roles object.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	protected function flush_roles() {
		global $wp_roles;

		unset( $GLOBALS['wp_user_roles'] );
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_roles = new WP_Roles();

		return;
	}
}
