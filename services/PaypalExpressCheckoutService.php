<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

include DIR_PAYMENT_FILES . 'paypal_express.php';

class PaypalExpressCheckoutService {

    public function getPaypalExpressChekcoutPaymentId() {
        $processor_id = db_get_row("SELECT processor_id  FROM ?:payment_processors WHERE processor = ?s", 'PayPal Express Checkout');
        $paymentMethodId = db_get_row("SELECT payment_id  FROM ?:payments WHERE status = 'A' and processor_id = ?s", $processor_id['processor_id']);
        if (!empty($paymentMethodId)) {
            return $paymentMethodId['payment_id'];
        }
        return '';
    }

    private function getPaymentMethodData($_payment_id = false) {
        if (!$_payment_id) {
            $_payment_id = $this->getPaypalExpressChekcoutPaymentId();
        }
        $processor_data = fn_get_payment_method_data($_payment_id);
        $key = isset($processor_data['params']) ? 'params' : 'processor_params';

        return array($_payment_id, $processor_data, $key);
    }

    public function returnFromPaypal() {
        list($_payment_id, $processor_data, $key) = $this->getPaymentMethodData();
        $pp_username = $processor_data[$key]['username'];
        $pp_password = $processor_data[$key]['password'];
        $cert_file = $signature = $url_prefix = '';
        if (!empty($processor_data[$key]['authentication_method']) && $processor_data[$key]['authentication_method'] == 'signature') {
            $signature = '<Signature>' . $processor_data[$key]['signature'] . '</Signature>';
            $url_prefix = '-3t';
        } else {
            $cert_file = DIR_PAYMENT_FILES . 'certificates/' . $processor_data[$key]['certificate'];
        }

        if ($processor_data[$key]['mode'] == "live") {
            $post_url = "https://api$url_prefix.paypal.com:443/2.0/";
            $payment_url = "https://www.paypal.com";
        } else {
            $post_url = "https://api$url_prefix.sandbox.paypal.com:443/2.0/";
            $payment_url = "https://www.sandbox.paypal.com";
        }
        $token = $_REQUEST['token'];
        $request = <<<EOT
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Header>
    <RequesterCredentials xmlns="urn:ebay:api:PayPalAPI">
      <Credentials xmlns="urn:ebay:apis:eBLBaseComponents">
        <Username>$pp_username</Username>
        <ebl:Password xmlns:ebl="urn:ebay:apis:eBLBaseComponents">$pp_password</ebl:Password>
        $signature
      </Credentials>
    </RequesterCredentials>
  </soap:Header>
  <soap:Body>
    <GetExpressCheckoutDetailsReq xmlns="urn:ebay:api:PayPalAPI">
      <GetExpressCheckoutDetailsRequest>
        <Version xmlns="urn:ebay:apis:eBLBaseComponents">1.00</Version>
        <Token>$token</Token>
      </GetExpressCheckoutDetailsRequest>
    </GetExpressCheckoutDetailsReq>
  </soap:Body>
</soap:Envelope>
EOT;
        $result = fn_paypal_request($request, $post_url, $cert_file);
        $s_firstname = $s_lastname = '';
        if (!empty($result['address']['Name'])) {
            $name = explode(' ', $result['address']['Name']);
            $s_firstname = $name[0];
            unset($name[0]);
            $s_lastname = (!empty($name[1])) ? implode(' ', $name) : '';
        }

        $s_state = $result['address']['StateOrProvince'];

        $s_state_codes = db_get_hash_array("SELECT ?:states.code, lang_code FROM ?:states LEFT JOIN ?:state_descriptions ON ?:state_descriptions.state_id = ?:states.state_id WHERE ?:states.country_code = ?s AND ?:state_descriptions.state = ?s", 'lang_code', $result['address']['Country'], $s_state);

        if (!empty($s_state_codes[CART_LANGUAGE])) {
            $s_state = $s_state_codes[CART_LANGUAGE]['code'];
        } elseif (!empty($s_state_codes)) {
            $s_state = $s_state['code'];
        }

        $address = array(
            's_firstname' => $s_firstname,
            's_lastname' => $s_lastname,
            's_address' => $result['address']['Street1'],
            's_address_2' => !empty($result['address']['Street2']) ? $result['address']['Street2'] : '',
            's_city' => $result['address']['CityName'],
            's_county' => $result['address']['StateOrProvince'],
            's_state' => $s_state,
            's_country' => $result['address']['Country'],
            's_zipcode' => $result['address']['PostalCode'],
            's_phone' => $result['ContactPhone']
        );

        $_SESSION['auth'] = empty($_SESSION['auth']) ? array() : $_SESSION['auth'];
        $auth = & $_SESSION['auth'];

        // Update profile info if customer is registered user
        if (!empty($auth['user_id']) && $auth['area'] == 'C') {
            foreach ($address as $k => $v) {
                $_SESSION['cart']['user_data'][$k] = $v;
            }

            $profile_id = !empty($_SESSION['cart']['user_data']['profile_id']) ? $_SESSION['cart']['user_data']['profile_id'] : db_get_field("SELECT profile_id FROM ?:user_profiles WHERE user_id = ?i AND profile_type='P'", $auth['user_id']);
            db_query('UPDATE ?:user_profiles SET ?u WHERE profile_id = ?i', $_SESSION['cart']['user_data'], $profile_id);

            // Or jyst update info in the cart
        } else {
            // fill customer info
            $_SESSION['cart']['user_data'] = array(
                'firstname' => $result['FirstName'],
                'lastname' => $result['LastName'],
                'email' => $result['Payer'],
                'company' => '',
                'phone' => !empty($result['ContactPhone']) ? $result['ContactPhone'] : '1234567890',
                'fax' => '',
            );

            foreach ($address as $k => $v) {
                $_SESSION['cart']['user_data'][$k] = $v;
                $_SESSION['cart']['user_data']['b_' . substr($k, 2)] = $v;
            }
        }

        $_SESSION['cart']['payment_id'] = $_payment_id;
        $_SESSION['pp_express_details'] = $result;
    }

