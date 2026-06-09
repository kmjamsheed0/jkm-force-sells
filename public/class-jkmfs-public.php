<?php
/**
 * Woo Force Sells Public
 *
 * @author   Jamsheed KM
 * @since    1.0.0
 *
 * @package    jkm-force-sells
 * @subpackage jkm-force-sells/public
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if(!class_exists('JKMFS_Public')) :
class JKMFS_Public {

    /**
     * Cart item keys for forced products currently being removed with their parent.
     *
     * @var array
     */
    private $removing_forced_cart_item_keys = array();


    public function enqueue_public_styles_and_scripts() {
        $debug_mode = apply_filters('jkmfs_debug_mode', false);
        $suffix = $debug_mode ? '' : '.min';

        $this->enqueue_styles($suffix);
    }
    
    private function enqueue_styles($suffix) {
        wp_enqueue_style('jkmfs-public-style', JKMFS_URL . 'public/assets/css/jkmfs-public'. $suffix .'.css',array(), JKMFS_VERSION);
    }

    /**
     * Display the add-ons products list
     * 
     * 
     */
    public function jkmfs_show_force_sell_products() {
        global $product;
        $p_id = $product ? $product->get_id() : 0 ;

        // Get force-sell product IDs associated with the current product
        $product_ids = JKMFS_Utils::jkmfs_get_force_sell_ids($p_id, array('normal', 'synced'));
        $product_datas = array();

        // Check if products exist and avoid duplicate IDs
        foreach (array_values(array_unique($product_ids)) as $product_id) {
            $prd = wc_get_product($product_id);

            // Ensure product exists and is not trashed
            if ($prd && $prd->exists() && 'trash' !== $prd->get_status()) {
                $product_datas[$product_id] = array(
                    'title' => $prd->get_name(), // Get product name
                    'image' => $prd->get_image(), // Get product image
                    'price' => $prd->get_price_html()  // Get formatted price HTML
                );
            }
        }

        if (!empty($product_datas)) {
            // Get settings
            $options = get_option('jkmfs_settings');
            $view_type = isset($options['display_type']) ? $options['display_type'] : 'list';
            $show_images = isset($options['show_images']) ? $options['show_images'] : 'no';
            $show_price = isset($options['show_price']) ? $options['show_price'] : 'no';
            $custom_message = isset($options['custom_message_text']) && !empty($options['custom_message_text']) ? $options['custom_message_text'] : __('The following product(s) will also be added to your cart:', 'jkm-force-sells');

            // Apply filters to allow customization of these settings
            $view_type = apply_filters('jkmfs_products_display_type', $view_type);
            $show_images = apply_filters('jkmfs_show_products_images', $show_images);
            $show_price = apply_filters('jkmfs_show_products_prices', $show_price);
            $custom_message = apply_filters('jkmfs_modify_custom_message', $custom_message);

            echo '<div class="clear"></div>';
            echo '<div class="jkmfs-wc-force-sells">';
            echo '<p>' . wp_kses_post($custom_message, 'jkm-force-sells') . '</p>';

            // Switch case to handle different view types
            switch ($view_type) {
                case 'grid':
                    echo '<div class="jkmfs-force-sells-grid">';
                    foreach ($product_datas as $data) {
                        echo '<div class="jkmfs-force-sell-item">';

                        // Display product image
                        if ($show_images === 'yes' && !empty($data['image'])) {

                            echo '<div class="jkmfs-force-sell-image">' . wp_kses_post($data['image']) . '</div>';
                        }

                        echo '<div class="jkmfs-force-sell-title-wrapper">';
                        //Display title
                        echo '<div class="jkmfs-force-sell-title">' . esc_html($data['title']) . '</div>'; // Title below image

                        // Display product price
                        if ($show_price === 'yes' && !empty($data['price'])) {
                            echo '<div class="jkmfs-force-sell-price">(' . wp_kses_post($data['price']) . ')</div>';
                        }

                        echo '</div>';
                        echo '</div>';
                    }
                    echo '</div>';
                    break;

                case 'list':
                default:
                    echo '<ul class="jkmfs-force-sells-list">';
                    foreach ($product_datas as $data) {
                        echo '<li class="jkmfs-force-sell-item">';

                        // Display product image
                        if ($show_images === 'yes' && !empty($data['image'])) {

                            echo '<div class="jkmfs-force-sell-image">' . wp_kses_post($data['image']) . '</div>';
                        }

                        echo '<div class="jkmfs-force-sell-title-wrapper">';
                        // Display product title
                        echo '<div class="jkmfs-force-sell-title">' . esc_html($data['title']) . '</div>'; // Title next to image

                        // Display product price
                        if ($show_price === 'yes' && !empty($data['price'])) {
                            echo '<div class="jkmfs-force-sell-price">(' . wp_kses_post($data['price']) . ')</div>';
                        }

                        echo '</div>';
                        echo '</li>';
                    }
                    echo '</ul>';
                    break;
            }

            echo '</div>';
        }
    }


    /**
     * Add linked products when current product is added to the cart.
     *
     * @param string $cart_item_key  Cart item key.
     * @param int    $product_id     Product ID.
     * @param int    $quantity       Quantity added to cart.
     * @param int    $variation_id   Producat varation ID.
     * @param array  $variation      Attribute values.
     * @param array  $cart_item_data Extra cart item data.
     *
     * @throws Exception Notice message when the forced item is out of stock and parent isn't added.
     */
    public function jkmfs_add_force_sell_items_to_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
        // Check if this product is forced in itself, so it can't force in others (to prevent adding in loops).
        if ( isset( WC()->cart->cart_contents[ $cart_item_key ]['forced_by'] ) ) {
            $forced_by_key = WC()->cart->cart_contents[ $cart_item_key ]['forced_by'];

            if ( isset( WC()->cart->cart_contents[ $forced_by_key ] ) ) {
                return;
            }
        }

        // Don't force products on the manual payment page (they are already forced when creating the order).
        if ( is_checkout_pay_page() ) {
            return;
        }

        $product = wc_get_product( $product_id );

        $force_sell_ids = array_filter( JKMFS_Utils::jkmfs_get_force_sell_ids( $product_id, array( 'normal', 'synced' ) ), array( 'JKMFS_Utils', 'jkmfs_force_sell_is_valid' ) );
        $synced_ids     = array_filter( JKMFS_Utils::jkmfs_get_force_sell_ids( $product_id, array( 'synced' ) ), array( 'JKMFS_Utils', 'jkmfs_force_sell_is_valid' ) );

        if ( ! empty( $force_sell_ids ) ) {
            foreach ( $force_sell_ids as $id ) {
                $cart_id = WC()->cart->generate_cart_id( $id, '', '', array( 'forced_by' => $cart_item_key ) );
                $key     = WC()->cart->find_product_in_cart( $cart_id );

                if ( ! empty( $key ) ) {
                    WC()->cart->set_quantity( $key, WC()->cart->cart_contents[ $key ]['quantity'] );
                } else {
                    $args = array();

                    if ( $synced_ids ) {
                        if ( in_array( $id, $synced_ids, true ) ) {
                            $args['forced_by'] = $cart_item_key;
                        }
                    }

                    $params = apply_filters( 'jkmfs_force_sell_add_to_cart_product', array( 'id' => $id, 'quantity' => $quantity, 'variation_id' => '', 'variation' => '' ), WC()->cart->cart_contents[ $cart_item_key ] );
                    $result = WC()->cart->add_to_cart( $params['id'], $params['quantity'], $params['variation_id'], $params['variation'], $args );

                    // If the forced sell product was not able to be added, don't add the main product either. "Can be filtered".
                    if ( empty( $result ) && apply_filters( 'jkmfs_force_sell_disallow_no_stock', true ) ) {
                        WC()->cart->remove_cart_item( $cart_item_key );
                        /* translators: %s: Product title */
                        throw new Exception( sprintf( esc_html__( '%s will also be removed as they\'re sold together.', 'jkm-force-sells' ),esc_html( $product->get_title() )) );
                    }
                }
            }
        }
    }

    /**
     * Update the forced product's quantity in the cart when the product that forcing
     * it got qty updated.
     *
     * @param string $cart_item_key Cart item key.
     * @param int    $quantity      Quantity.
     */
    public function jkmfs_update_force_sell_quantity_in_cart( $cart_item_key, $quantity = 0 ) {
        if ( ! empty( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
            if ( 0 === $quantity || 0 > $quantity ) {
                $quantity = 0;
            } else {
                $quantity = WC()->cart->cart_contents[ $cart_item_key ]['quantity'];
            }

            foreach ( WC()->cart->cart_contents as $key => $value ) {
                if ( isset( $value['forced_by'] ) && $cart_item_key === $value['forced_by'] ) {
                    $quantity = apply_filters( 'jkmfs_force_sell_update_quantity', $quantity, WC()->cart->cart_contents[ $key ] );

                    if ( 0 >= $quantity ) {
                        $this->removing_forced_cart_item_keys[ $key ] = true;
                    }

                    WC()->cart->set_quantity( $key, $quantity );

                    unset( $this->removing_forced_cart_item_keys[ $key ] );
                }
            }
        }
    }

    /**
     * Get forced product added again to cart when item is loaded from session.
     *
     * @param array $cart_item Item in cart.
     * @param array $values    Item values.
     *
     * @return array Cart item.
     */
    public function jkmfs_get_cart_item_from_session( $cart_item, $values ) {
       if ( isset( $values['forced_by'] ) ) {
            $cart_item['forced_by'] = $values['forced_by'];
        }
        return $cart_item;
    }

    /**
     * Making sure linked products from an item is displayed in cart.
     *
     * @param array $data      Data.
     * @param array $cart_item Cart item.
     *
     * @return array
     */
    public function jkmfs_get_linked_to_product_data( $data, $cart_item ) {
        if ( isset( $cart_item['forced_by'] ) ) {
            $product_key = WC()->cart->find_product_in_cart( $cart_item['forced_by'] );

            if ( ! empty( $product_key ) ) {
                $product_name = WC()->cart->cart_contents[ $product_key ]['data']->get_title();
                $data[]       = array(
                    'name'    => __( 'Linked to', 'jkm-force-sells' ),
                    'display' => $product_name,
                );
            }
        }

        return $data;
    }

    /**
     * Looks to see if a product with the key of 'forced_by' actually exists and
     * deletes it if not.
     */
    public function jkmfs_remove_orphan_force_sells() {
        $cart_contents = WC()->cart->get_cart();

        foreach ( $cart_contents as $key => $value ) {
            if ( isset( $value['forced_by'] ) ) {
                if ( ! array_key_exists( $value['forced_by'], $cart_contents ) ) {
                    WC()->cart->remove_cart_item( $key );
                }
            }
        }
    }

    /**
     * Checks the cart contents to make sure we don't
     * have duplicated force sell products.
     *
     */
    public function jkmfs_maybe_remove_duplicate_force_sells() {
        $cart_contents = WC()->cart->get_cart();
        $product_ids   = array();

        foreach ( $cart_contents as $key => $value ) {
            if ( isset( $value['forced_by'] ) ) {
                $product_ids[] = $value['product_id'];
            }
        }

        foreach ( WC()->cart->get_cart() as $key => $value ) {
            if ( ! isset( $value['forced_by'] ) && in_array( $value['product_id'], $product_ids, true ) ) {
                WC()->cart->remove_cart_item( $key );
            }
        }
    }


    /**
     * Remove link in cart item for Synced Force Sells products.
     *
     * @param string $link          Remove link.
     * @param string $cart_item_key Cart item key.
     *
     * @return string Link.
     */
    public function jkmfs_cart_item_remove_link( $link, $cart_item_key ) {
        if ( isset( WC()->cart->cart_contents[ $cart_item_key ]['forced_by'] ) ) {
            return '';
        }

        return $link;
    }

    /**
     * Makes quantity cart item for Synced Force Sells products uneditable.
     *
     * @param string $quantity      Quantity input.
     * @param string $cart_item_key Cart item key.
     *
     * @return string Quantity input or static text of quantity.
     */
    public function jkmfs_cart_item_quantity( $quantity, $cart_item_key ) {
        if ( isset( WC()->cart->cart_contents[ $cart_item_key ]['forced_by'] ) ) {
            return WC()->cart->cart_contents[ $cart_item_key ]['quantity'];
        }

        return $quantity;
    }

    /**
     * Makes synced force sell quantities read-only in Store API cart responses.
     *
     * @param bool       $editable  Whether the quantity is editable.
     * @param WC_Product $product   Product object.
     * @param array|null $cart_item Cart item data.
     *
     * @return bool
     */
    public function jkmfs_store_api_product_quantity_editable( $editable, $product, $cart_item = null ) {
        if ( is_array( $cart_item ) && isset( $cart_item['forced_by'] ) ) {
            return false;
        }

        return $editable;
    }

    /**
     * Locks synced force sell Store API quantity limits to their current quantity.
     *
     * @param int|float  $quantity_limit Quantity limit.
     * @param WC_Product $product        Product object.
     * @param array|null $cart_item      Cart item data.
     *
     * @return int|float
     */
    public function jkmfs_store_api_product_quantity_limit( $quantity_limit, $product, $cart_item = null ) {
        if ( is_array( $cart_item ) && isset( $cart_item['forced_by'], $cart_item['quantity'] ) ) {
            return $cart_item['quantity'];
        }

        return $quantity_limit;
    }


    /**
     * When an item gets removed from the cart, do the same for forced sells.
     *
     * @param string $cart_item_key Cart item key.
     */
    public function jkmfs_cart_item_removed( $cart_item_key ) {
        $removed_item = isset( WC()->cart->removed_cart_contents[ $cart_item_key ] ) ? WC()->cart->removed_cart_contents[ $cart_item_key ] : array();

        if ( isset( $removed_item['forced_by'] ) && empty( $this->removing_forced_cart_item_keys[ $cart_item_key ] ) && isset( WC()->cart->cart_contents[ $removed_item['forced_by'] ] ) ) {
            WC()->cart->restore_cart_item( $cart_item_key );
            return;
        }

        foreach ( WC()->cart->get_cart() as $key => $value ) {
            if ( isset( $value['forced_by'] ) && $cart_item_key === $value['forced_by'] ) {
                $this->removing_forced_cart_item_keys[ $key ] = true;
                WC()->cart->remove_cart_item( $key );
                unset( $this->removing_forced_cart_item_keys[ $key ] );
            }
        }
    }

    /**
     * When an item gets removed from the cart, do the same for forced sells.
     *
     * @param string $cart_item_key Cart item key.
     */
    public function jkmfs_cart_item_restored( $cart_item_key ) {
        foreach ( WC()->cart->removed_cart_contents as $key => $value ) {
            if ( isset( $value['forced_by'] ) && $cart_item_key === $value['forced_by'] ) {
                WC()->cart->restore_cart_item( $key );
            }
        }
    }
}
endif;
