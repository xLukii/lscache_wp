<?php
/**
 * Cloud service cls
 *
 * @since      3.0
 */
namespace LiteSpeed;
defined( 'WPINC' ) || exit;

class Cloud extends Base {
	const LOG_TAG = '❄️';
	const CLOUD_SERVER = 'https://api.quic.cloud';
	const CLOUD_IPS = 'https://quic.cloud/ips';
	const CLOUD_SERVER_DASH = 'https://my.quic.cloud';
	const CLOUD_SERVER_WP = 'https://wpapi.quic.cloud';

	const SVC_D_NODES 			= 'd/nodes';
	const SVC_D_SYNC_CONF		= 'd/sync_conf';
	const SVC_D_USAGE 			= 'd/usage';
	const SVC_D_SETUP_TOKEN		= 'd/get_token';
	const SVC_PAGE_OPTM 		= 'page_optm';
	const SVC_CCSS 				= 'ccss';
	const SVC_UCSS 				= 'ucss';
	const SVC_LQIP 				= 'lqip';
	const SVC_IMG_OPTM			= 'img_optm';
	const SVC_HEALTH			= 'health';
	const SVC_CDN				= 'cdn';

	const BM_IMG_OPTM_PRIO = 16;
	const BM_IMG_OPTM_JUMBO_GROUP = 32;
	const IMG_OPTM_JUMBO_GROUP = 1000;
	const IMG_OPTM_DEFAULT_GROUP = 200;

	const IMGOPTM_TAKEN         = 'img_optm-taken';

	const TTL_NODE = 3; // Days before node expired
	const EXPIRATION_REQ = 300; // Seconds of min interval between two unfinished requests
	const EXPIRATION_TOKEN = 900; // Min intval to request a token 15m
	const TTL_IPS = 3; // Days for node ip list cache

	const API_REPORT		= 'wp/report' ;
	const API_NEWS 			= 'news';
	const API_VER			= 'ver';
	const API_BETA_TEST		= 'beta_test';

	private static $CENTER_SVC_SET = array(
		self::SVC_D_NODES,
		self::SVC_D_SYNC_CONF,
		self::SVC_D_USAGE,
		// self::API_NEWS,
		self::API_REPORT,
		// self::API_VER,
		// self::API_BETA_TEST,
		self::SVC_D_SETUP_TOKEN,
	);

	private static $WP_SVC_SET = array(
		self::API_NEWS,
		self::API_VER,
		self::API_BETA_TEST,
	);

	// No api key needed for these services
	private static $_PUB_SVC_SET = array(
		self::API_NEWS,
		self::API_REPORT,
		self::API_VER,
		self::API_BETA_TEST,
	);

	public static $SERVICES_LOAD_CHECK = array(
		self::SVC_CCSS,
		self::SVC_UCSS,
		self::SVC_LQIP,
		self::SVC_HEALTH,
	);

	public static $SERVICES = array(
		self::SVC_IMG_OPTM,
		self::SVC_PAGE_OPTM,
		self::SVC_CCSS,
		self::SVC_UCSS,
		self::SVC_LQIP,
		self::SVC_CDN,
		self::SVC_HEALTH,
	);

	const TYPE_CLEAR_PROMO 		= 'clear_promo';
	const TYPE_REDETECT_CLOUD 	= 'redetect_cloud';
	const TYPE_CLEAR_CLOUD 		= 'clear_cloud';
	const TYPE_GEN_KEY 			= 'gen_key';
	const TYPE_LINK 			= 'link';
	const TYPE_SYNC_USAGE 		= 'sync_usage';
	const TYPE_CDN_SETUP_LINK	= 'cdn_setup_link';
	const TYPE_CDN_SETUP_NOLINK	= 'cdn_setup_nolink';
	const TYPE_CDN_SETUP_RUN	= 'cdn_setup';
	const TYPE_CDN_SETUP_STATUS = 'cdn_status';
	const TYPE_CDN_SETUP_RESET	= 'cdn_reset';

	private $_api_key;
	private $_setup_token;
	protected $_summary;

	/**
	 * Init
	 *
	 * @since  3.0
	 */
	public function __construct() {
		$this->_api_key = $this->conf( self::O_API_KEY );
		$this->_setup_token = $this->conf( self::O_QC_TOKEN );
		$this->_summary = self::get_summary();
	}

	/**
	 * Show latest commit version always if is on dev
	 *
	 * @since 3.0
	 */
	public function check_dev_version() {
		if ( ! preg_match( '/[^\d\.]/', Core::VER ) ) {
			return;
		}

		$last_check = empty( $this->_summary[ 'last_request.' . self::API_VER ] ) ? 0 : $this->_summary[ 'last_request.' . self::API_VER ] ;

		if ( time() - $last_check > 600 ) {
			$auto_v = self::version_check( 'dev' );
			if ( ! empty( $auto_v[ 'dev' ] ) ) {
				$this->_summary[ 'version.dev' ] = $auto_v[ 'dev' ];
				self::save_summary( $this->_summary );
			}
		}

		if ( empty( $this->_summary[ 'version.dev' ] ) ) {
			return;
		}

		self::debug( 'Latest dev version ' . $this->_summary[ 'version.dev' ] );

		if ( version_compare( $this->_summary[ 'version.dev' ], Core::VER, '<=' ) ) {
			return;
		}

		// Show the dev banner
		require_once LSCWP_DIR . 'tpl/banner/new_version_dev.tpl.php';
	}

	/**
	 * Check latest version
	 *
	 * @since  2.9
	 * @access public
	 */
	public static function version_check( $src = false ) {
		$req_data = array(
			'v'		=> defined( 'LSCWP_CUR_V' ) ? LSCWP_CUR_V : '',
			'src'	=> $src,
		);
		if ( defined( 'LITESPEED_ERR' ) ) {
			$req_data[ 'err' ] = base64_encode( ! is_string( LITESPEED_ERR ) ? json_encode( LITESPEED_ERR ) : LITESPEED_ERR ) ;
		}
		$data = self::get( self::API_VER, $req_data );

		return $data;
	}

	/**
	 * Show latest news
	 *
	 * @since 3.0
	 */
	public function news() {
		$this->_update_news();

		if ( empty( $this->_summary[ 'news.new' ] ) ) {
			return;
		}

		if ( ! empty( $this->_summary[ 'news.plugin' ] ) && Activation::cls()->dash_notifier_is_plugin_active( $this->_summary[ 'news.plugin' ] ) ) {
			return;
		}

		require_once LSCWP_DIR . 'tpl/banner/cloud_news.tpl.php' ;
	}

