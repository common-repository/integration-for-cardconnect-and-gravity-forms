<?php
/**
 * Main Gravity Forms / CardConnect class definition file.
 */

GFForms::include_payment_addon_framework();

/**
 * The Gravity Forms / CardConnect payment add-on class.
 */
class GF_CardConnect extends GFPaymentAddOn {

	/**
	 * Add-on version.
	 *
	 * @var string
	 */
	protected $_version = GF_CARDCONNECT_VERSION;

	/**
	 * Minimum tested Gravity Forms version.
	 *
	 * @var string
	 */
	protected $_min_gravityforms_version = '2.4.6';

	/**
	 * Add-on slug.
	 *
	 * @var string
	 */
	protected $_slug = 'gravityforms-cardconnect';

	/**
	 * Path to add-on files.
	 *
	 * @var string
	 */
	protected $_path = 'gravityforms-cardconnect/gravityforms-cardconnect.php';

	/**
	 * Path to main add-on file.
	 *
	 * @var string
	 */
	protected $_full_path = __FILE__;

	/**
	 * Developer URL.
	 *
	 * @var string
	 */
	protected $_url = 'https://cornershopcreative.com/';

	/**
	 * Add-on name.
	 *
	 * @var string
	 */
	protected $_title = 'Gravity Forms CardConnect Add-On';

	/**
	 * Abbreviated name for menus, etc.
	 *
	 * @var string
	 */
	protected $_short_title = 'CardConnect';

	/**
	 * Add-on-specific capabilities.
	 *
	 * @var array
	 */
	protected $_capabilities = array( 'gravityforms_cardconnect', 'gravityforms_cardconnect_uninstall' );

	/**
	 * Add-on-specific capabilities.
	 *
	 * @var string
	 */
	protected $_capabilities_settings_page = 'gravityforms_cardconnect';
	protected $_capabilities_form_settings = 'gravityforms_cardconnect';
	protected $_capabilities_uninstall     = 'gravityforms_cardconnect_uninstall';

	/**
	 * Don't use callbacks/webhooks.
	 *
	 * @var bool
	 */
	protected $_supports_callbacks = false;

	/**
	 * Don't let users add a feed to a form unless a credit card field exists.
	 *
	 * @var bool
	 */
	protected $_requires_credit_card = true;

	/**
	 * Indicates if the payment gateway requires monetary amounts to be formatted as the smallest unit for the currency being used.
	 *
	 * For example, $100.00 will be formatted as 10000.
	 *
	 * @since  Unknown
	 * @access protected
	 *
	 * @used-by GFPaymentAddOn::get_amount_export()
	 * @used-by GFPaymentAddOn::get_amount_import()
	 *
	 * @var bool True if the smallest unit should be used. Otherwise, will include the decimal places.
	 */
	protected $_requires_smallest_unit = true;

	/**
	 * The global add-on instance.
	 *
	 * @var GF_CardConnect
	 */
	private static $_instance = null;

	/**
	 * Get the global add-on instance.
	 */
	public static function get_instance() {

		if ( null === self::$_instance ) {
			self::$_instance = new GF_CardConnect();
		}

		return self::$_instance;
	}

	/**
	 * Add hooks to run on the site frontend.
	 */
	public function init_frontend() {

		parent::init_frontend();
	}


	public function pre_init() {
		parent::pre_init();
		add_action( 'gfcardconnect_inquire_transaction', array( $this, 'inquire_transaction' ), 10, 3 );
		$settings_option = 'gravityformsaddon_' . $this->_slug . '_settings';
		add_action( "update_option_$settings_option", array( $this, 'clear_cardconnect_api_status_cache' ) );

		add_filter( 'gform_export_fields', array( $this, 'gform_export_fields' ), 10, 1 );
		add_filter( 'gform_export_field_value', array( $this, 'gform_export_field_value' ), 10, 4 );

	}

