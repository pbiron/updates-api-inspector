<?php
/**
 * Update_Checker class.
 *
 * @package updates-api-inspector
 * @since 0.2.0
 */

namespace SHC\Updates_API_Inspector;

use WP_Error;

defined( 'ABSPATH' ) || die;

/**
 * Class that queries the various Updates API endpoints and captures the response and
 * transients that are set as a result.
 *
 * @since 0.2.0
 */
class Update_Checker extends Singleton {
	/**
	 * The URL of the current request.
	 *
	 * @since 0.1.0
	 * @since 0.2.0 Moved here from the Plugin class.
	 *
	 * @var string
	 */
	protected $request_url = '';

	/**
	 * The current request arguments.
	 *
	 * @since 0.1.0
	 * @since 0.2.0 Moved here from the Plugin class.
	 *
	 * @var array()
	 */
	protected $request = array();

	/**
	 * The successful response for the current request.
	 *
	 * @since 0.1.0
	 * @since 0.2.0 Moved here from the Plugin class.
	 *
	 * @var array
	 */
	protected $response = array();

	/**
	 * The error response for the current request.
	 *
	 * @since 0.1.0
	 * @since 0.2.0 Moved here from the Plugin class.
	 *
	 * @var WP_Error|array
	 */
	protected $request_error;

	/**
	 * The site transient value as set.
	 *
	 * @since 0.1.1
	 * @since 0.2.0 Moved here from the Plugin class.
	 *
	 * @var object
	 */
	protected $transient_as_set;

	/**
	 * Query the Updates API for the type specified.
	 *
	 * This is where the magic happens :-)
	 *
	 * @since 0.1.0
	 * @since 0.2.0 Moved here from the Plugin class.  It now returns `string[]`.
	 *
	 * @param string $type The Updates API endpoint type.  Accepts 'core', 'plugins', 'themes'.
	 * @return string[] {
	 *     XXX.
	 *
	 *     @type string         $request_url       The URL of the current request.
	 *     @type array          $request           The current request arguments.
	 *     @type WP_Error|array $request_error     The error response for the current request.
	 *     @type array          $api_response      The successful response for the current request.
	 *     @type object         $transient_as_set  The site transient value as set.
	 *     @type object         $transient_as_read The site transient value as read.
	 * }
	 */
	public function do_check( $type ) {
		if ( ! $type ) {
			$this->request_url      = '';
			$this->request          = array();
			$this->request_error    = array();
			$this->response         = '';
			$this->transient_as_set = '';

			return array(
				'request_url'       => $this->request_url,
				'request'           => $this->request,
				'request_error'     => $this->request_error,
				'api_response'      => $this->response,
				'transient_as_set'  => $this->transient_as_set,
				'transient_as_read' => '',
			);
		}

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
		add_filter( 'pre_http_request', array( $this, 'pre_http_request' ), PHP_INT_MAX, 3 );
		add_action( 'http_api_debug', array( $this, 'http_api_debug' ), PHP_INT_MAX, 5 );
		add_filter( 'http_response', array( $this, 'http_response' ), PHP_INT_MAX, 3 );
		add_action( "set_site_transient_{$transient_name}", array( $this, 'capture_transient_as_set' ), PHP_INT_MAX );

		// If the request returns an "error", core will raise a Warning with trigger_error().  That's OK.
		// Trap the warnings so they aren't output, because we've captured that error
		// return and will display it ourselves.
		// @todo ensure that the $error_type param stays up-to-date with what core
		//       passes to trigger_error().
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
		set_error_handler( array( $this, 'trap_warnings' ), headers_sent() || WP_DEBUG ? E_USER_WARNING : E_USER_NOTICE );

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

		// Restore the default error handler.
		restore_error_handler();

		// remove our capture hooks in reverse order that we added them.
		remove_action( "set_site_transient_{$transient_name}", array( $this, 'capture_transient_as_set' ), PHP_INT_MAX );
		remove_filter( 'http_response', array( $this, 'http_response' ), PHP_INT_MAX );
		remove_action( 'http_api_debug', array( $this, 'http_api_debug' ), PHP_INT_MAX );
		remove_filter( 'pre_http_request', array( $this, 'pre_http_request' ), PHP_INT_MAX );

		return array(
			'request_url'       => $this->request_url,
			'request'           => $this->request,
			'request_error'     => $this->request_error,
			'api_response'      => $this->response,
			'transient_as_set'  => $this->transient_as_set,
			'transient_as_read' => empty( $this->request_error ) ? get_site_transient( $transient_name ) : null,
		);
	}

