<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

/**
 * Order Service, Utility
 * @package services 
 */
class OrderService {

    /**
     * get orders info
     * @param type $userId
     * @param type $pageNo
     * @param type $pageSize
     * @return type 
     */
    public function getOrderInfos($params, $userId, $pageNo, $pageSize) {
        list($orders, $count) = $this->getUserOrders($params, $userId, $pageNo, $pageSize);

        $orderInfos = array();
        foreach ($orders as $order) {
            $orderDetail = fn_get_order_info($order['order_id']);
            $currency = $orderDetail['secondary_currency'];
            $extractToOrder = array();
            $extractToOrder['order_id'] = $order['order_id'];
            $extractToOrder['display_id'] = ' #' . $order['order_id'];
            $extractToOrder['email'] = $orderDetail['email'];
			$extractToOrder['firstname'] = $orderDetail['firstname'];
			$extractToOrder['lastname'] = $orderDetail['lastname'];
            $extractToOrder['currency'] = $currency;
			$extractToOrder['date_added'] = date('Y-m-d, H:i:s', $orderDetail['timestamp']);
            $extractToOrder['order_items'] = $this->getOrderItems($orderDetail, $currency);
            $extractToOrder['price_infos'] = $this->getOrderPriceInfos($orderDetail, $currency);
            $extractToOrder['order_status'] = $this->extractOrderStatus($orderDetail);
            $extractToOrder['last_status_id'] = '1';
            $orderInfos[] = $extractToOrder;
        }
        $result = array('total_results' => $count, 'orders' => $orderInfos);
        return $result;
    }

    public function getUserOrders($params, $userId, $pageNo, $pageSize) {

        //$start = ($pageNo - 1) * $pageSize;
        //$orders = db_get_array("SELECT order_id FROM ?:orders WHERE user_id = ?i and status != ?s ORDER BY order_id desc LIMIT $start,$pageSize", $userId, STATUS_INCOMPLETED_ORDER);
        //$count = db_get_row("SELECT COUNT(*) as total FROM ?:orders WHERE user_id = ?i and status != ?s", $userId, STATUS_INCOMPLETED_ORDER);
		
		$results = current(fn_get_orders($params));
		$count = 0;
		$orders = array();
		foreach($results as $result){
			$count++;
			$orders[]['order_id'] = $result['order_id'];
		}
		
        return array($orders, $count);
    }

    /**
     * Get one order information
     * @param type $userId
     * @param type $order
     * @return type 
     */
    public function getOneOrderInfo($orderId) {

        $orderDetail = fn_get_order_info($orderId);
        $orderInfo = $this->extractOrderInfo($orderDetail);
        return $orderInfo;
    }

    private function extractOrderStatus($order) {
        $orderStatuses = array();
        $row = fn_get_status_data($order['status'], STATUSES_ORDER);
        if ($row) {
            $status = array();
            $status['status_id'] = $row['status_id'];
			$status['status_type'] = $row['status'];
            $status['status_name'] = $row['description'];
            $status['comments'] = $order['notes'];
			$status['status_color'] = !empty($row['params'])?(!empty($row['params']['color'])?$row['params']['color']:''):'';
            $status['position'] = 1;
            $orderStatuses = $status;
        }
        return $orderStatuses;
    }

    private function extractOrderInfo($orderDetail) {
        $extractToOrder = array();
        $extractToOrder['order_id'] = $orderDetail['order_id'];
        $extractToOrder['display_id'] = ' #' . $orderDetail['order_id'];
        $extractToOrder['email'] = $orderDetail['email'];
		$extractToOrder['firstname'] = $orderDetail['firstname'];
		$extractToOrder['lastname'] = $orderDetail['lastname'];
        $extractToOrder['currency'] = CART_SECONDARY_CURRENCY;
		$extractToOrder['date_added'] = date('Y-m-d, H:i:s', $orderDetail['timestamp']);
		$extractToOrder['barcode_img_url'] = $this->exctractBarcodeImage($orderDetail['order_id']);
		$extractToOrder['invoice_pdf_url'] = fn_url('orders.print_invoice&order_id=99&format=pdf', AREA, 'current');
        $extractToOrder['shipping_address'] = $this->extractShippingAddress($orderDetail);
        $extractToOrder['billing_address'] = $this->extractBillingAddress($orderDetail);
        $extractToOrder['payment_method'] = $this->extractPaymentMethod($orderDetail);
        $extractToOrder['shipping_method'] = $this->extractShippingMethod($orderDetail);
        $extractToOrder['order_items'] = $this->getOrderItems($orderDetail);
        $extractToOrder['price_infos'] = $this->getOrderPriceInfos($orderDetail);
        $extractToOrder['order_status'] = $this->extractOrderStatus($orderDetail);
        $extractToOrder['last_status_id'] = '1';
        return $extractToOrder;
    }