	/**
	 * Update latest news
	 *
	 * @since 2.9.9.1
	 */
	private function _update_news() {
		if ( ! empty( $this->_summary[ 'news.utime' ] ) && time() - $this->_summary[ 'news.utime' ] < 86400 * 3 ) {
			return;
		}

		$this->_summary[ 'news.utime' ] = time();
		self::save_summary();

		$data = self::get( self::API_NEWS );
		if ( empty( $data[ 'id' ] ) ) {
			return;
		}

		// Save news
		if ( ! empty( $this->_summary[ 'news.id' ] ) && $this->_summary[ 'news.id' ] == $data[ 'id' ] ) {
			return;
		}

		$this->_summary[ 'news.id' ] = $data[ 'id' ];
		$this->_summary[ 'news.plugin' ] = ! empty( $data[ 'plugin' ] ) ? $data[ 'plugin' ] : '';
		$this->_summary[ 'news.title' ] = ! empty( $data[ 'title' ] ) ? $data[ 'title' ] : '';
		$this->_summary[ 'news.content' ] = ! empty( $data[ 'content' ] ) ? $data[ 'content' ] : '';
		$this->_summary[ 'news.zip' ] = ! empty( $data[ 'zip' ] ) ? $data[ 'zip' ] : '';
		$this->_summary[ 'news.new' ] = 1;

		if ( $this->_summary[ 'news.plugin' ] ) {
			$plugin_info = Activation::cls()->dash_notifier_get_plugin_info( $this->_summary[ 'news.plugin' ] );
			if ( $plugin_info && ! empty( $plugin_info->name ) ) {
				$this->_summary[ 'news.plugin_name' ] = $plugin_info->name;
			}
		}

		self::save_summary();
	}

	/**
	 * Check if contains a package in a service or not
	 *
	 * @since  4.0
	 */
	public function has_pkg( $service, $pkg ) {
		if ( ! empty( $this->_summary[ 'usage.' . $service ][ 'pkgs' ] ) && $this->_summary[ 'usage.' . $service ][ 'pkgs' ] & $pkg ) {
			return true;
		}

		return false;
	}

	/**
	 * Get allowance of current service
	 *
	 * @since  3.0
	 * @access private
	 */
	public function allowance( $service, &$err = false ) {
		// Only auto sync usage at most one time per day
		if ( empty( $this->_summary[ 'last_request.' . self::SVC_D_USAGE ] ) || time() - $this->_summary[ 'last_request.' . self::SVC_D_USAGE ] > 86400 ) {
			$this->sync_usage();
		}

		if ( in_array( $service, array( self::SVC_CCSS, self::SVC_UCSS ) ) ) { // @since 4.2
			$service = self::SVC_PAGE_OPTM;
		}

		if ( empty( $this->_summary[ 'usage.' . $service ] ) ) {
			return 0;
		}
		$usage = $this->_summary[ 'usage.' . $service ];

		// Image optm is always free
		$allowance_max = 0;
		if ( $service == self::SVC_IMG_OPTM ) {
			$allowance_max = self::IMG_OPTM_DEFAULT_GROUP;
			if ( ! empty( $usage[ 'pkgs' ] ) && $usage[ 'pkgs' ] & self::BM_IMG_OPTM_JUMBO_GROUP ) {
				$allowance_max = self::IMG_OPTM_JUMBO_GROUP;
			}
		}

		$allowance = $usage[ 'quota' ] - $usage[ 'used' ];

		$err = 'out_of_quota';

		if ( $allowance > 0 ) {
			if ( $allowance_max && $allowance_max < $allowance ) {
				$allowance = $allowance_max;
			}

			// Daily limit @since 4.2
			if ( isset( $usage[ 'remaining_daily_quota' ] ) && $usage[ 'remaining_daily_quota' ] >= 0 && $usage[ 'remaining_daily_quota' ] < $allowance ) {
				$allowance = $usage[ 'remaining_daily_quota' ];
				if ( ! $allowance ) {
					$err = 'out_of_daily_quota';
				}
			}

			return $allowance;
		}

		// Check Pay As You Go balance
		if ( empty( $usage[ 'pag_bal' ] ) ) {
			return $allowance_max;
		}

		if ( $allowance_max && $allowance_max < $usage[ 'pag_bal' ] ) {
			return $allowance_max;
		}

		return $usage[ 'pag_bal' ];
	}

	/**
	 * Sync Cloud usage summary data
	 *
	 * @since  3.0
	 * @access public
	 */
	public function sync_usage() {
		$usage = $this->_post( self::SVC_D_USAGE );
		if ( ! $usage ) {
			return;
		}

		self::debug( 'sync_usage ' . json_encode( $usage ) );

		foreach ( self::$SERVICES as $v ) {
			$this->_summary[ 'usage.' . $v ] = ! empty( $usage[ $v ] ) ? $usage[ $v ] : false;
		}

		self::save_summary();

		return $this->_summary;
	}

	/**
	 * Clear all existing cloud nodes for future reconnect
	 *
	 * @since  3.0
	 * @access public
	 */
	public function clear_cloud() {
		foreach ( self::$SERVICES as $service ) {
			if ( isset( $this->_summary[ 'server.' . $service ] ) ) {
				unset( $this->_summary[ 'server.' . $service ] );
			}
			if ( isset( $this->_summary[ 'server_date.' . $service ] ) ) {
				unset( $this->_summary[ 'server_date.' . $service ] );
			}
		}
		self::save_summary();

		self::debug( 'Cleared all local service node caches' );
	}

