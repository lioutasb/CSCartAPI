<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

/**
 * ShoppingCart Service,Utility
 * @package services 
 */
class ShoppingCartService {

    public function __construct() {
        if (empty($_SESSION['cart'])) {
            fn_clear_cart($_SESSION['cart']);
        }
        $this->cart = &$_SESSION['cart'];
        //$this->cart['notes'] = "from mobile";
        $this->auth = &$_SESSION['auth'];
        /* affect taxes when user logout */
        fn_define('CHECKOUT', true);
        $type = ($this->cart['display_shipping_cost'] > 0 && $this->cart['shipping']) ? 'A' : 'S';
        fn_calculate_cart_content($this->cart, $this->auth, $type, true, 'F', true); //reflush cart S only calculate taxes again
    }

    /**
     * Get shopping cart detail
     */
    public function get() {
        $result = array();
        $this->initShoppingCartGetResult($result);

        if ($this->cart) {
            $products = $this->cart['products'];
            foreach ($products as $product) {
                $result['cart_items_count'] += $product['amount'];
            }
            $result['cart_items'] = $this->getCartItems($products);
            $result['messages'] = array();
            $result['price_infos'] = $this->getPriceInfos();
            $result['is_virtual'] = false;
            $result['payment_methods'] = $this->getPaymentMethods();
			//$result['asd'] = $products;
        }
        return $result;
    }
	
	public function getItemsCount(){
		$result = array();
		$result['cart_items_count'] = 0;
		if ($this->cart) {
			$products = $this->cart['products'];
            foreach ($products as $product) {
                $result['cart_items_count'] += $product['amount'];
            }
		}
		return $result;
	}

    public function getPaymentMethods() {
        $payplExpressCheckoutService = ServiceFactory::factory('PaypalExpressCheckout');
        if ($payplExpressCheckoutService->getPaypalExpressChekcoutPaymentId()) {
            return array('paypalec');
        }
        return array();
    }

    private function initShoppingCartGetResult(&$result) {
        $result['cart_items_count'] = 0;
        $result['cart_items'] = array();
        $result['messages'] = array();
        $result['price_infos'] = array();
    }

    public function getCartItems($cs_products) {
        $cart_items = array();
        foreach ($cs_products as $p_id => $product) {
            if ($product['amount'] == 0) {
                $this->dropCartGoods($this->cart, $p_id);
                continue;
            }
            $cart_item = array();
            $cart_item['cart_item_id'] = $p_id;
            $cart_item['cart_item_key'] = '';
            $cart_item['item_id'] = $product['product_id'];
            $cart_item['item_display_id'] = fn_get_product_code($product['product_id']);
            $cart_item['item_title'] = fn_get_product_name($product['product_id']);
            $cart_item['thumbnail_pic_url'] = get_product_image_url(isset($product['main_pair']) ? $product['main_pair'] : $product['product_id']);
            $cart_item['currency'] = CART_SECONDARY_CURRENCY;
            $cart_item['item_price'] = convert_price($product['price']);
            $cart_item['subtotal_price'] = convert_price($product['price'] * $product['amount']);
            $cart_item['qty'] = $product['amount'];
            $cart_item['display_attributes'] = $this->displayAttribute($product['product_id'], $product['product_options']);
            $cart_items[] = $cart_item;
        }
        return $cart_items;
    }

    private function displayAttribute($product_id, $dis_att) {
        $display_attribute = array();
        if (!empty($dis_att)) {
            $product_options = fn_get_product_options($product_id);
            foreach ($dis_att as $key => $value) {
				if(!empty($product_options[$key]['variants']))
					$display_attribute[] = '- ' . $product_options[$key]['option_name'] . ': ' . $product_options[$key]['variants'][$value]['variant_name'];
				else
					$display_attribute[] = '- ' . $product_options[$key]['option_name'] . ': ' . $value;
            }
        }
        return $display_attribute;
    }

