<?php
$path = preg_replace('/wp-content(?!.*wp-content).*/', '', __DIR__);

require_once($path . 'wp-load.php');

require_once 'config.php';

$content = trim(file_get_contents("php://input"));
$decoded = json_decode($content, true);

if (isset($decoded['paypal_email']) && isset($decoded['email_subject']) && isset($decoded['email_body']) && isset($decoded['amount'])) {
    global $wpdb;

    $to = $decoded['paypal_email'];
    $subject = $decoded['email_subject'];
    $body = $decoded['email_body'];
    $amount = $decoded['amount'];
    $request_id = uniqid();
    $request_url = "https://www.bestarhost.com/payment-request/?request_id=" . $request_id;

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . MAIL_FROM . "\r\n";

    $result = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'Paypal_Settings LIMIT 1', ARRAY_A);

    if (empty($result)) {
        $currency = PAYPAL_DEFAULT_CURRENCY;

        $body = str_replace("[amount]", $amount, $body);
        $body = str_replace("[currency]", $currency, $body);
        $body = str_replace("[request_url]", $request_url, $body);
        try {
            $result = $wpdb->insert(
                $wpdb->prefix . "Paypal_Request",
                array(
                    'request_id' => $request_id,
                    'email' => $to,
                    'currency' => $currency,
                    'amount' => $amount,
                    'request_url' => $request_url,
                    'status' => 'pending',
                )
            );

            if (mail($to, $subject, $body, $headers)) {
                echo json_encode(array(
                    'msg' => 'Message has been sent'
                ), JSON_PRETTY_PRINT);
                die();
            } else {
                echo json_encode(array(
                    'msg' => 'Message has not been sent',
                    'error' => error_get_last()['message']
                ), JSON_PRETTY_PRINT);
                die();
            }
        } catch (Exception $e) {
            echo json_encode(array(
                'msg' => 'Message has not been sent',
                'error' => $e->getMessage()
            ));
            die();
        }
    } else {
        foreach ($result as $row) {
            $currency = $row['currency'];

            $body = str_replace("[amount]", $amount, $body);
            $body = str_replace("[currency]", $currency, $body);
            $body = str_replace("[request_url]", $request_url, $body);
            try {
                $result = $wpdb->insert(
                    $wpdb->prefix . "Paypal_Request",
                    array(
                        'request_id' => $request_id,
                        'email' => $to,
                        'currency' => $currency,
                        'amount' => $amount,
                        'request_url' => $request_url,
                        'status' => 'pending',
                    )
                );
                if ($result === false) {
                    echo json_encode(array(
                        'msg' => 'Message has not been sent',
                        'error' => $wpdb->last_error
                    ), JSON_PRETTY_PRINT);
                    die();
                }

                if (mail($to, $subject, $body, $headers)) {
                    echo json_encode(array(
                        'msg' => 'Message has been sent'
                    ), JSON_PRETTY_PRINT);
                    die();
                } else {
                    echo json_encode(array(
                        'msg' => 'Message has not been sent',
                        'error' => error_get_last()['message']
                    ), JSON_PRETTY_PRINT);
                    die();
                }
            } catch (Exception $e) {
                echo json_encode(array(
                    'msg' => 'Message has not been sent',
                    'error' => $e->getMessage()
                ), JSON_PRETTY_PRINT);
                die();
            }
        }
    }
} else {
    echo json_encode(
        array(
            'msg' => 'Cannot updated request status',
            'error' => 'Missing needed parameter(s)'
        ),
        JSON_PRETTY_PRINT
    );
    die();
}
