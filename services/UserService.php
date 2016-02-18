<?php
if (!defined('IN_KANCART')) {
    header('HTTP/1.1 404 Not Found');
    die();
}

class UserService {

    /**
     * update user's address
     * @param type $address
     * @return boolean
     */
    public function updateAddress($address) {
	$_SERVER['REQUEST_METHOD'] = 'POST';
        $_REQUEST['user_data'] = $address;
        $_REQUEST['copy_address'] = false;
        $_REQUEST['notify_customer'] = false;
        $_REQUEST['default_cc'] = false;
        $auth = &$_SESSION['auth'];
        $mode = 'update';
        Registry::set('settings.Image_verification.use_for_register', 'N', true);

        $core_post_controllers = fn_init_core_controllers('profiles', defined('KC_REGISTER_CONTROLLERS') ? KC_REGISTER_CONTROLLERS : GET_POST_CONTROLLERS);
        $register_file = current($core_post_controllers);
        list($result, ) = include $register_file;
        if ($result === CONTROLLER_STATUS_OK) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * remove user's address
     */
    public function removeAddress($profileId) {
        $user_id = $_SESSION['auth']['user_id'];
        $user_type = isset($_SESSION['auth']['is_root']) && $_SESSION['auth']['is_root'] == 'Y' ? 'A' : 'C';
        $params = array(
            'user_id' => $user_id,
            'user_data' => array('user_type' => $user_type)
        );
        if (AREA == 'A' && (fn_is_restricted_admin($params) == true || defined('COMPANY_ID'))) {
            return false;
        }

        $can_delete = db_get_field("SELECT profile_id FROM ?:user_profiles WHERE user_id = ?i AND profile_id = ?i AND profile_type = 'S'", $user_id, $profileId);
        if (!empty($can_delete)) {
            db_query("DELETE FROM ?:user_profiles WHERE profile_id = ?i", $profileId);
            if (isset($_SESSION['auth']['address_id']) && $_SESSION['auth']['address_id'] == $profileId) { //delete address is payment address
                $_SESSION['auth']['address_id'] = get_default_address_id();
            }
            return true;
        }
        return false;
    }

    /**
     * check address's integrity
     * @param type $address
     */
    public function checkAddressIntegrity($address) {
        if ($address) {
            return (int) $address['address_book_id'] > 0
                    && $address['firstname']
                    && $address['lastname']
                    && strlen($address['country_id']) > 0
                    && $address['city']
                    && $address['postcode']
                    && $address['gender']
                    && $address['zone_id'] ? (int) $address['zone_id'] > 0 : $address['state'];
        }
        return false;
    }

    public function getBillingAddress($billingAddressId) {
        $user_id = $_SESSION['auth']['user_id'];
        $user_data = fn_get_user_info($user_id, true, $billingAddressId);
        $address = translate_address($user_data, 'billing');
        return $address;
    }

    /**
     * Add an address entry to user profile
     * @return int the id of the new entry
     */
    public function addAddress($address) {
        $profiles_num = db_get_field("SELECT COUNT(*) FROM ?:user_profiles WHERE user_id = ?i", $_SESSION['auth']['user_id']);
        if ($profiles_num < 1 || Registry::get('settings.General.user_multiple_profiles') == 'Y') {
            $auth = &$_SESSION['auth'];
            $address['user_id'] = $auth['user_id'];
            $address['profile_id'] = false;
            $result = fn_update_user($auth['user_id'], $address, $auth, false, true);
            if ($result === false) {
                return get_error_message();
            }
            return $result[1];
        } else {
            return 'You can\'t add this address by configuration limits.';
        }
    }

    public function getShippingAddress($shippingAddressId) {
        $user_id = $_SESSION['auth']['user_id'];
        $user_data = fn_get_user_info($user_id, true, $shippingAddressId);
        $address = translate_address($user_data, 'shipping');
        return $address;
    }

    public function getAddress($profileId) {
        $user_id = $_SESSION['auth']['user_id'];
        $user_data = fn_get_user_info($user_id, true, $profileId);
        $address = translate_address($user_data, 'shipping');
        return $address;
    }

    public function getAllAddresses($type = 'shipping') {
        $user_id = $_SESSION['auth']['user_id'];
        $addresses = array();
        if ($user_id) {
            $profiles = db_get_array("SELECT * FROM ?:user_profiles WHERE user_id = ?i", $user_id);
            foreach ($profiles as $profile) {
                $addresses[] = translate_address($profile, $type);
            }
        }
        return $addresses;
    }

	public function getUserName(){
		$user_id = $_SESSION['auth']['user_id'];
        $user_data = fn_get_user_info($user_id, true);
		return array('firstname' => $user_data['firstname'], 'lastname' => $user_data['lastname']);
	}
}
?>