	/**
	 * Capture the API request and response.
	 *
	 * @since 0.2.0
	 *
	 * @param false|array|WP_Error $preempt     A preemptive return value of an HTTP request. Default false.
	 * @param array                $parsed_args HTTP request arguments.
	 * @param string               $url         The request URL.
	 * @return false|array|WP_Error
	 *
	 * @filter pre_http_request
	 */
	public function pre_http_request( $preempt, $parsed_args, $url ) {
		if ( false !== $preempt ) {
			$this->capture_request_response( $preempt, $parsed_args, $url );
		}

		return $preempt;
	}

	/**
	 * Capture the API request and response.
	 *
	 * @since 0.2.0
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
	public function http_api_debug( $response, $context, $class, $parsed_args, $url ) {
		$this->capture_request_response( $response, $parsed_args, $url );

		return;
	}

	/**
	 * Capture the API request and response.
	 *
	 * @since 0.2.0
	 *
	 * @param array  $response    HTTP response.
	 * @param array  $parsed_args HTTP request arguments.
	 * @param string $url         The request URL.
	 * @return array
	 *
	 * @filter http_response
	 */
	public function http_response( $response, $parsed_args, $url ) {
		$this->capture_request_response( $response, $parsed_args, $url );

		return $response;
	}

	/**
	 * Capture the API request and response.
	 *
	 * For the request, we only capture query args and `$options` explicitly passed
	 * to {@link https://developer.wordpress.org/reference/functions/wp_remote_post/ wp_remote_post()}.
	 *
	 * @since 0.1.0
	 * @since 0.2.0 Moved here from the Plugin class.
	 *
	 * @param array|WP_Error $response    HTTP response or WP_Error object.
	 * @param array          $parsed_args HTTP request arguments.
	 * @param string         $url         The request URL.
	 * @return void
	 */
	protected function capture_request_response( $response, $parsed_args, $url ) {
		// Even though this method is hooked/unhooked right around the core functions
		// that access the API, we check the URL just to be sure that we don't
		// capture incorrect info should something do additional wp_remote_xxx() calls
		// as part of hooks they fire while the API request is being processed.
		if ( ! preg_match( '@^https?://api.wordpress.org/(core/version-check|(plugins|themes)/update-check)/\d+(\.\d+)*/@', $url ) ) {
			return;
		}

		// First, capture the request.
		$this->request_url = $url;
		$keys              = array(
			'timeout',
			'user-agent',
			'body',
			// headers is only used for core update check.
			'headers',
		);
		$this->request     = array_filter(
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
			$this->request_url = preg_replace( '@\?.*$@', '', $url );
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
			$this->request_error    = $response;
			$this->response         = array();
			$this->transient_as_set = null;
		} else {
			$this->request_error = array();
			$this->response      = json_decode( wp_remote_retrieve_body( $response ), true );
		}

		return;
	}

	/**
	 * Capture the site transient value as set.
	 *
	 * Note that this will be called multiple times during each update check,
	 * because of the way those are done (1 or 2 times to update the `last_checked` value,
	 * and once for setting the actual value).  Ulimately, the last call is the one
	 * shown in our UI.
	 *
	 * @since 0.1.1
	 * @since 0.2.0 Moved here from the Plugin class.
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
	 * Trap PHP warnings raised by core when the API request returns an error response.
	 *
	 * @since 0.2.0
	 *
	 * @param int    $errno      The level of the error raised.
	 * @param string $errstr     The error message.
	 * @param string $errfile    The filename that the error was raised in.
	 * @param int    $errline    The line number the error was raised at.
	 * @param array  $errcontext The active symbol table at the point the error occurred.
	 * @return bool True if the error has been "handled", false otherwise.
	 */
	public function trap_warnings( $errno, $errstr, $errfile, $errline, $errcontext ) {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$backtrace = wp_list_pluck( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 ), 'function' );

		// Only trap the warning if it was raised in one of the core update functions
		// and not, for instance, by plugin code attached to a hook fired during the request.
		if ( isset( $backtrace[2] ) &&
				in_array( $backtrace[2], array( 'wp_version_check', 'wp_update_plugins', 'wp_update_themes' ), true ) ) {
			return true;
		}

		// Let the default error handler handle it.
		return false;
	}
}
