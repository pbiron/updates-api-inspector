<?php
/**
 * Inspector_Tool class.
 *
 * @package updates-api-inspector
 * @subpackage Administration
 * @since 0.2.0
 */

namespace SHC\Updates_API_Inspector;

use WP_Error;
use WP_User;

defined( 'ABSPATH' ) || die;

/**
 * Class to inspect Updates API requests, responses, and transients.
 *
 * @since 0.2.0
 */
class Updates_API_Inspector extends Singleton {
	/**
	 * The capability necessary to view this tool.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	const CAPABILITY = 'view_updates_api_inspector';

	/**
	 * Our admin directory.
	 *
	 * This is the equivalent of core's `WP_ADMIN` global constant.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	const ADMIN = __DIR__ . '/..';

	/**
	 * Our templates directory.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	const TEMPLATES_DIR = self::ADMIN . '/includes/templates';

	/**
	 * The update type currently displayed.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	public $type = '';

	/**
	 * The section currently being output.
	 *
	 * @since 0.2.0
	 *
	 * @var string
	 */
	public $current_section = '';

	/**
	 * Our update types.
	 *
	 * @since 0.2.0
	 * .
	 * @var string[] Keys are types, values are labels.
	 */
	public $types = array();

	/**
	 * Our sections.
	 *
	 * Section anchors *must* correspond to a public property of the
	 * {@see Update_Checker} class.
	 *
	 * @since 0.2.0
	 * .
	 * @var string[] Keys are section anchors, values are section titles.
	 */
	public $sections = array();

	/**
	 * Our template strings.
	 *
	 * @var string[]
	 */
	public $strings = array();

	/**
	 * The URL of the current request.
	 *
	 * @since 0.1.0
	 * @since 0.2.0 Moved here from the Plugin class and visibility/scope changed to public static.
	 *
	 * @var string
	 */
	public $request_url = '';

	/**
	 * The current request arguments.
	 *
	 * @since 0.1.0
	 * @since 0.2.0 Moved here from the Plugin class and visibility/scope changed to public static.
	 *
	 * @var array()
	 */
	public $request = array();

	/**
	 * The successful response for the current request.
	 *
	 * @since 0.1.0
	 * @since 0.2.0 Moved here from the Plugin class and visibility/scope changed to public static.
	 *
	 * @var array
	 */
	public $api_response = array();

	/**
	 * The error response for the current request.
	 *
	 * @since 0.1.0
	 * @since 0.2.0 Moved here from the Plugin class and visibility/scope changed to public static.
	 *
	 * @var WP_Error|array
	 */
	public $request_error = array();

	/**
	 * The site transient value as set.
	 *
	 * @since 0.1.1
	 * @since 0.2.0 Moved here from the Plugin class and visibility/scope changed to public static.
	 *
	 * @var object
	 */
	public $transient_as_set;

	/**
	 * The site transient value as read.
	 *
	 * @since 0.1.1
	 * @since 0.2.0
	 *
	 * @var object
	 */
	public $transient_as_read;

