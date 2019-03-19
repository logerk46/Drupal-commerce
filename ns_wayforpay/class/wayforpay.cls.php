<?php

class Wayforpay
{
  const ORDER_APPROVED = 'Approved';

  const ORDER_PENDING = 'Pending';

  const ORDER_SEPARATOR = '#';

  const SIGNATURE_SEPARATOR = ';';

  const URL = "https://secure.wayforpay.com/pay/";

  const TEST_MERCHANT_ACCOUNT = 'test_merch_n1';

  const TEST_MERCHANT_SECRET_KEY = 'flk3409refn54t54t*FNJRET';
  
  protected $keysForResponseSignature = array(
    'merchantAccount',
    'orderReference',
    'amount',
    'currency',
    'authCode',
    'cardPan',
    'transactionStatus',
    'reasonCode'
  );

  /** @var array */
  protected $keysForSignature = array(
    'merchantAccount',
    'merchantDomainName',
    'orderReference',
    'orderDate',
    'amount',
    'currency',
    'productName',
    'productCount',
    'productPrice'
  );

  protected $module_id = '';

  protected $debug_state = FALSE;

  public function __construct($module_id = 'ns_wayforpay'){
    $this->module_id = $module_id;
  }


  /**
   * @param $option
   * @param $keys
   * @return string
   */
  public function getSignature($option, $keys)
  {
    $hash = array();
    foreach ($keys as $dataKey) {
      if (!isset($option[$dataKey])) {
        continue;
      }
      if (is_array($option[$dataKey])) {
        foreach ($option[$dataKey] as $v) {
          $hash[] = $v;
        }
      } else {
        $hash [] = $option[$dataKey];
      }
    }

    $hash = implode(self::SIGNATURE_SEPARATOR, $hash);
    return hash_hmac('md5', $hash, $this->getSecretKey());
  }


  /**
   * @param $options
   * @return string
   */
  public function getRequestSignature($options)
  {
    return $this->getSignature($options, $this->keysForSignature);
  }

  /**
   * @param $options
   * @return string
   */
  public function getResponseSignature($options)
  {
    return $this->getSignature($options, $this->keysForResponseSignature);
  }


  /**
   * @param array $data
   * @return string
   */
  public function getAnswerToGateWay($data)
  {   
    $time = time();
    $responseToGateway = array(
      'orderReference' => $data['orderReference'],
      'status' => 'accept',
      'time' => $time
    );
    $sign = array();
    foreach ($responseToGateway as $dataKey => $dataValue) {
      $sign [] = $dataValue;
    }
    $sign = implode(self::SIGNATURE_SEPARATOR, $sign);
    $sign = hash_hmac('md5', $sign, $this->getSecretKey());
    $responseToGateway['signature'] = $sign;

    return json_encode($responseToGateway);
  }

  /**
   * @param $response
   * @return bool|string
   */
  public function isPaymentValid($response)
  {

    if (!isset($response['merchantSignature']) && isset($response['reason'])) {
      return $response['reason'];
    }
    $sign = $this->getResponseSignature($response);
    if ($sign != $response['merchantSignature']) {
      return 'An error has occurred during payment';
    }

    if ($response['transactionStatus'] == self::ORDER_APPROVED) {
      return true;
    }

    return false;
  }

  /**
   * @param $response
   * @return bool|string
   */
  public function isReturnValid($response)
  {

    if (!isset($response['merchantSignature']) && isset($response['reason'])) {
      return $response['reason'];
    }
    $sign = $this->getResponseSignature($response);
    if ($sign != $response['merchantSignature']) {
      return 'An error has occurred during payment';
    }

    if ($response['transactionStatus'] == self::ORDER_APPROVED) {
      return true;
    }

    if ($response['transactionStatus'] == self::ORDER_PENDING) {
      return true;
    }

    return false;
  }

  public static function _isPaymentValid($wayforpaySettings, $response)
  {
      list($orderId,) = explode(self::ORDER_SEPARATOR, $response['order_id']);

      $order = commerce_order_load($orderId);
      $order_state = FALSE;
      $settings = $this->getPaymentMethodSettings();

      if($order){
        $order_status = commerce_order_status_load($order->status);
        $order_state = commerce_order_state_load($order_status['state']);
      }

      if ($order === FALSE || $order_state != 'checkout') {
          return t('An error has occurred during payment. Please contact us to ensure your order has submitted.');
      }

      if ($settings['merchant_id'] != $response['merchant_id']) {
          return t('An error has occurred during payment. Merchant data is incorrect.');
      }

      $originalResponse = $response;
      foreach ($response as $k => $v) {
          if (!in_array($k, self::$responseFields)) {
              unset($response[$k]);
          }
      }

      if (self::getSignature($response, $wayforpaySettings->secret_key) != $originalResponse['signature']) {
          return t('An error has occurred during payment. Signature is not valid.');
      }

      if (drupal_strtolower($originalResponse['sender_email']) !== drupal_strtolower($order->mail)) {
          $replacements = array('!email' => check_plain($originalResponse['sender_email']));
          $message = 'Customer used a different e-mail address during payment: !email';
          watchdog('wayforpay', $message, $replacements, WATCHDOG_WARNING);
      }

      return true;
  }

  public function getPaymentMethodSettings(){
    $payment_method = commerce_payment_method_instance_load("{$this->module_id}|commerce_payment_{$this->module_id}");
    if($payment_method['settings']['account'] == 'test'){
      $payment_method['settings']['merchant_id'] = self::TEST_MERCHANT_ACCOUNT;
      $payment_method['settings']['secret_key'] = self::TEST_MERCHANT_SECRET_KEY;
    }
    return $payment_method['settings'];
  }

  public function getSecretKey()
  {
    $settings = $this->getPaymentMethodSettings();
    return $settings['secret_key'];
  }

  public function debug($variable = NULL, $name = 'wayforpay'){
    if($this->debug_state) watchdog($name, '<pre>' . print_r( $variable, true) . '</pre>');
  }
}
