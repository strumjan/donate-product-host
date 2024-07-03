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
function dph_generate_client_key($domain) {
    $reversed_domain = strrev($domain);
	$combinedDomain = $domain . $reversed_domain;
	$maskedDomain = md5($combinedDomain);
    return $maskedDomain;
	// za ime na fajlot user_data_{$maskedDomain}.json
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
        </h2>
        <div class="tab-content">
            <?php
            if ($tab == 'add_campaign') {
                dph_add_campaign_page();
            } else {
                dph_view_campaigns_page();
            }
            ?>
        </div>
    </div>
    <?php
}

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
        
        if ($product) {
            $product_price = $product->get_price();
            $required_quantity = intval($_POST['required_quantity']);
            $client_key = dph_generate_client_key($client_domain);

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
            ];

            $client_domain_filename = str_replace('.', '_', $client_domain);
            $json_filename = "{$client_domain_filename}_{$client_key}.json";

            // Define the path to the campaigns folder
            $campaigns_folder = plugin_dir_path(__FILE__) . 'campaigns';
            if (!file_exists($campaigns_folder)) {
                mkdir($campaigns_folder, 0755, true);
            }

            $json_file_path = $campaigns_folder . '/' . $json_filename;
            file_put_contents($json_file_path, json_encode($json_data));

            echo '<div class="notice notice-success is-dismissible"><p>' . __('Client and campaign added successfully!', 'donate-product-host') . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Invalid Product ID!', 'donate-product-host') . '</p></div>';
        }
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
// Additional functions for managing the plugin logic will be added here...

