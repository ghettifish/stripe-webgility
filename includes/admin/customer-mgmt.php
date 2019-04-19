<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function associated_credit_cards($order) {

    $order_id = $order->get_id();
    $token_id = get_post_meta($order_id, "_linked_token_id");
    $user = $order->get_user();
    $user_id = $order->get_user_id();
    $customer_token = get_user_meta($user_id, '_stripe_customer_id', true);

    $wtf = WC_Payment_Tokens::get($token_id[0]);
    $token = $wtf->get_token();
    $data = $wtf->get_last4();
    $html = sprintf(
        '
        <div>
            <h3>Associated Credit Card</h3>
            <p>Last four: %s</p>
            <input type="hidden" id="stripe_token" name="selected_payment_token" value="%s">
            <input type="hidden" id="customer_token" name="customer_token" value="%s">
        </div>
        ',
        $data,
        $token,
        $customer_token
    );
    echo $html;
    $order_status = $order->get_status();
        if($order_status != 'completed' && $order_status != 'pending'){
            add_action("woocommerce_order_item_add_action_buttons", "charge", 10, 3 );
        }
    }
    
    function charge() {
        echo '<button type="button" class="button charge-items" id="chargeButton">Charge</button>';
    }
   
    add_action( 'woocommerce_admin_order_data_after_billing_address', 'associated_credit_cards', 10, 1);
    add_action( 'wp_ajax_charge_customer', 'charge_customer' );
    add_action( 'admin_footer', 'my_action_javascript' ); // Write our JS below here

    function charge_customer() {
        global $wpdb; // this is how you get access to the database
        $order_id = intval( $_POST['whatever'] );
        $stripe_gateway = new WB_Gateway_Stripe;
        $response = $stripe_gateway->bill_customer($order_id);
        
        //$response = WB_Stripe_API::request( $this->generate_payment_request( $order, $prepared_source ) );
        echo $response;
        wp_die(); // this is required to terminate immediately and return a proper response
    }


    function my_action_javascript() { ?>
        <script type="text/javascript" >
        jQuery(document).ready(function($) {
            var data = {
                'action': 'charge_customer',
                'whatever': jQuery("#post_ID").val(),
                'stripe_token': jQuery("#stripe_token").val(),
                'customer': jQuery("#customer_token").val()
            };
            jQuery("#chargeButton").click(function($) {
                // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
                jQuery.post(ajaxurl, data, function(response) {
                    alert('Got this from the server: ' + response);
                    location.reload();
                });
            })
            
        });
        </script> <?php
    }