	private function exctractBarcodeImage($orderID){
		if (is_addon_enabled('barcode')) {
			return fn_url('image.barcode.draw&id='.$orderID.'&type='.Registry::get('addons.barcode.type'), AREA, 'current');
		}
		return '';
	}
	
    private function extractPaymentMethod($orderDetail) {
        $orderPayment = array();
        $paymentInfo = $orderDetail['payment_method'];
        $orderPayment['pm_id'] = $paymentInfo['payment_id'];
        $orderPayment['pm_title'] = $paymentInfo['payment'];
        $orderPayment['pm_description'] = $paymentInfo['description'];
        $orderPayment['pm_img_url'] = '';
		if(!empty($paymentInfo['image'])){
			$orderPayment['pm_img_url'] = $paymentInfo['image']['icon']['http_image_path'];
		}
        return $orderPayment;
    }

    private function extractShippingMethod($orderDetail) {
        $orderShippingMethod = array();
        $shippingMethodInfo = $orderDetail['shipping'];
        if (!empty($shippingMethodInfo)) {
            list($key, $value) = each($shippingMethodInfo);
        }
        $price = 0;
        foreach ($value['rates'] as $rate) {
            $price += $rate;
        }
        $orderShippingMethod['sm_id'] = $key;
        $orderShippingMethod['sm_code'] = '';
        $orderShippingMethod['title'] = !empty($value['shipping'])?$value['shipping']:'';
        $orderShippingMethod['description'] = '';
        $orderShippingMethod['price'] = convert_price($price);
        $orderShippingMethod['currency'] = CART_SECONDARY_CURRENCY;
        return $orderShippingMethod;
    }

    private function getOrderItems($orderDetail, $currency = CART_SECONDARY_CURRENCY) {
        $orderItems = array();
        if (isset($orderDetail['items'])) {
            $goodsList = $orderDetail['items'];
        } else {
            $goodsList = $orderDetail['products'];
        }
        if ($goodsList) {
            foreach ($goodsList as $goods) {
                $orderItem = array();
                $orderItem['order_item_id'] = $goods['item_id'];
                $orderItem['item_title'] = $goods['product'];
                $orderItem['item_id'] = $goods['product_id'];
				$orderItem['item_display_id'] = fn_get_product_code($goods['product_id']);
                $orderItem['thumbnail_pic_url'] = get_product_image_url((int) $goods['product_id']);
                $sku = '';
                if (!empty($goods['product_options'])) {
                    $product_options = $goods['product_options'];
                    foreach ($product_options as $product_option) {
                        $sku .= '-' . $product_option['option_name'] . ': ' . $product_option['variant_name'] . '<br>';
                    }
                }
                else if(!empty($goods['extra']['product_options'])){
                    $product_options_has = $goods['extra']['product_options'];
                    $product_options = fn_get_product_options($goods['product_id']);
                    foreach ($product_options_has as $key=>$value) {
                        if(!empty($product_options[$key]['variants']))
                            $sku .= '- ' . $product_options[$key]['option_name'] . ': ' . $product_options[$key]['variants'][$value]['variant_name']. '<br>';
                        else
                            $sku .= '- ' . $product_options[$key]['option_name'] . ': ' . $value. '<br>';
                    }

                }
                $orderItem['display_attributes'] = $sku;
                $orderItem['qty'] = $goods['amount'];
                $orderItem['virtual_flag'] = $goods['virtual_flag'];
                $orderItem['price'] = convert_price($goods['price'], $currency);
                $orderItem['final_price'] = convert_price((string)$goods['subtotal'], $currency);
                $orderItems[] = $orderItem;
            }
        }
        return $orderItems;
    }