	/**
	 * Add fields to the add-on settings page.
	 */
	public function plugin_settings_fields() {

		$description = '<p>' . esc_html__( 'The CardConnect API requires a Merchant ID, username, password, and URL.', 'gfcardconnect' ) . '</p>';

		return array(
			array(
				'title'       => 'Gravity Forms CardConnect Add-On',
				'description' => $description,
				'fields'      => array(
					// Validity indicator. See settings_validation_message().
					array(
						'name'  => 'validation_message',
						'label' => '',
						'type'  => 'validation_message',
					),
					array(
						'name'  => 'cardconnect_api_merchid',
						'label' => esc_html__( 'Merchant ID', 'gfcardconnect' ),
						'type'  => 'text',
						'class' => 'medium',
					),
					array(
						'name'  => 'cardconnect_api_username',
						'label' => esc_html__( 'API username', 'gfcardconnect' ),
						'type'  => 'text',
						'class' => 'medium',
					),
					array(
						'name'       => 'cardconnect_api_password',
						'label'      => esc_html__( 'API password', 'gfcardconnect' ),
						'type'       => 'text',
						'input_type' => 'password',
						'class'      => 'medium password',
					),
					array(
						'name'    => 'cardconnect_api_url',
						'label'   => esc_html__( 'API Host', 'gfcardconnect' ),
						'type'    => 'text',
						'class'   => 'medium',
						'tooltip' => esc_html__( 'This is the Host (Domain and optional Port) to use when connecting to the CardConnect API. Example: <code>fts-uat.cardconnect.com</code>', 'gfcardconnect' ),
					),
					array(
						'type'     => 'save',
						'messages' => array(
							'success' => esc_html__( 'Settings have been updated.', 'gfcardconnect' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Display a message indicating whether the provided settings are valid.
	 */
	public function settings_validation_message() {
		// If any of the credentials fields have been filled out, indicate whether they're valid or not.
		if ( $this->is_configured() ) {
			if ( $this->is_valid_cardconnect_auth() ) {
				echo '<p><i class="fa icon-check fa-check gf_valid"></i> <span class="gf_keystatus_valid_text">' . __( 'The CardConnect credentials you have entered are valid.', 'gfcardconnect' ) . '</span></p>';
			} else {
				echo '<p><i class="fa icon-remove fa-times gf_invalid"></i> <span class="gf_keystatus_invalid_text">' . __( 'The CardConnect credentials you have appear to be invalid.', 'gfcardconnect' ) . '</span></p>';
			}
		}
	}

	/**
	 * Prevent feeds being listed or created if the api key isn't valid.
	 *
	 * @return bool
	 */
	public function can_create_feed() {
		return $this->is_valid_cardconnect_auth();
	}

	/**
	 * If the api key is invalid or empty return the appropriate message.
	 *
	 * @return string
	 */
	public function configure_addon_message() {

		// translators: Placeholder is the plugin's name.
		$settings_label = sprintf( esc_html__( '%s Settings', 'gfcardconnect' ), $this->get_short_title() );
		// translators: First placeholder is plugin settings url, second is the settings label.
		$settings_link = sprintf( '<a href="%s">%s</a>', esc_url( $this->get_plugin_settings_url() ), $settings_label );

		$settings = $this->get_plugin_settings();

		if ( ! $this->is_configured() ) {

			// translators: Placeholder is a link to adjust this plugin's settings.
			return sprintf( esc_html__( 'To get started, please configure your %s.', 'gfcardconnect' ), $settings_link );
		}

		// translators: Placeholder is a link to adjust this plugin's settings.
		return sprintf( esc_html__( 'Unable to connect to CardConnect with the provided credentials. Please make sure you have entered valid information on the %s page.', 'gfcardconnect' ), $settings_link );

	}

	/**
	 * Display a warning message instead of the feeds if the API key isn't valid.
	 *
	 * @param array   $form The form currently being edited.
	 * @param integer $feed_id The current feed ID.
	 */
	public function feed_edit_page( $form, $feed_id ) {

		if ( ! $this->can_create_feed() ) {

			echo '<h3><span>' . esc_html( $this->feed_settings_title() ) . '</span></h3>';
			echo '<div>' . wp_kses_post( $this->configure_addon_message() ) . '</div>';

			return;
		}

		parent::feed_edit_page( $form, $feed_id );
	}

	/**
	 * Check to make sure the CardConnect credentials stored in settings actually work.
	 *
	 * @return bool True if they work, false if they don't.
	 */
	public function is_valid_cardconnect_auth() {

		if ( ! $this->is_configured() ) {
			return false;
		}

		// Check cached status (we don't want to make a million test calls if we can avoid it).
		$is_valid = get_transient( 'gravityforms_cardconnect_api_status' );

		// Boolean false indicates an expired/unset transient, so make the API call.
		if ( false === $is_valid ) {

			// Make a test request.
			$is_valid = $this->test_api();

			// Store result as 0 or 1, so we can tell the difference between cached bad credentials and an
			// expired transient.
			set_transient( 'gravityforms_cardconnect_api_status', $is_valid ? 1 : 0, HOUR_IN_SECONDS );
		}

		return (bool) $is_valid;
	}

	/**
	 * Clear cached connection status info whenever the add-on's settings are changed.
	 */
	public function clear_cardconnect_api_status_cache() {
		delete_transient( 'gravityforms_cardconnect_api_status' );
	}

	/**
	 * Get a REST client object we can use to make API calls.
	 *
	 * @return CardConnectRestClient API client object.
	 */
	public function get_api() {

		if ( ! $this->is_configured() ) {
			return false;
		}

		if ( ! class_exists( 'CardConnectRestClient' ) ) {
			require_once 'vendor/CardConnectRestClient.php';
		}

		$settings = $this->get_plugin_settings();

		$client = new CardConnectRestClient(
			$this->get_api_url(),
			rgar( $settings, 'cardconnect_api_username' ),
			rgar( $settings, 'cardconnect_api_password' )
		);

		return $client;
	}

	/**
	 * Get the full URL for the CardConnect API.
	 */
	public function get_api_url() {

		$url_setting = rgar( $this->get_plugin_settings(), 'cardconnect_api_url' );

		if ( empty( $url_setting ) ) {
			return false;
		}

		// If the URL in the GF settings is more than just a domain or IP with or without a port, reject it.
		if ( ! preg_match( '/^(([a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z0-9]|[A-Za-z0-9][A-Za-z0-9\-]*[A-Za-z0-9])(:[0-9]+)?$/', $url_setting ) ) {
			return false;
		}

		return esc_url( 'https://' . $url_setting . '/cardconnect/rest' );
	}

	/**
	 * Get the merchant ID from the plugin settings.
	 */
	public function get_merchant_id() {
		return rgar( $this->get_plugin_settings(), 'cardconnect_api_merchid' );
	}

	/**
	 * Make a test call to the CardConnect API using the credentials found in the plugin settings.
	 */
	public function test_api() {

		// If we don't have credentials, there's no point in even trying.
		if ( ! $this->is_configured() ) {
			return false;
		}

		// Note: we can't use the CardConnect-provided API class for this request, because that sets an
		// 'Accept: application/json' header, which the server will reject.
		$test_request = $this->send_api_request(
			'/',
			'PUT',
			array(
				'merchid' => $this->get_merchant_id(),
			)
		);

		if ( is_wp_error( $test_request ) ) {
			$this->log_error( __METHOD__ . '(): API test call failed: ' . $test_request->get_error_message() );
			return false;
		}

		// If the request is successful, the server will respond with a bit of happy HTML containing
		// these magic words.
		// (see https://developer.cardconnect.com/cardconnect-api#testing-your-api-credentials)
		$response = $test_request['body'];
		$success  = ( false !== strpos( $response, 'CardConnect REST Servlet' ) );

		if ( ! $success ) {
			$this->log_error( __METHOD__ . '(): API test call failed. Server response: ' . $response );
		}

		return $success;
	}

	/**
	 * Make a direct call to the API without using CardConnect's provided PHP class.
	 *
	 * Not every API method has a corresponding method in the class, and some (like the test method)
	 * will get mad if faced with an 'Accept: application/json' header.
	 *
	 * @param string $endpoint API endpoint.
	 * @param string $method   HTTP request method.
	 * @param array  $data     Request data to send as JSON.
	 */
	public function send_api_request( $endpoint, $method, $data ) {

		// If we don't have credentials, bail.
		if ( ! $this->is_configured() ) {
			return false;
		}

		$settings = $this->get_plugin_settings();

		return wp_remote_request(
			trailingslashit( $this->get_api_url() . $endpoint ),
			array(
				'method'                                  => $method,
				'user-agent'                              => 'CardConnectRestClient-PHP (v1.0)',
				// UA used by the 'real' API class.
												'headers' => array(
													'Authorization' => 'Basic ' . base64_encode( rgar( $settings, 'cardconnect_api_username' ) . ':' . rgar( $settings, 'cardconnect_api_password' ) ),
													'Content-Type' => 'application/json',
												),
				'body'                                    => json_encode( $data ),
			)
		);
	}

	/**
	 * Check whether all required settings fields have been filled out.
	 *
	 * @return bool True if no fields are empty. Note that this function doesn't care whether they're
	 *              filled out _correctly_.
	 */
	public function is_configured() {

		$required_fields = array(
			'cardconnect_api_merchid',
			'cardconnect_api_username',
			'cardconnect_api_password',
			'cardconnect_api_url',
		);

		$settings = $this->get_plugin_settings();

		if ( ! is_array( $settings ) ) {
			return false;
		}

		foreach ( $required_fields as $key ) {
			if ( empty( $settings[ $key ] ) ) {
				return false;
			}
		}

		// If we got this far, all settings are set. Yay.
		return true;
	}

	/**
	 * Prompt users to add their CardConnect info before creating a feed.
	 */
	public function feed_list_no_item_message() {

		if ( ! $this->is_configured() ) {
			return sprintf(
				// translators: %1$s: opening <a> tag; %2$s: closing </a> tag.
				esc_html__( 'To get started, please configure your %1$sCardConnect Settings%2$s!', 'gfcardconnect' ),
				'<a href="' . admin_url( 'admin.php?page=gf_settings&subview=' . $this->_slug ) . '">',
				'</a>'
			);
		} else {
			return parent::feed_list_no_item_message();
		}
	}

	/**
	 * Specify fields for individual payment feeds.
	 */
	public function feed_settings_fields() {

		if ( $this->is_configured() && ! $this->is_valid_cardconnect_auth() ) {
			?>
			<div class="notice notice-error cardconnect_api_message"><p>
				<?php
				sprintf(
					// translators: %1$s: opening <a> tag; %2$s: closing </a> tag.
					esc_html__( 'Warning: Unable to connect to the CardConnect API. Please check your %1$sCardConnect settings%2$s to make sure your merchant ID, username, password, and URL are correct.', 'gfcardconnect' ),
					'<a href="' . esc_url( $this->get_plugin_settings_url() ) . '">',
					'</a>'
				);
				?>
			</p></div>
			<?php
		}

		$fields = array(
			array(
				'description' => '',
				'fields'      => array(
					array(
						'name'     => 'feedName',
						'label'    => esc_html__( 'Name', 'gfcardconnect' ),
						'type'     => 'text',
						'class'    => 'medium',
						'required' => true,
						'tooltip'  => '<h6>' . esc_html__( 'Name', 'gfcardconnect' ) . '</h6>' . esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gfcardconnect' ),
					),
					array(
						'name'          => 'requestProfile',
						'label'         => esc_html__( 'Request Profile ID', 'gfcardconnect' ),
						'type'          => 'select',
						'choices'       => [
							[
								'label' => esc_html__( 'Yes', 'gfcardconnect' ),
								'value' => true,
							],
							[
								'label' => esc_html__( 'No', 'gfcardconnect' ),
								'value' => false,
							],
						],
						'default_value' => false,
					),
					array(
						'name'          => 'transactionType',
						'type'          => 'hidden',
						'default_value' => 'product',
					),
					array(
						'name'          => 'paymentAmount',
						'label'         => esc_html__( 'Payment Amount', 'gfcardconnect' ),
						'type'          => 'select',
						'choices'       => $this->product_amount_choices(),
						'required'      => true,
						'default_value' => 'form_total',
						'tooltip'       => '<h6>' . esc_html__( 'Payment Amount', 'gfcardconnect' ) . '</h6>' . esc_html__( "Select which field determines the payment amount, or select 'Form Total' to use the total of all pricing fields as the payment amount.", 'gfcardconnect' ),
					),
					array(
						'name'          => 'doCapture',
						'label'         => esc_html__( 'Capture payments immediately?', 'gfcardconnect' ),
						'type'          => 'radio',
						'choices'       => array(
							array(
								'name'  => 'capture',
								'value' => 'capture',
								'label' => esc_html__( 'Yes, authorize and capture payment', 'gfcardconnect' ),
							),
							array(
								'name'  => 'authorize_only',
								'value' => 'authorize_only',
								'label' => esc_html__( 'No, authorize only', 'gfcardconnect' ),
							),
						),
						'required'      => true,
						'default_value' => 'capture',
						'tooltip'       => '<h6>' . esc_html__( 'Capturing Payments', 'gfcardconnect' ) . '</h6>' . esc_html__( 'By default, this add-on will charge users\' credit or debit cards as soon as they submit your form. If you would prefer to authorize payments instead, and capture them yourself later from the CardPointe merchant portal, select "No, authorize only". This may be useful if, for instance, you are using your form to sell goods or services, and want to charge your users after shipping or after service.', 'gfcardconnect' ),
					),
					array(
						'name'      => 'billingInformation',
						'label'     => esc_html__( 'Billing Information', 'gfcardconnect' ),
						'type'      => 'field_map',
						'field_map' => $this->billing_info_fields(),
						'tooltip'   => '<h6>' . esc_html__( 'Billing Information', 'gfcardconnect' ) . '</h6>' . esc_html__( 'Map your Form Fields to the available listed fields.', 'gfcardconnect' ),
					),
					array(
						'name'    => 'conditionalLogic',
						'label'   => esc_html__( 'Conditional Logic', 'gfcardconnect' ),
						'type'    => 'feed_condition',
						'tooltip' => '<h6>' . esc_html__( 'Conditional Logic', 'gfcardconnect' ) . '</h6>' . esc_html__( 'When conditions are enabled, form submissions will only be sent to the payment gateway when the conditions are met. When disabled, all form submissions will be sent to the payment gateway.', 'gfcardconnect' ),
					),
				),
			),
		);

		/**
		 * Filter the feed settings fields for a CardConnect feed.
		 *
		 * @param array $fields The default feed settings fields.
		 */
		return apply_filters( 'gform_cardconnect_feed_settings_fields', $fields );
	}

	/**
	 * Override the default columns for the list of feeds.
	 *
	 * The base payment add-on class includes a column for "transaction type," but this add-on only
	 * supports one transaction type, so we don't need to show it.
	 */
	public function feed_list_columns() {
		$columns = parent::feed_list_columns();
		unset( $columns['transactionType'] );
		return $columns;
	}

	/**
	 * Set fields for the Billing Information field map.
	 */
	public function billing_info_fields() {

		$fields = array(
			array(
				'name'     => 'address',
				'label'    => esc_html__( 'Address', 'gfcardconnect' ),
				'required' => false,
			),
			array(
				'name'     => 'city',
				'label'    => esc_html__( 'City', 'gfcardconnect' ),
				'required' => false,
			),
			array(
				'name'     => 'state',
				'label'    => esc_html__( 'State', 'gfcardconnect' ),
				'required' => false,
			),
			array(
				'name'     => 'zip',
				'label'    => esc_html__( 'Zip', 'gfcardconnect' ),
				'required' => false,
			),
			array(
				'name'     => 'country',
				'label'    => esc_html__( 'Country', 'gfcardconnect' ),
				'required' => false,
			),
			array(
				'name'     => 'email',
				'label'    => esc_html__( 'Email', 'gfcardconnect' ),
				'required' => false,
			),
			array(
				'name'     => 'phone',
				'label'    => esc_html__( 'Phone', 'gfcardconnect' ),
				'required' => false,
			),
		);

		return $fields;
	}

	/**
	 * Set the title for the Billing Information field maps left-hand column.
	 */
	public function field_map_title() {
		return esc_html__( 'CardConnect Field', 'gfcardconnect' );
	}

	/**
	 * Prevent the GFPaymentAddOn version of the options field being added to the feed settings.
	 *
	 * @return bool
	 */
	public function option_choices() {
		return false;
	}

	/**
	 * Authorize a transaction.
	 *
	 * This method is executed during the form validation process and allows the form submission process to fail with a
	 * validation error if there is anything wrong with the payment/authorization. This method is only supported by
	 * single payments. For subscriptions or recurring payments, use the GFPaymentAddOn::subscribe() method.
	 *
	 * @param array $feed            Current configured payment feed.
	 * @param array $submission_data Contains form field data submitted by the user as well as payment information
	 *                               (i.e. payment amount, setup fee, line items, etc...).
	 * @param array $form            The Form Object.
	 * @param array $entry           The Entry Object. NOTE: the entry hasn't been saved to the database at this point,
	 *                               so this $entry object does not have the 'ID' property and is only a memory
	 *                               representation of the entry.
	 * @param array $max_retries     The maximum number of times an authorization should be retried before giving up.
	 *
	 * @return array {
	 *     Return an $authorization array.
	 *
	 *     @type bool   $is_authorized  True if the payment is authorized. Otherwise, false.
	 *     @type string $error_message  The error message, if present.
	 *     @type string $transaction_id The transaction ID.
	 *     @type array  $captured_payment {
	 *         If payment is captured, an additional array is created.
	 *
	 *         @type bool   $is_success     If the payment capture is successful.
	 *         @type string $error_message  The error message, if any.
	 *         @type string $transaction_id The transaction ID of the captured payment.
	 *         @type int    $amount         The amount of the captured payment, if successful.
	 *     }
	 * }
	 */
	public function authorize( $feed, $submission_data, $form, $entry, $max_retries = 2 ) {

		try {
			$this->log_debug( __METHOD__ . '(): Handling authorization request... ' );

			$client = $this->get_api();

			// Get credit card type (API docs say this isn't required but examples all include it...).
			$card_type = GFCommon::get_card_type( rgar( $submission_data, 'card_number' ) );
			if ( is_array( $card_type ) && ! empty( $card_type['slug'] ) ) {
				$card_type = strtoupper( $card_type['slug'] );
			} else {
				$card_type = '';
			}

			// Strip out non-digits from phone, because CardPointe truncates after 15 chars
			$phone_digits = preg_replace( '/[^\d]/', '', rgar( $submission_data, 'phone' ) );

			// Convert country name to country code, because CardPointe truncates after 3 chars
			$country_code = GF_Fields::get( 'address' )->get_country_code( rgar( $submission_data, 'country' ) );

			$request = array(
				'merchid'  => $this->get_merchant_id(),
				'accttype' => $card_type,
				'account'  => rgar( $submission_data, 'card_number' ),
				'expiry'   => sprintf(
					'%02d%02d',
					rgars( $submission_data, 'card_expiration_date/1' ),
					rgars( $submission_data, 'card_expiration_date/0' )
				),
				'cvv2'     => rgar( $submission_data, 'card_security_code' ),
				'amount'   => $this->get_amount_export( $submission_data['payment_amount'], rgar( $entry, 'currency' ) ),
				'currency' => rgar( $entry, 'currency' ),
				'name'     => rgar( $submission_data, 'card_name' ),
				'address'  => rgar( $submission_data, 'address' ),
				// In v1.2.0 and below, we sent the address under the key 'street', but the API appears to
				// expect it as 'address' now. Let's keep 'street' for backwards compatibility just in case.
				'street'   => rgar( $submission_data, 'address' ),
				'city'     => rgar( $submission_data, 'city' ),
				'region'   => rgar( $submission_data, 'state' ),
				'country'  => $country_code,
				'phone'    => $phone_digits,
				'postal'   => rgar( $submission_data, 'zip' ),
				'email'    => rgar( $submission_data, 'email' ),
				'tokenize' => 'Y',
			);

			$this->log_debug( __METHOD__ . '(): Feed metadata and settings. ' . print_r( $feed['meta'], true ) );
			if ( ! empty( $feed['meta']['requestProfile'] ) && $feed['meta']['requestProfile'] ) {
				$request['profile'] = 'Y';
			}

			$response = $client->authorizeTransaction( $request );

			// Handle invalid or unexpected response (some users are getting "Illegal string offset 'respstat'" warnings).
			if ( ! is_array( $response ) || empty( $response['respstat'] ) ) {
				$this->log_debug( __METHOD__ . '(): Authorization failed with unexpected response: ' . print_r( $response, true ) );
				throw new Exception( 'Unexpected API response: ' . print_r( $response, true ) );
			}

			// 'B' indicates that we should retry this authorization.
			if ( 'B' === $response['respstat'] ) {
				if ( $max_retries > 0 ) {
					$this->log_debug( __METHOD__ . '(): CardConnect responded with code B/Retry. Retrying (' . absint( $max_retries ) . ' retries remaining)...' );
					// Call again, decrementing $max_retries so we eventually give up.
					return $this->authorize( $feed, $submission_data, $form, $entry, $max_retries - 1 );
				} else {
					throw new Exception( 'Exceeded maximum retry count' );
				}
			} elseif ( 'A' !== $response['respstat'] ) {
				// Anything else should carry some kind of error message response text from CardConnect.
				$this->log_error( __METHOD__ . '(): Authorization failed. ' . print_r( $response, true ) );
				throw new Exception( $response['resptext'] );
			}

			$this->log_debug( __METHOD__ . '(): Authorization successful. ' . print_r( $response, true ) );
			return array(
				'is_authorized'  => true,
				'error_message'  => '',
				'transaction_id' => $response['retref'],
				'expiry'         => $response['expiry'] ?? '',
				'profileid'      => $response['profileid'] ?? '',
			);

		} catch ( Exception $e ) {
			$this->log_error( __METHOD__ . '(): Caught exception during authorization: ' . $e->getMessage() );
			return array(
				'is_authorized'  => false,
				'error_message'  => $e->getMessage(),
				'transaction_id' => '',
				'expiry'         => '',
				'profileid'      => '',
			);
		}//end try
	}

	/**
	 * Capture an authorized transaction.
	 *
	 * @param array $authorization   Contains the result of the authorize() function.
	 * @param array $feed            Current configured payment feed.
	 * @param array $submission_data Contains form field data submitted by the user as well as payment information.
	 *                               (i.e. payment amount, setup fee, line items, etc...).
	 * @param array $form            Current form array containing all form settings.
	 * @param array $entry           Current entry array containing entry information (i.e data submitted by users).
	 *
	 * @return array {
	 *     Return an array with the information about the captured payment in the following format:
	 *
	 *     @type bool   $is_success     If the payment capture is successful.
	 *     @type string $error_message  The error message, if any.
	 *     @type string $transaction_id The transaction ID of the captured payment.
	 *     @type int    $amount         The amount of the captured payment, if successful.
	 *     @type string $payment_method The card issuer.
	 * }
	 */
	public function capture( $authorization, $feed, $submission_data, $form, $entry ) {

		try {
			$this->log_debug( __METHOD__ . '(): Capture request received for entry #' . rgar( $entry, 'id' ) );

			// If this is an authorize-only feed, stop here.
			if ( ! empty( $feed['meta']['doCapture'] ) && 'authorize_only' === $feed['meta']['doCapture'] ) {
				$this->log_debug( __METHOD__ . '(): Feed #' . $feed['id'] . ' is configured to authorize payments without capture. Skipping capture.' );
				$this->add_note( $entry['id'], 'Payment authorized. Skipping capture because feed #' . $feed['id'] . ' is configured to authorize payments without capturing them.', 'success' );
				// Returning null here signals to the GFPaymentAddOn class that complete_authorization()
				// should be called instead of complete_payment().
				return;
			}

			$client = $this->get_api();

			$request = array(
				'merchid' => $this->get_merchant_id(),
				'retref'  => rgar( $authorization, 'transaction_id' ),
			);

			$response = $client->CaptureTransaction( $request );

			// Handle invalid or unexpected response.
			if ( ! is_array( $response ) || empty( $response['setlstat'] ) ) {
				$this->log_debug( __METHOD__ . '(): Capture failed with unexpected response: ' . print_r( $response, true ) );
				throw new Exception( 'Unexpected API response: ' . print_r( $response, true ) );
			}

			if ( ! in_array( $response['setlstat'], array( 'Accepted', 'Queued for Capture' ), true ) ) {
				$this->log_error( __METHOD__ . '(): Capture failed. ' . print_r( $response, true ) );
				throw new Exception( $response['setlstat'] );
			}

			$is_queued = 'Queued for Capture' === $response['setlstat'];

			if ( $is_queued ) {
				// Check status again in six hours.
				$this->schedule_inquiry(
					rgar( $authorization, 'transaction_id' ),
					// `retref` parameter for API call.
					rgar( $entry, 'id' ),
					// Current entry ID.
					12
					// Max number of subsequent retries before giving up on this transaction.
					// 12 retries * 6 hours = we'll only give up on a transaction after 78 hours or more.
				);
			}

			// Save the token & authcode for export
			// Must be done in Capture because the entry doesn't have an id yet in authorize
			$capture_meta_keys = [ 'authcode', 'token' ];
			foreach ( $capture_meta_keys as $meta_key ) {
				// SKIP if missing or empty
				if ( empty( $response[ $meta_key ] ) ) {
					continue;
				}
				// Save the to entry meta
				gform_update_meta( $entry['id'], $meta_key, $response[ $meta_key ], $entry['form_id'] );
			}
			// Save authorization fields for export, too
			$auth_meta_keys = [ 'expiry', 'profileid' ];
			foreach ( $auth_meta_keys as $meta_key ) {
				// SKIP if missing or empty
				if ( empty( $authorization[ $meta_key ] ) ) {
					continue;
				}
				// Save the to entry meta
				gform_update_meta( $entry['id'], $meta_key, $authorization[ $meta_key ], $entry['form_id'] );
			}

			$this->log_debug( __METHOD__ . '(): Capture successful. ' . print_r( $response, true ) );
			return array(
				'is_success'     => true,
				'is_queued'      => $is_queued,
				'error_message'  => '',
				'transaction_id' => rgar( $authorization, 'transaction_id' ),
				'amount'         => $response['amount'],
				'payment_method' => 'CardConnect',
			);
		} catch ( Exception $e ) {

			$this->log_error( __METHOD__ . '(): Caught exception during capture: ' . $e->getMessage() );
			return array(
				'is_success'     => false,
				'error_message'  => $e->getMessage(),
				'transaction_id' => rgar( $authorization, 'transaction_id' ),
				'amount'         => rgar( $authorization, 'amount' ),
				'payment_method' => 'CardConnect',
			);
		}//end try
	}

	/**
	 * Schedule an action one hour from now to check the settlement status of a transaction.
	 *
	 * @param string $retref       Transaction ID as returned by the CardPointe API.
	 * @param int    $entry_id     Form entry ID.
	 * @param int    $max_attempts If the transaction is still queued for capture, check back up to
	 *                             this many times before giving up.
	 */
	protected function schedule_inquiry( $retref, $entry_id, $max_attempts ) {
		return as_schedule_single_action(
			time() + 6 * HOUR_IN_SECONDS,
			'gfcardconnect_inquire_transaction',
			array( $retref, $entry_id, $max_attempts ),
			'gfcardconnect'
		);
	}

	/**
	 * Check the settlement status of a transaction.
	 *
	 * This function is executed on the `gfcardconnect_inquire_transaction` hook.
	 *
	 * @param string $retref       Transaction ID as returned by the CardPointe API.
	 * @param int    $entry_id     Form entry ID.
	 * @param int    $max_attempts If the transaction is still queued for capture, check back up to
	 *                             this many times before giving up.
	 */
	public function inquire_transaction( $retref, $entry_id, $max_attempts ) {

		$transaction_description = 'transaction #' . absint( $retref ) . ' (entry #' . absint( $entry_id ) . ')';
		$this->log_debug( __METHOD__ . "(): Inquiring with CardConnect about status of $transaction_description" );

		$max_attempts--;

		try {
			$client = $this->get_api();

			$inquiry = $client->inquireTransaction( $this->get_merchant_id(), $retref );
			if ( ! is_array( $inquiry ) ) {
				$this->log_error( __METHOD__ . "(): Invalid inquireTransaction response for $transaction_description. " . print_r( $inquiry, true ) );
				throw new Exception( 'Invalid inquireTransaction response: ' . print_r( $inquiry, true ) );
			}

			$setlstat = $inquiry['setlstat'];
			$this->log_debug( __METHOD__ . "(): Status for $transaction_description: $setlstat" );

			switch ( $setlstat ) {

				case 'Accepted':
					// The payment has been processed successfully.
					$this->add_note( $entry_id, 'Payment accepted.', 'success' );
					GFAPI::update_entry_property( $entry_id, 'payment_status', 'Paid' );
					// This should already be the case.
					return;
					break;

				case 'Queued for Capture':
					// If we've already checked on this payment many times, let's give up, and set the
					// payment status to Failed to indicate that payment hasn't really gone through.
					if ( $max_attempts < 0 ) {
						$this->log_error( __METHOD__ . "(): Settlement status remains 'Queued for Capture' after too many re-inquiries. Giving up on $transaction_description." );
						$this->add_note( $entry_id, 'Payment remains queued (neither accepted nor rejected). There may be a problem with the CardPointe service.', 'error' );
						GFAPI::update_entry_property( $entry_id, 'payment_status', 'Failed' );
						return;
					}
					break;

				default:
					// Statuses other than Accepted and Queued should be treated as failure.
					$this->log_error( __METHOD__ . "(): Settlement status indicates neither success nor pending success. Marking $transaction_description as Failed." );
					$this->add_note( $entry_id, "Payment failed! CardPointe settlement status: '$setlstat'.", 'error' );
					GFAPI::update_entry_property( $entry_id, 'payment_status', 'Failed' );
					return;
			}//end switch
		} catch ( Exception $e ) {

			$this->log_error( __METHOD__ . '(): Caught exception during inquiry: ' . $e->getMessage() );

			// If we've already checked this payment many times, give up and set the payment status to
			// Failed, because clearly the API isn't working correctly.
			if ( $max_attempts < 0 ) {
				$this->add_note( $entry_id, 'Payment failed! There may be a problem with the CardPointe service. Caught exception during inquiry: ' . $e->getMessage(), 'error' );
				GFAPI::update_entry_property( $entry_id, 'payment_status', 'Failed' );
				return;
			}
		}//end try

		// If we made it this far, plan to check back again with the API later.
		$this->log_debug( __METHOD__ . "(): Scheduling next inquiry for $transaction_description (max. $max_attempts attempts remaining)." );
		$this->schedule_inquiry( $retref, $entry_id, $max_attempts );
	}
	/**
	 * Add meta fields to entries export screen
	 *
	 * @param  $form object
	 * @return object
	 */
	public function gform_export_fields( $form ) {
		// Get all active Feeds of this add-on on this form
		// https://docs.gravityforms.com/managing-add-on-feeds-with-the-gfapi/#get-feeds
		$feeds = GFAPI::get_feeds( null, $form['id'], $this->_slug );
		// BAIL if this form does not have any active instances of this feed.
		if ( is_wp_error( $feeds ) ) {
			return $form;
		}

		$meta_keys     = [ 'authcode', 'token', 'expiry', 'profileid' ];
		$export_fields = [];
		foreach ( $meta_keys as $meta_key ) {
			array_push(
				$export_fields,
				array(
					'id'    => $meta_key,
					'label' => __(
						"CardPointe ($meta_key)",
						'gfcardconnect'
					),
				)
			);
		}
		// Insert the CardPointe export fields right before created_by
		$second_array = [];
		foreach ( $form['fields'] as $i => $field ) {
			if ( 'created_by' === $field['id'] ) {
				$second_array = array_splice( $form['fields'], $i );
				break;
			}
		}
		$form['fields'] = array_merge( $form['fields'], $export_fields, $second_array );
		return $form;
	}
	/**
	 * Populate meta field values when exporting entries
	 *
	 * @param  string $value    Value of the field being exported
	 * @param  int    $form_id     ID of the current form.
	 * @param  int    $field_id    ID of the current field.
	 * @param  object $entry     The current entry.
	 * @return string
	 */
	public function gform_export_field_value( $value, $form_id, $field_id, $entry ) {
		$meta_keys = [ 'authcode', 'token', 'expiry', 'profileid' ];
		if ( in_array( $field_id, $meta_keys, true ) ) {
			$value = gform_get_meta( $entry['id'], $field_id ) ?? '';
		}
		return $value;
	}

	/**
	 * Add supported notification events.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFFeedAddOn::notification_events()
	 * @uses    GFFeedAddOn::has_feed()
	 *
	 * @param array $form The form currently being processed.
	 *
	 * @return array|false The supported notification events. False if feed cannot be found within $form.
	 */
	public function supported_notification_events( $form ) {

		// If this form does not have a Stripe feed, return false.
		if ( ! $this->has_feed( $form['id'] ) ) {
			return false;
		}

		// Return CardPointe notification events.
		return array(
			'complete_payment'          => esc_html__( 'Payment Completed', 'gfcardconnect' ),
		);

	}
}
