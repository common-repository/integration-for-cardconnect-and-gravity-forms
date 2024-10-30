<?php
/**
Plugin Name: Integration for CardConnect and Gravity Forms
Description: Allows Gravity Forms to process payments using CardConnect's CardPointe gateway
Version: 1.3.0
Author: Cornershop Creative
Author URI: https://cornershopcreative.com
Text Domain: gfcardconnect
 */

define( 'GF_CARDCONNECT_VERSION', '1.3.0' );

add_action( 'gform_loaded', array( 'GF_CardConnect_Bootstrap', 'load' ), 5 );

// Register the bundled version of Action Scheduler for loading. If another plugin includes a newer
// version, that one will be loaded instead.
require_once plugin_dir_path( __FILE__ ) . '/vendor/action-scheduler/action-scheduler.php';

/**
 * Tells GravityForms to load up the Add-On
 */
class GF_CardConnect_Bootstrap {

	public static function load() {

		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		require_once 'class-gravityforms-cardconnect.php';

		GFAddOn::register( 'GF_CardConnect' );

		add_action( 'admin_enqueue_scripts',                         array( __CLASS__, 'admin_enqueue_scripts' ) );
		add_action( 'admin_notices',                                 array( __CLASS__, 'maybe_display_sunset_warning' ) );
		add_action( 'wp_ajax_gf_cardconnect_dismiss_sunset_warning', array( __CLASS__, 'dismiss_sunset_warning' ) );
	}

	/**
	 * Enqueue the script that makes the sunset warning dismissible.
	 */
	public static function admin_enqueue_scripts() {
		wp_enqueue_script( 'gf-cardconnect-admin', plugins_url( 'admin.js', __FILE__ ), 'jquery', false, true );
	}

	/**
	 * Display the sunset warning, if appropriate.
	 */
	public static function maybe_display_sunset_warning() {

		// Don't display the sunset warning to users who can't add or remove plugins.
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		// Don't display the sunset warning to users who have already dismissed it.
		if ( '' !== get_user_meta( get_current_user_id(), '_gf_cardconnect_sunset_warning_dismissed', true ) ) {
			return;
		}

		?>
		<div class="notice notice-warning gf-cardconnect-sunset-warning is-dismissible">
			<p><strong><?php echo esc_html__( 'Warning: End of support for the Integration for CardConnect and Gravity Forms plugin', 'gfcardconnect' ); ?></strong></p>
			<p><?php
				printf(
					// translators: %s: support email link
					esc_html__( 'Cornershop is no longer actively maintaining or enhancing this plugin. We are currently looking for a new home for someone to take over the ownership and provide quality support for customers that need it. The plugin will continue to work in its current form, while we search for a new home. If you are interested in custom support on this plugin or taking over ownership, please contact us at %s.', 'gfcardconnect' ),
					'<a href="mailto:support@cornershopcreative.com">support@cornershopcreative.com</a>'
				);
			?></p>
		</div>
		<?php
	}

	/**
	 * AJAX callback for dismissing the sunset warning.
	 */
	public static function dismiss_sunset_warning() {
		update_user_meta( get_current_user_id(), '_gf_cardconnect_sunset_warning_dismissed', 1 );
		wp_send_json_success();
	}
}

function gf_cardconnect() {
	return GF_CardConnect::get_instance();
}
