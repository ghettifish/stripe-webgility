<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


function charge_customer() {
    global $wpdb; // this is how you get access to the database
    $order_id = get_the_ID();
    $stripe_gateway = new WB_Gateway_Stripe;
    $response = $stripe_gateway->bill_customer($order_id);
}
add_action( 'woocommerce_order_action_wc_stripe_charge_customer', 'charge_customer' );


function charge_customer_action($actions) {
        global $theorder;
        if($theorder->is_paid()) {
          return $actions;
        }
        $actions['wc_stripe_charge_customer'] = __( 'Charge Customer', 'webgility-stripe' );
        return $actions;
}
add_action( 'woocommerce_order_actions', 'charge_customer_action' );


function associated_credit_cards($order) {

	$order_status = $order->get_status();
  if($order_status != 'completed' && $order_status != 'pending') {
    $order_id = $order->get_id();
    $order = wc_get_order( $order_id );
    $stripe_gateway = new WB_Gateway_Stripe;
    $source_id = get_post_meta( $order_id, '_stripe_source_id', true );
    $what = get_current_user_id();
    $user_id = $order->get_user_id();
    $source = $stripe_gateway->admin_prepare_source( $user_id, false);

    $user = $order->get_user();
    $customer_token = get_user_meta($user_id, '_stripe_customer_id', true);

    $response = WB_Stripe_API::retrieve(
        'sources/' . $source_id
    );

    $card = $response->card;
    $last4 = $card->last4;
    $brand = $card->brand;

    $html = sprintf(
        '
        <div>
            <h3>Associated Credit Card</h3>
            <p>%s ending in %s</p>
            <input type="hidden" id="stripe_token" name="selected_payment_token" value="%s">
            <input type="hidden" id="customer_token" name="customer_token" value="%s">
        </div>
        ',
        $brand,
        $last4,
        $source_id,
        $customer_token
    );
    echo $html;
  }
}

add_action( 'woocommerce_admin_order_data_after_billing_address', 'associated_credit_cards', 10, 1);
