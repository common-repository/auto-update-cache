<?php

class A_u_cache_Admin_Settings
{

    /**
     * A_u_cache_Admin_Settings Constructor.
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
        add_action( 'wp_ajax_pbc_update_clear_cache_time', array( $this, 'update_clear_cache_time' ) );
    }

    /**
     * Add options page.
     */
    public function add_plugin_page()
    {
        add_options_page(
            esc_html__( 'Auto Update Cache', 'auto-update-cache' ),
            esc_html__( 'Auto Update Cache', 'auto-update-cache' ),
            'manage_options',
            'auto-update-cache',
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Options page callback.
     */
    public function create_admin_page()
    {
        ?>
        <div class="wrap">
            <h1 class="text-center"><?php esc_html_e( 'Auto update cache', 'auto-update-cache' ); ?></h1>
            <div id="pbc_notices">
                <div class="updated settings-error notice pbc-notice pbc-notice-update-caching-time" style="display: none">
                    <p><strong><?php esc_html_e( 'Completed, Your site is now updated.', 'auto-update-cache' ); ?></strong></p>
                    <button type="button" class="notice-dismiss" onclick="pbc_close_notice(this)"><span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'auto-update-cache' ); ?></span></button>
                </div>
            </div>
            <button class="accordion"><?php esc_html_e( 'Settings', 'auto-update-cache' ); ?></button>
            <div class="panel" style="display:block">
              <form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
                   <?php
                   do_settings_sections( 'auto-update-cache' );
                   settings_fields( 'A_u_cache_options_group' );
                   ?>
              </form>
            </div>

            <button class="accordion"><?php esc_html_e( 'Update center', 'auto-update-cache' ); ?></button>
            <div class="panel">
                <h2><?php esc_html_e( 'More functions are on the way...', 'auto-update-cache' ); ?></h2>
                <p><?php esc_html_e( 'If this plugin helped you, please consider giving it a rating! Thank you :)', 'auto-update-cache' ); ?></p>
            </div>

            <script>
            var acc = document.getElementsByClassName("accordion");
            var i;

            for (i = 0; i < acc.length; i++) {
                acc[i].addEventListener("click", function() {
                    this.classList.toggle("active");
                    var panel = this.nextElementSibling;
                    if (panel.style.display === "block") {
                        panel.style.display = "none";
                    } else {
                        panel.style.display = "block";
                    }
                });
            }
            </script>

        </div>
        <?php
    }

    /**
     * Register and add settings.
     */
    public function page_init()
    {
        register_setting(
            'A_u_cache_options_group', // Option group
            'A_u_cache_options', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'A_u_cache_settings', // ID
            null, // Title
            null, // Callback
            'auto-update-cache' // Page
        );

        add_settings_field(
            'always_clear_cache',
            esc_html__( 'Automatic', 'auto-update-cache' ),
            array( $this, 'clear_cache_automatically_callback' ),
            'auto-update-cache',
            'A_u_cache_settings'
        );
        add_settings_field(
            'update_css_js_files',
            esc_html__( 'Manual', 'auto-update-cache' ),
            array( $this, 'clear_cache_manually_callback' ),
            'auto-update-cache',
            'A_u_cache_settings'
        );
    }

    /**
     * Sanitize each setting field as needed.
     *
     * @param $input
     * @return mixed
     */
    public function sanitize( $input )
    {
        return A_u_cache::instance()->filter_options( $input );
    }

    /**
     * Displays options to clear cache automatically.
     */
    public function clear_cache_automatically_callback()
    {
        $options = A_u_cache::instance()->get_options();
        $clear_cache_automatically = $options['clear_cache_automatically'];
        $clear_cache_automatically_minutes = esc_attr( $options['clear_cache_automatically_minutes'] );
        ?>

        <label>
            <input type="radio" name="A_u_cache_options[clear_cache_automatically]" value="every_period"<?php echo $clear_cache_automatically == 'every_period' ? ' checked' : ''; ?> />
            <?php esc_html_e( 'After every', 'auto-update-cache' ); ?> <input type="number" name="A_u_cache_options[clear_cache_automatically_minutes]" value="<?php echo esc_attr( $clear_cache_automatically_minutes ); ?>" step="1" min="1" max="99999" style="width: 65px"> <?php esc_html_e( 'minutes', 'auto-update-cache' ); ?>
        </label><br>
        <label>
            <input type="radio" name="A_u_cache_options[clear_cache_automatically]" value="never"<?php echo $clear_cache_automatically == 'never' ? ' checked' : ''; ?> />
            <?php esc_html_e( 'Do not update automatically', 'auto-update-cache' ); ?>
        </label><br>

        <?php
         submit_button();
    }

    /**
     * Displays options to clear cache manually.
     */
    public function clear_cache_manually_callback()
    {
        ?>
         <button class="button" onclick="pbc_update_clear_cache_time(this)"><?php esc_html_e( 'Update CSS and JS files now', 'auto-update-cache' ); ?></button>

         <script>
             function pbc_close_notice(element) {
                 jQuery(element).parents('.pbc-notice').fadeOut('fast');
             }

             function pbc_update_clear_cache_time( element ) {
                 var update_button = jQuery( element );

                 var ajax_url = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';

                 var data = {
                     action: 'pbc_update_clear_cache_time',
                     nonce: '<?php echo esc_js( wp_create_nonce( 'pbc_update_clear_cache_time' ) ); ?>'
                 };

                 update_button.attr('disabled', true);
                 jQuery.post(ajax_url, data, function() {
                     update_button.attr('disabled', false );
                     jQuery('.pbc-notice-update-caching-time').hide().addClass('is-dismissible').fadeIn('fast');
                 });
             }
         </script>

         <?php
    }

    /**
     * Ajax actions to clear cache manually.
     */
    public function update_clear_cache_time()
    {
        check_ajax_referer( 'pbc_update_clear_cache_time', 'nonce' );

        update_option( 'A_u_cache_clear_cache_time', time() );

        exit;
    }
}

new A_u_cache_Admin_Settings();