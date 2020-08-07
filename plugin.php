<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * Plugin Name: Updates API Inspector
 * Description: Inspect various aspects of the Updates API.
 * Author: Paul V. Biron/Sparrow Hawk Computing
 * Author URI: https://sparrowhawkcomputing.com/
 * Plugin URI: https://wordpress.org/plugins/updates-api-inspector/
 * GitHub Plugin URI: https://github.com/pbiron/updates-api-inspector
 * Network: true
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
	 *
	 * @var Plugin
	 */
	protected static $instance;

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
	 * @var array()
	 */
	protected $request = array();

	/**
	 * The successful response for the current request.
	 *
	 * @since 0.1.0
	 *
	 * @var array
	 */
	protected $response = array();

	/**
	 * The site transient value as set.
	 *
	 * @since 0.1.1
	 *
	 * @var object
	 */
	protected $transient_as_set;

	/**
	 * The error response for the current request.
	 *
	 * @since 0.1.0
	 *
	 * @var WP_Error|array
	 */
	protected $error;

	/**
	 * Add hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	protected function add_hooks() {
		if ( ! is_multisite() ) {
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'load-tools_page_updates-api-inspector', array( $this, 'maybe_do_update_check' ) );
			add_action( 'load-tools_page_updates-api-inspector', array( $this, 'add_help' ) );
			add_action( 'admin_print_styles-tools_page_updates-api-inspector', array( $this, 'print_styles' ) );
		} else {
			add_action( 'network_admin_menu', array( $this, 'admin_menu' ) );
			add_action( 'load-toplevel_page_updates-api-inspector', array( $this, 'maybe_do_update_check' ) );
			add_action( 'load-toplevel_page_updates-api-inspector', array( $this, 'add_help' ) );
			add_action( 'admin_print_styles-toplevel_page_updates-api-inspector', array( $this, 'print_styles' ) );
		}

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
	public function maybe_do_update_check() {
		// just in case the user has bypassed the caps check for the menu item.
		if ( ! current_user_can( is_multisite() ? 'manage_network_options' : 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to inspect the Updates API.', 'updates-api-inspector' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
		$type = isset( $_REQUEST['type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['type'] ) ) : '';
		if ( $type ) {
			check_admin_referer( 'updates-api-inspector' );

			if ( ! in_array( $type, array( 'core', 'plugins', 'themes' ), true ) ) {
				wp_die(
					esc_html(
						sprintf(
							/* translators: Updates API endpoint. */
							__( 'Unknown Updates API Inspector type: %s', 'updates-api-inspector' ),
							$type
						)
					)
				);
			}

			$this->update_check( $type );
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
	 * @return void
	 */
	protected function update_check( $type ) {
		$transient_name = "update_{$type}";

		// wp_version_check(), i.e., the core check, doesn't need this.
		if ( 'plugins' === $type || 'themes' === $type ) {
			// trick core into thinking the transient has not been set recently,
			// so that it's throttling mechanism doesn't cause it to return early.
			$current = get_site_transient( $transient_name );
			if ( ! is_object( $current ) ) {
				$current = new \stdClass();
			}
			$current->last_checked = time() - ( 12 * HOUR_IN_SECONDS ) - 1;
			set_site_transient( $transient_name, $current );
		}

		// add our capture hooks.
		// priority is as late as possible, so that we capture any modifications made by
		// by anything else hooking into the various Requests hooks.
		add_action( 'http_api_debug', array( $this, 'capture_request_response' ), PHP_INT_MAX, 5 );
		add_action( "set_site_transient_{$transient_name}", array( $this, 'capture_transient_as_set' ), PHP_INT_MAX );

		// Query the API.
		switch ( $type ) {
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

		// remove our capture hooks.
		remove_action( "set_site_transient_{$transient_name}", array( $this, 'capture_transient_as_set' ), PHP_INT_MAX );
		remove_action( 'http_api_debug', array( $this, 'capture_request_response' ) );

		return;
	}

	/**
	 * Capture the API request and response.
	 *
	 * For the request, we only capture query args and `$options` explicitly passed
	 * to {@link https://developer.wordpress.org/reference/functions/wp_remote_post/ wp_remote_post()}.
	 *
	 * @since 0.1.0
	 *
	 * @param array|WP_Error $response    HTTP response or WP_Error object.
	 * @param string         $context     Context under which the hook is fired.
	 * @param string         $class       HTTP transport used.
	 * @param array          $parsed_args HTTP request arguments.
	 * @param string         $url         The request URL.
	 * @return void
	 *
	 * @action http_api_debug
	 */
	public function capture_request_response( $response, $context, $class, $parsed_args, $url ) {
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
			parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $this->request['query_string'] );
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
			$this->response = array();
		} else {
			$this->error    = array();
			$this->response = json_decode( wp_remote_retrieve_body( $response ), true );
		}

		return;
	}

	/**
	 * Capture the site transient as set.
	 *
	 * Note that this will be called multiple times during each update check,
	 * because of the way those are done (1 or 2 times to update the `last_checked` value,
	 * and once for setting the actual value).  Ulimately, the last call is the one
	 * shown in our UI.
	 *
	 * @since 0.1.1
	 *
	 * @param mixed $value Site transient value.
	 * @return void
	 *
	 * @action set_site_transient_update_core, set_site_transient_update_plugins,
	 *         set_site_transient_update_themes
	 */
	public function capture_transient_as_set( $value ) {
		$this->transient_as_set = $value;

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
	public function render_tools_page() {
		if ( ! current_user_can( is_multisite() ? 'manage_network_options' : 'manage_options' ) ) {
			// this won't produce a "normal" WP die screen (it's too late for that),
			// but it's better than letting the user see the inspection.
			// @see Plugin::maybe_do_update_check().
			wp_die( esc_html__( 'Sorry, you are not allowed to inspect the Updates API.', 'updates-api-inspector' ) );
		}

		$checks = array(
			'core'    => __( 'Core', 'updates-api-inspector' ),
			'plugins' => __( 'Plugins', 'updates-api-inspector' ),
			'themes'  => __( 'Themes', 'updates-api-inspector' ),
		);

		// phpcs:ignore WordPress.Security.NonceVerification.NoNonceVerification
		$current = isset( $_REQUEST['type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['type'] ) ) : '';
		if ( $current ) {
			// The nonce check should aready have happened in {@see Plugin::maybe_do_update_check()}
			// but it doesn't help to check it again, just to be sure.
			check_admin_referer( 'updates-api-inspector' );
		}
		?>

<div class='wrap updates-api-inspector'>
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<p><?php esc_html_e( 'This tool will allow you to inspect requests/responses from the Updates API and the site transients core sets as a result.', 'updates-api-inspector' ); ?></p>

	<nav role='tablist' class='nav-tab-wrapper wp-clearfix'>
		<?php
		foreach ( $checks as $type => $label ) {
			$href = wp_nonce_url( add_query_arg( 'type', $type ), 'updates-api-inspector' );

			$class  = 'nav-tab';
			$class .= $current === $type ? ' nav-tab-active' : '';
			?>
		<a href='<?php echo esc_url( $href ); ?>' role='tab' aria-selected='<?php echo ( $current === $type ? 'true' : 'false' ); ?>' class='<?php echo esc_attr( $class ); ?>'><?php echo esc_html( $label ); ?></a>
			<?php
		}
		?>
	</nav>

		<?php
		if ( $current ) {
			?>
	<div id='result'>
			<?php
			switch ( $current ) {
				case 'core':
					?>
		<p>
					<?php
					esc_html_e( 'The results of querying for core updates are displayed below. You can jump to a specific section with any of the links.', 'updates-api-inspector' );
					?>
		</p>
					<?php
					break;
				case 'plugins':
					?>
		<p>
					<?php
					esc_html_e( 'The results of querying for plugin updates are displayed below.', 'updates-api-inspector' );
					?>
		</p>
					<?php
					break;
				case 'themes':
					?>
		<p>
					<?php
					esc_html_e( 'The results of querying for theme updates are displayed below.', 'updates-api-inspector' );
					?>
		</p>
					<?php
					break;
			}
			?>
			<?php $this->jump_links( 'top' ); ?>

		<section id='request'>
			<h3><?php esc_html_e( 'Request', 'updates-api-inspector' ); ?></h3>

			<?php
			switch ( $current ) {
				case 'core':
					?>
			<p>
					<?php
					printf(
						/* translators: 1: a variable name, 2: function call, 3: link to code reference */
						esc_html__( 'This is the value of %1$s in %2$s, as called in %3$s.', 'updates-api-inspector' ),
						'<code>$options</code>',
						"<code>wp_remote_post( '" . esc_html( $this->url ) . "', \$options )</code>",
						// @todo in RTL, the '()' part of the link text appears at the other end of the line.  I don't know why.
						sprintf(
							'<a href="%s">wp_version_check()</a>',
							esc_url( __( 'https://developer.wordpress.org/reference/functions/wp_version_check/', 'updates-api-inspector' ) )
						)
					);
					?>
			</p>
			<p>
					<?php
					printf(
						/* translators: 1: variable name, 2 URL query string separator, 3 variable name */
						esc_html__( '%1$s is transmitted as part of the query string (the part after %2$s in the URL) but is displayed as part of %3$s here to make the output easier to read.', 'updates-api-inspector' ),
						'<code>query_string</code>',
						'<code>?</code>',
						'<code>$options</code>'
					);
					?>
			</p>
					<?php

					break;
				case 'plugins':
					?>
			<p>
					<?php
					printf(
						/* translators: 1: variable name, 2: function call, 3: link to code reference */
						esc_html__( 'This is the value of %1$s in %2$s, as called in %3$s.', 'updates-api-inspector' ),
						'<code>$options</code>',
						"<code>wp_remote_post( '" . esc_html( $this->url ) . "', \$options )</code>",
						// @todo in RTL, the '()' part of the link text appears at the other end of the line.  I don't know why.
						sprintf(
							'<a href="%s">wp_update_plugins()</a>',
							esc_url( __( 'https://developer.wordpress.org/reference/functions/wp_update_plugins/', 'updates-api-inspector' ) )
						)
					);
					?>
			</p>
					<?php

					break;
				case 'themes':
					?>
			<p>
					<?php
					printf(
						/* translators: 1: variable name, 2: function call, 3: link to code reference */
						esc_html__( 'This is the value of %1$s in %2$s, as called in %3$s.', 'updates-api-inspector' ),
						'<code>$options</code>',
						"<code>wp_remote_post( '" . esc_html( $this->url ) . "', \$options )</code>",
						// @todo in RTL, the '()' part of the link text appears at the other end of the line.  I don't know why.
						sprintf(
							'<a href="%s">wp_update_themes()</a>',
							esc_url( __( 'https://developer.wordpress.org/reference/functions/wp_update_themes/', 'updates-api-inspector' ) )
						)
					);
					?>
			</p>
					<?php

					break;
			}
			?>
			<p>
				<?php
					printf(
						/* translators: variable name */
						esc_html__( '%s is json encoded when the request is made but has been decoded here to make the output easier to read.', 'updates-api-inspector' ),
						'<code>body</code>'
					);
				?>
			</p>
			<form>
				<textarea rows='25' readonly><?php echo esc_html( $this->pretty_print( $this->request ) ); ?></textarea>
			</form>
			<?php $this->jump_links( 'request' ); ?>
		</section>
			<?php
			if ( $this->error ) {
				?>
		<section id='error'>
			<h3><?php esc_html_e( 'Error', 'updates-api-inspector' ); ?></h3>
			<p>
				<?php esc_html_e( 'An error occured during the request.', 'updates-api-inspector' ); ?>
			</p>
			<form>
				<textarea rows='25' readonly><?php echo esc_html( $this->pretty_print( $this->error ) ); ?></textarea>
			</form>
				<?php $this->jump_links( 'error' ); ?>
		</section>
				<?php
			} else {
				$transient_as_read = get_site_transient( "update_{$current}" );
				?>
		<section id='response'>
			<h3><?php esc_html_e( 'API Response', 'updates-api-inspector' ); ?></h3>
			<p>
				<?php esc_html_e( 'This is the response from the API.', 'updates-api-inspector' ); ?>
			</p>
			<p>
				<?php
					esc_html_e( 'The fields in this response are not documented anywhere, so do not take what is displayed here as all-and-only what may ever be returned!!', 'updates-api-inspector' );
				?>
			</p>
				<?php
				switch ( $current ) {
					case 'plugins':
						?>
			<p>
						<?php esc_html_e( 'In general, only plugins hosted in the .org repo will be included here; however, in rare cases, something may have hooked into one of the lower-level hooks to inject directly into the API response information about externally hosted plugins.', 'updates-api-inspector' ); ?>
			</p>
						<?php

						break;
					case 'themes':
						?>
			<p>
						<?php esc_html_e( 'In general, only themes hosted in the .org repo will be included here; however, in rare cases, something may have hooked into one of the lower-level hooks to inject directly into the API response information about externally hosted themes.', 'updates-api-inspector' ); ?>
			</p>
						<?php

						break;
				}
				?>
			<form>
				<textarea rows='25' readonly><?php echo esc_html( $this->pretty_print( $this->response ) ); ?></textarea>
			</form>
				<?php $this->jump_links( 'response' ); ?>
		</section>
		<section id='transient-set'>
			<h3><?php esc_html_e( 'Transient Value As Set', 'updates-api-inspector' ); ?></h3>
				<?php
				switch ( $current ) {
					case 'core':
						?>
			<p>
						<?php
						printf(
							/* translators: 1: variable name, 2: function call, 3: link to code reference */
							esc_html__( 'This is the value of %1$s in %2$s, as called in %3$s.', 'updates-api-inspector' ),
							'<code>$updates</code>',
							"<code>set_site_transient( 'update_core', \$updates )</code>",
							// @todo in RTL, the '()' part of the link text appears at the other end of the line.  I don't know why.
							sprintf(
								'<a href="%s">wp_version_check()</a>',
								esc_url( __( 'https://developer.wordpress.org/reference/functions/wp_version_check/', 'updates-api-inspector' ) )
							)
						);
						?>
			</p>
						<?php
						// Display an info notice if the transient as set is different from as read.
						// Must use loose comparison here otherwise PHP could test with they
						// are the same object.
						// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
						if ( $transient_as_read != $this->transient_as_set ) {
							?>
			<div class='notice notice-info inline'>
				<p>
							<?php
							printf(
								/* translators: link to another section of this page */
								esc_html__( 'The transient value here is different than it\'s value in %s.', 'updates-api-inspector' ),
								'<a href="#transient-read">' . esc_html__( 'Transient Value as Read', 'updates-api-inspector' ) . '</a>'
							);
							echo '&nbsp;&nbsp';
							printf(
								/* translators: 1: link to code reference, 2: link to code reference */
								esc_html__( 'There are a number of different ways this could happen, but it often is the result of something hooking into %1$s rather that %2$s.', 'updates-api-inspector' ),
								sprintf(
									'<a href="%s">site_transient_update_core</a>',
									esc_url( __( 'https://developer.wordpress.org/reference/hooks/set_site_transient_transient/', 'updates-api-inspector' ) )
								),
								sprintf(
									'<a href="%s">pre_set_site_transient_update_core</a>',
									esc_url( __( 'https://developer.wordpress.org/reference/hooks/pre_set_site_transient_transient/', 'updates-api-inspector' ) )
								)
							);
							?>
				</p>
			</div>
							<?php
						}

						break;
					case 'plugins':
						?>
			<p>
						<?php
						printf(
							/* translators: 1: variable name, 2: function call, 3: link to code reference */
							esc_html__( 'This is the value of %1$s in %2$s, as called in %3$s.', 'updates-api-inspector' ),
							'<code>$new_option</code>',
							"<code>set_site_transient( 'update_plugins', \$new_option )</code>",
							// @todo in RTL, the '()' part of the link text appears at the other end of the line.  I don't know why.
							sprintf(
								'<a href="%s">wp_update_plugins()</a>',
								esc_url( __( 'https://developer.wordpress.org/reference/functions/wp_update_plugins/', 'updates-api-inspector' ) )
							)
						);
						?>
			</p>
						<?php
						// Display an info notice if the transient as set is different from as read.
						// Must use loose comparison here otherwise PHP could test with they
						// are the same object.
						// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
						if ( $transient_as_read != $this->transient_as_set ) {
							?>
			<div class='notice notice-info inline'>
				<p>
							<?php
							printf(
								/* translators: link to another section of this page */
								esc_html__( 'The transient value here is different than it\'s value in %s.', 'updates-api-inspector' ),
								'<a href="#transient-read">' . esc_html__( 'Transient Value as Read', 'updates-api-inspector' ) . '</a>'
							);
							echo '&nbsp;&nbsp';
							printf(
								/* translators: 1: link to code reference, 2: link to code reference */
								esc_html__( 'There are a number of different ways this could happen, but it often is the result of something hooking into %1$s rather that %2$s.', 'updates-api-inspector' ),
								sprintf(
									'<a href="%s">site_transient_update_plugins</a>',
									esc_url( __( 'https://developer.wordpress.org/reference/hooks/set_site_transient_transient/', 'updates-api-inspector' ) )
								),
								sprintf(
									'<a href="%s">pre_set_site_transient_update_plugins</a>',
									esc_url( __( 'https://developer.wordpress.org/reference/hooks/pre_set_site_transient_transient/', 'updates-api-inspector' ) )
								)
							);
							?>
				</p>
			</div>
							<?php
						}

						break;
					case 'themes':
						?>
			<p>
						<?php
						printf(
							/* translators: 1: variable name, 2: function call, 3: link to code reference */
							esc_html__( 'This is the value of %1$s in %2$s, as called in %3$s.', 'updates-api-inspector' ),
							'<code>$new_option</code>',
							"<code>set_site_transient( 'update_themes', \$new_option )</code>",
							// @todo in RTL, the '()' part of the link text appears at the other end of the line.  I don't know why.
							sprintf(
								'<a href="%s">wp_update_themes()</a>',
								esc_url( __( 'https://developer.wordpress.org/reference/hooks/wp_update_themes/', 'updates-api-inspector' ) )
							)
						);
						?>
			</p>
						<?php
						// Display an info notice if the transient as set is different from as read.
						// Must use loose comparison here otherwise PHP could test with they
						// are the same object.
				// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
						if ( $transient_as_read != $this->transient_as_set ) {
							?>
			<div class='notice notice-info inline'>
				<p>
							<?php
							printf(
								/* translators: link to another section of this page */
								esc_html__( 'The transient value here is different than it\'s value in %s.', 'updates-api-inspector' ),
								'<a href="#transient-read">' . esc_html__( 'Transient Value as Read', 'updates-api-inspector' ) . '</a>'
							);
							echo '&nbsp;&nbsp';
							printf(
								/* translators: 1: link to code reference, 2: link to code reference */
								esc_html__( 'There are a number of different ways this could happen, but it often is the result of something hooking into %1$s rather that %2$s.', 'updates-api-inspector' ),
								sprintf(
									'<a href="%s">site_transient_update_themes</a>',
									esc_url( __( 'https://developer.wordpress.org/reference/hooks/set_site_transient_transient/', 'updates-api-inspector' ) )
								),
								sprintf(
									'<a href="%s">pre_set_site_transient_update_themes</a>',
									esc_url( __( 'https://developer.wordpress.org/reference/hooks/pre_set_site_transient_transient/', 'updates-api-inspector' ) )
								)
							);
							?>
				</p>
			</div>
							<?php
						}

						break;
				}
				?>
			<p>
					<?php
						esc_html_e( 'The fields in this transient are not documented anywhere, so do not take what is displayed here as all-and-only what may ever be returned!!', 'updates-api-inspector' );
					?>
			</p>
			<form>
				<textarea rows='25' readonly><?php echo esc_html( $this->pretty_print( $this->transient_as_set ) ); ?></textarea>
			</form>
				<?php $this->jump_links( 'transient-set' ); ?>
		</section>
		<section id='transient-read'>
			<h3><?php esc_html_e( 'Transient Value As Read', 'updates-api-inspector' ); ?></h3>
				<?php
				switch ( $current ) {
					case 'core':
						?>
			<p>
						<?php
						printf(
							/* translators: function call */
							esc_html__( 'This is the value returned by %s.', 'updates-api-inspector' ),
							"<code>get_site_transient( 'update_core' )</code>"
						);
						echo '&nbsp;&nbsp;';
						printf(
							/* translators: variable name */
							esc_html__( 'In this value, %s is what determines which core updates are available, both manually in the dashboad and as auto-updates.', 'updates-api-inspector' ),
							'<code>updates</code>'
						);
						echo '&nbsp;&nbsp;';
						printf(
							/* translators: link to code reference */
							esc_html__( 'For auto-updates, the %s filter is applied and a falsey return value will prevent core from auto-updating.', 'updates-api-inspector' ),
							sprintf(
								'<a href="%s">auto_update_core</a>',
								esc_url( __( 'https://developer.wordpress.org/reference/hooks/auto_update_core/', 'updates-api-inspector' ) )
							)
						);
						?>
			</p>
						<?php
						// Display an info notice if the transient as set is different from as read.
						// Must use loose comparison here otherwise PHP could test with they
						// are the same object.
						// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
						if ( $transient_as_read != $this->transient_as_set ) {
							?>
			<div class='notice notice-info inline'>
				<p>
							<?php
							printf(
								/* translators: link to another section of this page */
								esc_html__( 'The transient value here is different than it\'s value in %s.', 'updates-api-inspector' ),
								'<a href="#transient-set">' . esc_html__( 'Transient Value as Set', 'updates-api-inspector' ) . '</a>'
							);
							echo '&nbsp;&nbsp';
							printf(
								/* translators: 1: link to code reference, 2: link to code reference */
								esc_html__( 'There are a number of different ways this could happen, but it often is the result of something hooking into %1$s rather that %2$s.', 'updates-api-inspector' ),
								sprintf(
									'<a href="%s">site_transient_update_core</a>',
									esc_url( __( 'https://developer.wordpress.org/reference/hooks/set_site_transient_transient/', 'updates-api-inspector' ) )
								),
								sprintf(
									'<a href="%s">pre_set_site_transient_update_core</a>',
									esc_url( __( 'https://developer.wordpress.org/reference/hooks/pre_set_site_transient_transient/', 'updates-api-inspector' ) )
								)
							);
							?>
				</p>
			</div>
							<?php
						}

						break;
					case 'plugins':
						?>
			<p>
						<?php
						printf(
							/* translators: function call */
							esc_html__( 'This is the value returned by %s.', 'updates-api-inspector' ),
							"<code>get_site_transient( 'update_plugins' )</code>"
						);
						echo '&nbsp;&nbsp;';
						printf(
							/* translators: variable name */
							esc_html__( 'In this value, %s is what determines which plugin updates are available, both manually in the dashboad and as auto-updates.', 'updates-api-inspector' ),
							'<code>response</code>'
						);
						echo '&nbsp;&nbsp;';
						printf(
							/* translators: link to code reference */
							esc_html__( 'For auto-updates, the %s filter is applied and a falsey return value will prevent the plugin from auto-updating.', 'updates-api-inspector' ),
							sprintf(
								'<a href="%s">auto_update_plugin</a>',
								esc_url( __( 'https://developer.wordpress.org/reference/hooks/auto_update_type/', 'updates-api-inspector' ) )
							)
						);
						?>
			</p>
			<p>
						<?php
						printf(
							/* translators: 1: variable name, 2: variable name, 3: ??? */
							esc_html__( 'The values of %1$s and %2$s will contain plugins that are externally hosted (if any) and are arrays of %3$s.', 'updates-api-inspector' ),
							'<code>response</code>',
							'<code>no_update</code>',
							'<strong>' . esc_html_x( 'objects', 'PHP data type', 'updates-api-inspector' ) . '</strong>'
						);
						?>
			</p>
			<div class='notice notice-warning inline'>
				<p>
						<?php
						echo '<strong>' . esc_html__( 'Important', 'updates-api-inspector' ) . '</strong>:' .
							sprintf(
								/* translators: variable name */
								esc_html__( 'The Auto-updates UI, introduced in WordPress 5.5.0, will not work correctly for externally hosted plugins that do not populate %s with information about their plugin!', 'updates-api-inspector' ),
								'<code>no_update</code>'
							);
							echo '&nbsp;&nbsp;';
							esc_html_e( 'For more infomration, see the sidebar in the Help tab on this screen.', 'updates-api-inspector' );
						?>
				</p>
			</div>
						<?php
						// Display an info notice if the transient as set is different from as read.
						// Must use loose comparison here otherwise PHP could test with they
						// are the same object.
						// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
						if ( $transient_as_read != $this->transient_as_set ) {
							?>
			<div class='notice notice-info inline'>
				<p>
							<?php
							printf(
								/* translators: link to another section of this page */
								esc_html__( 'The transient value here is different than it\'s value in %s.', 'updates-api-inspector' ),
								'<a href="#transient-set">' . esc_html__( 'Transient Value as Set', 'updates-api-inspector' ) . '</a>'
							);
							echo '&nbsp;&nbsp';
							printf(
								/* translators: 1: link to code reference, 2: link to code reference */
								esc_html__( 'There are a number of different ways this could happen, but it often is the result of something hooking into %1$s rather that %2$s.', 'updates-api-inspector' ),
								sprintf(
									'<a href="%s">site_transient_update_plugins</a>',
									esc_url( __( 'https://developer.wordpress.org/reference/hooks/set_site_transient_transient/', 'updates-api-inspector' ) )
								),
								sprintf(
									'<a href="%s">pre_set_site_transient_update_plugins</a>',
									esc_url( __( 'https://developer.wordpress.org/reference/hooks/pre_set_site_transient_transient/', 'updates-api-inspector' ) )
								)
							);
							?>
				</p>
			</div>
							<?php
						}

						break;
					case 'themes':
						?>
			<p>
						<?php
						printf(
							/* translators: function call */
							esc_html__( 'This is the value returned by %s.', 'updates-api-inspector' ),
							"<code>get_site_transient( 'update_themes' )</code>"
						);
						echo '&nbsp;&nbsp;';
						printf(
							/* translators: variable name */
							esc_html__( 'In this value, %s is what determines which theme updates are available, both manually in the dashboad and as auto-updates.', 'updates-api-inspector' ),
							'<code>response</code>'
						);
						echo '&nbsp;&nbsp;';
						printf(
							/* translators: link to code reference */
							esc_html__( 'For auto-updates, the %s filter is applied and a falsey return value will prevent the theme from auto-updating.', 'updates-api-inspector' ),
							sprintf(
								'<a href="%s">auto_update_theme</a>',
								esc_url( __( 'https://developer.wordpress.org/reference/hooks/auto_update_type/', 'updates-api-inspector' ) )
							)
						);
						?>
			</p>
			<p>
						<?php
						printf(
							/* translators: 1: variable name, 2: variable name, 3: ??? */
							esc_html__( 'The values of %1$s and %2$s will contain themes that are externally hosted (if any) and are arrays of %3$s.', 'updates-api-inspector' ),
							'<code>response</code>',
							'<code>no_update</code>',
							'<strong>' . esc_html_x( 'arrays', 'PHP data type', 'updates-api-inspector' ) . '</strong>'
						);
						?>
			</p>
			<div class='notice notice-warning inline'>
				<p>
						<?php
						echo '<strong>' . esc_html__( 'Important', 'updates-api-inspector' ) . '</strong>:' .
							sprintf(
								/* translators: variable name */
								esc_html__( 'The Auto-updates UI, introduced in WordPress 5.5.0, will not work correctly for externally hosted themes that do not populate %s with information about their theme!', 'updates-api-inspector' ),
								'<code>no_update</code>'
							);
						echo '&nbsp;&nbsp;';
						esc_html_e( 'For more infomration, see the sidebar in the Help tab on this screen.', 'updates-api-inspector' );
						?>
				</p>
			</div>
						<?php
						// Display an info notice if the transient as set is different from as read.
						// Must use loose comparison here otherwise PHP could test with they
						// are the same object.
						// phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
						if ( $transient_as_read != $this->transient_as_set ) {
							?>
			<div class='notice notice-info inline'>
				<p>
							<?php
							printf(
								/* translators: link to another section of this page */
								esc_html__( 'The transient value here is different than it\'s value in %s.', 'updates-api-inspector' ),
								'<a href="#transient-set">' . esc_html__( 'Transient Value as Set', 'updates-api-inspector' ) . '</a>'
							);
							echo '&nbsp;&nbsp';
							printf(
								/* translators: 1: link to code reference, 2: link to code reference */
								esc_html__( 'There are a number of different ways this could happen, but it often is the result of something hooking into %1$s rather that %2$s.', 'updates-api-inspector' ),
								sprintf(
									'<a href="%s">site_transient_update_themes</a>',
									esc_url( __( 'https://developer.wordpress.org/reference/hooks/set_site_transient_transient/', 'updates-api-inspector' ) )
								),
								sprintf(
									'<a href="%s">pre_set_site_transient_update_themes</a>',
									esc_url( __( 'https://developer.wordpress.org/reference/hooks/pre_set_site_transient_transient/', 'updates-api-inspector' ) )
								)
							);
							?>
				</p>
			</div>
							<?php
						}

						break;
				}
				?>
			<p>
					<?php
						esc_html_e( 'The fields in this transient are not documented anywhere, so do not take what is displayed here as all-and-only what may ever be returned!!', 'updates-api-inspector' );
					?>
			</p>
			<form>
				<textarea rows='25' readonly><?php echo esc_html( $this->pretty_print( $transient_as_read ) ); ?></textarea>
			</form>
				<?php
			}
			?>
			<?php $this->jump_links( 'transient-read' ); ?>
		</section>
	</div>
			<?php
		} else {
			?>
			<p>
				<?php esc_html_e( 'Simply click on the tab for the request type you would like to inspect.', 'updates-api-inspector' ); ?>
			</p>
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
	public function admin_menu() {
		if ( ! is_multisite() ) {
			add_management_page(
				_x( 'Updates API Inspector', 'Page title', 'updates-api-inspector' ),
				_x( 'Updates API Inspector', 'Menu item', 'updates-api-inspector' ),
				'manage_options',
				'updates-api-inspector',
				array( $this, 'render_tools_page' )
			);
		} else {
			add_menu_page(
				_x( 'Updates API Inspector', 'Page title', 'updates-api-inspector' ),
				_x( 'Updates API Inspector', 'Menu item', 'updates-api-inspector' ),
				'manage_network_options',
				'updates-api-inspector',
				array( $this, 'render_tools_page' ),
				'dashicons-update'
			);
		}

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
	public function print_styles() {
		?>
<style id='updates-api-inspector'>
	/* make room for the admin bar when the jump links are used. */
	#request,
	#response,
	#transient-set,
	#transient-read {
		padding-top: 32px;
	}

	@media screen and (max-width: 782px) {
		/* make room for the admin bar when the jump links are used. */
		#request,
		#response,
		#transient-set,
		#transient-read {
			padding-top: 46px;
		}
	}

	.updates-api-inspector section nav {
		float: right;
	}

	.rtl .updates-api-inspector section nav {
		float: left;
	}

	.updates-api-inspector nav li {
		float: left;
	}

	.rtl .updates-api-inspector nav li {
		float: right;
	}

	.updates-api-inspector nav li::after {
		content: ' | ';
		display: inline-block;
		margin: 0 0.5em;
	}

	.updates-api-inspector nav li:last-of-type::after {
		content: '';
	}

	.updates-api-inspector nav::after {
		clear: both;
		content: '';
		display: table;
	}

	.updates-api-inspector textarea {
		-moz-tab-size: 4;
		font-family: monospace;
		resize: both;
		tab-size: 4;
		width: 95%;
	}

	@media screen and (max-width: 782px) {
		.updates-api-inspector textarea {
			-moz-tab-size: 2;
			tab-size: 2;
		}
	}

	.updates-api-inspector textarea[readonly] {
		background-color: #fff; /* override WP's forms.css, which uses #eee for textarea[readonly] */
		direction: ltr;
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
	public function add_help() {
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
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		if ( isset( self::$instance ) ) {
			return self::$instance;
		}

		$this->add_hooks();
	}

	/**
	 * Pretty print a variable's value, approximating WPCS.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $variable The variable to be pretty printed.
	 * @return string
	 */
	protected function pretty_print( $variable ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
		$str = var_export( $variable, true );

		$str = preg_replace(
			array(
				'/=>\s+/',      // this includes newlines and leading whitespace on the next line.
				'/array\s+\(/', // for some arrays, var_export() adds the extra whitespace, others it doesn't.
				'/\d+ =>\s+/',  // strip numeric indexes from arrays.
				'/\(\s+\)/',    // ensure empty arrays appear on 1 line.
			),
			array(
				'=> ',
				'array(',
				'',
				'()',
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
				// end of an arrary or object.
				// align '=>' based on the longest key in the array/object.
				// first, find the max length of keys.
				$max_length = 0;
				foreach ( $array_stack[ $indent ] as $k ) {
					$key        = preg_replace( '/^\s+\'([^\']+)\'\s+=>.*$/U', '$1', $lines[ $k ] );
					$max_length = max( $max_length, strlen( $key ) );
				}

				// now that we know the max length key, do the actually alignment.
				foreach ( $array_stack[ $indent ] as $k ) {
					$key         = preg_replace( '/^\s+\'([^\']+)\'\s+=>.*$/U', '$1', $lines[ $k ] );
					$padding     = str_repeat( ' ', $max_length - strlen( $key ) + 1 );
					$lines[ $k ] = preg_replace( '/^(\s+\'[^\']+\')(\s+)(=>.*$)/U', "\$1{$padding}\$3", $lines[ $k ] );
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

	/**
	 * Output jump links.
	 *
	 * @since 0.2.0
	 *
	 * @param string $section The section the jump links are in.
	 *                        Accepts 'top', 'request', 'response', 'transient-set',
	 *                        'transient-read' and 'error'.  The 'top' "section" is the
	 *                        top of the page.
	 * @return void
	 */
	protected function jump_links( $section ) {
		$links = array(
			'request'        => __( 'Request', 'updates-api-inspector' ),
			'response'       => __( 'API Response', 'updates-api-inspector' ),
			'transient-set'  => __( 'Transient Value as Set', 'updates-api-inspector' ),
			'transient-read' => __( 'Transient Value as Read', 'updates-api-inspector' ),
		);
		if ( $this->error ) {
			// the request generates an error response, we don't
			// show the these sections.
			unset( $links['response'], $links['transient-set'], $links['transient-read'] );

			$links['error'] = __( 'Error', 'updates-api-inspector' );
		}
		?>
<nav>
	<ul>
		<?php
		if ( 'top' !== $section ) {
			?>
			<li><a href='#top'><?php esc_html_e( 'Back to top', 'updates-api-inspector' ); ?></a></li>
			<?php
		}

		foreach ( $links as $anchor => $text ) {
			if ( $anchor !== $section ) {
				?>
			<li><a href='#<?php echo esc_attr( $anchor ); ?>'><?php echo esc_html( $text ); ?></a></li>
				<?php
			} else {
				?>
			<li><?php echo esc_html( $text ); ?></li>
				<?php
			}
		}
		?>
	</ul>
</nav>
		<?php

			return;
	}
}

// Instantiate ourself.
Plugin::get_instance();
