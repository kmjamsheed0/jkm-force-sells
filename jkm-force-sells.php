<?php
/**
 * Plugin Name: Force Sells and Smart Bundles for WooCommerce
 * Description: Automatically add selected products to the cart with a main item, creating smart bundles effortlessly.
 * Author:      WCPlugins
 * Version:     1.2.1
 * Author URI:  https://wcplugins.xyz/
 * Plugin URI:  https://wcplugins.xyz/force-sells
 * Text Domain: jkm-force-sells
 * Domain Path: /languages
 * License:		GPL-2.0-or-later
 * License URI:	https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 * WC requires at least: 4.0.0
 * WC tested up to: 10.9
 */

if(!defined('ABSPATH')){ exit; }

// Add WooCommerce feature compatibility declarations
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('remote_logging', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

if (!function_exists('is_woocommerce_active')){
	function is_woocommerce_active(){
	    $active_plugins = (array) get_option('active_plugins', array());
	    if(is_multisite()){
		   $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
	    }
	    return in_array('woocommerce/woocommerce.php', $active_plugins) || array_key_exists('woocommerce/woocommerce.php', $active_plugins) || class_exists('WooCommerce');
	}
}

if(is_woocommerce_active()) {
	if(!class_exists('JKM_Force_Sells_Products')){
		class JKM_Force_Sells_Products {
			const TEXT_DOMAIN = 'jkm-force-sells';

			public function __construct(){
				add_action('init', array($this, 'init'));
			}

			public function init() {
				define('JKMFS_VERSION', '1.2.1');
				!defined('JKMFS_BASE_NAME') && define('JKMFS_BASE_NAME', plugin_basename( __FILE__ ));
				!defined('JKMFS_PATH') && define('JKMFS_PATH', plugin_dir_path( __FILE__ ));
				!defined('JKMFS_URL') && define('JKMFS_URL', plugins_url( '/', __FILE__ ));
				// !defined('JKMFS_ASSETS_URL') && define('JKMFS_ASSETS_URL', JKMFS_URL .'assets/');

				$this->load_plugin_textdomain();

				require_once( JKMFS_PATH . 'includes/class-jkmfs.php' );
				JKMFS::instance();
			}

			public function load_plugin_textdomain(){
				$locale = apply_filters('plugin_locale', get_locale(), self::TEXT_DOMAIN);

				load_textdomain(self::TEXT_DOMAIN, WP_LANG_DIR.'/jkm-force-sells/'.self::TEXT_DOMAIN.'-'.$locale.'.mo');
				load_plugin_textdomain(self::TEXT_DOMAIN, false, dirname(JKMFS_BASE_NAME) . '/languages/');
			}
		}
	}
	new JKM_Force_Sells_Products();
}