	/**
	 * ping clouds to find the fastest node
	 *
	 * @since  3.0
	 * @access public
	 */
	public function detect_cloud( $service, $force = false ) {
		if ( in_array( $service, self::$CENTER_SVC_SET ) ) {
			return self::CLOUD_SERVER;
		}

		if ( in_array( $service, self::$WP_SVC_SET ) ) {
			return self::CLOUD_SERVER_WP;
		}

		// Check if the stored server needs to be refreshed
		if ( ! $force ) {
			if ( ! empty( $this->_summary[ 'server.' . $service ] ) && ! empty( $this->_summary[ 'server_date.' . $service ] ) && $this->_summary[ 'server_date.' . $service ] > time() - 86400 * self::TTL_NODE ) {
				return $this->_summary[ 'server.' . $service ];
			}
		}

		if ( ! $service || ! in_array( $service, self::$SERVICES ) ) {
			$msg = __( 'Cloud Error', 'litespeed-cache' ) . ': ' . $service;
			Admin_Display::error( $msg );
			return false;
		}

		// Send request to Quic Online Service
		$json = $this->_post( self::SVC_D_NODES, array( 'svc' => $service ) );

		// Check if get list correctly
		if ( empty( $json[ 'list' ] ) || ! is_array( $json[ 'list' ] ) ) {
			self::debug( 'request cloud list failed: ', $json );

			if ( $json ) {
				$msg = __( 'Cloud Error', 'litespeed-cache' ) . ": [Service] $service [Info] " . json_encode( $json );
				Admin_Display::error( $msg );
			}

			return false;
		}

		// Ping closest cloud
		$speed_list = array();
		foreach ( $json[ 'list' ] as $v ) {
			$speed_list[ $v ] = Utility::ping( $v );
		}

		$min = min( $speed_list );

		if ( $min == 99999 ) {
			self::debug( 'failed to ping all clouds' );
			return false;
		}

		// Random pick same time range ip (230ms 250ms)
		$range_len = strlen( $min );
		$range_num = substr( $min, 0, 1 );
		$valid_clouds = array();
		foreach ($speed_list as $node => $speed ) {
			if ( strlen( $speed ) == $range_len && substr( $speed, 0, 1 ) == $range_num ) {
				$valid_clouds[] = $node;
			}
			// Append the lower speed ones
			else if ( $speed < $min * 4 ) {
				$valid_clouds[] = $node;
			}
		}

		if ( ! $valid_clouds ) {
			$msg = __( 'Cloud Error', 'litespeed-cache' ) . ": [Service] $service [Info] " . __( 'No available Cloud Node.', 'litespeed-cache' );
			Admin_Display::error( $msg );
			return false;
		}

		self::debug( 'Closest nodes list', $valid_clouds );

		// Check server load
		if ( in_array( $service, self::$SERVICES_LOAD_CHECK ) ) {
			$valid_cloud_loads = array();
			foreach ( $valid_clouds as $k => $v ) {
				$response = wp_remote_get( $v, array( 'timeout' => 5, 'sslverify' => true ) );
				if ( is_wp_error( $response ) ) {
					$error_message = $response->get_error_message();
					self::debug( 'failed to do load checker: ' . $error_message );
					continue;
				}

				$curr_load = json_decode( $response[ 'body' ], true );
				if ( ! empty( $curr_load[ '_res' ] ) && $curr_load[ '_res' ] == 'ok' && isset( $curr_load[ 'load' ] ) ) {
					$valid_cloud_loads[ $v ] = $curr_load[ 'load' ];
				}
			}

			if ( ! $valid_cloud_loads ) {
				$msg = __( 'Cloud Error', 'litespeed-cache' ) . ": [Service] $service [Info] " . __( 'No available Cloud Node after checked server load.', 'litespeed-cache' );
				Admin_Display::error( $msg );
				return false;
			}

			self::debug( 'Closest nodes list after load check', $valid_cloud_loads );

			$qualified_list = array_keys( $valid_cloud_loads, min( $valid_cloud_loads ) );
		}
		else {
			$qualified_list = $valid_clouds;
		}

		$closest = $qualified_list[ array_rand( $qualified_list ) ];

		self::debug( 'Chose node: ' . $closest );

		// store data into option locally
		$this->_summary[ 'server.' . $service ] = $closest;
		$this->_summary[ 'server_date.' . $service ] = time();
		self::save_summary();

		return $this->_summary[ 'server.' . $service ];
	}

	/**
	 * Get data from QUIC cloud server
	 *
	 * @since  3.0
	 * @access public
	 */
	public static function get( $service, $data = array() ) {
		$instance = self::cls();
		return $instance->_get( $service, $data );
	}

	/**
	 * Get data from QUIC cloud server
	 *
	 * @since  3.0
	 * @access private
	 */
	private function _get( $service, $data = false ) {
		$service_tag = $service;
		if ( ! empty( $data[ 'action' ] ) ) {
			$service_tag .= '-' . $data[ 'action' ];
		}

		if ( ! $this->_maybe_cloud( $service_tag ) ) {
			return;
		}

		$server = $this->detect_cloud( $service );
		if ( ! $server ) {
			return;
		}

		$url = $server . '/' . $service;

		$param = array(
			'site_url'		=> home_url(),
			'domain_key'	=> $this->_api_key,
			'ver'			=> Core::VER,
		);

		if ( $data ) {
			$param[ 'data' ] = $data;
		}

		$url .= '?' . http_build_query( $param );

		self::debug( 'getting from : ' . $url );

		$this->_summary[ 'curr_request.' . $service_tag ] = time();
		self::save_summary();

		$response = wp_remote_get( $url, array( 'timeout' => 15, 'sslverify' => true ) );

		return $this->_parse_response( $response, $service, $service_tag, $server );
	}

	/**
	 * Check if is able to do cloud request or not
	 *
	 * @since  3.0
	 * @access private
	 */
	private function _maybe_cloud( $service_tag ) {
		if ( ! wp_http_validate_url( home_url() ) ) {
			return false;
		}

		// we don't want the `img_optm-taken` to fail at any given time
		if ( $service_tag == self::IMGOPTM_TAKEN ) {
			return true;
		}

		if ( $service_tag == self::SVC_D_SYNC_CONF && $this->_setup_token && ! $this->_api_key ) {
			self::debug( "Skip sync conf if API key is not available yet." );
			return false;
		}

		$expiration_req = self::EXPIRATION_REQ;
		// Limit frequent unfinished request to 5min
		$timestamp_tag = 'curr_request.';
		if ( $service_tag == self::SVC_IMG_OPTM . '-' . Img_Optm::TYPE_NEW_REQ ) {
			$timestamp_tag = 'last_request.';
			if ( $this->has_pkg( self::SVC_IMG_OPTM, self::BM_IMG_OPTM_PRIO ) ) {
				$expiration_req /= 10;
			}
		}
		else {
			// For all other requests, if is under debug mode, will always allow
			if ( $this->conf( self::O_DEBUG ) && $this->_api_key ) {
				return true;
			}
		}

		if ( ! empty( $this->_summary[ $timestamp_tag . $service_tag ] ) ) {
			$expired = $this->_summary[ $timestamp_tag . $service_tag ] + $expiration_req - time();
			if ( $expired > 0 ) {
				self::debug( "❌ try [$service_tag] after $expired seconds" );

				if ( $service_tag !== self::API_VER ) {
					$msg = __( 'Cloud Error', 'litespeed-cache' ) . ': ' . sprintf( __( 'Please try after %1$s for service %2$s.', 'litespeed-cache' ), Utility::readable_time( $expired, 0, true ), '<code>' . $service_tag . '</code>' );
					Admin_Display::error( array( 'cloud_trylater' => $msg ) );
				}

				return false;
			}
		}

		if ( in_array( $service_tag, self::$_PUB_SVC_SET ) ) {
			return true;
		}

		if ( ! $this->_api_key ) {
			Admin_Display::error( Error::msg( 'lack_of_api_key' ) );
			return false;
		}

		return true;
	}