    private function getPriceInfos() {
        $position = 0;
        $priceInfos = array();

        $total = 0;

        $priceInfos[] = array(
            'title' => fn_get_lang_var('subtotal'),
            'type' => 'subtotal',
            'price' => convert_price($this->cart['display_subtotal']),
            'currency' => CART_SECONDARY_CURRENCY,
            'position' => $position++
        );
        $total +=  $this->cart['display_subtotal'];


        if (!$this->cart['shipping_failed']) {
            if ($this->cart['shipping_required']) { //not free shipping
                if (($this->cart['display_shipping_cost'] > 0 || $this->cart['shipping_cost'] > 0) && $this->cart['shipping']) {
                    $price = $this->cart['display_shipping_cost'] > 0 ? $this->cart['display_shipping_cost'] : $this->cart['shipping_cost'];
                    $title = '';
                    foreach ($this->cart['shipping'] as $shipping) {
                        $title .=$shipping['shipping'] . ' ';
                    }
                    $priceInfos[] = array(
                        'title' => $title,
                        'type' => 'discount',
                        'price' => convert_price($price),
                        'currency' => CART_SECONDARY_CURRENCY,
                        'position' => $position++
                    );
                    $total +=  $price;
                }
            }
        }

        if ($this->cart['discount'] > 0) {
            $priceInfos[] = array(
                'title' => fn_get_lang_var('including_discount'),
                'type' => 'discount',
                'price' => convert_price($this->cart['discount']),
                'currency' => CART_SECONDARY_CURRENCY,
                'position' => $position++
            );
            $total -=  $this->cart['discount'];
        }

        if ($this->cart['subtotal_discount'] > 0) {
            $priceInfos[] = array(
                'title' => fn_get_lang_var('order_discount'),
                'type' => 'discount',
                'price' => convert_price($this->cart['subtotal_discount']),
                'currency' => CART_SECONDARY_CURRENCY,
                'position' => $position++
            );
            $total -=  $this->cart['subtotal_discount'];
        }

        $payment_surcharge = 0;
        $take_surcharge_from_vendor = (PRODUCT_TYPE == 'MULTIVENDOR') ? fn_take_payment_surcharge_from_vendor($this->cart['products']) : false;
        if ($this->cart['payment_surcharge'] > 0 && !$take_surcharge_from_vendor) {
            $priceInfos[] = array(
                'title' => $this->cart['payment_surcharge_title'] ? $this->cart['payment_surcharge_title'] : fn_get_lang_var('payment_surcharge'),
                'type' => 'payment',
                'price' => convert_price($this->cart['payment_surcharge']),
                'currency' => CART_SECONDARY_CURRENCY,
                'position' => $position++
            );
            $payment_surcharge =  $this->cart['payment_surcharge'];
        }

        $taxes = 0;
        if ($this->cart['taxes']) {
            foreach ($this->cart['taxes'] as $tax) {
                $priceInfos[] = array(
                    'title' => get_tax_title($tax),
                    'type' => 'taxes',
                    'price' => convert_price($tax['tax_subtotal']),
                    'currency' => CART_SECONDARY_CURRENCY,
                    'position' => $position++
                );
                //$total +=  $tax['tax_subtotal'];
                $taxes += $tax['tax_subtotal'];
            }
        }

        if (isset($this->cart['use_gift_certificates']) && $this->cart['use_gift_certificates']) {
            foreach ($this->cart['use_gift_certificates'] as $gift_code => $gift) {
                $priceInfos[] = array(
                    'title' => fn_get_lang_var('gift_certificate') . ' (' . $gift_code . ')',
                    'type' => 'gift_certificate',
                    'price' => convert_price($gift['cost']),
                    'currency' => CART_SECONDARY_CURRENCY,
                    'position' => $position++
                );
                $total -=  $gift['cost'];
            }
        }


        if(($total + $taxes) == $this->cart['total']){
            $total += $taxes;
        }

        if($total + $payment_surcharge > $this->cart['total'])
            $total += $payment_surcharge;

        $priceInfos[] = array(
            'title' => fn_get_lang_var('order_total'),
            'type' => 'total',
            'price' => convert_price($total),//$this->cart['total']),
            'currency' => CART_SECONDARY_CURRENCY,
            'position' => $position++
        );

        return $priceInfos;
    }

    /**
     * Add product to shopping cart
     * @param type $goods
     * @return type 
     */
    public function add($product_data) {
        $auth = &$_SESSION['auth'];
        $result = fn_add_product_to_cart($product_data, $this->cart, $auth, true);
        fn_save_cart_content($this->cart, $auth['user_id']);
        fn_calculate_cart_content($this->cart, $auth, 'S', true, 'F', true);
        $this->cart['recalculate'] = true;
        $this->cart['change_cart_products'] = true; //apply for 4.0+

        return $result;
    }

    /**
     * Update cart
     * @param type $arr
     * @return type 
     */
    public function update($arr) {
        $auth = &$_SESSION['auth'];
        $cart_item_id = $arr['cart_item_id'];
        $qty = $arr['qty'];
        if (isset($this->cart['products'][$cart_item_id])) {
            $cart_product = $this->cart['products'][$cart_item_id];
            $product_options = empty($arr['product_options']) ? $cart_product['product_options'] : $arr['product_options'];
            if ($qty != 0) {
                unset($this->cart['products'][$cart_item_id]['original_amount']);
                $this->cart['products'][$cart_item_id]['amount'] = 0;
                $product_id = $cart_product['product_id'];
                $amount = $qty;
                $is_edp = 'N';
                $product_data = array();
                $product_data[$product_id] = array('product_id' => $product_id, 'amount' => $amount, 'product_options' => $product_options, 'is_edp' => $is_edp);
                fn_update_cart_products($this->cart, $product_data, $auth);
            }
            fn_save_cart_content($this->cart, $auth['user_id']);
        }
        list(, $_SESSION['shipping_rates']) = fn_calculate_cart_content($this->cart, $auth, 'A', true, 'F', true);
        $this->cart['change_cart_products'] = true; //apply for 4.0+
        return array('result' => true, 'err_msg' => '');
    }

    /**
     * Drop cart item
     *
     * @access  public
     * @param   integer $id
     * @return  void
     */
    public function dropCartGoods($id) {
        $auth = &$_SESSION['auth'];
        fn_delete_cart_product($this->cart, $id);

        if (fn_cart_is_empty($this->cart) == true) {
            fn_clear_cart($this->cart);
        }
        fn_save_cart_content($this->cart, $auth['user_id']);
        $this->cart['recalculate'] = true;
        list(, $_SESSION['shipping_rates']) = fn_calculate_cart_content($this->cart, $auth, 'A', true, 'F', true);
        $this->cart['change_cart_products'] = true; //apply for 4.0+
    }
	
	public function clearCart(){
		$auth = &$_SESSION['auth'];
		fn_clear_cart($this->cart);
		fn_save_cart_content($this->cart, $auth['user_id']);
        $this->cart['recalculate'] = true;
        list(, $_SESSION['shipping_rates']) = fn_calculate_cart_content($this->cart, $auth, 'A', true, 'F', true);
        $this->cart['change_cart_products'] = true;
	}

}

?>