    public function startExpressCheckout() {
        $locale_codes = array("AU", "DE", "FR", "GB", "IT", "JP", "US");

        list($_payment_id, $processor_data, $key) = $this->getPaymentMethodData();
        $pp_token = '';
        $pp_username = $processor_data[$key]['username'];
        $pp_password = $processor_data[$key]['password'];
        $pp_currency = $processor_data[$key]['currency'];

        $cert_file = $signature = $url_prefix = '';
        if (!empty($processor_data[$key]['authentication_method']) && $processor_data[$key]['authentication_method'] == 'signature') {
            $signature = '<Signature>' . $processor_data[$key]['signature'] . '</Signature>';
            $url_prefix = '-3t';
        } else {
            $cert_file = DIR_PAYMENT_FILES . 'certificates/' . $processor_data[$key]['certificate'];
        }

        $pp_total = fn_format_price($_SESSION['cart']['total'], $pp_currency);

        if ($processor_data[$key]['mode'] == "live") {
            $post_url = "https://api$url_prefix.paypal.com:443/2.0/";
            $payment_url = "https://www.paypal.com";
        } else {
            $post_url = "https://api$url_prefix.sandbox.paypal.com:443/2.0/";
            $payment_url = "https://www.sandbox.paypal.com";
        }

        if (!empty($_payment_id) && !empty($_SESSION['cart']['products'])) {

            $return_url = $_REQUEST['return_url'];
            $cancel_url = $_REQUEST['cancel_url'];
            $pp_locale_code = "US";
            if (in_array(CART_LANGUAGE, $locale_codes)) {
                $pp_locale_code = CART_LANGUAGE;
            }

            if (isset($_SESSION['auth']) && $_SESSION['auth']['user_id'] > 0) {
                ServiceFactory::factory('Checkout')->applyPayPalAddress();
                $address = $_SESSION['cart']['user_data'];
                $address['s_country'] == 'US' || $address['s_state'] = fn_get_state_name($address['s_state'], $address['s_country']);
                $address['b_country'] == 'US' || $address['b_state'] = fn_get_state_name($address['b_state'], $address['b_country']);
                $_address = <<<EOT
                <ReqConfirmShipping>0</ReqConfirmShipping>
                <AddressOverride>1</AddressOverride>
                 <Address>
                       <Name>$address[s_firstname] $address[s_lastname]</Name>
                       <Street1>$address[s_address]</Street1>
                       <Street2>$address[s_address_2]</Street2>
                       <CityName>$address[s_city]</CityName>
                       <StateOrProvince>$address[s_state]</StateOrProvince>
                       <PostalCode>$address[s_zipcode]</PostalCode>
                       <Country>$address[s_country]</Country>
                 </Address>
EOT;
            } else {
                $_address = '';
            }

            $xml_cart = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Header>
    <RequesterCredentials xmlns="urn:ebay:api:PayPalAPI">
      <Credentials xmlns="urn:ebay:apis:eBLBaseComponents">
        <Username>$pp_username</Username>
        <ebl:Password xmlns:ebl="urn:ebay:apis:eBLBaseComponents">$pp_password</ebl:Password>
        $signature
      </Credentials>
    </RequesterCredentials>
  </soap:Header>
  <soap:Body>
    <SetExpressCheckoutReq xmlns="urn:ebay:api:PayPalAPI">
      <SetExpressCheckoutRequest>
        <Version xmlns="urn:ebay:apis:eBLBaseComponents">1.00</Version>
        <SetExpressCheckoutRequestDetails xmlns="urn:ebay:apis:eBLBaseComponents">
          <OrderTotal currencyID="$pp_currency">$pp_total</OrderTotal>
          <ReturnURL>$return_url</ReturnURL>
          <CancelURL>$cancel_url</CancelURL>
          <PaymentAction>Authorization</PaymentAction>
          <LocaleCode>$pp_locale_code</LocaleCode>
          {$_address}
        </SetExpressCheckoutRequestDetails>
      </SetExpressCheckoutRequest>
    </SetExpressCheckoutReq>
  </soap:Body>
</soap:Envelope>
EOT;
            $result = fn_paypal_request($xml_cart, $post_url, $cert_file);
            if ($result['success'] && !empty($result['Token'])) {
                $pp_token = $result['Token'];
                if (isset($_SESSION['commit']) && $_SESSION['commit']) {
                    $suffix = '&useraction=commit';
                    unset($_SESSION['commit']);
                } else {  //if logout do not return confirm                   
                    $suffix = '&useraction=continue';
                }
                return array(
                    'paypal_redirect_url' => $payment_url . '/webscr?cmd=_express-checkout&token=' . $result['Token'] . $suffix,
                    'token' => $pp_token
                );
            }
            return isset($result['error']) ? $result['error'] : 'Unknow Error!';
        }
    }