	/**
	 * Post data to QUIC.cloud server
	 *
	 * @since  3.0
	 * @access public
	 */
	public static function post( $service, $data = false, $time_out = false ) {
		$instance = self::cls();
		return $instance->_post( $service, $data, $time_out );
	}

	/**
	 * Post data to cloud server
	 *
	 * @since  3.0
	 * @access private
	 */
	private function _post( $service, $data = false, $time_out = false ) {
		$service_tag = $service;
		if ( ! empty( $data[ 'action' ] ) ) {
			$service_tag .= '-' . $data[ 'action' ];
		}

		if ( ! $this->_maybe_cloud( $service_tag ) ) {
			return;
		}

		$server = $this->detect_cloud( $service );
		if ( ! $server ) {
			return;
		}

		$url = $server . '/' . $service;

		self::debug( 'posting to : ' . $url );

		$param = array(
			'site_url'		=> home_url(),
			'domain_key'	=> $this->_api_key,
			'ver'			=> Core::VER,
			'data' 			=> $data,
		);

		$this->_summary[ 'curr_request.' . $service_tag ] = time();
		self::save_summary();

		$response = wp_remote_post( $url, array( 'body' => $param, 'timeout' => $time_out ?: 15, 'sslverify' => true ) );

		return $this->_parse_response( $response, $service, $service_tag, $server );
	}

	/**
	 * Parse response JSON
	 * Mark the request successful if the response status is ok
	 *
	 * @since  3.0
	 */
	private function _parse_response( $response, $service, $service_tag, $server ) {
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			self::debug( 'failed to request: ' . $error_message );

			if ( $service !== self::API_VER ) {
				$msg = __( 'Failed to request via WordPress', 'litespeed-cache' ) . ': ' . $error_message . " [server] $server [service] $service";
				Admin_Display::error( $msg );

				// Force redetect node
				self::debug( 'Node error, redetecting node [svc] ' . $service );
				$this->detect_cloud( $service, true );
			}
			return;
		}

		$json = json_decode( $response[ 'body' ], true );

		if ( ! is_array( $json ) ) {
			self::debug( 'failed to decode response json: ' . $response[ 'body' ] );

			if ( $service !== self::API_VER ) {
				$msg = __( 'Failed to request via WordPress', 'litespeed-cache' ) . ': ' . $response[ 'body' ] . " [server] $server [service] $service";
				Admin_Display::error( $msg );

				// Force redetect node
				self::debug( 'Node error, redetecting node [svc] ' . $service );
				$this->detect_cloud( $service, true );
			}

			return;
		}

		if ( ! empty( $json[ '_code' ] ) ) {
			if ( $json[ '_code' ] == 'heavy_load' || $json[ '_code' ] == 'redetect_node' ) {
				// Force redetect node
				self::debug( 'Node redetecting node [svc] ' . $service );
				Admin_Display::info( __( 'Redetected node', 'litespeed-cache' ) . ': ' . Error::msg( $json[ '_code' ] ) );
				$this->detect_cloud( $service, true );
			}
		}

		if ( ! empty( $json[ '_503' ] ) ) {
			self::debug( 'service 503 unavailable temporarily. ' . $json[ '_503' ] );

			$msg = __( 'We are working hard to improve your online service experience. The service will be unavailable while we work. We apologize for any inconvenience.', 'litespeed-cache' );
			$msg .= ' ' . $json[ '_503' ] . " [server] $server [service] $service";
			Admin_Display::error( $msg );

			// Force redetect node
			self::debug( 'Node error, redetecting node [svc] ' . $service );
			$this->detect_cloud( $service, true );

			return;
		}

		if ( ! empty( $json[ '_info' ] ) ) {
			self::debug( '_info: ' . $json[ '_info' ] );
			$msg = __( 'Message from QUIC.cloud server', 'litespeed-cache' ) . ': ' . $json[ '_info' ];
			$msg .= $this->_parse_link( $json );
			Admin_Display::info( $msg );
			unset( $json[ '_info' ] );
		}

		if ( ! empty( $json[ '_note' ] ) ) {
			self::debug( '_note: ' . $json[ '_note' ] );
			$msg = __( 'Message from QUIC.cloud server', 'litespeed-cache' ) . ': ' . $json[ '_note' ];
			$msg .= $this->_parse_link( $json );
			Admin_Display::note( $msg );
			unset( $json[ '_note' ] );
		}

		if ( ! empty( $json[ '_success' ] ) ) {
			self::debug( '_success: ' . $json[ '_success' ] );
			$msg = __( 'Good news from QUIC.cloud server', 'litespeed-cache' ) . ': ' . $json[ '_success' ];
			$msg .= $this->_parse_link( $json );
			Admin_Display::succeed( $msg );
			unset( $json[ '_success' ] );
		}

		// Upgrade is required
		if ( ! empty( $json[ '_err_req_v' ] ) ) {
			self::debug( '_err_req_v: ' . $json[ '_err_req_v' ] );
			$msg = sprintf( __( '%1$s plugin version %2$s required for this action.', 'litespeed-cache' ), Core::NAME, 'v' . $json[ '_err_req_v' ] . '+' ) . " [server] $server [service] $service";

			// Append upgrade link
			$msg2 = ' ' . GUI::plugin_upgrade_link( Core::NAME, Core::PLUGIN_NAME, $json[ '_err_req_v' ] );

			$msg2 .= $this->_parse_link( $json );
			Admin_Display::error( $msg . $msg2 );
			return;
		}

