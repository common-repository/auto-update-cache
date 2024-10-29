<?php
/**
 * Plugin Name: Auto Update Cache
 * Description: Automatically updates the version of all CSS and JS files, ensuring users/viewers always see the latest changes.
 * Version: 1.1
 * Author: Sheikh Mizan
 * Author URI: https://profiles.wordpress.org/sheikhmizanbd
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: auto-update-cache
 * Domain Path: /lang/
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists('A_u_cache') ) {

    /**
     * Main A_u_cache Class.
     */
    class A_u_cache {

        /**
         * Single instance of the class.
         *
         * @var A_u_cache
         */
        protected static $_instance = null;

        /**
         * Plugin options.
         *
         * @var array
         */
        public $options = array();

        /**
         * Cache clear time.
         *
         * @var string
         */
        public $clear_cache_time = '';

        /**
         * Show toolbar button.
         *
         * @var bool
         */
        public $show_on_toolbar = false;

        /**
         * Cache-busting query argument.
         *
         * @var string
         */
        public $time_query_arg = '';

        /**
         * Returns the main instance of the class.
         *
         * @static
         * @return A_u_cache - Main instance
         */
        public static function instance() {
            if (is_null(self::$_instance)) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        /**
         * A_u_cache Constructor.
         */
        public function __construct() {
            $this->init_params();

            add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'plugin_actions' ) );
            add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

            if ( $this->show_on_toolbar ) {
                add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 100 );
                add_action( 'template_redirect', array( $this, 'update_css_js' ), 100 );
            }

            // Enqueue scripts and styles
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
            if ( ! is_admin() ) {
                add_filter( 'style_loader_src', array( $this, 'add_query_arg' ), 100 );
                add_filter( 'script_loader_src', array( $this, 'add_query_arg' ), 100 );
            }
        }

        /**
         * Initialize A_u_cache parameters.
         */
        public function init_params() {
            $options = $this->get_options();
            $clear_cache_automatically = $options['clear_cache_automatically'];
            $time = '';

            if ( $clear_cache_automatically === 'every_time' ) {
                $time = time();
            } elseif ( $clear_cache_automatically === 'every_period' ) {
                $time = $this->calculate_cache_time($options);
            } elseif ( $clear_cache_automatically === 'never' ) {
                $time = $this->get_clear_cache_time();
            }

            $this->time_query_arg = $time;
            $this->show_on_toolbar = $options['show_on_toolbar'];
        }

        /**
         * Calculate cache time based on the set period.
         *
         * @param array $options
         * @return int Cache time
         */
        public function calculate_cache_time($options) {
            $time = '';
            $update_time = true;

            if ( isset( $_COOKIE['A_u_cache_time'] ) ) {
                $time = max( intval( $_COOKIE['A_u_cache_time'] ), $this->get_clear_cache_time() );
                $current_time = time();
                $cached_minutes = round( ( $current_time - $time ) / 60 );

                if ( $cached_minutes <= intval( $options['clear_cache_automatically_minutes'] ) ) {
                    $update_time = false;
                }
            }

            if ( $update_time ) {
                $time = time();
                setcookie( 'A_u_cache_time', $time, $time + 60 * intval( $options['clear_cache_automatically_minutes'] ), '/' );
            }

            return $time;
        }

        /**
         * Add settings link to the plugin actions.
         *
         * @param array $actions Plugin actions.
         * @return array Modified plugin actions.
         */
        public function plugin_actions( $actions ) {
            $settings_link = '<a href="' . esc_url( menu_page_url( 'auto-update-cache', false ) ) . '">' . esc_html__( 'Settings', 'auto-update-cache' ) . '</a>';
            array_unshift( $actions, $settings_link );
            return $actions;
        }

        /**
         * Load plugin textdomain for translations.
         */
        public function load_textdomain() {
            load_plugin_textdomain( 'auto-update-cache', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
        }

        /**
         * Sanitize and filter plugin options.
         *
         * @param array $options Plugin options.
         * @return array Sanitized options.
         */
        public function filter_options( $options ) {
            $clear_cache_automatically = isset( $options['clear_cache_automatically'] ) ? sanitize_text_field( $options['clear_cache_automatically'] ) : 'every_time';
            if ( ! in_array( $clear_cache_automatically, array( 'every_time', 'every_period', 'never' ) ) ) {
                $clear_cache_automatically = 'every_time';
            }

            $clear_cache_automatically_minutes = isset( $options['clear_cache_automatically_minutes'] ) ? intval( $options['clear_cache_automatically_minutes'] ) : 10;
            $clear_cache_automatically_minutes = max( 1, min( 99999, $clear_cache_automatically_minutes ) );

            $show_on_toolbar = ! empty( $options['show_on_toolbar'] );

            return array(
                'clear_cache_automatically' => $clear_cache_automatically,
                'clear_cache_automatically_minutes' => $clear_cache_automatically_minutes,
                'show_on_toolbar' => $show_on_toolbar
            );
        }

        /**
         * Retrieve the plugin options.
         *
         * @return array Plugin options.
         */
        public function get_options() {
            if ( empty( $this->options ) ) {
                $this->options = $this->filter_options( get_option('A_u_cache_options', array() ) );
            }
            return $this->options;
        }

        /**
         * Retrieve the cache clear time option.
         *
         * @return int Cache clear time.
         */
        public function get_clear_cache_time() {
            if ( ! $this->clear_cache_time ) {
                $this->clear_cache_time = intval( get_option('A_u_cache_clear_cache_time') );
            }
            return $this->clear_cache_time;
        }

        /**
         * Add cache-busting query argument to CSS and JS file URLs.
         *
         * @param string $src File URL.
         * @return string Modified file URL with cache-busting query argument.
         */
        public function add_query_arg( $src ) {
            if ( $this->time_query_arg ) {
                $src = add_query_arg( 'time', $this->time_query_arg, $src );
            }
            return $src;
        }

        /**
         * Get the current page URL.
         *
         * @return string Current page URL.
         */
        public function get_current_url() {
            $is_https = is_ssl();
            return ( $is_https ? 'https' : 'http' ) . '://' . ( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '' ) . ( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '' );
        }

        /**
         * Add the toolbar item to update CSS and JS files.
         *
         * @param WP_Admin_Bar $wp_admin_bar Toolbar object.
         */
        public function admin_bar_menu( $wp_admin_bar ) {
            $current_url = $this->get_current_url();
            $update_url = add_query_arg( 'pbc_update_css_js', wp_create_nonce( 'pbc_update_css_js' ), $current_url );

            $wp_admin_bar->add_menu( array(
                'id'    => 'pbc_update_css_js',
                'title' => esc_html__( 'Update CSS/JS', 'auto-update-cache' ),
                'href'  => esc_url( $update_url )
            ));
        }

        /**
         * Update the CSS and JS files when triggered via the toolbar.
         */
        public function update_css_js() {
            // Check if the 'pbc_update_css_js' parameter is set in the query string and verify nonce.
            if ( isset( $_GET['pbc_update_css_js'] ) ) {
                $pbc_update_css_js = sanitize_text_field( wp_unslash( $_GET['pbc_update_css_js'] ) );

                if ( wp_verify_nonce( $pbc_update_css_js, 'pbc_update_css_js' ) ) {
                    update_option( 'A_u_cache_clear_cache_time', time() );

                    wp_safe_redirect( remove_query_arg( 'pbc_update_css_js', $this->get_current_url() ) );
                    exit;
                }
            }
        }

        /**
         * Enqueue admin styles.
         */
        public function enqueue_admin_styles() {
            wp_enqueue_style( 'auc_style', plugins_url('includes/css/auc.css', __FILE__), [], '1.1', 'all' );
        }

    }

    A_u_cache::instance();

}