    private function getOrderPriceInfos($orderDetail, $currency = CART_SECONDARY_CURRENCY) {
        $position = 0;

//        if ($orderDetail['shipping'] && Registry::get('settings.General.use_shipments') != 'Y') {  //do not show payment because of mobile not support
//            foreach ($orderDetail['shipping'] as $shipping) {
//                $totals[] = array(
//                    'title' => fn_get_lang_var('shipping'),
//                    'type' => 'total',
//                    'price' => $shipping['shipping'],
//                    'currency' => $currency,
//                    'position' => $position++
//                );
//            }
//        }

        $totals[] = array(
            'title' => fn_get_lang_var('subtotal'),
            'type' => 'subtotal',
            'price' => convert_price($orderDetail['display_subtotal'], $currency),
            'currency' => $currency,
            'position' => $position++
        );

        if ($orderDetail['display_shipping_cost'] > 0) {
            $totals[] = array(
                'title' => fn_get_lang_var('shipping_cost'),
                'type' => 'shipping_cost',
                'price' => convert_price($orderDetail['display_shipping_cost'], $currency),
                'currency' => $currency,
                'position' => $position++
            );
        }

        if ($orderDetail['discount'] > 0) {
            $totals[] = array(
                'title' => fn_get_lang_var('including_discount'),
                'type' => 'discount',
                'price' => convert_price($orderDetail['discount'], $currency),
                'currency' => $currency,
                'position' => $position++
            );
        }

        if ($orderDetail['subtotal_discount'] > 0) {
            $totals[] = array(
                'title' => fn_get_lang_var('order_discount'),
                'type' => 'discount',
                'price' => convert_price($orderDetail['subtotal_discount'], $currency),
                'currency' => $currency,
                'position' => $position++
            );
        }

//        if ($orderDetail['coupons']) {             //do not show coupon because of mobile not support
//            foreach ($orderDetail['coupons'] as $key => $coupon) {
//                $totals[] = array(
//                    'title' => fn_get_lang_var('coupon'),
//                    'type' => 'coupon',
//                    'price' => $key,
//                    'currency' => $currency,
//                    'position' => $position++
//                );
//            }
//        }

        if ($orderDetail['taxes']) {
            foreach ($orderDetail['taxes'] as $tax) {
                $totals[] = array(
                    'title' => get_tax_title($tax),
                    'type' => 'taxes',
                    'price' => convert_price($tax['tax_subtotal'], $currency),
                    'currency' => $currency,
                    'position' => $position++
                );
            }
        }

        $take_surcharge_from_vendor = (PRODUCT_TYPE == 'MULTIVENDOR' && function_exists('fn_take_payment_surcharge_from_vendor')) ? fn_take_payment_surcharge_from_vendor($orderDetail['items']) : false;
        if ($orderDetail['payment_surcharge'] > 0 && !$take_surcharge_from_vendor) {
            $totals[] = array(
                'title' => empty($orderDetail['payment_method']['surcharge_title']) ? fn_get_lang_var('payment_surcharge') : $orderDetail['payment_method']['surcharge_title'],
                'type' => 'payment',
                'price' => convert_price($orderDetail['payment_surcharge'], $currency),
                'currency' => $currency,
                'position' => $position++
            );
        }

        if ($orderDetail['use_gift_certificates']) {
            foreach ($this->cart['use_gift_certificates'] as $gift_code => $gift) {
                $totals[] = array(
                    'title' => fn_get_lang_var('gift_certificate') . ' (' . $gift_code . ')',
                    'type' => 'gift_certificate',
                    'price' => convert_price($gift['cost'], $currency),
                    'currency' => $currency,
                    'position' => $position++
                );
            }
        }

        $totals[] = array(
            'title' => fn_get_lang_var('total'),
            'type' => 'total',
            'price' => convert_price($orderDetail['total'], $currency),
            'currency' => $currency,
            'position' => $position++
        );

        return $totals;
    }