		// Parse _carry_on info
		if ( ! empty( $json[ '_carry_on' ] ) ) {
			self::debug( 'Carry_on usage', $json[ '_carry_on' ] );
			// Store generic info
			foreach ( array( 'usage', 'promo' ) as $v ) {
				if ( ! empty( $json[ '_carry_on' ][ $v ] ) ) {
					switch ( $v ) {
						case 'usage':
							$usage_svc_tag = in_array( $service, array( self::SVC_CCSS, self::SVC_UCSS ) ) ? self::SVC_PAGE_OPTM : $service;
							$this->_summary[ 'usage.' . $usage_svc_tag ] = $json[ '_carry_on' ][ $v ];
							break;

						case 'promo':
							if ( empty( $this->_summary[ $v ] ) || ! is_array( $this->_summary[ $v ] ) ) {
								$this->_summary[ $v ] = array();
							}
							$this->_summary[ $v ][] = $json[ '_carry_on' ][ $v ];
							break;

						default:
							break;
					}
				}
			}
			self::save_summary();
			unset( $json[ '_carry_on' ] );
		}

		// Parse general error msg
		if ( empty( $json[ '_res' ] ) || $json[ '_res' ] !== 'ok' ) {
			$json_msg = ! empty( $json[ '_msg' ] ) ? $json[ '_msg' ] : 'unknown';
			self::debug( '❌ _err: ' . $json_msg );

			$msg = __( 'Failed to communicate with QUIC.cloud server', 'litespeed-cache' ) . ': ' . Error::msg( $json_msg ) . " [server] $server [service] $service";
			$msg .= $this->_parse_link( $json );
			Admin_Display::error( $msg );

			// Site not on QC, delete invalid domain key
			if ( $json_msg == 'site_not_registered' || $json_msg == 'err_key' ) {
				$this->cls( 'Conf' )->update_confs( array( self::O_API_KEY => '' ) );

				$msg = __( 'Site not recognized. Domain Key has been automatically removed. Please request a new one.', 'litespeed-cache' );
				$msg .= Doc::learn_more( admin_url( 'admin.php?page=litespeed-general' ), __( 'Click here to set.', 'litespeed-cache' ), true, false, true );
				$msg .= Doc::learn_more( 'https://docs.litespeedtech.com/lscache/lscwp/general/#domain-key', false, false, false, true );
				Admin_Display::error( $msg, false, true );
			}

			return;
		}

		unset( $json[ '_res' ] );
		if ( ! empty( $json[ '_msg' ] ) ) {
			unset( $json[ '_msg' ] );
		}

		$this->_summary[ 'last_request.' . $service_tag ] = $this->_summary[ 'curr_request.' . $service_tag ];
		$this->_summary[ 'curr_request.' . $service_tag ] = 0;
		self::save_summary();

		if ( $json ) {
			self::debug2( 'response ok', $json );
		}
		else {
			self::debug2( 'response ok' );
		}

