<?php

if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class shoppingcart_addresses_update_action extends BaseAction {

    private function updateAddress($shippingAddressId, $address) {
        $checkoutService = ServiceFactory::factory('Checkout');
        $checkoutService->updateAddress($shippingAddressId, $address);
    }

    private function addAddress($shippingAddress) {
        $checkoutService = ServiceFactory::factory('Checkout');
        return $checkoutService->addAddress($shippingAddress);
    }

    public function execute() {
        $shippingAddressBookId = max($_REQUEST['shipping_address_book_id'], $_REQUEST['billing_address_book_id']);
        $shippingAddressJson = max($_REQUEST['shipping_address'], $_REQUEST['billing_address']);
        if ($shippingAddressBookId) { //update address           
            $shippingAddress = array();
            if ($shippingAddressJson) {
                $addr = json_decode(htmlspecialchars_decode($shippingAddressJson, ENT_COMPAT), true);
                $shippingAddress = array_merge(prepare_address($_REQUEST, 'shipping'), prepare_address($_REQUEST, 'billing'));
            }
            $this->updateAddress($shippingAddressBookId, $shippingAddress);
        } else {
            $shippingAddress = json_decode(htmlspecialchars_decode($shippingAddressJson, ENT_COMPAT), true);
            $addr = array_merge(prepare_address($_REQUEST, 'shipping'), prepare_address($_REQUEST, 'billing'));  //the shipping address and the billing adress is the same
            if ($_SESSION['auth']['user_id']) {
                $this->addAddress($addr);
            } else { //apply for guest express checkout
                foreach ($addr as $key => $value) {
                    $_SESSION['cart']['user_data'][$key] = $value;
                }
            }
        }
        $this->setSuccess(ServiceFactory::factory('Checkout')->detail());
    }

}

?>
