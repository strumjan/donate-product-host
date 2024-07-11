<?php
/*
 * Plugin Name: Donate Product Host
 * Description: A WooCommerce plugin to manage donation campaigns.
 * Version: 1.0
 * Author: Ilija Iliev Strumjan
 * Text Domain: donate-product-host
 * Domain Path: /languages
 * Requires Plugins: woocommerce
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Use JWT
require_once plugin_dir_path(__FILE__) . 'jwt/src/JWT.php';
require_once plugin_dir_path(__FILE__) . 'jwt/src/Key.php';
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

// Register activation hook
register_activation_hook(__FILE__, 'dph_activate');
function dph_activate() {
    dph_create_table();
}

// Register deactivation hook
register_deactivation_hook(__FILE__, 'dph_deactivate');
function dph_deactivate() {
    // Add deactivation logic if needed
}

// Load plugin text domain for translations
add_action('plugins_loaded', 'dph_load_textdomain');
function dph_load_textdomain() {
    load_plugin_textdomain('donate-product-host', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

// Create the custom table for storing client data
function dph_create_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dph_clients';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        campaign_name varchar(255) NOT NULL,
        client_domain varchar(255) NOT NULL,
        client_email varchar(255) NOT NULL,
        product_id bigint(20) NOT NULL,
        product_price decimal(10, 2) NOT NULL,
        required_quantity mediumint(9) NOT NULL,
        donated_quantity mediumint(9) DEFAULT 0,
        json_downloads mediumint(9) DEFAULT 0,
        total_amount decimal(10, 2) DEFAULT 0,
        start_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        client_key varchar(255) NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Add admin menu
add_action('admin_menu', 'dph_add_admin_menu');
function dph_add_admin_menu() {
    add_menu_page(
        __('Donate Product Host', 'donate-product-host'),
        __('Donate Product Host', 'donate-product-host'),
        'manage_options',
        'donate-product-host',
        'dph_admin_page',
        'dashicons-heart',
        20
    );
}

// Function to generate client key
function dph_generate_client_key($client_domain) {
    $reversed_domain = strrev($client_domain);
	$combinedDomain = $client_domain . $reversed_domain;
	$maskedDomain = md5($combinedDomain);
    return $maskedDomain;
}

// For admin notifications
if (!session_id()) {
    session_start();
}


// Admin page callback with tabs
function dph_admin_page() {
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'add_campaign';
    ?>
    <div class="wrap">
        <h1><?php _e('Donate Product Host', 'donate-product-host'); ?></h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=donate-product-host&tab=add_campaign" class="nav-tab <?php echo $tab == 'add_campaign' ? 'nav-tab-active' : ''; ?>"><?php _e('Add New Campaign', 'donate-product-host'); ?></a>
            <a href="?page=donate-product-host&tab=view_campaigns" class="nav-tab <?php echo $tab == 'view_campaigns' ? 'nav-tab-active' : ''; ?>"><?php _e('View Campaigns', 'donate-product-host'); ?></a>
            <a href="?page=donate-product-host&tab=settings" class="nav-tab <?php echo $tab == 'settings' ? 'nav-tab-active' : ''; ?>"><?php _e('Settings', 'donate-product-host'); ?></a>
        </h2>
        <div class="tab-content">
            <?php
            if ($tab == 'add_campaign') {
                dph_add_campaign_page();
            } elseif ($tab == 'view_campaigns') {
                dph_view_campaigns_page();
            } elseif ($tab == 'settings') {
                dph_settings_page();
            }
            ?>
        </div>
    </div>
    <?php
}

// Function for settings page
function dph_settings_page() {
    ?>
    <div class="wrap">
        <h2><?php _e('Settings', 'donate-product-host'); ?></h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('dph_settings_group');
            do_settings_sections('dph-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action('admin_init', 'dph_register_settings');
function dph_register_settings() {
    register_setting('dph_settings_group', 'dph_secret_key');
    register_setting('dph_settings_group', 'dph_host_email');
    register_setting('dph_settings_group', 'dph_at_self');
    register_setting('dph_settings_group', 'dph_product_id_at_self');

    add_settings_section(
        'dph_settings_section',
        __('Main Settings', 'donate-product-host'),
        'dph_settings_section_callback',
        'dph-settings'
    );

    add_settings_field(
        'dph_secret_key',
        __('Secret Key', 'donate-product-host'),
        'dph_secret_key_callback',
        'dph-settings',
        'dph_settings_section'
    );

    add_settings_field(
        'dph_host_email',
        __('Host email', 'donate-product-host'),
        'dph_host_email_callback',
        'dph-settings',
        'dph_settings_section'
    );

    add_settings_field(
        'dph_at_self',
        __('Donate at self', 'donate-product-host'),
        'dph_at_self_callback',
        'dph-settings',
        'dph_settings_section'
    );

    add_settings_field(
        'dph_product_id_at_self',
        __('Product ID to donate at self', 'donate-product-host'),
        'dph_product_id_at_self_callback',
        'dph-settings',
        'dph_settings_section'
    );
}

function dph_settings_section_callback() {
    echo __('Configure the main settings for the Donate Product Host plugin.', 'donate-product-host');
}

function dph_secret_key_callback() {
    $secret_key = get_option('dph_secret_key');
    echo '<input type="text" id="dph_secret_key" name="dph_secret_key" value="' . esc_attr($secret_key) . '" />';
}

function dph_host_email_callback() {
    $host_email = get_option('dph_host_email');
    echo '<input type="text" id="dph_host_email" name="dph_host_email" value="' . esc_attr($host_email) . '" />';
}

function dph_at_self_callback() {
    $at_self = get_option('dph_at_self');
    $checked = $at_self ? 'checked' : '';
    echo '<input type="checkbox" id="dph_at_self" name="dph_at_self" value="1" ' . $checked . ' />';
}

function dph_product_id_at_self_callback() {
    $product_id_at_self = get_option('dph_product_id_at_self');
    echo '<input type="text" id="dph_product_id_at_self" name="dph_product_id_at_self" value="' . esc_attr($product_id_at_self) . '" />';
}

function dph_admin_script() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            function toggleProductIDField() {
                if ($('#dph_at_self').is(':checked')) {
                    $('#dph_product_id_at_self').closest('tr').show();
                } else {
                    $('#dph_product_id_at_self').closest('tr').hide();
                }
            }
            toggleProductIDField();
            $('#dph_at_self').change(function() {
                toggleProductIDField();
            });
        });
    </script>
    <?php
}
add_action('admin_footer', 'dph_admin_script');

// Function to display add campaign form
function dph_add_campaign_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dph_clients';

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $campaign_name = sanitize_text_field($_POST['campaign_name']);
        $client_domain = sanitize_text_field($_POST['client_domain']);
        $client_email = sanitize_email($_POST['client_email']);
        $product_id = intval($_POST['product_id']);
        $product = wc_get_product($product_id);
        $secret_key = get_option('dph_secret_key');
        $payload_key = dph_generate_client_key($client_domain);
        $payload = array(
            'payload_key' => $payload_key,
            'client_domain' => $client_domain
        );
        
        if ($product) {
            $product_price = $product->get_price();
            $required_quantity = intval($_POST['required_quantity']);
            $client_key = dph_generate_jwt($payload, $secret_key);
            $client_email = get_option('dph_host_email');

            $wpdb->insert(
                $table_name,
                [
                    'campaign_name' => $campaign_name,
                    'client_domain' => $client_domain,
                    'client_email' => $client_email,
                    'product_id' => $product_id,
                    'product_price' => $product_price,
                    'required_quantity' => $required_quantity,
                    'client_key' => $client_key,
                ]
            );
			// Create JSON file for the client
            $json_data = [
                'campaign_name' => $campaign_name,
                'product_id' => $product_id,
                'product_price' => $product_price,
                'required_quantity' => $required_quantity,
                'client_email' => $client_email,
            ];

            $client_domain_filename = str_replace('.', '_', $client_domain);
            $client_key_short = substr($client_key, 0, 8);
            $json_filename = "{$client_domain_filename}_{$client_key_short}.json";

            // Define the path to the campaigns folder
            $campaigns_folder = plugin_dir_path(__FILE__) . 'campaigns';
            if (!file_exists($campaigns_folder)) {
                mkdir($campaigns_folder, 0755, true);
            }

            $json_file_path = $campaigns_folder . '/' . $json_filename;
            file_put_contents($json_file_path, json_encode($json_data));

            // Add success notice
            $_SESSION['dph_admin_notices'] = [
                'type' => 'success',
                'message' => __('Client and campaign added successfully!', 'donate-product-host')
            ];
        } else {
            // Add error notice
            $_SESSION['dph_admin_notices'] = [
                'type' => 'error',
                'message' => __('Invalid Product ID!', 'donate-product-host')
            ];
        }
        // Redirect to clear the form and display the notice
        wp_redirect(add_query_arg('tab', 'add_campaign', admin_url('admin.php?page=donate-product-host')));
        exit;
    }

    ?>
    <form method="POST">
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="campaign_name"><?php _e('Campaign Name', 'donate-product-host'); ?></label></th>
                <td><input type="text" id="campaign_name" name="campaign_name" required /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="client_domain"><?php _e('Client Domain', 'donate-product-host'); ?></label></th>
                <td><input type="text" id="client_domain" name="client_domain" required /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="client_email"><?php _e('Client Email', 'donate-product-host'); ?></label></th>
                <td><input type="email" id="client_email" name="client_email" required /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="product_id"><?php _e('Product ID', 'donate-product-host'); ?></label></th>
                <td><input type="number" id="product_id" name="product_id" required /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="required_quantity"><?php _e('Required Quantity', 'donate-product-host'); ?></label></th>
                <td><input type="number" id="required_quantity" name="required_quantity" required /></td>
            </tr>
        </table>
        <?php submit_button(__('Add Campaign', 'donate-product-host')); ?>
    </form>
    <?php
}

// Admin notifications
function dph_admin_notices() {
    if (isset($_SESSION['dph_admin_notices'])) {
        $notice = $_SESSION['dph_admin_notices'];
        ?>
        <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
            <p><?php echo esc_html($notice['message']); ?></p>
        </div>
        <?php
        // Clear the notice after displaying it
        unset($_SESSION['dph_admin_notices']);
    }

    // Check for settings-updated parameter
    if (isset($_GET['settings-updated'])) {
        if ($_GET['settings-updated'] == 'true') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Settings saved successfully!', 'donate-product-host'); ?></p>
            </div>
            <?php
        } else {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e('Settings not saved.', 'donate-product-host'); ?></p>
            </div>
            <?php
        }
    }
}
add_action('admin_notices', 'dph_admin_notices');

// Function to display list of campaigns
function dph_view_campaigns_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dph_clients';
    $campaigns = $wpdb->get_results("SELECT * FROM $table_name");

    if ($campaigns) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>' . __('Campaign Name', 'donate-product-host') . '</th>';
        echo '<th>' . __('Client Domain', 'donate-product-host') . '</th>';
        echo '<th>' . __('Client Email', 'donate-product-host') . '</th>';
        echo '<th>' . __('Product ID', 'donate-product-host') . '</th>';
        echo '<th>' . __('Product Price', 'donate-product-host') . '</th>';
        echo '<th>' . __('Required Quantity', 'donate-product-host') . '</th>';
        echo '<th>' . __('Donated Quantity', 'donate-product-host') . '</th>';
        echo '<th>' . __('Total Amount', 'donate-product-host') . '</th>';
        echo '<th>' . __('Start Date', 'donate-product-host') . '</th>';
		echo '<th>' . __('Client key', 'donate-product-host') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        foreach ($campaigns as $campaign) {
            echo '<tr>';
            echo '<td>' . esc_html($campaign->campaign_name) . '</td>';
            echo '<td>' . esc_html($campaign->client_domain) . '</td>';
            echo '<td>' . esc_html($campaign->client_email) . '</td>';
            echo '<td>' . esc_html($campaign->product_id) . '</td>';
            echo '<td>' . esc_html($campaign->product_price) . '</td>';
            echo '<td>' . esc_html($campaign->required_quantity) . '</td>';
            echo '<td>' . esc_html($campaign->donated_quantity) . '</td>';
            echo '<td>' . esc_html($campaign->total_amount) . '</td>';
            echo '<td>' . esc_html($campaign->start_date) . '</td>';
			echo '<td>' . esc_html($campaign->client_key) . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>' . __('No campaigns found.', 'donate-product-host') . '</p>';
    }
}

// Генерирање и проверка на JWT токенот

function dph_generate_jwt($payload, $secret_key) {
    return JWT::encode($payload, $secret_key, 'HS256');
}

function dph_verify_jwt($jwt, $secret_key) {
    try {
        $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
        return (array) $decoded;
    } catch (Exception $e) {
        //error_log('JWT verification failed: ' . $e->getMessage());
        return false;
    }
}

// REST API for update JSON
// Register the REST API route
add_action('rest_api_init', function () {
    register_rest_route('donate-product-host/v1', '/update_quantity', array(
        'methods' => 'POST',
        'callback' => 'dph_update_campaign_quantity',
        'permission_callback' => '__return_true'
    ));
});

// Callback function to update the quantity
function dph_update_campaign_quantity(WP_REST_Request $request) {
    //error_log("dph_update_campaign_quantity executed.");
    global $wpdb;
    $params = $request->get_json_params();
    $secret_key = get_option('dph_secret_key');
    $jwt_token = $request->get_header('Authorization');
    $jwt_token = str_replace('Bearer ', '', $jwt_token);

    if (!dph_verify_jwt($jwt_token, $secret_key)) {
        //error_log("Invalid JWT token.");
        return new WP_Error('invalid_token', 'Invalid JWT token', array('status' => 401));
    }

    $client_domain = sanitize_text_field($params['client_domain']);
    $client_domain_base = str_replace('_', '.', $client_domain);
    $table_name = $wpdb->prefix . 'dph_clients';
    $query = $wpdb->prepare("SELECT client_key FROM $table_name WHERE client_domain = %s", $client_domain_base);
    $client_key = $wpdb->get_var($query);//error_log('Ova e od bazata: '.$client_key);
    //$client_key = sanitize_text_field($params['client_key']);
    $donated_quantity = intval($params['donated_quantity']);
    $client_key_short = substr($client_key, 0, 8);

    $file_path = plugin_dir_path(__FILE__) . "campaigns/{$client_domain}_{$client_key_short}.json";

    if (!file_exists($file_path)) {
        //error_log("Campaign file not found: " . $file_path);
        return new WP_Error('file_not_found', 'Campaign file not found', array('status' => 404));
    }

    $campaign_data = json_decode(file_get_contents($file_path), true);

    if ($campaign_data === null) {
        //error_log("Invalid JSON file: " . $file_path);
        return new WP_Error('invalid_json', 'Invalid JSON file', array('status' => 500));
    }

    $campaign_data['required_quantity'] = max(0, intval($campaign_data['required_quantity']) - $donated_quantity);
    file_put_contents($file_path, json_encode($campaign_data));

    //error_log("Quantity updated successfully for " . $client_domain . "_" . $client_key);

    return rest_ensure_response(array('success' => true, 'new_required_quantity' => $campaign_data['required_quantity']));
}

// Add a checkbox to the checkout page
add_action('woocommerce_review_order_before_order_total', 'dph_add_donation_checkbox');

function dph_add_donation_checkbox() {
    if (get_option('dph_at_self')) {
        $product_id = get_option('dph_product_id_at_self');
        $product = wc_get_product($product_id);
        $product_name = $product ->get_name();
        $product_price = $product->get_price();
        $max_quantity = $product->get_stock_status();
        if ($max_quantity == 'instock') {
            echo '<tr class="donation_product">
                    <th>' . esc_html($product_name) . '</th>
                    <td>
                        <input type="checkbox" id="add_donation_product" name="add_donation_product" onchange="checkboxAction();" value="' . esc_attr($product_id) . '" data-price="' . esc_attr($product_price) . '">
                        <input type="number" id="donation_product_quantity" name="donation_product_quantity" onchange="updateTotal();" min="1" max="' . esc_attr($max_quantity) . '" value="1" style="width: 60px; margin-left: 10px;" disabled>
                        ' . wc_price($product_price) . '
                    </td>
                  </tr>';
        }
    }
}

// Enqueue the script for adding donation product price in total
add_action('wp_enqueue_scripts', 'dph_enqueue_scripts');

function dph_enqueue_scripts() {
    if (is_checkout()) {
        wp_enqueue_script('dph_donation_product', plugins_url('/dph-donation-product.js', __FILE__), array('jquery'), null, true);
        wp_localize_script('dph_donation_product', 'wc_price_params', array(
            'currency_format_num_decimals' => get_option('woocommerce_price_num_decimals'),
            'currency_format_symbol'       => get_woocommerce_currency_symbol()
        ));
    }
}

// At self add donate product in order
add_action('woocommerce_checkout_create_order', 'dph_add_donation_product_to_order', 20, 1);

function dph_add_donation_product_to_order($order) {
    //if (get_option('dph_at_self') && isset($_POST['donation_product_checkbox'])) {
    if (isset($_POST['add_donation_product']) && $_POST['add_donation_product']) {
        $product_id = get_option('dph_product_id_at_self');
        $product = wc_get_product($product_id);
        $product_quantity = isset($_POST['donation_product_quantity']) ? intval($_POST['donation_product_quantity']) : 1;

        if ($product) {
            $product_name = $product ->get_name();;
            $product_price = floatval($product->get_price());

            // Create a new order item for the donation product
            $item = new WC_Order_Item_Product();
            $item->set_product_id($product_id);
            $item->set_name($product_name);
            $item->set_quantity($product_quantity);
            $item->set_total($product_price * $product_quantity);
            $item->add_meta_data('_donation_product', 'yes', true);
            $item->add_meta_data('_donation_product_quantity', sanitize_text_field($_POST['donation_product_quantity']), true);

            // Add the item to the order
            $order->add_item($item);
        }
    }
}

// At self add donation product to order as a fee
add_action('woocommerce_cart_calculate_fees', 'dph_add_donation_product_to_cart');

function dph_add_donation_product_to_cart() {
    //if (get_option('dph_at_self') && isset($_POST['donation_product_checkbox'])) {
    if (isset($_POST['add_donation_product']) && !empty($_POST['add_donation_product'])) {
        $product_id = get_option('dph_product_id_at_self');
        $quantity = isset($_POST['donation_product_quantity']) ? intval($_POST['donation_product_quantity']) : 1;
        $product = wc_get_product($product_id);

        if ($product) {
            $product_price = $product->get_price();
            WC()->cart->add_fee($product->get_name(), $product_price * $quantity);
        }
    }
}


// At self hook into WooCommerce order placement
//add_action('woocommerce_thankyou', 'dph_handle_order_placement');

function dph_handle_order_placement($order_id) {
    if (get_option('dph_at_self')) {

    }
}