		// Only successful request return Array
		return $json;
	}

	private function _req_rest_api($api, $body = [])
	{

		$token = $this->_setup_token;

		if (empty($token)) {

			Admin_Display::error( __( 'Cannot request REST API, no token saved.', 'litespeed-cache' ));
			return;
		}
		$req_args = [
			'headers' => [
				'Authorization' => 'bearer ' . $token,
				'Content-Type' => 'application/json',
			],
		];
		if (!empty($body)) {
			$req_args['body'] = json_encode($body);

			$response = wp_remote_post(self::CLOUD_SERVER . '/v2' . $api, $req_args);
		} else {
			$response = wp_remote_get(self::CLOUD_SERVER . '/v2' . $api, $req_args);
		}

		return $this->_parse_rest_response($response);
	}

	private function _parse_rest_response($response)
	{
		if ( is_wp_error( $response ) ) {

			$error_message = $response->get_error_message();
			self::debug( 'failed to request REST API: ' . $error_message );
			$this->_summary['cdn_setup_err'] = $error_message;
			self::save_summary();
			Admin_Display::error( __( 'Cloud REST Error', 'litespeed-cache' ) . ': ' . $error_message );
			return;
		}

		$json = json_decode( $response[ 'body' ], true );

		if (!$json['success']) {
			if (isset($json['info']['errors'])) {
				$errs = [];
				foreach ($json['info']['errors'] as $err) {
					$errs[] = 'Error ' . $err['code'] . ': ' . $err['message'];
				}
				$error_message = implode('<br>', $errs);
			} else {
				$error_message = 'Unknown error, contact QUIC.cloud support.';
			}
			$this->_summary[ 'cdn_setup_err' ] = $error_message;
			self::save_summary();
			Admin_Display::error( __( 'Cloud REST API returned error: ', 'litespeed-cache' ) . $error_message );
			return;
		}

		return $json;
	}

	/**
	 * Show promo from cloud
	 *
	 * @since  3.0
	 * @access public
	 */
	public function show_promo() {
		// if ( ! $this->_api_key && ! defined( 'LITESPEED_DISMISS_DOMAIN_KEY' ) ) {
		// 	Admin_Display::error( Error::msg( 'lack_of_api_key' ), true );
		// }

		if ( empty( $this->_summary[ 'promo' ] ) ) {
			return;
		}

		require_once LSCWP_DIR . 'tpl/banner/cloud_promo.tpl.php' ;
	}

	/**
	 * Clear promo from cloud
	 *
	 * @since  3.0
	 * @access private
	 */
	private function _clear_promo() {
		if ( count( $this->_summary[ 'promo' ] ) > 1 ) {
			array_shift( $this->_summary[ 'promo' ] );
		}
		else {
			$this->_summary[ 'promo' ] = array();
		}
		self::save_summary();
	}

	/**
	 * Parse _links from json
	 *
	 * @since  1.6.5
	 * @since  1.6.7 Self clean the parameter
	 * @access private
	 */
	private function _parse_link( &$json ) {
		$msg = '';

		if ( ! empty( $json[ '_links' ] ) ) {
			foreach ( $json[ '_links' ] as $v ) {
				$msg .= ' ' . sprintf( '<a href="%s" class="%s" target="_blank">%s</a>', $v[ 'link' ], ! empty( $v[ 'cls' ] ) ? $v[ 'cls' ] : '', $v[ 'title' ] );
			}

			unset( $json[ '_links' ] );
		}

		return $msg;
	}

	/**
	 * Request callback validation from Cloud
	 *
	 * @since  3.0
	 * @access public
	 */
	public function ip_validate() {
		if ( empty( $_POST[ 'hash' ] ) ) {
			self::debug( 'Lack of hash param' );
			return self::err( 'lack_of_param' );
		}

		if ( empty( $this->_api_key ) ) {
			self::debug( 'Lack of API key' );
			return self::err( 'lack_of_api_key' );
		}

		$to_validate = substr( $this->_api_key, 0, 4 );
		if ( $_POST[ 'hash' ] !== md5( $to_validate ) ) {
			self::debug( '__callback IP request hash wrong: md5(' . $to_validate . ') !== ' . $_POST[ 'hash' ] );
			return self::err( 'err_hash' );
		}

		Control::set_nocache( 'Cloud IP hash validation' );

		$res_hash = substr( $this->_api_key, 2, 4 );

		self::debug( '__callback IP request hash: md5(' . $res_hash . ')' );

		return self::ok( array( 'hash' => md5( $res_hash ) ) );
	}

	/**
	 * Can apply for a new token or not
	 *
	 * @since 3.0
	 */
	public function can_token() {
		return empty( $this->_summary[ 'token_ts' ] ) || time() - $this->_summary[ 'token_ts' ] > self::EXPIRATION_TOKEN;
	}

	/**
	 * Send request for domain key, get json [ 'token' => 'asdfasdf' ]
	 *
	 * @since  3.0
	 * @access public
	 */
	public function gen_key() {
		$data = array(
			'site_url'	=> home_url(),
			'rest'		=> function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : apply_filters( 'rest_url_prefix', 'wp-json' ),
			'server_ip'	=> $this->conf( self::O_SERVER_IP ),
		);
		if ( ! empty( $this->_summary[ 'token' ] ) ) {
			$data[ 'token' ] = $this->_summary[ 'token' ];
		}

		$response = wp_remote_get( self::CLOUD_SERVER . '/d/req_key?data=' . Utility::arr2str( $data ) );
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			self::debug( 'failed to gen_key: ' . $error_message );
			Admin_Display::error( __( 'Cloud Error', 'litespeed-cache' ) . ': ' . $error_message );
			return;
		}

		$json = json_decode( $response[ 'body' ], true );

		// Save token option
		if ( ! empty( $json[ 'token' ] ) ) {
			$this->_summary[ 'token' ] = $json[ 'token' ];
			$this->_summary[ 'token_ts' ] = time();
			if ( ! empty( $this->_summary[ 'apikey_ts' ] ) ) {
				unset( $this->_summary[ 'apikey_ts' ] );
			}
			self::save_summary();
		}

		// Parse general error msg
		if ( empty( $json[ '_res' ] ) || $json[ '_res' ] !== 'ok' ) {
			// clear current token
			unset( $this->_summary[ 'token' ] );
			self::save_summary();

			$json_msg = ! empty( $json[ '_msg' ] ) ? $json[ '_msg' ] : 'unknown';
			self::debug( '❌ _err: ' . $json_msg );

			$msg = __( 'Failed to communicate with QUIC.cloud server', 'litespeed-cache' ) . ': ' . Error::msg( $json_msg );
			$msg .= $this->_parse_link( $json );
			Admin_Display::error( $msg );

			return;
		}

		// This is a ok msg
		if ( ! empty( $json[ '_msg' ] ) ) {
			self::debug( '_msg: ' . $json[ '_msg' ] );

			$msg = __( 'Message from QUIC.cloud server', 'litespeed-cache' ) . ': ' . Error::msg( $json[ '_msg' ] );
			$msg .= $this->_parse_link( $json );
			Admin_Display::info( $msg );
			return;
		}

		self::debug( '✅ send request for key successfully.' );

		Admin_Display::succeed( __( 'Applied for Domain Key successfully. Please wait for result. Domain Key will be automatically sent to your WordPress.', 'litespeed-cache' ) );
	}

	/**
	 * Token callback validation from Cloud
	 *
	 * @since  3.0
	 * @access public
	 */
	public function token_validate() {
		try {
			$this->_validate_hash();
		} catch( \Exception $e ) {
			return self::err( $e->getMessage() );
		}

		Control::set_nocache( 'Cloud token validation' );

		self::debug( '✅ __callback token validation passed' );

		return self::ok( array( 'hash' => md5( substr( $this->_summary[ 'token' ], 3, 8 ) ) ) );
	}

	/**
	 * Callback for approval of api key after validated token and gen key from QUIC.cloud
	 *
	 * @since  3.0
	 * @access public
	 */
	public function save_apikey() {
		// Validate token hash first
		if ( empty( $_POST[ 'domain_key' ] ) || ! isset( $_POST[ 'is_linked' ] ) ) {
			return self::err( 'lack_of_param' );
		}

		try {
			$this->_validate_hash( 1 );
		} catch( \Exception $e ) {
			return self::err( $e->getMessage() );
		}

		// This doesn't need to sync QUIC conf but need to clear nodes
		$this->cls( 'Conf' )->update_confs( array( self::O_API_KEY => $_POST[ 'domain_key' ] ) );

		$this->_summary[ 'is_linked' ] = $_POST[ 'is_linked' ] ? 1 : 0;
		$this->_summary[ 'apikey_ts' ] = time();
		// Clear token
		unset( $this->_summary[ 'token' ] );
		self::save_summary();

		self::debug( '✅ saved auth_key' );
		Admin_Display::succeed( '🎊 ' . __( 'Congratulations, your Domain Key has been approved! The setting has been updated accordingly.', 'litespeed-cache' ) );

		return self::ok();
	}

	/**
	 * Validate POST hash match local token or not
	 *
	 * @since  3.0
	 */
	private function _validate_hash( $offset = 0 ) {
		if ( empty( $_POST[ 'hash' ] ) ) {
			self::debug( 'Lack of hash param' );
			throw new \Exception( 'lack_of_param' );
		}

		if ( empty( $this->_summary[ 'token' ] ) ) {
			self::debug( 'token validate failed: token not exist' );
			throw new \Exception( 'lack_of_local_token' );
		}

		if ( $_POST[ 'hash' ] !== md5( substr( $this->_summary[ 'token' ], $offset, 8 ) ) ) {
			self::debug( 'token validate failed: token mismatch hash !== ' . $_POST[ 'hash' ] );
			throw new \Exception( 'mismatch' );
		}
	}

	/**
	 * If can link the domain to QC user or not
	 *
	 * @since  3.0
	 */
	public function can_link_qc() {
		return empty( $this->_summary[ 'is_linked' ] ) && $this->_api_key;
	}

	/**
	 * Link the domain to QC user
	 *
	 * @since  3.0
	 */
	private function _link_to_qc() {
		if ( ! $this->can_link_qc() ) {
			return;
		}

		$data = array(
			'site_url'		=> home_url(),
			'domain_hash'	=> md5( substr( $this->_api_key, 0, 8 ) ),
			'ref'			=> get_admin_url( null, 'admin.php?page=litespeed-general' ),
		);

		wp_redirect( self::CLOUD_SERVER_DASH . '/u/wp?data=' . Utility::arr2str( $data ) );
		exit;
	}

	/**
	 * Update is_linked status if is a redirected back from QC
	 *
	 * @since  3.0
	 */
	public function update_is_linked_status() {
		if ( empty( $_GET[ 'qc_res' ] ) || empty( $_GET[ 'domain_hash' ] ) ) {
			return;
		}

		if ( ! $this->_api_key ) {
			return;
		}

		if ( md5( substr( $this->_api_key, 2, 8 ) ) !== $_GET[ 'domain_hash' ] ) {
			Admin_Display::error( __( 'Domain Key hash mismatch', 'litespeed-cache' ), true );
			return;
		}

		$this->_summary[ 'is_linked' ] = 1;
		self::save_summary();

		// Drop QS
		echo "<script>window.history.pushState( 'remove_gen_link', document.title, window.location.href.replace( '&qc_res=" . sanitize_key( $_GET[ 'qc_res' ] ) . "&domain_hash=" . sanitize_key( $_GET[ 'domain_hash' ] ) . "', '' ) );</script>";
	}

	/**
	 * Callback for updating Auto CDN Setup status after run
	 *
	 * @since  4.7
	 * @access public
	 */
	public function update_cdn_status() {
		// Validate token hash first

		if ( !isset( $_POST[ 'success' ] ) || !isset( $_POST[ 'result' ] ) ) {
			$this->_summary[ 'cdn_setup_err' ] = __( 'Received invalid message from the cloud server. Please submit a ticket.', 'litespeed-cache' );
			self::save_summary();
			return self::err( 'lack_of_param' );
		}
		if (!$_POST[ 'success' ])
		{
			$this->_summary[ 'cdn_setup_err' ] = $_POST[ 'result' ][ '_msg' ];
			self::save_summary();
			Admin_Display::error( __( 'There was an error during CDN setup: ', 'litespeed-cache' ) . $_POST[ 'result' ][ '_msg' ] );
		} else {
			$this->_process_cdn_status($_POST[ 'result' ]);
		}

		return self::ok();
	}

	/**
	 * Request an update on Auto CDN Setup status
	 *
	 * @since  4.7
	 * @access private
	 */
	private function _qc_setup_cdn_refresh() {

		$json = $this->_req_rest_api('/user/cdn/status');

		if (!$json) {
			return;
		}

		$result = [];
		if (isset($json['info']['messages'])) {
			$result['_msg'] = implode('<br>', $json['info']['messages']);
		}
		$this->_process_cdn_status($result);
	}

	/**
	 * Process the returned Auto CDN Setup status
	 *
	 * @since  4.7
	 * @access private
	 */
	private function _process_cdn_status($result)
	{

		if ( isset($result[ 'nameservers' ] ) ) {
			if (isset($this->_summary['cdn_setup_err'])) {
				unset($this->_summary['cdn_setup_err']);
			}
			$this->_summary[ 'is_linked' ] = 1;
			$this->cls( 'Conf' )->update_confs( array( self::O_QC_NAMESERVERS => $result[ 'nameservers' ], self::O_CDN_QUIC => true ) );
			Admin_Display::succeed( '🎊 ' . __( 'Congratulations, QUIC.cloud successfully set this domain up for the CDN. Please update your nameservers to:', 'litespeed-cache' ) . $result[ 'nameservers' ] );
		} else if ( isset($result[ 'done' ] ) ) {
			if ( isset( $this->_summary[ 'cdn_setup_err' ] ) ) {
				unset( $this->_summary[ 'cdn_setup_err' ] );
			}
			$this->_summary[ 'cdn_setup_done_ts' ] = time();
			self::save_summary();

			$this->_setup_token = '';
			$this->cls( 'Conf' )->update_confs( array( self::O_QC_TOKEN => '', self::O_QC_NAMESERVERS => '' ) );
		} else if ( isset($result[ '_msg' ] ) ) {
			Admin_Display::succeed( $result[ '_msg' ] );
		} else {
			Admin_Display::succeed( __( 'CDN Setup is running.', 'litespeed-cache' ) );
		}
		self::save_summary();
	}

	/**
	 * Process the returned Auto CDN Setup status
	 *
	 * @since  4.7
	 * @access private
	 */
	private function _qc_setup_cdn_reset() {

		if (!empty($this->_setup_token)) {
			$data = [
				'site_url' => home_url(),
				'rest'		=> function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : apply_filters( 'rest_url_prefix', 'wp-json' ),
			];

			$this->_req_rest_api('/user/cdn/reset', $data);
		}

		if ( isset( $this->_summary[ 'cdn_setup_ts' ] ) ) {
			unset( $this->_summary[ 'cdn_setup_ts' ] );
		}
		if ( isset( $this->_summary[ 'cdn_setup_done_ts' ] ) ) {
			unset( $this->_summary[ 'cdn_setup_done_ts' ] );
		}
		if ( isset( $this->_summary[ 'cdn_setup_err' ] ) ) {
			unset( $this->_summary[ 'cdn_setup_err' ] );
		}
		self::save_summary();

		$this->_setup_token = '';
		$this->cls( 'Conf' )->update_confs( array( self::O_QC_TOKEN => '', self::O_QC_NAMESERVERS => '', self::O_CDN_QUIC => false ) );
		Admin_Display::succeed( __( 'CDN Setup Token reset. Note: if my.quic.cloud account deletion is desired, that the account still exists and must be deleted separately.', 'litespeed-cache' ) );
		return self::ok();
	}

	/**
	 * If setup token already exists or not
	 *
	 * @since  4.7
	 */
	public function has_cdn_setup_token() {
		return !empty( $this->_setup_token );
	}

	/**
	 * Get QC user setup token
	 *
	 * This method initiates a link to a QUIC.cloud account.
	 *
	 * @since  4.7
	 */
	private function _qc_setup_cdn_link() {
		if ( $this->has_cdn_setup_token() ) {
			return;
		}

		$data = array(
			'site_url'		=> home_url(),
			'ref'			=> get_admin_url( null, 'admin.php?page=litespeed-cdn' ),
		);

		wp_redirect( self::CLOUD_SERVER_DASH . '/u/wptoken?data=' . Utility::arr2str( $data ) );
		exit;
	}

	/**
	 * Get QC user setup token
	 *
	 * This method is used when the installation is already linked to an account.
	 *
	 * @since  4.7
	 */
	private function _qc_setup_cdn_nolink() {
		if ( $this->has_cdn_setup_token() ) {
			return;
		}

		$data = array(
			'site_url'		=> home_url(),
		);

		$json = self::post( self::SVC_D_SETUP_TOKEN, $data);

		if (isset($json[ 'token' ])) {
			$this->_summary[ 'cdn_setup_ts' ] = time();
			self::save_summary();
			$this->_setup_token = $json[ 'token' ];
			$this->cls( 'Conf' )->update_confs( array( self::O_QC_TOKEN => $this->_setup_token ) );
		}
	}

	/**
	 * Update setup token status if is a redirected back from QC
	 *
	 * @since  4.7
	 */
	public function save_setuptoken() {
		if ( empty( $_GET[ 'qc_res' ] ) || empty( $_GET[ 'token' ] ) ) {
			return;
		}

		$this->_setup_token = $_GET[ 'token' ];
		$this->cls( 'Conf' )->update_confs( array( self::O_QC_TOKEN => $this->_setup_token ) );

		// Drop QS
		unset($_GET['qc_res']);
		unset($_GET['token']);
		echo "<script>window.history.pushState( 'remove_gen_link', document.title, window.location.href.replace( '&qc_res=" . sanitize_key( $_GET[ 'qc_res' ] ) . "&token=" . sanitize_key( $_GET[ 'token' ] ) . "', '' ) );
		window.onload =  () => document.querySelector(\"a[href='#auto_setup']\").click()</script>";
	}

	/**
	 * Initiate or continue a QC CDN Setup.
	 *
	 * @since  4.7
	 */
	private function _qc_setup_cdn_run() {

		$data = [
			'site_url' => home_url(),
			'rest'		=> function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : apply_filters( 'rest_url_prefix', 'wp-json' ),
			'server_ip'	=> $this->conf( self::O_SERVER_IP ),
		];
		$json = $this->_req_rest_api('/user/cdn/', $data);

		if (!$json) {
			return;
		}

		$msg = '';
		if (isset($json['info']['messages'])) {
			$msg = implode('<br>', $json['info']['messages']);
		}

		$json = $json['result'];

		if ( isset( $this->_summary[ 'cdn_setup_err' ] ) ) {
			unset( $this->_summary[ 'cdn_setup_err' ] );
		}

		// Save token option
		if ( ! empty( $json[ 'token' ] ) ) {
			$this->_summary[ 'token' ] = $json[ 'token' ];
			$this->_summary[ 'token_ts' ] = time();
			$this->_summary[ 'cdn_setup_ts' ] = time();
			if ( ! empty( $this->_summary[ 'apikey_ts' ] ) ) {
				unset( $this->_summary[ 'apikey_ts' ] );
			}
		}
		self::save_summary();

		// This is a ok msg
		if ( ! empty( $msg ) ) {
			self::debug( '_msg: ' . $msg );

			$msg = __( 'Message from QUIC.cloud server', 'litespeed-cache' ) . ': ' . $msg;
			Admin_Display::info( $msg );
			return;
		}

		self::debug( '✅ Successfully start CDN setup.' );
	}

	/**
	 * Check if this visit is from cloud or not
	 *
	 * @since  3.0
	 */
	public function is_from_cloud() {
		if ( empty( $this->_summary[ 'ips' ] ) || empty( $this->_summary[ 'ips_ts' ] ) || time() - $this->_summary[ 'ips_ts' ] > 86400 * self::TTL_IPS ) {
			$this->_update_ips();
		}

		$res = $this->cls( 'Router' )->ip_access( $this->_summary[ 'ips' ] );
		if ( ! $res ) {
			self::debug( '❌ Not our cloud IP' );

			// Refresh IP list for future detection
			$this->_update_ips();
		}
		else {
			self::debug( '✅ Passed Cloud IP verification' );
		}

		return $res;
	}

	/**
	 * Update Cloud IP list
	 *
	 * @since 4.2
	 */
	private function _update_ips() {
		self::debug( 'Load remote Cloud IP list from ' . self::CLOUD_IPS );

		$response = wp_remote_get( self::CLOUD_IPS . '?json' );
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			self::debug( 'failed to get ip whitelist: ' . $error_message );
			throw new \Exception( 'Failed to fetch QUIC.cloud whitelist ' . $error_message );
		}

		$json = json_decode( $response[ 'body' ], true );

		$this->_summary[ 'ips_ts' ] = time();
		$this->_summary[ 'ips' ] = $json;
		self::save_summary();
	}

	/**
	 * Return succeeded response
	 *
	 * @since  3.0
	 */
	public static function ok( $data = array() ) {
		$data[ '_res' ] = 'ok';
		return $data;
	}

	/**
	 * Return error
	 *
	 * @since  3.0
	 */
	public static function err( $code ) {
		return array( '_res' => 'err', '_msg' => $code );
	}

	/**
	 * Handle all request actions from main cls
	 *
	 * @since  3.0
	 * @access public
	 */
	public function handler() {
		$type = Router::verify_type();

		switch ( $type ) {
			case self::TYPE_CLEAR_CLOUD:
				$this->clear_cloud();
				break;

			case self::TYPE_REDETECT_CLOUD:
				if ( ! empty( $_GET[ 'svc' ] ) ) {
					$this->detect_cloud( $_GET[ 'svc' ], true );
				}
				break;

			case self::TYPE_CLEAR_PROMO:
				$this->_clear_promo();
				break;

			case self::TYPE_GEN_KEY:
				$this->gen_key();
				break;

			case self::TYPE_LINK:
				$this->_link_to_qc();
				break;

			case self::TYPE_CDN_SETUP_LINK:
				$this->_qc_setup_cdn_link();
				break;

			case self::TYPE_CDN_SETUP_NOLINK:
				$this->_qc_setup_cdn_nolink();
				break;

			case self::TYPE_CDN_SETUP_RUN:
				$this->_qc_setup_cdn_run();
				break;

			case self::TYPE_CDN_SETUP_STATUS:
				$this->_qc_setup_cdn_refresh();
				break;

			case self::TYPE_CDN_SETUP_RESET:
				$this->_qc_setup_cdn_reset();
				break;

			case self::TYPE_SYNC_USAGE:
				$this->sync_usage();

				$msg = __( 'Sync credit allowance with Cloud Server successfully.', 'litespeed-cache' ) ;
				Admin_Display::succeed( $msg ) ;
				break;

			default:
				break;
		}

		Admin::redirect();
	}
}
