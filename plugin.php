<?php

/**
 * Plugin Name: Updates API Inspector
 * Description: Inspect various aspects of the Updates API.
 * Author: Paul V. Biron/Sparrow Hawk Computing
 * Author URI: https://sparrowhawkcomputing.com/
 * Plugin URI: https://github.com/pbiron/updates-api-inspector
 * Version: 0.1.1
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace SHC\Updates_API_Inspector;

use WP_Error;

defined( 'ABSPATH' ) || die;

/**
 * Our main plugin class.
 *
 * @since 0.1.0
 *
 * @todo add a11y suport (e.g., screen-reader-text, @aria-xxx, etc).
 * @todo make the strings displayed easier to deal with, there is ALOT of
 *       repeation and it's a pain to keep the request-type specific strings
 *       in sync with one other.
 */
class Plugin {
	/**
	 * Our static instance.
	 *
	 * @since 0.1.0
	 */
	static $instance;

	/**
	 * Our version number.
	 *
	 * @since 0.1.1
	 *
	 * @var string
	 */
	const VERSION = '0.1.1';

	/**
	 * The URL of the current request.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	protected $request_url = '';

	/**
	 * The current request arguments.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	protected $request = '';

	/**
	 * The successful response for the current request.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	protected $response = '';

	/**
	 * The error response for the current request.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	protected $error = '';

	/**
	 * Add hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function add_hooks() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_print_styles-tools_page_updates-api-inspector', array( $this, 'print_styles' ) );
		add_action( 'load-tools_page_updates-api-inspector', array( $this, 'maybe_do_update_check' ) );
		add_action( 'load-tools_page_updates-api-inspector', array( $this, 'add_help' ) );

		return;
	}

	/**
	 * If this WP request is to run one of our update checks, verify the nonce
	 * and do the update check.
	 *
	 * This is run very early so that we can do a proper `wp_die()` screen if necessary.
	 * Waiting until {@see Plugin::render_tools_page()} it is too late to offer a
	 * standard WP UX.
	 *
	 * @since 0.1.1
	 *
	 * @return void
	 *
	 * @action load-tools_page_updates-api-inspector
	 */
	function maybe_do_update_check() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_REQUEST['type'] ) && in_array( $_REQUEST['type'], array( 'core', 'plugins' , 'themes' ), true ) ? wp_unslash( $_REQUEST['type'] ) : '';
		if ( $current ) {
			check_admin_referer( 'updates-api-inspector' );

			if ( ! $this->update_check( $current ) ) {
				$message = sprintf( __( 'Updates API Inspector: Unknown update type: %s', 'updates-api-inspector' ), $current );

				wp_die( $message, __( 'Something went wrong.', 'updates-api-inspector' ), 403 );
			}
		}

		return;
	}

	/**
	 * Query the Updates API for the type specified.
	 *
	 * This is where the magic happens :-)
	 *
	 * @since 0.1.0
	 *
	 * @param string $type The Updates API endpoint type.  Accepts 'core', 'plugins', 'themes'.
	 * @return bool True if an update check was performed, false otherwise.
	 */
	protected function update_check( $type ) {
		if ( ! in_array( $type, array( 'core', 'plugins', 'themes' ), true ) ) {
			return false;
		}

		$transient_name = "update_{$type}";

		// wp_version_check(), i.e., the core check, doesn't need this.
		if ( 'plugins' === $type || 'themes' === $type ) {
			// trick core into thinking the transient has not been set recently,
			// so that it's throttling mechanism doesn't cause it to return early.
			$current = get_site_transient( $transient_name );
			if ( ! is_object( $current ) ) {
				$current = new \stdClass;
			}
			$current->last_checked = time() - ( 12 * HOUR_IN_SECONDS ) - 1;
			set_site_transient( $transient_name, $current );
		}

		// add our capture hook.
		// priority is as late as possible, so that we capture any modifications made by
		// by anything else hooking into the various Requests hooks.
		add_action( 'http_api_debug', array( $this, 'capture' ), PHP_INT_MAX, 5 );

		// Query the API.
		switch( $type ) {
			case 'core':
				wp_version_check( array(), true );

				break;
			case 'plugins':
				wp_update_plugins();

				break;
			case 'themes':
				wp_update_themes();

				break;
		}

		// unhook our capture callback.
		remove_action( 'http_api_debug', array( $this, 'capture' ) );

		return true;
	}

	/**
	 * Capture the request and response.
	 *
	 * For the request, we only capture query args and `$options` explicitly passed
	 * to {@link https://developer.wordpress.org/reference/functions/wp_remote_post/ wp_remote_post()}.
	 *
	 * @since 0.1.0
	 *
	 * @param array|WP_Error $response 	HTTP response or WP_Error object.
	 * @param string $context           Context under which the hook is fired.
	 * @param string $class             HTTP transport used.
	 * @param array $parsed_args        HTTP request arguments.
	 * @param string $url               The request URL.
	 * @return void
	 *
	 * @action http_api_debug
	 */
	function capture( $response, $context, $class, $parsed_args, $url ) {
		// Even though this method is hooked/unhooked right around the core functions
		// that access the API, we check the URL just to be sure that we don't
		// capture incorrect info should something do additional wp_remote_xxx() calls
		// as part of hooks they fire while the API request is being processed.
		if ( ! preg_match( '@^https?://api.wordpress.org/(core/version-check|(plugins|themes)/update-check)/\d+(\.\d+)*/@', $url ) ) {
			return;
		}

		// First, capture the request.
		$this->url     = $url;
		$keys          = array(
			'timeout',
			'user-agent',
			'body',
			// headers is only used for core update check.
			'headers',
		);
		$this->request = array_filter(
			$parsed_args,
			function( $key ) use ( $keys ) {
				return in_array( $key, $keys, true );
			},
			ARRAY_FILTER_USE_KEY
		);
		if ( preg_match( '@^https?://api.wordpress.org/core/version-check/\d+(\.\d+)*/@', $url ) ) {
			// core update check.

			// parse the query string and add it to the request and then
			// strip off the query_string from our local copy of it.
			parse_str( parse_url( $url, PHP_URL_QUERY ), $this->request['query_string'] );
			$this->url = preg_replace( '@\?.*$@', '', $url );
		} else {
			// plugins or themes check.
			// since core only adds headers to core update checks,
			// remove it just in case some other hook added it...so as to be
			// more "educational".
			unset( $this->request['headers'] );
		}
		$this->request['body'] = array_map( 'json_decode', $this->request['body'] );

		// Now, capture the "response", whether an error or a successful response.
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$this->error    = $response;
			$this->response = '';
		} else {
			$this->error    = '';
			$this->response = json_decode( wp_remote_retrieve_body( $response ), true );
		}

		return;
	}

	/**
	 * Render our tools page.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 *
	 * @todo figure out a convient way to store the strings for the "explanation" in each
	 *       fieldset in an array and iterate over it because there is ALOT of duplication
	 *       and it is silly to repeat things over and over again.  core does that and
	 *       I hate it!
	 */
	function render_tools_page() {
		$checks = array(
			'core'    => __( 'Core', 'updates-api-inspector' ),
			'plugins' => __( 'Plugins', 'updates-api-inspector' ),
			'themes'  => __( 'Themes', 'updates-api-inspector' ),
		);

		$current = isset( $_REQUEST['type'] ) ? $_REQUEST['type'] : '';
		switch ( $current ) {
			case 'core':
				$core_function = 'wp_version_check';

				break;
			case 'plugins':
			case 'themes':
				$core_function = "wp_update_{$current}";

				break;
		}
 ?>

<div class='wrap updates-api-inspector'>
	<h1><?php esc_html_e( get_admin_page_title() ) ?></h1>

	<p><?php esc_html_e( 'This tool will allow you to inspect requests/responses from the Updates API.  Simply click on the request type...and see the results.', 'updates-api-inspector' ) ?></p>

	<ul id='request_types'>
		<?php
			foreach ( $checks as $type => $label ) {
				$href = esc_url( wp_nonce_url( add_query_arg( 'type', $type ), 'updates-api-inspector' ) );
		 ?>
		<li>
			<a href='<?php echo $href ?>'><?php esc_html_e( $label ) ?></a>
				<?php
					if ( $current === $type ) {
				 ?>
						<span class='results-displayed'>(<?php esc_html_e( 'Results displayed', 'updates-api-inispector' ) ?>)</span>
				<?php
					}
			 	 ?>
		</li>

		<?php
			}
	 ?>
	</ul>

	<?php

	if ( $current ) {
			$rows = 25;
	 ?>
	<div id='result'>
		<form>
			<fieldset id='request'>
				<legend><?php esc_html_e( 'Request', 'updates-api-inspector' ) ?></legend>
				<p>
					<?php // @todo I know, this will be hard for translators...will address that in a later version ?>
					<?php _e( "This is the value of <code>\$options</code> in <code>wp_remote_post( '{$this->url}', \$options )</code>, as called in <code>{$core_function}()</code>.", 'updates-api-inspector' ) ?>
				</p>
				<p>
					<?php _e( '<code>body</code> is json encoded when the request is made but has been decoded here to make the output easier to read.', 'updates-api-inspector' ) ?>
				</p>
				<?php
					switch ( $current ) {
						case 'core':
				 ?>
						<p>
							<?php _e( '<code>query_string</code> is transmitted as part of the query string (the part after <code>?</code> in the URL) but is displayed as part of <code>$options</code> here to make the output easier to read.', 'updates-api-inspector' ) ?>
						</p>
				<?php
							break;
					}
				 ?>
				<div class='core'>
				</div>
				<textarea rows='<?php echo $rows ?>' readonly><?php echo $this->pretty_print_var_export( var_export( $this->request, true ) ) ?></textarea>
			</fieldset>
			<?php
				if ( $this->error ) {
			 ?>
			<fieldset id='error'>
				<legend><?php esc_html_e( 'Error', 'updates-api-inspector' ) ?></legend>
				<p>
					<?php esc_html_e( 'An error occured during the request.', 'updates-api-inspector' ) ?>
				</p>
				<textarea rows='<?php echo $rows ?>' readonly><?php echo $this->pretty_print_var_export( var_export( $this->error, true ) ) ?></textarea>
			</fieldset>
			<?php
				} else {
			 ?>
			<fieldset id='success'>
				<fieldset id='response'>
					<legend><?php esc_html_e( 'API Response', 'updates-api-inspector' ) ?></legend>
					<p>
						<?php esc_html_e( 'This is the response from the API.', 'updates-api-inspector' ) ?>
					</p>
					<p>
						<?php _e( 'The fields in this response are not documented anywhere, so do not take what is displayed here as <em>all-and-only</em> what may ever be returned!!', 'updates-api-inspector' ) ?>
					</p>
					<?php
						switch ( $current ) {
							case 'plugins':
					 ?>
					<p>
						<?php _e( 'In general, only plugins hosted in the .org repo will be included; however, in rare cases, something may have hooked into one of the lower-level hooks to inject directly into the API response information about externally hosted plugins.', 'updates-api-inspector' ) ?>
					</p>
					<?php

								break;
							case 'themes':
					 ?>
					<p>
						<?php _e( 'In general, only themes hosted in the .org repo will be included; however, in rare cases, something may have hooked into one of the lower-level hooks to inject directly into the API response information about externally hosted themes.', 'updates-api-inspector' ) ?>
					</p>
					<?php

								break;
						}
					 ?>
					<textarea rows='<?php echo $rows ?>' readonly><?php echo $this->pretty_print_var_export( var_export( $this->response, true ) ) ?></textarea>
				</fieldset>
				<fieldset id='transient'>
					<legend><?php esc_html_e( 'Transient Value', 'updates-api-inspector' ) ?></legend>
					<?php
						switch ( $current ) {
							case 'core':
					 ?>
					<p>
						<?php _e( 'This is the value returned by <code>get_site_transient( \'update_core\' )</code>.', 'updates-api-inspector' ) ?>
					</p>
					<p>
						<?php _e( 'The fields in this transient are not documented anywhere, so do not take what is displayed here as <em>all-and-only</em> what may ever be returned!!', 'updates-api-inspector' ) ?>
					</p>
					<?php

								break;
							case 'plugins':
					 ?>
					<p>
						<?php _e( 'This is the value returned by <code>get_site_transient( \'update_plugins\' )</code>.', 'updates-api-inspector' ) ?>
					</p>
					<p>
						<?php _e( 'The fields in this transient are not documented anywhere, so do not take what is displayed here as <em>all-and-only</em> what may ever be returned!!', 'updates-api-inspector' ) ?>
					</p>
					<p>
						<?php _e( 'The values of <code>response</code> and <code>no_update</code> will contain plugins that are externally hosted (if any) and are arrays of <strong>objects</strong>.', 'updates-api-inspector' ) ?>
					</p>
					<div class='notice notice-warning inline'>
						<p>
							<?php _e( '<strong>Important</strong>: The Auto-updates UI, introduced in WordPress 5.5.0, will not work correctly for externally hosted plugins that do not populate <code>no_update</code> with information about their plugin!', 'updates-api-inspector' ) ?>
						</p>
					</div>
					<?php

								break;
							case 'themes':
					 ?>
					<p>
						<?php _e( 'This is the value returned by <code>get_site_transient( \'update_themes\' )</code>.', 'updates-api-inspector' ) ?>
					</p>
					<p>
						<?php _e( 'The fields in this transient are not documented anywhere, so do not take what is displayed here as <em>all-and-only</em> what may ever be returned!!', 'updates-api-inspector' ) ?>
					</p>
					<p>
						<?php _e( 'The values of <code>response</code> and <code>no_update</code> will contain themes that are externally hosted (if any) and are arrays of <strong>arrays</strong>.', 'updates-api-inspector' ) ?>
					</p>
					<div class='notice notice-warning inline'>
						<p>
							<?php _e( '<strong>Important</strong>: The Auto-updates UI, introduced in WordPress 5.5.0, will not work correctly for externally hosted themes that do not populate <code>no_update</code> with information about their theme!', 'updates-api-inspector' ) ?>
						</p>
					</div>
					<?php

								break;
						}
					 ?>
					<textarea rows='<?php echo $rows ?>' readonly><?php echo $this->pretty_print_var_export( var_export( get_site_transient( "update_{$current}" ), true ) ) ?></textarea>
				</fieldset>
			</fieldset>
			<?php
				}
			 ?>
		</form>
	</div>
	<?php
		}
	 ?>
</div>
<?php

		return;
	}

	/**
	 * Add our menu item to the Tools menu.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 *
	 * @action admin_menu
	 *
	 * @todo find a better cap.
	 */
	function admin_menu() {
		add_management_page(
			__( 'Updates API Inspector', 'updates-api-inspector' ),
			__( 'Updates API', 'updates-api-inspector' ),
			is_multisite() ? 'manage_network' : 'manage_options',
			'updates-api-inspector',
			array( $this, 'render_tools_page' )
		);

		return;
	}

	/**
	 * Print out styles.
	 *
	 * @since 0.1.1
	 *
	 * @return void
	 *
	 * @action admin_print_styles-tools_page_updates-api-inspector
	 */
	function print_styles() {
 ?>
<style id='updates-api-inspector'>
	/* make legend look close to what core does for h2,h3 */
	.updates-api-inspector legend {
		color: #23282d;
		display: block;
		font-size: 1.3em;
		font-weight: 600;
		margin-top: 1em;
	}

	.updates-api-inspector textarea {
		font-family: monospace;
		margin: 1em 0;
		resize: both;
		tab-size: 4;
		width: 100%;
	}

	.updates-api-inspector textarea[readonly] {
		background-color: #fff; /* override WP's forms.css, which uses #eee for textarea[readonly] */
	}

	#request_types {
		list-style: disc inside;
		margin-left: 2em;
	}

	.results-displayed {
		font-weight: 600;
	}
