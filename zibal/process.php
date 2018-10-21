<?php
/*
 * Zibal ONline payment module for Prestashop<=1.6
 * @author Mohammad Zamanzadeh (zamanzadeh@zibal.ir)
 */
if (isset($_GET['do'])) {

	include (dirname(__FILE__) . '/../../config/config.inc.php');
    include (dirname(__FILE__) . '/../../header.php');
	include (dirname(__FILE__) . '/zibal.php');
	$zibal = new zibal();

	if ($_GET['do'] == 'payment') {
		$zibal->do_payment($cart);

	} else {

        if (isset($_GET['amount']) && isset($_POST['success']) && isset($_POST['trackId']) && isset($_POST['orderId'])&& isset($_GET['currency_id'])&& isset($_GET['iso_code'])) {
            $order = $_POST['orderId'];
            $amount = htmlspecialchars($_GET['amount']);
            $currency_id = $_GET['currency_id'];
            $currency_iso_code = $_GET['iso_code'];

            $cookie = new Cookie('order');
            $cookie = $cookie->hash;


            if (isset($cookie) && $cookie) {

                $hash = md5($order . $amount . Configuration::get('zibal_hash'));


                if ($hash == $cookie) {


                    $success        = htmlspecialchars($_POST['success']);
                    $trackId      = htmlspecialchars($_POST['trackId']);
                    $orderId = htmlspecialchars($_POST['orderId']);

                    if (isset($success) && $success == 1) {

                        $merchant = Configuration::get('zibal_merchant_id');


                        $params = array (

                            'merchant'     => $merchant,
                            'trackId' => $trackId
                        );

                        $result = $zibal->postToZibal('verify', $params);


                        if ($result && isset($result->result) && $result->result == 100) {

                            if ($amount == $result->amount) {

                                $customer = new Customer((int)$cart->id_customer);
                                $currency = $context->currency;

                                $message = 'تراکنش شماره '.$trackId.' با درگاه آنلاین زیبال پرداخت شد';

                                if ($currency_iso_code != 'IRR'){
                                    $amount = $amount / 10;
                                }

                                $zibal->validateOrder((int)$order, _PS_OS_PAYMENT_, $amount, $zibal->displayName, $message, array(), (int)$currency->id, false, $customer->secure_key);


                                Tools::redirect('history.php');

                            } else {

                                echo $zibal->error('رقم تراكنش با رقم پرداخت شده مطابقت ندارد');
                            }

                        } else {

                            $message = 'در ارتباط با وب سرویس zibal.ir و بررسی تراکنش خطایی رخ داده است';
                            $message .= isset($result->message) ? $result->message : '';

                            echo $zibal->error($message);
                        }

                    } else {

                        $message = $message ? $message : 'تراكنش با خطا مواجه شد و یا توسط پرداخت کننده کنسل شده است';

                        echo $zibal->error($message);
                    }

                } else {

                    echo $zibal->error('الگو رمزگذاری تراکنش غیر معتبر است');
                }

            } else {

                echo $zibal->error('سفارش یافت نشد و یا نشست پرداخت منقضی شده است');
            }

        } else {

            echo $zibal->error('اطلاعات ارسال شده مربوط به تایید تراکنش ناقص و یا غیر معتبر است');
        }

    }

    include (dirname(__FILE__) . '/../../footer.php');
} else {

	die('Something wrong');
}
