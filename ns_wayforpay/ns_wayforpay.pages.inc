<?php

/**
 * @file
 * Page callbacks for Wayforpay.
 */

/**
 * Callback function return page.
 */
function ns_wayforpay_return_page_callback() {
    $data = $_POST;
    if (empty($data)) {
      drupal_goto('cart');
    }
    $w4p = new Wayforpay();
    list($orderId,) = explode(wayforpay::ORDER_SEPARATOR, $data['orderReference']);
  
    $paymentInfo = $w4p->isReturnValid($data);

    $order = commerce_order_load($orderId);
    if(!$order){
      drupal_set_message(t('Unknown error.'));
      drupal_goto('<front>');
    }
  
    if ($paymentInfo === TRUE) {
      
      drupal_set_message(t('Thank you, payment successful.'));
      commerce_payment_redirect_pane_next_page($order);
      drupal_goto(commerce_checkout_order_uri($order));
      
    }
    else {
      drupal_set_message($paymentInfo, 'warning');
      commerce_payment_redirect_pane_previous_page($order);
      drupal_goto(commerce_checkout_order_uri($order));
    }
}

/**
 * Callback function service page.
 */
  
function ns_wayforpay_service_page_callback() {
  
    $data = json_decode(file_get_contents("php://input"), TRUE);
    if (empty($data)) {
      drupal_goto('<front>');
    }
    $w4p = new Wayforpay();
    list($orderId,) = explode(wayforpay::ORDER_SEPARATOR, $data['orderReference']);
  
    $paymentInfo = $w4p->isPaymentValid($data);

    $order = commerce_order_load($orderId);

    if (!$order) exit();
  
    if ($paymentInfo === TRUE) {
      $order_wrapper = entity_metadata_wrapper('commerce_order', $order);
      $paid_in_full = isset($order->data['commerce_payment_order_paid_in_full_invoked']);
      if(!$paid_in_full) ns_wayforpay_create_transaction($order, $order_wrapper);
    }
    echo $w4p->getAnswerToGateWay($data);
}