</style>
<?php

		return;
	}

	/**
	 * Add on-screen help.
	 *
	 * @since 0.1.1
	 *
	 * @return void
	 *
	 * @action load-tools_page_updates-api-inspector
	 */
	function add_help() {
		$screen = get_current_screen();

		$tabs = array(
			array(
				'title'   => __( 'General', 'updates-api-inspector' ),
				'id'      => 'general',
				'content' => '<p>' . __( 'This is a very preliminary release of this plugin, released in this early state because of confusion about the meaning of some fields in the <code>update_plugins</code> and <code>update_themes</code> site transients and the need for plugins/themes not hosted in the .org repo to properly populate the <code>no_update</code> fields therein for the Auto-updates UI in WordPress 5.5.0 to properly work for such plugins/themes.', 'updates-api-inspector' ) . '</p>' .
							'<p>' . __( 'Since there is no official documentation on the Updates API, it is hoped that this inspector will at least give developers a sense of the values in those fields for both .org plugins/themes and other externally hosted plugins/themes.', 'updates-api-inspector' ) . '</p>' .
							'<p>' . __( 'In future releases I hope to expand this help information to provide more context about the Updates API, in general, as well as provide more functionality.', 'updates-api-inspector' ) . '</p>',
			),
		);
		foreach ( $tabs as $tab ) {
			$screen->add_help_tab( $tab );
		}

		$sidebar =
			'<p><strong>' . __( 'For more information:', 'updates-api-inspector' ) . '</strong></p>' .
			'<p>' . __( '<a href="https://make.wordpress.org/core/2020/07/15/controlling-plugin-and-theme-auto-updates-ui-in-wordpress-5-5/">Controlling Plugin and Theme auto-updates UI in WordPress 5.5</a>' ) . '</p>' .
			'<p>' . __( '<a href="https://make.wordpress.org/core/2020/07/30/recommended-usage-of-the-updates-api-to-support-the-auto-updates-ui-for-plugins-and-themes-in-wordpress-5-5">Recommended usage of the Updates API to support the auto-updates UI for Plugins and Themes in WordPress 5.5</a>' ) . '</p>';

		$screen->set_help_sidebar( $sidebar );

		return;
	}

	/**
	 * Get our static instance.
	 *
	 * @since 0.1.0
	 */
	static function get_instance() {
		if  ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	function __construct() {
		if ( isset( self::$instance ) ) {
			return self::$instance;
		}

		$this->add_hooks();
	}

	/**
	 * Pretty print the output of PHP's
	 * {@link https://www.php.net/manual/en/function.var-export var_export()}.
	 *
	 * @since 0.1.0
	 *
	 * @param string $str
	 * @return string
	 *
	 * @todo align array keys and object properties based on the longest key/property, ala
	 *       WPCS.  Doing so is WAY too much trouble this early in the dev of this
	 *       plugin, but if I can later find an easy way to call phpcs, the sure,
	 *       will do that.
	 */
	protected function pretty_print_var_export( $str ) {
		$str = preg_replace( '/=>\s+/', '=> ', $str );
		$str = str_replace( 'array (', 'array(', $str );
		$str = preg_replace( '/\d+ =>\s+/', '  ', $str );
		$str = preg_replace( '/\(\s+\)/', '()', $str );

		// Replace leading spaces with tabs.
		for ( $i = 10; $i >= 2 ; $i-- ) {
			$str = preg_replace( "/^[ ]{{$i}}/m", str_repeat( "\t", $i / 2 ), $str );
		}

		return $str;
	}
}

// Instantiate ourself.
Plugin::get_instance();