    private function extractShippingAddress($orderDetail) {
        $address = array();
        $address['address_book_id'] = '';
        $address['address_type'] = 'ship';
        $address['lastname'] = $orderDetail['s_lastname'];
        $address['firstname'] = $orderDetail['s_firstname'];
        $address['gender'] = !empty($orderDetail['b_title'])?$orderDetail['b_title']:'';
        $address['telephone'] = $orderDetail['s_phone'];
        $address['tax_code'] = '';
        $address['postcode'] = $orderDetail['s_zipcode'];
        $address['address1'] = $orderDetail['s_address'];
        $address['address2'] = $orderDetail['s_address_2'];
        $address['city'] = $orderDetail['s_city'];
        $address['zone_id'] = $orderDetail['s_state'];
        $address['zone_code'] = $orderDetail['s_state'];
        $address['zone_name'] = $orderDetail['s_state_descr'];
        $address['country_id'] = $orderDetail['s_country'];
        $address['country_code'] = $orderDetail['s_country'];
        $address['country_name'] = $orderDetail['s_country_descr'];
        $address['state'] = $orderDetail['s_state'];
        return $address;
    }

    private function extractBillingAddress($orderDetail) {
        $address = array();
        $address['address_book_id'] = '';
        $address['address_type'] = 'bill';
        $address['lastname'] = $orderDetail['b_lastname'];
        $address['firstname'] = $orderDetail['b_firstname'];
        $address['gender'] = !empty($orderDetail['b_title'])?$orderDetail['b_title']:'';
        $address['telephone'] = $orderDetail['b_phone'];
        $address['tax_code'] = '';
        $address['postcode'] = $orderDetail['b_zipcode'];
        $address['address1'] = $orderDetail['b_address'];
        $address['address2'] = $orderDetail['b_address_2'];
        $address['city'] = $orderDetail['b_city'];
        $address['zone_id'] = $orderDetail['b_state'];
        $address['zone_code'] = $orderDetail['b_state'];
        $address['zone_name'] = $orderDetail['b_state_descr'];
        $address['country_id'] = $orderDetail['b_country'];
        $address['country_code'] = $orderDetail['b_country'];
        $address['country_name'] = $orderDetail['b_country_descr'];
        $address['state'] = $orderDetail['b_state'];
        return $address;
    }

    public function getPaymentOrderInfo($order, $tx = '') {
        $orderItem = array();
        $orderId = false;

        if ($order) {
            $orderItem['display_id'] = $orderId = $order['order_id'];
            $orderItem['shipping_address'] = $this->getPaymentAddress($order);
            $orderItem['price_infos'] = $this->getPaymentPriceInfos($order);
            $orderItem['order_items'] = $this->getPaymentOrderItems($order);

            $total = convert_price($order['total']);
            $currency = CART_SECONDARY_CURRENCY;
        } else {
            $total = 0;
            $currency = CART_SECONDARY_CURRENCY;
        }

        return array(
            'transaction_id' => $tx,
            'payment_total' => $total,
            'currency' => $currency,
            'order_id' => $orderId,
            'orders' => sizeof($orderItem) ? array($orderItem) : false
        );
    }

    public function getPaymentAddress($order) {
        $addrress = array(
            'city' => $order['s_city'],
            'country_id' => $order['s_country'],
            'zone_id' => '', //1
            'zone_name' => $order['s_state_descr'], //2
            'state' => $order['s_state_descr'], //3
            'address1' => $order['s_address'],
            'address2' => $order['s_address_2'],
        );

        return $addrress;
    }

    public function getPaymentPriceInfos($order) {
        $info = array();

        $info[] = array(
            'type' => 'total',
            'home_currency_price' => convert_price($order['total'])
        );

        $info[] = array(
            'type' => 'shipping',
            'home_currency_price' => convert_price($order['display_shipping_cost'])
        );

        if ($order['taxes']) {
            $taxes = 0;
            foreach ($order['taxes'] as $tax) {
                $taxes+=$tax['tax_subtotal'];
            }
            $info[] = array(
                'type' => 'tax',
                'home_currency_price' => convert_price($taxes)
            );
        }

        return $info;
    }

    public function getPaymentOrderItems($order) {
        $items = array();
        $products = $order['items'];
        foreach ($products as $product) {
            $items[] = array(
                'order_item_key' => $product['product_id'],
                'item_title' => $product['product'],
                'category_name' => '',
                'home_currency_price' => convert_price($product['price']),
                'qty' => $product['amount']
            );
        }

        return $items;
    }

}

?>
