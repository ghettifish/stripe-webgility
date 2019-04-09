<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function associated_credit_cards($order) {

    $order_id = $order->get_id();
    $token_id = get_post_meta($order_id, "_linked_token_id");
    
    $wtf = WC_Payment_Tokens::get($token_id[0]);
    $data = $wtf->get_last4();
    $html = sprintf(
        '
        <div>
            <h3>Associated Credit Card</h3>
            <p>Last four: %1$s</p>
        </div>
        ',
        $data
    );
    echo $html;
    }
    
    function charge() {
        echo '<button type="button" class="button charge-items" id="chargeButton">Charge</button>';
    }
    
    add_action("woocommerce_order_item_add_action_buttons", "charge", 10, 3 );
    add_action( 'woocommerce_admin_order_data_after_billing_address', 'associated_credit_cards', 10, 1);
    add_action( 'wp_ajax_my_action', 'my_action' );
    add_action( 'admin_footer', 'my_action_javascript' ); // Write our JS below here

    function my_action() {
        global $wpdb; // this is how you get access to the database

        $whatever = intval( $_POST['whatever'] );

        $whatever += 10;

            echo $whatever;

        wp_die(); // this is required to terminate immediately and return a proper response
    }


    function my_action_javascript() { ?>
        <script type="text/javascript" >
        jQuery(document).ready(function($) {

            var data = {
                'action': 'my_action',
                'whatever': 1234
            };
            jQuery("#chargeButton").click(function($) {
                // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
                jQuery.post(ajaxurl, data, function(response) {
                    alert('Got this from the server: ' + response);
                });
            })
            
        });
        </script> <?php
    }