<?php
/*
 * Zibal online payment module for Prestashop<=1.6
 * @author Mohammad Zamanzadeh (zamanzadeh@zibal.ir)
 */
if (defined('_PS_VERSION_') == FALSE) {

	die('This file cannot be accessed directly');
}

class zibal extends PaymentModule {

	private $_html = '';

	public function __construct() {

		$this->name             = 'zibal';
		$this->tab              = 'payments_gateways';
		$this->version          = '1.0';
		$this->author           = 'zibal.ir';
		$this->currencies       = TRUE;
		$this->currencies_mode  = 'radio';

		parent::__construct();

		$this->displayName      = 'پرداخت آنلاین زیبال';
		$this->description      = 'Online Payment with zibal.ir';
		$this->confirmUninstall = 'Are you sure you want to delete your details?';

		if (!sizeof(Currency::checkPaymentCurrencies($this->id))) {

			$this->warning = 'No currency has been set for this module.';
		}

		$config = Configuration::getMultiple(array('zibal_merchant_id'));

		if (!isset($config['zibal_merchant_id'])) {

			$this->warning = 'شما باید پارامترهای پیکربندی ماژول پرداخت آنلاین زیبال را وارد کنید';
		}
	}


	public function install() {

		if (!parent::install() || !Configuration::updateValue('zibal_merchant_id', 'zibal') || !Configuration::updateValue('zibal_direct', '0') || !Configuration::updateValue('zibal_logo', '') || !Configuration::updateValue('zibal_hash', $this->hash_key()) || !$this->registerHook('payment') || !$this->registerHook('paymentReturn')) {

			return FALSE;

		} else {

			return TRUE;
		}
	}

	public function uninstall() {

		if (!Configuration::deleteByName('zibal_merchant_id') ||!Configuration::deleteByName('zibal_direct') || !Configuration::deleteByName('zibal_logo') || !Configuration::deleteByName('zibal_hash') || !parent::uninstall()) {

			return FALSE;

		} else {

			return TRUE;
		}
	}

	public function hash_key() {

		$en = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z');

		$one   = rand(1, 26);
		$two   = rand(1, 26);
		$three = rand(1, 26);

		return $hash = $en[$one] . rand(0, 9) . rand(0, 9) . $en[$two] . $en[$three] . rand(0, 9) . rand(10, 99);
	}

	public function getContent() {

		if (Tools::isSubmit('zibal_setting')) {

			Configuration::updateValue('zibal_merchant_id', $_POST['zibal_merchant_id']);
			Configuration::updateValue('zibal_direct', $_POST['zibal_direct']);
			Configuration::updateValue('zibal_logo', $_POST['zibal_logo']);

			$this->_html .= '<div class="conf confirm">' . 'Settings Updated' . '</div>';
		}

		$this->_generateForm();

		return $this->_html;
	}

	private function _generateForm() {

		$this->_html .= '<div align="center" dir="rtl">';
		$this->_html .= '<form action="' . $_SERVER['REQUEST_URI'] . '" method="post">';
        $this->_html .= 'کدمرچنت: ' . '';
		$this->_html .= '<input type="text" name="zibal_merchant_id" value="' . Configuration::get('zibal_merchant_id') . '" ><br/>';
     
        $this->_html .= 'درگاه مستقیم?' ;
		$direct = (Configuration::get('zibal_direct')=='1')?'checked':'';
		$this->_html .= 'فعال <input type="radio" name="zibal_direct" value="1" '.$direct.'>';
        $direct = (Configuration::get('zibal_direct')=='0')?'checked':'';
        $this->_html .= 'غیرفعال <input type="radio" name="zibal_direct" value="0" '.$direct.'><br/><br/>';
		$this->_html .= '<input type="submit" name="zibal_setting" value="' . 'ذخیره' . '" class="button" />';
		$this->_html .= '</form>';
		$this->_html .= '</div>';
	}
	

	public function do_payment($cart) {

        if (extension_loaded('curl')) {

            $server   = (Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . __PS_BASE_URI__;
            $amount   = floatval(number_format($cart->getOrderTotal(true, 3), 2, '.', ''));
            $address  = new Address(intval($cart->id_address_invoice));
            $mobile   = isset($address->phone_mobile) ? $address->phone_mobile : NULL;
            $fullname   = isset($address->firstname) ? $address->firstname : '';
            $fullname   .= isset($address->lastname) ? $address->lastname : '';
            $merchant  = Configuration::get('zibal_merchant_id');
            $direct  = Configuration::get('zibal_direct');
            $currency_id = $cart->id_currency;

            foreach(Currency::getCurrencies() as $key => $currency){
                if ($currency['id_currency'] == $currency_id){
                    $currency_iso_code = $currency['iso_code'];
                }
            }

            if ($currency_iso_code != 'IRR'){
                $amount = $amount * 10;
            }

            $callback = $server . 'modules/zibal/process.php?do=call_back&amount=' . $amount.'&currency_id='.$currency_id.'&iso_code='.$currency_iso_code;


            $params = array(

                'merchant'          => $merchant,
                'amount'       => $amount,
                'callbackUrl'     => urlencode($callback),
                'mobile'       => $mobile,
                'orderId' => $cart->id,
                'description'  => 'خریدار: ' . $fullname,
            );

            $result = $this->postToZibal('request', $params);

            if ($result && isset($result->result) && $result->result == 100) {

                $cookie = new Cookie('order');
                $cookie->setExpire(time() + 20 * 60);
                $cookie->hash = md5($cart->id . $amount . Configuration::get('zibal_hash'));
                $cookie->write();

                $gateway_url = 'https://gateway.zibal.ir/start/' . $result->trackId;
                $gateway_url .= ($direct=='0')?'':'/direct';

                Tools::redirect($gateway_url);

            } else {

                $message = 'در ارتباط با وب سرویس zibal.ir خطایی رخ داده است';
                $message = isset($result->message) ? $result->message : $message;

                echo $this->error($message);
            }

        } else {

            echo $this->error('تابع cURL در سرور فعال نمی باشد');
        }
	}

	public function error($str) {

		return '<div class="alert error">' . $str . '</div>';
	}

	public function success($str) {

		echo '<div class="conf confirm">' . $str . '</div>';
	}

	public function hookPayment($params) {

		global $smarty;
            $smarty->assign('zibal_logo', Configuration::get('zibal_logo'));

            if ($this->active) {

                return $this->display(__FILE__, 'zibal.tpl');
//                return $this->context->smarty->fetch(__FILE__, 'zibal.tpl');
            }

	}

	public function hookPaymentReturn($params) {

		if ($this->active) {

			return NULL;
		}
	}

    function postToZibal($path, $parameters)
    {
        $url ='https://gateway.zibal.ir/'.$path;
       
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($parameters));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response  = curl_exec($ch);
        curl_close($ch);
        return json_decode($response);
    }
}