    private function startPayment($orderId) {
        set_time_limit(300);
        $idata = array(
            'order_id' => $orderId,
            'type' => 'S',
            'data' => TIME,
        );
        db_query("REPLACE INTO ?:order_data ?e", $idata);
    }

    public function pay() {
        $cart = &$_SESSION['cart'];
        $cart['notes'] = "from mobile";
        $auth = &$_SESSION['auth'];
        list($order_id, ) = fn_place_order($cart, $auth);
        if ($order_id) {
            $this->startPayment($order_id);
            $order_info = fn_get_order_info($order_id);
            list($_payment_id, $processor_data, $key) = $this->getPaymentMethodData();
            if (!isset($processor_data[$key])) { //apply for 4.0
                $processor_data[$key] = $processor_data['processor_params'];
            }
            $pp_order_id = $processor_data[$key]['order_prefix'] . (($order_info['repaid']) ? ($order_id . '_' . $order_info['repaid']) : $order_id);

            $pp_username = $processor_data[$key]['username'];
            $pp_password = $processor_data[$key]['password'];
            $pp_currency = $processor_data[$key]['currency'];

            $cert_file = $signature = $url_prefix = '';
            if (!empty($processor_data[$key]['authentication_method']) && $processor_data[$key]['authentication_method'] == 'signature') {
                $signature = '<Signature>' . $processor_data[$key]['signature'] . '</Signature>';
                $url_prefix = '-3t';
            } else {
                $cert_file = DIR_PAYMENT_FILES . 'certificates/' . $processor_data[$key]['certificate'];
            }
            if ($processor_data[$key]['mode'] == "live") {
                $post_url = "https://api$url_prefix.paypal.com:443/2.0/";
                $payment_url = "https://www.paypal.com";
            } else {
                $post_url = "https://api$url_prefix.sandbox.paypal.com:443/2.0/";
                $payment_url = "https://www.sandbox.paypal.com";
            }


            $pp_total = fn_format_price($_SESSION['cart']['total'], $pp_currency);

            $_address = '';
            if (!empty($processor_data[$key]['send_adress']) && $processor_data[$key]['send_adress'] == 'Y') {
                $_address = <<<EOT
          <ShipToAddress>
                <Name>$order_info[s_firstname] $order_info[s_lastname]</Name>
                <Street1>$order_info[s_address]</Street1>
                <Street2>$order_info[s_address_2]</Street2>
                <CityName>$order_info[s_city]</CityName>
                <StateOrProvince>$order_info[s_state]</StateOrProvince>
                <PostalCode>$order_info[s_zipcode]</PostalCode>
                <Country>$order_info[s_country]</Country>
          </ShipToAddress>
EOT;
            }

            $request = <<<EOT
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
  <soap:Header>
    <RequesterCredentials xmlns="urn:ebay:api:PayPalAPI">
      <Credentials xmlns="urn:ebay:apis:eBLBaseComponents">
        <Username>$pp_username</Username>
        <ebl:Password xmlns:ebl="urn:ebay:apis:eBLBaseComponents">$pp_password</ebl:Password>
        $signature
      </Credentials>
    </RequesterCredentials>
  </soap:Header>
  <soap:Body>
    <DoExpressCheckoutPaymentReq xmlns="urn:ebay:api:PayPalAPI">
      <DoExpressCheckoutPaymentRequest>
        <Version xmlns="urn:ebay:apis:eBLBaseComponents">1.00</Version>
        <DoExpressCheckoutPaymentRequestDetails xmlns="urn:ebay:apis:eBLBaseComponents">
          <PaymentAction>Sale</PaymentAction>
          <Token>{$_SESSION['pp_express_details']['Token']}</Token>
          <PayerID>{$_SESSION['pp_express_details']['PayerID']}</PayerID>
          <PaymentDetails>
            <OrderTotal currencyID="$pp_currency">{$pp_total}</OrderTotal>
            <ButtonSource>ST_ShoppingCart_EC_US</ButtonSource>
            <NotifyURL></NotifyURL>
            <InvoiceID>$pp_order_id</InvoiceID>
            <Custom></Custom>
            $_address
          </PaymentDetails>
        </DoExpressCheckoutPaymentRequestDetails>
      </DoExpressCheckoutPaymentRequest>
    </DoExpressCheckoutPaymentReq>
  </soap:Body>
</soap:Envelope>
EOT;

            $result = fn_paypal_request($request, $post_url, $cert_file);

            $pp_response['order_status'] = 'F';

            if (isset($result['PaymentStatus']) && (!strcasecmp($result['PaymentStatus'], 'Completed') || !strcasecmp($result['PaymentStatus'], 'Processed'))) {
                $pp_response['order_status'] = 'P';
                $reason_text = 'Accepted';
            } elseif (isset($result['PaymentStatus']) && !strcasecmp($result['PaymentStatus'], 'Pending')) {
                $pp_response['order_status'] = 'O';
                $reason_text = 'Pending';
            } else {
                $reason_text = 'Declined';
            }

            if (!empty($result['PaymentStatus'])) {
                $reason_text .= " Status: " . $result['PaymentStatus'];
            }
            if (!empty($result['PendingReason'])) {
                $reason_text .= ' Reason: ' . $result['PendingReason'];
            }

            $reason_text = fn_paypal_process_add_fields($result, $reason_text);

            if (!empty($result['error'])) {
                $reason_text .= sprintf("Error: %s (Code: %s%s)", $result['error']['LongMessage'], $result['error']['ErrorCode'], isset($result['error']['Severity']) ? ' , Severity:' . $result['error']['Severity'] : ''
                );
                return array('result' => false, 'error_msg' => $reason_text);
            }

            $pp_response['reason_text'] = $reason_text;

            if (preg_match("/<TransactionID>(.*)<\/TransactionID>/", $result['response'], $transaction)) {
                $pp_response['transaction_id'] = $transaction[1];
            }
            unset($_SESSION['pp_express_details']);
            fn_finish_payment($order_id, $pp_response, true);
            kc_order_placement_routines($order_id, false);
            return array('result' => true, 'order' => $order_info, 'paypal_response' => $pp_response);
        }
        $message = get_error_message('PayPalEC Place order failed.');
        return array('result' => false, 'error_msg' => join('<br/>', $message));
    }

}

if (!function_exists('fn_paypal_process_add_fields')) {

    function fn_paypal_process_add_fields($result, $reason_text) {
        $fields = array();
        foreach (array('ExchangeRate', 'FeeAmount', 'GrossAmount', 'PaymentType', 'SettleAmount', 'TaxAmount', 'TransactionID', 'TransactionType') as $f) {
            if (isset($result[$f]) && strlen($result[$f]) > 0) {
                $fields[] = ' ' . $f . ': ' . $result[$f];
            }
        }

        if (!empty($fields)) {
            $reason_text .= ' (' . implode(', ', $fields) . ')';
        }

        return $reason_text;
    }

}
?>
