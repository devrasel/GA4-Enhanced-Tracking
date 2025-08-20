<?php
/**
 * Plugin Name: GA4 Enhanced Tracking
 * Plugin URI: https://webextended.com/plugins
 * Description: Complete Google Analytics 4 Enhanced Ecommerce Tracking for WooCommerce with admin controls
 * Version: 1.0.01
 * Author: Rasel Ahmed
 * License: GPL v2 or later
 * Requires at least: 5.0
 * Tested up to: 6.3
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Declare HPOS compatibility
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

class GA4_Enhanced_Ecommerce {
    
    private $plugin_name = 'ga4-enhanced-ecommerce';
    private $version = '1.0.01';
    private $option_name = 'ga4_ecommerce_settings';
    private $is_woocommerce_active = false;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Check if WooCommerce is active
        $this->is_woocommerce_active = $this->is_woocommerce_active();
    }
    
    public function init() {
        // Add GA4 tracking code to head/footer/after body
        $this->add_ga4_tracking_code();
        
        // Initialize ecommerce tracking only if WooCommerce is active
        if ($this->is_woocommerce_active) {
            $this->init_ecommerce_tracking();
        }
    }
    
    private function is_woocommerce_active() {
        return in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) 
               || (is_multisite() && array_key_exists('woocommerce/woocommerce.php', get_site_option('active_sitewide_plugins', array())));
    }
    
    public function activate() {
        // Set default options
        $default_options = array(
            'ga4_code' => '',
            'code_placement' => 'header',
            'code_priority' => 1,
            'view_item_enabled' => true,
            'add_to_cart_enabled' => true,
            'begin_checkout_enabled' => true,
            'purchase_enabled' => true
        );
        
        if (!get_option($this->option_name)) {
            add_option($this->option_name, $default_options);
        }
    }
    
    public function deactivate() {
        // Clean up if needed
    }
    
    public function add_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=' . $this->plugin_name . '">' . __('Settings') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    public function add_admin_menu() {
        add_options_page(
            'GA4 Enhanced Tracking Settings',
            'GA4 Enhanced Tracking',
            'manage_options',
            $this->plugin_name,
            array($this, 'options_page')
        );
    }
    
    public function settings_init() {
        register_setting($this->plugin_name, $this->option_name);
        
        // GA4 Code Section
        add_settings_section(
            $this->plugin_name . '_ga4_code',
            'GA4 Tracking Code',
            array($this, 'ga4_code_section_callback'),
            $this->plugin_name
        );
        
        add_settings_field(
            'ga4_code',
            'GA4 Tracking Code',
            array($this, 'ga4_code_render'),
            $this->plugin_name,
            $this->plugin_name . '_ga4_code'
        );
        
        add_settings_field(
            'code_placement',
            'Code Placement',
            array($this, 'code_placement_render'),
            $this->plugin_name,
            $this->plugin_name . '_ga4_code'
        );
        
        add_settings_field(
            'code_priority',
            'Code Priority',
            array($this, 'code_priority_render'),
            $this->plugin_name,
            $this->plugin_name . '_ga4_code'
        );
        
        // Events Control Section (only show if WooCommerce is active)
        if ($this->is_woocommerce_active) {
            add_settings_section(
                $this->plugin_name . '_events',
                'WooCommerce Event Tracking Controls',
                array($this, 'events_section_callback'),
                $this->plugin_name
            );
            
            add_settings_field(
                'view_item_enabled',
                'View Item Event',
                array($this, 'view_item_enabled_render'),
                $this->plugin_name,
                $this->plugin_name . '_events'
            );
            
            add_settings_field(
                'add_to_cart_enabled',
                'Add to Cart Event',
                array($this, 'add_to_cart_enabled_render'),
                $this->plugin_name,
                $this->plugin_name . '_events'
            );
            
            add_settings_field(
                'begin_checkout_enabled',
                'Begin Checkout Event',
                array($this, 'begin_checkout_enabled_render'),
                $this->plugin_name,
                $this->plugin_name . '_events'
            );
            
            add_settings_field(
                'purchase_enabled',
                'Purchase Event',
                array($this, 'purchase_enabled_render'),
                $this->plugin_name,
                $this->plugin_name . '_events'
            );
        }
    }
    
    public function ga4_code_section_callback() {
        echo '<p>Enter your GA4 tracking code and choose where to place it. This works independently of WooCommerce.</p>';
    }
    
    public function events_section_callback() {
        if ($this->is_woocommerce_active) {
            echo '<p>Enable or disable specific WooCommerce ecommerce events. These options are only available when WooCommerce is active.</p>';
        } else {
            echo '<p><strong>Note:</strong> WooCommerce is not active. Ecommerce event tracking is disabled. Only basic GA4 tracking code will be loaded.</p>';
        }
    }
    
    public function ga4_code_render() {
        $options = get_option($this->option_name);
        ?>
        <textarea name="<?php echo $this->option_name; ?>[ga4_code]" rows="10" cols="70" placeholder="<!-- Google tag (gtag.js) -->
<script async src='https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX'></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-XXXXXXXXXX');
</script>"><?php echo isset($options['ga4_code']) ? esc_textarea($options['ga4_code']) : ''; ?></textarea>
        <p class="description">Paste your complete GA4 tracking code here, including script tags. This will work with or without WooCommerce.</p>
        <?php
    }
    
    public function code_placement_render() {
        $options = get_option($this->option_name);
        $placement = isset($options['code_placement']) ? $options['code_placement'] : 'header';
        ?>
        <select name="<?php echo $this->option_name; ?>[code_placement]">
            <option value="header" <?php selected($placement, 'header'); ?>>Header (wp_head) - Recommended</option>
            <option value="footer" <?php selected($placement, 'footer'); ?>>Footer (wp_footer)</option>
            <option value="after_body" <?php selected($placement, 'after_body'); ?>>After Body Tag (wp_body_open)</option>
        </select>
        <p class="description">Choose where to place your GA4 tracking code.</p>
        <?php
    }
    
    public function code_priority_render() {
        $options = get_option($this->option_name);
        $priority = isset($options['code_priority']) ? $options['code_priority'] : 1;
        ?>
        <select name="<?php echo $this->option_name; ?>[code_priority]">
            <option value="1" <?php selected($priority, 1); ?>>Very High Priority (1) - Loads First</option>
            <option value="5" <?php selected($priority, 5); ?>>High Priority (5)</option>
            <option value="10" <?php selected($priority, 10); ?>>Normal Priority (10) - Default</option>
            <option value="20" <?php selected($priority, 20); ?>>Low Priority (20)</option>
        </select>
        <p class="description">Set the loading priority. Lower numbers load first. Use "Very High Priority" to ensure GA4 loads before other scripts.</p>
        <?php
    }
    
    public function view_item_enabled_render() {
        if (!$this->is_woocommerce_active) return;
        
        $options = get_option($this->option_name);
        $enabled = isset($options['view_item_enabled']) ? $options['view_item_enabled'] : true;
        ?>
        <input type="checkbox" name="<?php echo $this->option_name; ?>[view_item_enabled]" value="1" <?php checked($enabled, 1); ?> />
        <label>Enable view_item event tracking on product pages</label>
        <?php
    }
    
    public function add_to_cart_enabled_render() {
        if (!$this->is_woocommerce_active) return;
        
        $options = get_option($this->option_name);
        $enabled = isset($options['add_to_cart_enabled']) ? $options['add_to_cart_enabled'] : true;
        ?>
        <input type="checkbox" name="<?php echo $this->option_name; ?>[add_to_cart_enabled]" value="1" <?php checked($enabled, 1); ?> />
        <label>Enable add_to_cart event tracking</label>
        <?php
    }
    
    public function begin_checkout_enabled_render() {
        if (!$this->is_woocommerce_active) return;
        
        $options = get_option($this->option_name);
        $enabled = isset($options['begin_checkout_enabled']) ? $options['begin_checkout_enabled'] : true;
        ?>
        <input type="checkbox" name="<?php echo $this->option_name; ?>[begin_checkout_enabled]" value="1" <?php checked($enabled, 1); ?> />
        <label>Enable begin_checkout event tracking on checkout page</label>
        <?php
    }
    
    public function purchase_enabled_render() {
        if (!$this->is_woocommerce_active) return;
        
        $options = get_option($this->option_name);
        $enabled = isset($options['purchase_enabled']) ? $options['purchase_enabled'] : true;
        ?>
        <input type="checkbox" name="<?php echo $this->option_name; ?>[purchase_enabled]" value="1" <?php checked($enabled, 1); ?> />
        <label>Enable purchase event tracking on thank you page</label>
        <?php
    }
    
    public function options_page() {
        ?>
        <div class="wrap">
            <h1>GA4 Enhanced Tracking Settings</h1>
            
            <?php if (!$this->is_woocommerce_active): ?>
            <div class="notice notice-info">
                <p><strong>WooCommerce Status:</strong> WooCommerce is not active. Only basic GA4 tracking code will be loaded. Ecommerce event tracking requires WooCommerce to be installed and activated.</p>
            </div>
            <?php else: ?>
            <div class="notice notice-success">
                <p><strong>WooCommerce Status:</strong> WooCommerce is active. All ecommerce tracking features are available.</p>
            </div>
            <?php endif; ?>
            
            <form action="options.php" method="post">
                <?php
                settings_fields($this->plugin_name);
                do_settings_sections($this->plugin_name);
                submit_button('Save Settings');
                ?>
            </form>
            
            <div class="card" style="margin-top: 20px;">
                <h2>Plugin Information</h2>
                <p><strong>Version:</strong> <?php echo $this->version; ?></p>
                <p><strong>Author:</strong> Rasel Ahmed</p>
                <p><strong>Plugin URI:</strong> <a href="https://webextended.com/plugins" target="_blank">https://webextended.com/plugins</a></p>
                <p><strong>Description:</strong> This plugin provides GA4 tracking code management and complete Enhanced Ecommerce tracking for WooCommerce when available.</p>
                
                <h3>Features:</h3>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li><strong>Basic GA4 Tracking:</strong> Works with or without WooCommerce</li>
                    <li><strong>Flexible Code Placement:</strong> Header, Footer, or After Body Tag</li>
                    <?php if ($this->is_woocommerce_active): ?>
                    <li><strong>WooCommerce Integration:</strong> Complete ecommerce event tracking</li>
                    <li><strong>HPOS Compatible:</strong> Supports WooCommerce High-Performance Order Storage</li>
                    <?php endif; ?>
                </ul>
                
                <?php if ($this->is_woocommerce_active): ?>
                <h3>WooCommerce Events Tracked:</h3>
                <ul style="list-style-type: disc; margin-left: 20px;">
                    <li><strong>view_item:</strong> When users view a product page</li>
                    <li><strong>add_to_cart:</strong> When users add products to cart (both AJAX and regular)</li>
                    <li><strong>begin_checkout:</strong> When users start the checkout process</li>
                    <li><strong>purchase:</strong> When order is completed on thank you page</li>
                </ul>
                <?php endif; ?>
                
                <h3>Multi-Currency Support:</h3>
                <p>Compatible with popular multi-currency plugins including WPML, WOOCS, and WooCommerce Multi-Currency.</p>
                
                <?php if (!$this->is_woocommerce_active): ?>
                <h3>To Enable WooCommerce Features:</h3>
                <ol>
                    <li>Install and activate WooCommerce plugin</li>
                    <li>Refresh this page to see ecommerce tracking options</li>
                </ol>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    private function get_options() {
        return get_option($this->option_name, array(
            'ga4_code' => '',
            'code_placement' => 'header',
            'code_priority' => 1,
            'view_item_enabled' => true,
            'add_to_cart_enabled' => true,
            'begin_checkout_enabled' => true,
            'purchase_enabled' => true
        ));
    }
    
    private function add_ga4_tracking_code() {
        $options = $this->get_options();
        
        if (empty($options['ga4_code'])) {
            return;
        }
        
        $priority = isset($options['code_priority']) ? intval($options['code_priority']) : 1;
        
        switch ($options['code_placement']) {
            case 'header':
                add_action('wp_head', array($this, 'output_ga4_code'), $priority);
                break;
            case 'footer':
                add_action('wp_footer', array($this, 'output_ga4_code'), $priority);
                break;
            case 'after_body':
                add_action('wp_body_open', array($this, 'output_ga4_code'), $priority);
                break;
        }
    }
    
    public function output_ga4_code() {
        $options = $this->get_options();
        if (!empty($options['ga4_code'])) {
            echo $options['ga4_code'] . "\n";
        }
    }
    
    private function init_ecommerce_tracking() {
        if (!$this->is_woocommerce_active) {
            return;
        }
        
        $options = $this->get_options();
        
        // View Item Tracking
        if ($options['view_item_enabled']) {
            add_action('wp_footer', array($this, 'add_ga4_view_item_tracking'));
        }
        
        // Add to Cart Tracking
        if ($options['add_to_cart_enabled']) {
            add_action('wp_footer', array($this, 'add_ga4_add_to_cart_script'));
            add_action('wp_ajax_get_product_data_for_ga4', array($this, 'handle_ga4_product_data_request'));
            add_action('wp_ajax_nopriv_get_product_data_for_ga4', array($this, 'handle_ga4_product_data_request'));
        }
        
        // Begin Checkout Tracking
        if ($options['begin_checkout_enabled']) {
            add_action('wp_footer', array($this, 'add_ga4_begin_checkout_tracking'));
        }
        
        // Purchase Tracking
        if ($options['purchase_enabled']) {
            add_action('woocommerce_thankyou', array($this, 'add_ga4_purchase_tracking'), 10, 1);
        }
    }
    
    // =============================================================================
    // 1. VIEW ITEM - When users view a product page
    // =============================================================================
    public function add_ga4_view_item_tracking() {
        if (!is_product() || !$this->is_woocommerce_active) {
            return;
        }
        
        global $product;
        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }
        
        // Get dynamic currency
        $currency = get_woocommerce_currency();
        
        // Get product data
        $item_id = $product->get_sku() ? $product->get_sku() : $product->get_id();
        $item_name = $product->get_name();
        $price = floatval($product->get_price());
        
        // Get product categories
        $categories = wp_get_post_terms($product->get_id(), 'product_cat');
        $category_name = !empty($categories) ? $categories[0]->name : '';
        
        ?>
        <script>
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'view_item',
            ecommerce: {
                currency: '<?php echo esc_js($currency); ?>',
                value: <?php echo $price; ?>,
                items: [{
                    item_id: '<?php echo esc_js($item_id); ?>',
                    item_name: '<?php echo esc_js($item_name); ?>',
                    <?php if ($category_name) : ?>
                    category: '<?php echo esc_js($category_name); ?>',
                    <?php endif; ?>
                    price: <?php echo $price; ?>,
                    quantity: 1
                }]
            }
        });
        </script>
        <?php
    }
    
    // =============================================================================
    // 2. ADD TO CART - When users add products to cart (AJAX & regular)
    // =============================================================================
    public function add_ga4_add_to_cart_script() {
        if (!$this->is_woocommerce_active) {
            return;
        }
        
        $currency = get_woocommerce_currency();
        ?>
        <script>
        // Handle add to cart events
        jQuery(document).ready(function($) {
            
      // For single product pages - handle both AJAX and regular form submissions 
        function sendGA4AddToCart(productId, quantity, source) {
            console.log('Sending GA4 tracking', {productId: productId, quantity: quantity, source: source});
            
            $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                action: 'get_product_data_for_ga4',
                product_id: productId,
                quantity: quantity,
                security: '<?php echo wp_create_nonce('ga4_product_data'); ?>'
            }, function(response) {
                console.log('AJAX response from ' + source, response);
                if (response.success) {
                    window.dataLayer = window.dataLayer || [];
                    window.dataLayer.push({
                        event: 'add_to_cart',
                        ecommerce: {
                            currency: response.data.currency,
                            value: response.data.value,
                            items: [response.data.item]
                        }
                    });
                    console.log('GA4 add_to_cart event pushed to dataLayer from ' + source);
                } else {
                    console.error('GA4 AJAX failed from ' + source, response);
                }
            }).fail(function(xhr, status, error) {
                console.error('GA4 AJAX error from ' + source, error, xhr.responseText);
            });
        }
        
        // 1. Single Product Page - Handle AJAX Add to Cart Button Click (NOT form submit)
        $('.single_add_to_cart_button').on('click', function(e) {
            var button = $(this);
            var form = button.closest('form.cart');
            
            // Skip if it's a variable product without selected variations
            if (button.hasClass('disabled') || button.hasClass('wc-variation-selection-needed')) {
                console.log('Add to cart button disabled or needs variation selection');
                return;
            }
            
            var productId = form.find('[name="add-to-cart"]').val() || 
                           form.find('[name="product_id"]').val() || 
                           button.val() ||
                           $('[name="add-to-cart"]').val();
                           
            var quantity = form.find('[name="quantity"]').val() || 1;
            
            console.log('Single product add to cart clicked', {
                productId: productId, 
                quantity: quantity,
                buttonClass: button.attr('class'),
                formClass: form.attr('class')
            });
            
            if (productId && !button.hasClass('ga4-processing')) {
                button.addClass('ga4-processing');
                sendGA4AddToCart(productId, quantity, 'single-product-ajax-click');
                
                setTimeout(function() {
                    button.removeClass('ga4-processing');
                }, 1000);
            }
        });
            
            
            // For shop/category pages (quick add to cart buttons)
            $(document).on('click', '.add_to_cart_button', function(e) {
                var button = $(this);
                var productId = button.data('product_id');
                var quantity = button.data('quantity') || 1;
                
                console.log('Quick add to cart clicked:', productId, quantity);
                
                if (productId && !button.hasClass('ga4-processing')) {
                    button.addClass('ga4-processing');
                    
                    $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                        action: 'get_product_data_for_ga4',
                        product_id: productId,
                        quantity: quantity,
                        security: '<?php echo wp_create_nonce('ga4_product_data'); ?>'
                    }, function(response) {
                        console.log('Quick add AJAX response:', response);
                        if (response.success) {
                            window.dataLayer = window.dataLayer || [];
                            window.dataLayer.push({
                                event: 'add_to_cart',
                                ecommerce: {
                                    currency: response.data.currency,
                                    value: response.data.value,
                                    items: [response.data.item]
                                }
                            });
                            console.log('GA4 quick add_to_cart event pushed to dataLayer');
                        } else {
                            console.error('Quick add AJAX failed:', response);
                        }
                    }).fail(function(xhr, status, error) {
                        console.error('Quick add AJAX error:', error, xhr.responseText);
                    }).always(function() {
                        setTimeout(function() {
                            button.removeClass('ga4-processing');
                        }, 1000);
                    });
                }
            });
            
        });
        </script>
        <?php
    }
    
    // AJAX handler for getting product data
    public function handle_ga4_product_data_request() {
        if (!$this->is_woocommerce_active) {
            wp_send_json_error('WooCommerce not active');
            return;
        }
        
        // Debug logging
        error_log('GA4 AJAX handler called with data: ' . print_r($_POST, true));
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['security'], 'ga4_product_data')) {
            error_log('GA4 AJAX: Nonce verification failed');
            wp_send_json_error('Security check failed');
        }
        
        $product_id = intval($_POST['product_id']);
        $quantity = intval($_POST['quantity']) ?: 1;
        
        if (!$product_id) {
            error_log('GA4 AJAX: Invalid product ID');
            wp_send_json_error('Invalid product ID');
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            error_log('GA4 AJAX: Product not found for ID: ' . $product_id);
            wp_send_json_error('Product not found');
        }
        
        // Get dynamic currency
        $currency = get_woocommerce_currency();
        
        // Get product data
        $item_id = $product->get_sku() ? $product->get_sku() : (string) $product->get_id();
        $item_name = $product->get_name();
        $price = floatval($product->get_price());
        $total_value = $price * $quantity;
        
        // Get product categories
        $categories = wp_get_post_terms($product->get_id(), 'product_cat');
        $category_name = !empty($categories) && !is_wp_error($categories) ? $categories[0]->name : '';
        
        $item_data = array(
            'item_id' => $item_id,
            'item_name' => $item_name,
            'price' => $price,
            'quantity' => $quantity
        );
        
        if ($category_name) {
            $item_data['category'] = $category_name;
        }
        
        // Add variation data if it's a variable product
        if ($product->is_type('variation')) {
            $variation_attributes = $product->get_variation_attributes();
            if (!empty($variation_attributes)) {
                $item_data['item_variant'] = implode(', ', array_values($variation_attributes));
            }
        }
        
        $response_data = array(
            'currency' => $currency,
            'value' => $total_value,
            'item' => $item_data
        );
        
        error_log('GA4 AJAX: Sending response: ' . print_r($response_data, true));
        wp_send_json_success($response_data);
    }
    
    // =============================================================================
    // 3. BEGIN CHECKOUT - When users start the checkout process
    // =============================================================================
    public function add_ga4_begin_checkout_tracking() {
        if (!is_checkout() || is_order_received_page() || !$this->is_woocommerce_active) {
            return;
        }
        
        // Get cart contents
        $cart = WC()->cart;
        if ($cart->is_empty()) {
            return;
        }
        
        // Get dynamic currency
        $currency = get_woocommerce_currency();
        
        // Calculate cart value (subtotal excluding tax and shipping)
        $cart_total = floatval($cart->get_subtotal());
        
        // Build items array
        $items = array();
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            if (!$product) continue;
            
            $item_id = $product->get_sku() ? $product->get_sku() : $product->get_id();
            $item_name = $product->get_name();
            $price = floatval($product->get_price());
            $quantity = intval($cart_item['quantity']);
            
            // Get product categories
            $categories = wp_get_post_terms($product->get_id(), 'product_cat');
            $category_name = !empty($categories) ? $categories[0]->name : '';
            
            $item_data = array(
                'item_id' => $item_id,
                'item_name' => $item_name,
                'price' => $price,
                'quantity' => $quantity
            );
            
            if ($category_name) {
                $item_data['category'] = $category_name;
            }
            
            // Add variation info if applicable
            if (!empty($cart_item['variation'])) {
                $variation_names = array();
                foreach ($cart_item['variation'] as $key => $value) {
                    $variation_names[] = $value;
                }
                if (!empty($variation_names)) {
                    $item_data['item_variant'] = implode(', ', $variation_names);
                }
            }
            
            $items[] = $item_data;
        }
        
        ?>
        <script>
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'begin_checkout',
            ecommerce: {
                currency: '<?php echo esc_js($currency); ?>',
                value: <?php echo $cart_total; ?>,
                items: <?php echo json_encode($items, JSON_NUMERIC_CHECK); ?>
            }
        });
        </script>
        <?php
    }
    
    // =============================================================================
    // 4. PURCHASE - When order is completed (Thank You page)
    // =============================================================================
    public function add_ga4_purchase_tracking($order_id) {
        if (!$this->is_woocommerce_active) {
            return;
        }
        
        // Only run once per order and if order exists
        if (!$order_id || get_post_meta($order_id, '_ga4_tracked', true) === 'yes') {
            return;
        }
        
        // Get the order object - HPOS compatible
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Mark as tracked to prevent duplicate events - HPOS compatible
        $order->update_meta_data('_ga4_tracked', 'yes');
        $order->save();
        
        // Get order data with dynamic currency
        $transaction_id = $order->get_order_number();
        $total = floatval($order->get_total());
        $tax = floatval($order->get_total_tax());
        $shipping = floatval($order->get_shipping_total());
        $currency = $order->get_currency(); // Dynamic currency from order
        $subtotal = floatval($order->get_subtotal());
        
        // Calculate value (subtotal excluding tax and shipping)
        $value = $subtotal > 0 ? $subtotal : ($total - $tax - $shipping);
        
        // Build items array
        $items = array();
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if (!$product) continue;
            
            $item_data = array(
                'item_id'   => $product->get_sku() ? $product->get_sku() : $product->get_id(),
                'item_name' => $item->get_name(),
                'price'     => floatval($order->get_item_total($item, false)), // Price excluding tax
                'quantity'  => intval($item->get_quantity())
            );
            
            // Get product categories
            $categories = wp_get_post_terms($product->get_id(), 'product_cat');
            if (!empty($categories)) {
                $item_data['category'] = $categories[0]->name;
            }
            
            // Add variation info if it's a variable product
            if ($product->is_type('variation')) {
                $variation_attributes = $product->get_variation_attributes();
                if (!empty($variation_attributes)) {
                    $item_data['item_variant'] = implode(', ', $variation_attributes);
                }
            }
            
            $items[] = $item_data;
        }
        
        ?>
        <script>
        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'purchase',
            ecommerce: {
                transaction_id: '<?php echo esc_js($transaction_id); ?>',
                value: <?php echo $value; ?>,
                tax: <?php echo $tax; ?>,
                shipping: <?php echo $shipping; ?>,
                currency: '<?php echo esc_js($currency); ?>',
                items: <?php echo json_encode($items, JSON_NUMERIC_CHECK); ?>
            }
        });
        </script>
        <?php
    }
}

// Initialize the plugin
new GA4_Enhanced_Ecommerce();

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================

/**
 * Get current currency for multi-currency setups
 * Supports popular multi-currency plugins
 */
function get_current_wc_currency() {
    // Return default if WooCommerce is not active
    if (!function_exists('get_woocommerce_currency')) {
        return 'USD';
    }
    
    $currency = get_woocommerce_currency();
    
    // WooCommerce Multi-Currency support
    if (class_exists('WOOMC\App')) {
        $currency = get_woocommerce_currency();
    }
    
    // WPML Multi-Currency support  
    if (class_exists('WCML_Multi_Currency')) {
        global $woocommerce_wpml;
        if ($woocommerce_wpml && $woocommerce_wpml->multi_currency) {
            $currency = $woocommerce_wpml->multi_currency->get_client_currency();
        }
    }
    
    // Currency Switcher for WooCommerce support
    if (class_exists('WOOCS')) {
        global $WOOCS;
        if ($WOOCS) {
            $currency = $WOOCS->current_currency;
        }
    }
    
    return $currency;
}
