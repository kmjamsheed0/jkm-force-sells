<?php
/**
 * Woo Force Sells Settings
 *
 * @author   Jamsheed KM
 * @since    1.0.0
 *
 * @package    jkm-force-sells
 * @subpackage jkm-force-sells/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if(!class_exists('JKMFS')) :
class JKMFS {
    
    private static $instance = null;
    private $screen_id;

    private function __construct() {
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        add_action('admin_menu', array($this, 'jkmfs_admin_menu'));
        add_action('admin_init', array($this, 'jkmfs_register_settings'));
        add_filter('plugin_action_links_'.JKMFS_BASE_NAME, array($this, 'add_settings_link'));
    }

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function load_dependencies() {
        require_once JKMFS_PATH . 'includes/utils/class-jkmfs-utils.php';
        require_once JKMFS_PATH . 'admin/class-jkmfs-admin.php';
        require_once JKMFS_PATH . 'public/class-jkmfs-public.php';
    }

    private function define_admin_hooks() {
        $admin = new JKMFS_Admin();
        add_action( 'woocommerce_product_options_related', array( $admin, 'jkmfs_write_panel_tab' ) );
        add_action( 'woocommerce_process_product_meta', array( $admin, 'jkmfs_process_extra_product_meta' ), 1, 2 );
        add_action( 'admin_enqueue_scripts', array($admin, 'enqueue_admin_styles_and_scripts') );
    }

    private function define_public_hooks() {
        $public = new JKMFS_Public();

        add_action('wp_enqueue_scripts', array($public, 'enqueue_public_styles_and_scripts'));

        // Retrieve settings from the database
        $options = get_option('jkmfs_settings');
        $hook_name = isset($options['display_position']) ? $options['display_position'] : 'woocommerce_before_add_to_cart_button';

        //Product display related hooks:
        $prd_display_hn = apply_filters('jkmfs_products_display_hook_name', $hook_name);        
        $prd_display_hp = apply_filters('jkmfs_products_display_hook_priority', 10);

        add_action( $prd_display_hn, array( $public, 'jkmfs_show_force_sell_products' ), $prd_display_hp );

        add_action( 'woocommerce_add_to_cart', array( $public, 'jkmfs_add_force_sell_items_to_cart' ), 11, 6 );
        add_action( 'woocommerce_after_cart_item_quantity_update', array( $public, 'jkmfs_update_force_sell_quantity_in_cart' ), 1, 4 );
        add_action( 'woocommerce_remove_cart_item', array( $public, 'jkmfs_update_force_sell_quantity_in_cart' ), 1, 2 );
        add_filter( 'woocommerce_get_cart_item_from_session', array( $public, 'jkmfs_get_cart_item_from_session' ), 10, 2 );
        add_filter( 'woocommerce_get_item_data', array( $public, 'jkmfs_get_linked_to_product_data' ), 10, 2 );
        add_action( 'woocommerce_cart_loaded_from_session', array( $public, 'jkmfs_remove_orphan_force_sells' ) );
        add_action( 'woocommerce_cart_loaded_from_session', array( $public, 'jkmfs_maybe_remove_duplicate_force_sells' ) );
        add_filter( 'woocommerce_cart_item_remove_link', array( $public, 'jkmfs_cart_item_remove_link' ), 10, 2 );
        add_filter( 'woocommerce_cart_item_quantity', array( $public, 'jkmfs_cart_item_quantity' ), 10, 2 );
        add_filter( 'woocommerce_store_api_product_quantity_editable', array( $public, 'jkmfs_store_api_product_quantity_editable' ), 10, 3 );
        add_filter( 'woocommerce_store_api_product_quantity_minimum', array( $public, 'jkmfs_store_api_product_quantity_limit' ), 10, 3 );
        add_filter( 'woocommerce_store_api_product_quantity_maximum', array( $public, 'jkmfs_store_api_product_quantity_limit' ), 10, 3 );
        add_action( 'woocommerce_cart_item_removed', array( $public, 'jkmfs_cart_item_removed' ), 30, 2 );
        add_action( 'woocommerce_cart_item_restored', array( $public, 'jkmfs_cart_item_restored' ), 30, 2 );
    }

    public function jkmfs_admin_menu() {
        $capability = JKMFS_Utils::jkmfs_capability();
        $this->screen_id = add_submenu_page('edit.php?post_type=product', __('Force Sell Settings', 'jkm-force-sells'),
        __('Force Sell Settings', 'jkm-force-sells'), $capability, 'jkmfs_force_sell_settings', array($this, 'output_settings'));
    }

    public function output_settings() {
        if (!current_user_can('manage_options')) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'jkm-force-sells' ) );
        }
        echo '<div class="wrap">';
        echo '<h2>' . esc_html__('Force Sell Settings', 'jkm-force-sells') . '</h2>';
        echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Force sell products can be added through each product edit page under the Linked products tab.', 'jkm-force-sells' ) . '</p></div>';
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('jkmfs_force_sell_settings_group');
            do_settings_sections('jkmfs_force_sell_settings');
            submit_button();
            ?>
        </form>
        </div>
        <?php
    }

    public function jkmfs_register_settings() {
        register_setting('jkmfs_force_sell_settings_group', 'jkmfs_settings');

        add_settings_section(
            'jkmfs_display_settings',
            __('Display Settings', 'jkm-force-sells'),
            array($this, 'jkmfs_display_settings_section_callback'),
            'jkmfs_force_sell_settings'
        );

        add_settings_field(
            'jkmfs_display_position',
            __('Display Position', 'jkm-force-sells'),
            array($this, 'jkmfs_display_position_field_callback'),
            'jkmfs_force_sell_settings',
            'jkmfs_display_settings'
        );

        add_settings_field(
            'jkmfs_display_type',
            __('Display Type', 'jkm-force-sells'),
            array($this, 'jkmfs_display_type_field_callback'),
            'jkmfs_force_sell_settings',
            'jkmfs_display_settings'
        );

        add_settings_field(
            'jkmfs_show_images',
            __('Show Product Images', 'jkm-force-sells'),
            array($this, 'jkmfs_show_images_field_callback'),
            'jkmfs_force_sell_settings',
            'jkmfs_display_settings'
        );

        add_settings_field(
            'jkmfs_show_price',
            __('Show Product Prices', 'jkm-force-sells'),
            array($this, 'jkmfs_show_price_field_callback'),
            'jkmfs_force_sell_settings',
            'jkmfs_display_settings'
        );

        add_settings_section(
            'jkmfs_other_settings',
            __('Other Settings', 'jkm-force-sells'),
            null,
            'jkmfs_force_sell_settings'
        );

        add_settings_field(
            'jkmfs_custom_message',
              __('Custom Message', 'jkm-force-sells') .
               ' <span class="jkmfs-tooltip-icon dashicons dashicons-editor-help" data-tooltip="' . esc_attr__('Leave blank for the default message.', 'jkm-force-sells') . '"></span>',
            array($this, 'jkmfs_custom_message_field_callback'),
            'jkmfs_force_sell_settings',
            'jkmfs_other_settings'
        );
    }

    public function jkmfs_display_settings_section_callback() {
        echo '<p>' . esc_html__('Configure the display settings for force-sell products.', 'jkm-force-sells') . '</p>';
    }

    public function jkmfs_display_position_field_callback() {
        $options = get_option('jkmfs_settings');
        $position = isset($options['display_position']) ? $options['display_position'] : 'woocommerce_before_add_to_cart_button';
        ?>
        <select name="jkmfs_settings[display_position]">
            <option value="woocommerce_before_add_to_cart_button" <?php selected($position, 'woocommerce_before_add_to_cart_button'); ?>><?php esc_html_e('Before Add to Cart Button', 'jkm-force-sells'); ?></option>
            <option value="woocommerce_after_add_to_cart_button" <?php selected($position, 'woocommerce_after_add_to_cart_button'); ?>><?php esc_html_e('After Add to Cart Button', 'jkm-force-sells'); ?></option>
        </select>
        <?php
    }

    public function jkmfs_display_type_field_callback() {
        $options = get_option('jkmfs_settings');
        $type = isset($options['display_type']) ? $options['display_type'] : 'list';
        ?>
        <select name="jkmfs_settings[display_type]">
            <option value="list" <?php selected($type, 'list'); ?>><?php esc_html_e('List View', 'jkm-force-sells'); ?></option>
            <option value="grid" <?php selected($type, 'grid'); ?>><?php esc_html_e('Grid View', 'jkm-force-sells'); ?></option>
        </select>
        <?php
    }

    public function jkmfs_show_images_field_callback() {
        $options = get_option('jkmfs_settings');
        $show_images = isset($options['show_images']) ? $options['show_images'] : 'no';
        ?>
        <input type="checkbox" name="jkmfs_settings[show_images]" value="yes" <?php checked($show_images, 'yes'); ?> />
        <label for="jkmfs_settings[show_images]"><?php esc_html_e('Show product images', 'jkm-force-sells'); ?></label>
        <?php
    }

    public function jkmfs_show_price_field_callback() {
        $options = get_option('jkmfs_settings');
        $show_price = isset($options['show_price']) ? $options['show_price'] : 'no';
        ?>
        <input type="checkbox" name="jkmfs_settings[show_price]" value="yes" <?php checked($show_price, 'yes'); ?> />
        <label for="jkmfs_settings[show_price]"><?php esc_html_e('Show product prices', 'jkm-force-sells'); ?></label>
        <?php
    }

    public function jkmfs_custom_message_field_callback() {
        $options = get_option('jkmfs_settings');
        $custom_message = isset($options['custom_message_text']) ? $options['custom_message_text'] : '';
        ?>
        <textarea 
            name="jkmfs_settings[custom_message_text]" 
            id="jkmfs_settings_custom_message_text" 
            rows="4" 
            cols="50" 
            placeholder="<?php esc_attr_e('The following product(s) will also be added to your cart:', 'jkm-force-sells'); ?>"
        ><?php echo esc_textarea($custom_message); ?></textarea>
        <?php
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="' . esc_url(admin_url('edit.php?post_type=product&page=jkmfs_force_sell_settings')) . '">' . __('Settings', 'jkm-force-sells') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

}
endif;
