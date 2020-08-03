<?php

/**
 * Plugin Name: Updates API Inspector
 * Description: Inspect various aspects of the Updates API.
 * Author: Paul V. Biron/Sparrow Hawk Computing
 * Author URI: http://sparrowhawkcomputing.com/
 * Version: 0.1.0
 */

namespace SHC;

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
	 * Add hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function add_hooks() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'wp_ajax_updates-api-inspector-update-check',  array( $this, 'ajax_update_check' ) );

		return;
	}

	/**
	 * Ajax handler to query the Updates API for the type specified.
	 *
	 * This is where the magic happens :-)
	 *
	 * @since 0.1.0
	 *
	 * @return void.
	 *
	 * @action wp_ajax_updates-api-inspector-update-check
	 */
	function ajax_update_check() {
		check_admin_referer( 'updates-api-inspector' );

		if ( ! isset( $_REQUEST['type'] ) ) {
			wp_send_json_error( __( 'Type not specified.', 'updates-api-inspector' ) );
		}

		$type = $_REQUEST['type'];
		if ( ! in_array( $type, array( 'core', 'plugins', 'themes' ), true ) ) {
			wp_send_json_error( sprintf( __( 'Unknown type: %s', 'updates-api-inspector' ), $type ) );
		}

		$transient_name = "update_{$type}";

		// wp_version_check(), i.e., the core check, doesn't need this.
		if ( 'plugins' === $type || 'themes' === $type ) {
			// trick core into thinking the transient has been set recently,
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
			case 'plugins':
				wp_update_plugins();

				break;
			case 'themes':
				wp_update_themes();

				break;
			case 'core':
				wp_version_check( array(), true );

				break;
		}

		// unhook our capture callback.
		remove_action( 'http_api_debug', array( $this, 'capture' ) );

		// Now, construct the data to send as the Ajax response.
		if ( $this->error ) {
			$data = array(
				'error' => var_export( $this->error, true ),
			);
		} else {
			$data = array(
				'response'  => var_export( $this->response, true ),
				'transient' => var_export( get_site_transient( $transient_name ), true ),
			);
		}
		$default_data = array(
			'request'  => var_export( $this->request, true ),
			'error'    => '',
			'response' => '',
			'url'      => $this->url,
		);
		$data = array_merge( $default_data, $data );

		$data = array_map( array( $this, 'pretty_print_var_export' ), $data );

		wp_send_json_success( $data );
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
		if ( ! preg_match( '@^https?://api.wordpress.org/(core/version-check|(plugins|themes)/update-check)/\d+(\.\d+)/@', $url ) ) {
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
		if ( preg_match( '@^https?://api.wordpress.org/core/version-check/\d+(\.\d+)/@', $url ) ) {
			// core update check.
			// parse the query string and add it to the request.
			parse_str( parse_url( $url, PHP_URL_QUERY ), $this->request['query_string'] );
			$this->url = preg_replace( '@\?.*$@', '', $url );
		} else {
			// plugins or themes check.
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
		$nonce = wp_create_nonce( 'updates-api-inspector' );

		$checks = array(
			'core'    => __( 'Core', 'updates-api-inspector' ),
			'plugins' => __( 'Plugins', 'updates-api-inspector' ),
			'themes'  => __( 'Themes', 'updates-api-inspector' ),
		);
 ?>
<style>
	body.busy {
		opacity: 0.5;
	}

	body.busy * {
		cursor: wait !important;
	}

	.updates-api-inspector legend {
		color: #23282d;
		display: block;
		font-size: 1.3em;
		font-weight: 600;
			margin-top: 1em;
	}
	.updates-api-inspector textarea {
		margin: 1em 0;
		resize: both;
 	}

	#request_types {
		list-style: disc inside;
		margin-left: 2em;
	}

 	.results-displayed {
 		font-weight: 600;
 	}

 	.updates-api-inspector .plugins p,
 	.updates-api-inspector .themes p {
 		margin: 1em 0;
	}

 	.updates-api-inspector .notice {
 		width: 60%;
 	}
</style>

<div class='wrap updates-api-inspector'>
	<h1><?php echo get_admin_page_title() ?></h1>
	<p><?php esc_html_e( 'This tool will allow you to inspect requests/responses from the Updates API.  Simply click on the request type...and see the results.', 'updates-api-inspector' ) ?></p>
	<ul id='request_types'>
		<?php
			foreach ( $checks as $type => $label ) {
		 ?>
		<li>
			<a href='#' class='update-check' data-type='<?php echo $type ?>' data-nonce='<?php echo $nonce ?>'><?php echo $label ?></a>
			<span class='results-displayed' hidden>(<?php esc_html_e( 'Results displayed', 'updates-api-inispector' ) ?>)</span>
		</li>

		<?php
			}
		 ?>
	</ul>

	<div id='result' hidden>
		<form>
			<fieldset id='request'>
				<legend><?php esc_html_e( 'Request', 'updates-api-inspector' ) ?></legend>
				<p>
					<?php _e( 'This is the value of <code>$options</code> in <code>wp_remote_post( \'<span class=\'url\'></span>\', $options )</code>.', 'updates-api-inspector' ) ?>
				</p>
				<p>
					<?php _e( '<code>body</code> is json encoded when the request is made but has been decoded here to make the output easier to read.', 'updates-api-inspector' ) ?>
				</p>
				<div class='core'>
					<p>
						<?php _e( '<code>query_stirng</code> is transmitted as part of the query string (the part after <code>?</code> in the URL) but is displayed as part of <code>$options</code> here to make the output easier to read.', 'updates-api-inspector' ) ?>
					</p>
				</div>
				<textarea rows='25' cols='100'></textarea>
			</fieldset>
			<fieldset id='error'>
				<legend><?php esc_html_e( 'Error', 'updates-api-inspector' ) ?></legend>
				<p>
					<?php esc_html_e( 'An error occured during the request.', 'updates-api-inspector' ) ?>
				</p>
				<textarea rows='25' cols='100'></textarea>
			</fieldset>
			<fieldset id='success'>
				<fieldset id='response'>
					<legend><?php esc_html_e( 'API Response', 'updates-api-inspector' ) ?></legend>
					<p>
						<?php esc_html_e( 'This is the response from the API.', 'updates-api-inspector' ) ?>
					</p>
					<p>
						<?php _e( 'The fields in this response are not documented anywhere, so do not take what is displayed here as <em>all-and-only</em> what may ever be returned!!', 'updates-api-inspector' ) ?>
					</p>
					<div class='plugins'>
						<p>
							<?php _e( 'In general, only plugins hosted in the .org repo will be included; however, in rare cases, something may have hooked into one of the more "esoteric" hooks to inject info for externally hosted plugins.', 'updates-api-inspector' ) ?>
						</p>
					</div>
					<div class='themes'>
						<p>
							<?php _e( 'In general, only themes hosted in the .org repo will be included; however, in rare cases, something may have hooked into one of the more "esoteric" hooks to inject info for externally hosted themes.', 'updates-api-inspector' ) ?>
						</p>
					</div>
					<textarea rows='25' cols='100'></textarea>
				</fieldset>
				<fieldset id='transient'>
					<legend><?php esc_html_e( 'Transient Value', 'updates-api-inspector' ) ?></legend>
					<div class='core'>
						<p>
							<?php _e( 'This is the value returned by <code>get_site_transient( \'update_core\' )</code>.', 'updates-api-inspector' ) ?>
						</p>
						<p>
							<?php _e( 'The fields in this transient are not documented anywhere, so do not take what is displayed here as <em>all-and-only</em> what may ever be returned!!', 'updates-api-inspector' ) ?>
						</p>
					</div>
					<div class='plugins'>
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
								<?php _e( '<strong>Important</strong>: The Auto-updates UI, introduced in WordPress 5.5.0, will not work correctly for externally hosted plugins that do not populate <code>no_update</code>.', 'updates-api-inspector' ) ?>
							</p>
						</div>
					</div>
					<div class='themes'>
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
								<?php _e( '<strong>Important</strong>: The Auto-updates UI, introduced in WordPress 5.5.0, will not work correctly for externally hosted themes that do not populate <code>no_update</code>.', 'updates-api-inspector' ) ?>
							</p>
						</div>
					</div>
<!--
					<div class='themes'>
						<p>
							<?php
								printf(
									esc_html__( 'This is the value returned by %s.', 'updates-api-inspector' ),
									'<code>get_site_transient( \'update_themes\' )</code>'
								);
							 ?>
						</p>
						<p>
							<?php
								printf(
									esc_html__( '%1$s The values of %2$s and %3$s will contain themes that are externally hosted (if any)', 'updates-api-inspector' ),
									'&nbsp;&nbsp;',
									'<code>response</code>',
									'<code>no_update</code>'
								);
								printf(
									esc_html__( '%1$s and are arrays of %2$s.', 'updates-api-inspector' ),
									'&nbsp;',
									'<strong>' . esc_html_x( 'arrays', 'xxx', 'updates-api-inspector' ) . '</strong>'
								);
							?>
						</p>
						<p class='important'>
							<?php esc_html_e( 'Unfortunately, the fields in the transient are not documented anywhere, so do not take the fields displayed here are all-and-only the possible fields!!', 'updates-api-inspector' ) ?>
						</p>
						<p class='important'>
							<?php
								printf(
									esc_html__( 'The Auto-updates UI, introduced in WordPress 5.5.0, will not work correctly for externally hosted themes that do not populate %1$s.', 'updates-api-inspector' ),
									'<code>no_update</code>',
								);
							 ?>
						</p>
					</div>
 -->
					<textarea rows='25' cols='100'></textarea>
				</fieldset>
			</field>
		</form>
	</div>

	<script>
		'use strict';

		( function( $ ) {
			$( document ).ready( function() {
				$( '.update-check' ).on( 'click', function( event ) {
					var type = $( this ).data( 'type' ),
						// ajax data.
						data = {
							action: 'updates-api-inspector-update-check',
							type: type,
							_wpnonce: $( this ).data( 'nonce' ),
						},
						// ajax settings.
						settings = {
							url: window.ajaxurl,
							method: 'POST',
							data: data,
						},
						/**
						 * Click handler that disables all mouse clicks.
						 *
						 * Used while ajax is processing.
						 *
						 * @since 0.1.0
						 *
						 * @return void
						 */
						disable_clicks = function( event ) {
							event.preventDefault();
						};

					event.preventDefault();
					$( this ).blur();

					// add our "busy" indicator, so the user knows something is happening.
					$( 'body' ).addClass( 'busy' );

					// prevent mouse-clicks on ANYTHING while ajax is working.
					// @todo figure out how to disable hovers.
					$( 'body' ).on( 'click', disable_clicks );

					// Start with a clean slate.
					$( '#error' ).hide();
					$( '#success' ).hide();
					$( '#result textarea' ).text( '' );

					$.ajax( settings ).done( function( response ) {
						// Hide any request-type specific notes.
						$( '#request_types a' ).each( function() {
							$( '#result .' + $( this ).data( 'type' ) ).hide();
						} );

						if ( response.success && response.data ) {
							// Populate the URL of the request.
							$( '#request .url' ).text( response.data.url );

							// We always populate the request textarea.
							$( '#request textarea' ).text( response.data.request );

							if ( response.data.error ) {
								// Populate the error textarea.
								$( '#error textarea' ).text( response.data.error );

								// Show the error fieldset.
								$( '#error' ).show();
							} else {
								// Populate the response and transient textareas.
								$( '#response textarea' ).text( response.data.response );
								$( '#transient textarea' ).text( response.data.transient );

								// Show request-type specific notes.
								$( '#result .' + type ).show();

								// Show the "success" fieldset.
								$( '#success' ).show();
							}

							// Show the result.
							$( '#result' ).show();
						}
					} ).error( function( jqXHR, textStatus, errorThrown ) {
						// Report the error on the console.
						console.log( 'updates-api-inspector: status = ' + textStatus + '; ' + error + errorThrown );
					} ).always( function() {
						// Let the user know which request type they clicked on :-)
						$( '.results-displayed' ).hide();
						$( '.updates-api-inspector a[data-type=' + type + '] + .results-displayed' ).show();

						// Restore the ability for user mouse clicks.
						$( 'body' ).off( 'click', disable_clicks );

						// Remove our "busy" indicator, so the user knows we're done.
						$( 'body' ).removeClass( 'busy' );
					} );
				} );
			} );
		} )( jQuery );
	</script>
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
	 * Pretty print the output of PHP's
	 * {@link https://www.php.net/manual/en/function.var-export var_export()}.
	 *
	 * @since 0.1.0
	 *
	 * @param string $str
	 * @return string
	 *
	 * @todo align array/object properties based on the longest key/property, ala
	 *       WPCS.  Doing so is WAY too much trouble this early in the dev of this
	 *       plugin, but if I can later find an easy way to call phpcs, the sure,
	 *       will do that.
	 */
	protected function pretty_print_var_export( $str ) {
		$str = preg_replace( '/=>\s+/', '=> ', $str );
		$str = str_replace( 'array (', 'array(', $str );
		$str = preg_replace( '/\d+ =>\s+/', '  ', $str );
		$str = preg_replace( '/\(\s+\)/', '()', $str );

		return $str;
	}
}

// Instantiate ourself.
Plugin::get_instance();
