<?php
require_once('../../../wp-load.php');

$content = trim(file_get_contents("php://input"));
$decoded = json_decode($content, true);

if (isset($decoded['client_id']) && isset($decoded['app_sceret']) && isset($decoded['currency']) && isset($decoded['default_amount']) && isset($decoded['environment'])) {
    global $wpdb;

    try {
        if ($decoded['environment'] == 'test') {
            $api_url = 'https://api-m.sandbox.paypal.com';
        } else {
            $api_url = 'https://api-m.paypal.com';
        }

        $client_id = $decoded['client_id'];
        $app_sceret = $decoded['app_sceret'];
        $currency = $decoded['currency'];
        $default_amount = $decoded['default_amount'];
        $environment = $decoded['environment'];

        $sql = "CREATE TABLE " . $wpdb->prefix . "Paypal_Settings (client_id VARCHAR(100) NOT NULL, app_sceret VARCHAR(100) NOT NULL, currency VARCHAR(5) NOT NULL, default_amount FLOAT(2) NOT NULL);";
        maybe_create_table($wpdb->prefix . "Paypal_Settings", $sql);

        $result = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "Paypal_Settings", ARRAY_A);
        if (empty($result)) {
            $result = $wpdb->insert(
                $wpdb->prefix . "Paypal_Settings",
                array(
                    'client_id' => $client_id,
                    'app_sceret' => $app_sceret,
                    'currency' => $currency,
                    'default_amount' => $default_amount,
                    'environment' => $environment,
                    'api_url' => $api_url
                )
            );

            if ($result === false) {
                echo json_encode(array(
                    'msg' => 'Message has not been sent',
                    'error' => $wpdb->last_error
                ), JSON_PRETTY_PRINT);
                die();
            }
        } else {
            $result = $wpdb->update(
                $wpdb->prefix . "Paypal_Settings",
                array(
                    'client_id' => $client_id,
                    'app_sceret' => $app_sceret,
                    'currency' => $currency,
                    'default_amount' => $default_amount,
                    'environment' => $environment,
                    'api_url' => $api_url
                )
            );

            if ($result === false) {
                echo json_encode(array(
                    'msg' => 'Message has not been sent',
                    'error' => $wpdb->last_error
                ), JSON_PRETTY_PRINT);
                die();
            }
        }

        echo json_encode(array(
            'msg' => 'update'
        ), JSON_PRETTY_PRINT);
        die();
    } catch (Exception $e) {
        echo json_encode(array(
            'error' => 'Cannot update settings',
            'msg' => $e->getMessage()
        ), JSON_PRETTY_PRINT);
        die();
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