	/**
	 * Query the Updates API.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public function check_updates() {
		$update_checker = Update_Checker::get_instance();
		$ret            = $update_checker->do_check( $this->type );

		// Copy the update checker's return values to our properties.
		foreach ( $ret as $key => $val ) {
			$this->{$key} = $val;
		}

		return;
	}

	/**
	 * Constructor.
	 *
	 * Note that `$type` will only be passed on the very first invocation of `self::get_instance()`,
	 * which should be in `admin/updates-api-inspector.php`.
	 *
	 * @since 0.2.0
	 *
	 * @param string $type The update type to be inspected.  Accepts 'core',
	 *                     'plugins', 'themes' or the empty string.
	 */
	protected function __construct( $type ) {
		parent::__construct();

		$this->type = $type;

		// Setup the update types.
		$this->types = array(
			'core'    => __( 'Core', 'updates-api-inspector' ),
			'plugins' => __( 'Plugins', 'updates-api-inspector' ),
			'themes'  => __( 'Themes', 'updates-api-inspector' ),
		);

		// Setup the section anchors and titles.
		$this->sections = array(
			'request'           => __( 'Request', 'updates-api-inspector' ),
			'request_error'     => __( 'Request Error', 'updates-api-inspector' ),
			'api_response'      => __( 'API Response', 'updates-api-inspector' ),
			'transient_as_set'  => __( 'Transient Value As Set', 'updates-api-inspector' ),
			'transient_as_read' => __( 'Transient Value As Read', 'updates-api-inspector' ),
		);

		// Setup the strings used in the various templates.
		// generally, these are strings that vary only slightly depending on the update type
		// that is being inspected.  They are collected here to make it easier to ensure that
		// when the string for one update type is changed, those for the other types are
		// changed accordingly.
		// There are other strings used in some templates that don't depending on the update
		// type being displayed and those aren't included here.
		$this->strings = array(
			'preface'                      => array(
				'core'    => __( 'The results of querying for core updates are displayed below.', 'updates-api-inspector' ),
				'plugins' => __( 'The results of querying for plugin updates are displayed below.', 'updates-api-inspector' ),
				'themes'  => __( 'The results of querying for theme updates are displayed below.', 'updates-api-inspector' ),
			),
			'auto_update_filter'           => array(
				/* translators: link to code reference */
				'core'    => __( 'For auto-updates, the %s filter is applied and a falsey return value will prevent core from auto-updating.', 'updates-api-inspector' ),
				/* translators: link to code reference */
				'plugins' => __( 'For auto-updates, the %s filter is applied and a falsey return value will prevent plugins from auto-updating.', 'updates-api-inspector' ),
				/* translators: link to code reference */
				'themes'  => __( 'For auto-updates, the %s filter is applied and a falsey return value will prevent themes from auto-updating.', 'updates-api-inspector' ),
			),
			'updates_available_field'      => array(
				/* translators: variable name */
				'core'    => __( 'In this value, %s is what determines which core updates are available, both manually in the dashboad and as auto-updates.', 'updates-api-inspector' ),
				/* translators: variable name */
				'plugins' => __( 'In this value, %s is what determines which plugin updates are available, both manually in the dashboad and as auto-updates.', 'updates-api-inspector' ),
				/* translators: variable name */
				'themes'  => __( 'In this value, %s is what determines which theme updates are available, both manually in the dashboad and as auto-updates.', 'updates-api-inspector' ),
			),
			'only_dotorg'                  => array(
				'core'    => '',
				'plugins' => __( 'In general, only plugins hosted in the .org repo will be included here; however, in rare cases, something may have hooked into one of the lower-level hooks to inject directly into the API response information about externally hosted plugins.', 'updates-api-inspector' ),
				'themes'  => __( 'In general, only themes hosted in the .org repo will be included here; however, in rare cases, something may have hooked into one of the lower-level hooks to inject directly into the API response information about externally hosted themes.', 'updates-api-inspector' ),
			),
			'externally_hosted'            => array(
				'core'    => '',
				/* translators: 1: variable name, 2: variable name, 3: PHP data type */
				'plugins' => __( 'The values of %1$s and %2$s may contain plugins that are externally hosted (if any) and are arrays of %3$s.', 'updates-api-inspector' ),
				/* translators: 1: variable name, 2: variable name, 3: PHP data type */
				'themes'  => __( 'The values of %1$s and %2$s may contain themes that are externally hosted (if any) and are arrays of %3$s.', 'updates-api-inspector' ),
			),
			'value-of-in-call-in-function' =>
				/* translators: 1: a variable name, 2: function call, 3: link to code reference */
				__( 'This is the value of %1$s in %2$s as called in %3$s.', 'updates-api-inspector' ),
			'function_call'                =>
				/* translators: function call */
				__( 'This is the value returned by %s.', 'updates-api-inspector' ),
			'not_documented'               => array(
				'api_response' => __( 'The fields in this API response are not documented anywhere, so do not take what is displayed here as all-and-only what may ever be returned!!', 'updates-api-inspector' ),
				'transient'    => __( 'The fields in this transient are not documented anywhere, so do not take what is displayed here as all-and-only what may ever be returned!!', 'updates-api-inspector' ),
			),
			// The following are links to various things in the WordPress Code Reference.
			// Although some of them may only be used once in this plugin (i.e., in a single template),
			// it makes sense to collect them all here to help ensure that all such Code Reference
			// links use the same "form".
			'code_reference'               => array(
				'wp_version_check'                      =>
					sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( 'https://developer.wordpress.org/reference/functions/wp_version_check/' ),
						'<code>wp_version_check()</code>'
					),
				'wp_update_plugins'                     =>
					sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( 'https://developer.wordpress.org/reference/functions/wp_update_plugins/' ),
						'<code>wp_update_plugins()</code>'
					),
				'wp_update_themes'                      =>
					sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( 'https://developer.wordpress.org/reference/functions/wp_update_themes/' ),
						'<code>wp_update_themes()</code>'
					),
				'site_transient_update_core'            =>
					sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( 'https://developer.wordpress.org/reference/hooks/site_transient_transient/' ),
						'<code>site_transient_update_core</code>'
					),
				'pre_set_site_transient_update_core'    =>
					sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( 'https://developer.wordpress.org/reference/hooks/pre_set_site_transient_transient/' ),
						'<code>pre_set_site_transient_update_core</code>'
					),
				'site_transient_update_plugins'         =>
					sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( 'https://developer.wordpress.org/reference/hooks/site_transient_transient/' ),
						'<code>site_transient_update_plugins</code>'
					),
				'pre_set_site_transient_update_plugins' =>
					sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( 'https://developer.wordpress.org/reference/hooks/pre_set_site_transient_transient/' ),
						'<code>pre_set_site_transient_update_plugins</code>'
					),
				'site_transient_update_themes'          =>
					sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( 'https://developer.wordpress.org/reference/hooks/site_transient_transient/' ),
						'<code>site_transient_update_themes</code>'
					),
				'pre_set_site_transient_update_themes'  =>
					sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( 'https://developer.wordpress.org/reference/hooks/pre_set_site_transient_transient/' ),
						'<code>pre_set_site_transient_update_themes</code>'
					),
				'auto_update_core'                      =>
					sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( 'https://developer.wordpress.org/reference/hooks/auto_update_type/' ),
						'<code>auto_update_core</code>'
					),
				'auto_update_plugin'                    =>
					sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( 'https://developer.wordpress.org/reference/hooks/auto_update_type/' ),
						'<code>auto_update_plugin</code>'
					),
				'auto_update_theme'                     =>
					sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( 'https://developer.wordpress.org/reference/hooks/auto_update_type/' ),
						'<code>auto_update_theme</code>'
					),
			),
		);

		// For strings that are update type-specific, set their value to be that for the
		// current update type.
		if ( $this->type ) {
			foreach ( $this->strings as $string => $strings ) {
				if ( is_array( $strings ) && ! empty( array_intersect( array_keys( $this->types ), array_keys( $strings ) ) ) ) {
					$this->strings[ $string ] = $strings[ $this->type ];
				}
			}
		}

		return;
	}

	/**
	 * Load a template.
	 *
	 * As of now, the templating system is **very** experimental
	 * and may not survive in it's current or any form.
	 *
	 * @since 0.2.0
	 *
	 * @param string $template The template to load.
	 * @return void
	 *
	 * @todo if the templating system survives, then provide more documentation
	 *       about it.
	 * @todo if the templating system survives, consider allowing plugins to
	 *       override templates (ala Woo templates).
	 */
	public function load_template( $template ) {
		$template = sprintf( '%s/%s.php', self::TEMPLATES_DIR, $template );
		if ( file_exists( $template ) ) {
			require $template;
		}

		return;
	}

	/**
	 * Get the sections to be output for the current request.
	 *
	 * The sections will differ depending on whether the request
	 * resulted in an error or not.
	 *
	 * @since 0.2.0
	 *
	 * @return string[] Keys are section IDs, values are section labels.
	 */
	public function get_sections() {
		$sections = $this->sections;

		if ( $this->request_error ) {
			// the request generated an error response, we don't
			// show the these sections.
			$sections = array_filter(
				$sections,
				function( $key ) {
					return in_array( $key, array( 'request', 'request_error' ), true );
				},
				ARRAY_FILTER_USE_KEY
			);
		} else {
			unset( $sections['request_error'] );
		}

		return $sections;
	}

	/**
	 * Ensure that the `noheader` query arg is added to our tool URL(s).
	 *
	 * This allows us to write `admin/updates-api-inspector.php` much closer to the way
	 * core admin screen driver files are written.  In particular, it allows
	 * caps/nonce/etc done early in that file and fail to result in "normal"
	 * wp_die() screens just like core screens where those kind of checks fail.
	 *
	 * It also makes it easier to setup screen help.
	 *
	 * `admin/updates-api-inspector.php` then just needs to manually load wp-admin/admin-header.php
	 * once it's done setting things up but before it produces any "real" output.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 *
	 * @action load-{$hook_suffix}, where $hook_suffix differs dependings on whether we're in
	 *         multisite or not.
	 */
	public static function maybe_add_noheader_arg() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['noheader'] ) ) {
			wp_safe_redirect( add_query_arg( 'noheader', '' ) );

			exit;
		}

		return;
	}

	/**
	 * Render our page.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public static function render_page() {
		self::register_styles();

		require self::ADMIN . '/updates-api-inspector.php';

		return;
	}

	/**
	 * Add our menu item.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 *
	 * @action admin_menu
	 */
	public static function admin_menu() {
		// We have to add this hook here (rather than, e.g., in an add_hooks() method once
		// we're instantiated) because add_submenu_page() will check the cap and if it
		// isn't mapped now the menu won't show up for the current user.
		add_filter( 'user_has_cap', array( __CLASS__, 'user_has_cap' ), 10, 4 );

		$page_title = _x( 'Updates API Inspector', 'Page title', 'updates-api-inspector' );
		$menu_title = _x( 'Updates API Inspector', 'Menu title', 'updates-api-inspector' );
		$cap        = self::CAPABILITY;
		$slug       = 'updates-api-inspector';
		$callback   = array( __CLASS__, 'render_page' );

		if ( ! is_multisite() ) {
			// child of Tools.
			$hook_suffix = add_management_page( $page_title, $menu_title, $cap, $slug, $callback );
		} else {
			// Top-level.
			// @todo if/when core adds a Tools menu in Network Admin, then
			//       add ourselves there instead of at the top level.
			$hook_suffix = add_menu_page( $page_title, $menu_title, $cap, $slug, $callback, 'dashicons-update' );
		}

		add_action( "load-{$hook_suffix}", array( __CLASS__, 'maybe_add_noheader_arg' ) );

		return;
	}

	/**
	 * Dynamically filter a user's capabilities.
	 *
	 * This is used to:
	 *  - Grant our cap to the user if they have the ability to manage options (or manage network options in multisite).
	 *
	 * This does not get called for Super Admins.
	 *
	 * @param bool[]   $allcaps Array of key/value pairs where keys represent a capability name
	 *                          and boolean values represent whether the user has that capability.
	 * @param string[] $caps    Required primitive capabilities for the requested capability.
	 * @param array    $args {
	 *     Arguments that accompany the requested capability check.
	 *
	 *     @type string    $0 Requested capability.
	 *     @type int       $1 Concerned user ID.
	 *     @type mixed  ...$2 Optional second and further parameters, typically object ID.
	 * }
	 * @param WP_User  $user    The user object.
	 * @return bool[] User's capabilities.
	 *
	 * @filter user_has_cap
	 */
	public static function user_has_cap( $allcaps, $caps, $args, $user ) {
		if ( self::CAPABILITY !== $args[0] ) {
			// Not our cap.
			// Nothing to do, so bail.
			return $allcaps;
		}

		if ( array_key_exists( self::CAPABILITY, $allcaps ) ) {
			// Our cap exists for the user (i.e., granted or revoked in URE or similar plugins).
			// Nothing to do, so bail.
			return $allcaps;
		}

		if ( user_can( $args[1], is_multisite() ? 'manage_network_options' : 'manage_options' ) ) {
			// Grant the user our cap.
			$allcaps[ self::CAPABILITY ] = true;
		}

		return $allcaps;
	}

	/**
	 * Register our styles.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public static function register_styles() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$rtl    = is_rtl() ? '-rtl' : '';

		wp_register_style(
			'updates-api-inspector',
			plugins_url( "assets/css/updates-api-inspector{$rtl}{$suffix}.css", Plugin::FILE ),
			array(),
			Plugin::VERSION
		);

		return;
	}
}
