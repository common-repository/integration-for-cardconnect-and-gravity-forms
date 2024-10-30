=== Integration for CardConnect and Gravity Forms ===
Contributors: kenjigarland, rxnlabs, harmoney
Tags: forms, crm, integration
Requires at least: 3.6
Tested up to: 6.6.2
Requires PHP: 7.0
Stable tag: trunk
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Use [CardConnect](https://cardconnect.com/) to process payments submitted via Gravity Forms.

== Description ==

**Cornershop is no longer actively maintaining or enhancing this plugin. We are currently looking for a new home for someone to take over the ownership and provide quality support for customers that need it. The plugin will continue to work in its current form, while we search for a new home. If you are interested in custom support on this plugin or taking over ownership, please contact us at [support@cornershopcreative.com](mailto:support@cornershopcreative.com).**

If you're using the [Gravity Forms](http://www.gravityforms.com/) plugin, you can now integrate it with CardConnect's CardPointe payment gateway.

To use this Add-On, you'll need:

1. A licensed, active version of Gravity Forms >= 1.9.18
2. A CardConnect CardPointe account

== Installation ==

1. Log in to your WordPress site and go to Plugins > Add New. Search for "Gravity Forms CardConnect" in the "Add plugins" section, then click "Install Now". Once it installs, you will see a link that says "Activate". Click that link, and the link should update to "Active". Alternatively, you can upload the directory directly to your plugins directory (typically /wp-content/plugins/)
2. Navigate to Forms > Settings in the WordPress admin
3. Click on "CardConnect" in the lefthand column of that page
4. Enter your CardConnect Merchant ID number, username, password, and API URL. You will need to contact CardConnect's [support](https://support.cardconnect.com/) to verify these details for your account.
5. Once you've entered your CardConnect account details, create a form or edit an existing form's settings. You'll see a "CardConnect" tab in settings where you can create a "CardConnect Feed". This allows you to send payment data collected by the form to CardConnect. If you've configured payment feeds for other PayPal add-ons before, the interface will be familiar. If not, see Gravity Forms' [guide](https://docs.gravityforms.com/configuring-paypal-payments-pro-feeds/).

== Frequently Asked Questions ==

= Does this work with Ninja Forms, Contact Form 7, Jetpack, etc.? =

Nope. This is specifically an Add-On for Gravity Forms and will not function properly if installed and activated without it.

= What version of Gravity Forms do I need? =

You must be running at least Gravity Forms 1.9.18.

= Can I use this add-on to set up recurring transactions or subscriptions? =

With a workaround! The CardPointe gateway does not offer native support for recurring transactions, so the plugin itself cannot create a recurring transaction. However you can request a Profile ID. This Profile ID, as well as the other required data, are then available through the GF Export feature for all entries.

= Can I use this add-on to authorize payments and capture them later? =

Yes! When you configure a CardConnect feed for your form, look for the "Capture payments immediately?" radio button, and select "No, authorize only". Users who submit your form will not be charged until you capture their authorized payments using the CardPointe portal.

== Changelog ==

= 1.3.0 =
* Added support for the "payment completed" notification event, making it possible to configure a Gravity Forms notification email that will only be sent after payment is captured.
* Billing address, email, and phone number data will now be correctly sent to CardPointe, and will be visible in your CardPointe merchant portal.
* Invalid or unexpected responses from the CardPointe API will now be displayed as errors by Gravity Forms, rather than triggering PHP warnings.
* Fixed a UI issue that could make it appear as if valid CardPointe API credentials were invalid, or invalid ones were valid.
* Added an admin message about the state of support for this plugin.

= 1.2.0 =
* Added the capability to authorize payments without capturing them.

= 1.1.0 =
* Added the capability to request Profile ID from the CardPointe gateway.
* Store and export CardPointe gateway authorization and capture fields.
* Tested on WP 5.9
* Increased minimum PHP version to 7.0

= 1.0.2 =
* Fixed a bug that caused some CardConnect testing domains to be incorrectly rejected as invalid (props @rscs).

= 1.0.1 =
* Fixed a bug in the way some Gravity Forms input was translated to pricing data for CardConnect.

= 1.0 =
* Initial release.
