<?php
function generateAccessToken()
{
    global $wpdb;
    $result = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'Paypal_Settings LIMIT 1', ARRAY_A);

    if (empty($result)) {

        $client_id = PAYPAL_DEFAULT_CLIENT_ID;
        $app_sceret = PAYPAL_DEFAULT_APP_SECRET;

        try {
            $curl = curl_init();
            $auth = base64_encode($client_id . ':' . $app_sceret);

            curl_setopt_array($curl, array(
                CURLOPT_URL => PAYPAL_API_URL . '/v1/oauth2/token',
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => array('Authorization: Basic ' . $auth),
                CURLOPT_POSTFIELDS => 'grant_type=client_credentials'
            ));

            $result = curl_exec($curl);

            if (!$result) {
                echo json_encode(
                    array(
                        'msg' => 'cURL returned an error',
                        'error' => curl_error($curl)
                    )
                );
            }
        } catch (Exception $e) {
            echo json_encode(
                array(
                    'msg' => 'cURL returned an error',
                    'error' => $e->getMessage()
                )
            );
        }

        $response = handleResponse($result);

        curl_close($curl);

        return $response['access_token'];
    } else {
        foreach ($result as $row) {
            $client_id = $row['client_id'];
            $app_sceret = $row['app_sceret'];

            try {
                $curl = curl_init();
                $auth = base64_encode($client_id . ':' . $app_sceret);

                $api_url = $row['api_url'];

                curl_setopt_array($curl, array(
                    CURLOPT_URL => $api_url . '/v1/oauth2/token',
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => array('Authorization: Basic ' . $auth),
                    CURLOPT_POSTFIELDS => 'grant_type=client_credentials'
                ));

                $result = curl_exec($curl);

                if (!$result) {
                    echo json_encode(
                        array(
                            'msg' => 'cURL returned an error',
                            'error' => curl_error($curl)
                        )
                    );
                }
            } catch (Exception $e) {
                echo json_encode(
                    array(
                        'msg' => 'cURL returned an error',
                        'error' => $e->getMessage()
                    )
                );
            }

            $response = handleResponse($result);
            curl_close($curl);

            return $response['access_token'];
        }
    }
}

function handleResponse($response)
{
    $response = json_decode($response, true);
    if (!empty($response['access_token']) || $response['status'] == 'CREATED' || $response['status'] == 'COMPLETED') {
        return $response;
    } else {
        throw new Exception(json_encode(array('error' => 'Invalid status code', 'response' => $response)));
    }
}

function createOrder()
{
    global $wpdb;

    $result = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'Paypal_Settings LIMIT 1', ARRAY_A);

    $accessToken = generateAccessToken();

    if (empty($result)) {
        $currency = PAYPAL_DEFAULT_CURRENCY;
        $amount = isset($_REQUEST['amount']) ? $_REQUEST['amount'] : PAYPAL_ORDERS_DEFAULT_AMOUNT;

        try {

            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => PAYPAL_API_URL . '/v2/checkout/orders',
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json'
                ),
                CURLOPT_POSTFIELDS => json_encode(array(
                    'intent' => 'CAPTURE',
                    'purchase_units' => array(
                        array(
                            'amount' => array(
                                'currency_code' => $currency,
                                'value' => $amount
                            )
                        )
                    )
                ))
            ));

            $result = curl_exec($curl);

            if (!$result) {
                echo json_encode(
                    array(
                        'msg' => 'cURL returned an error',
                        'error' => curl_error($curl)
                    )
                );
            }
        } catch (Exception $e) {
            echo json_encode(
                array(
                    'msg' => 'cURL returned an error',
                    'error' => $e->getMessage()
                )
            );
        }

        $response = handleResponse($result);
        curl_close($curl);

        echo json_encode($response, JSON_PRETTY_PRINT);
        die();
    } else {
        foreach ($result as $row) {
            $currency = $row['currency'];
            $amount = $row['default_amount'];
            $api_url = $row['api_url'];

            try {
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $api_url . '/v2/checkout/orders',
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => array(
                        'Authorization: Bearer ' . $accessToken,
                        'Content-Type: application/json'
                    ),
                    CURLOPT_POSTFIELDS => json_encode(array(
                        'intent' => 'CAPTURE',
                        'purchase_units' => array(
                            array(
                                'amount' => array(
                                    'currency_code' => $currency,
                                    'value' => $amount
                                )
                            )
                        )
                    ))
                ));

                $result = curl_exec($curl);

                if (!$result) {
                    echo json_encode(
                        array(
                            'msg' => 'cURL returned an error',
                            'error' => curl_error($curl)
                        )
                    );
                }
            } catch (Exception $e) {
                echo json_encode(
                    array(
                        'msg' => 'cURL returned an error',
                        'error' => $e->getMessage()
                    )
                );
            }

            $response = handleResponse($result);
            curl_close($curl);

            echo json_encode($response, JSON_PRETTY_PRINT);
            die();
        }
    }
}

function capturePayment()
{
    if (isset($_REQUEST['orderID'])) {
        $orderId = $_REQUEST['orderID'];
    } else {
        echo json_encode(
            array(
                'msg' => 'Cannot capture payment',
                'error' => 'MISSING REQUEST PARAMETER(S)',
                'missing_parameter' => 'orderID'
            ),
            JSON_PRETTY_PRINT
        );
        die();
    }

    $accessToken = generateAccessToken();

    global $wpdb;

    $result = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'Paypal_Settings LIMIT 1', ARRAY_A);

    if (empty($result)) {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => PAYPAL_API_URL . '/v2/checkout/orders/' . $orderId . '/capture',
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json'
                )
            ));

            $result = curl_exec($curl);

            if (!$result) {
                echo json_encode(
                    array(
                        'msg' => 'cURL returned an error',
                        'error' => curl_error($curl)
                    )
                );
            }
        } catch (Exception $e) {
            echo json_encode(
                array(
                    'msg' => 'cURL returned an error',
                    'error' => $e->getMessage()
                )
            );
        }

        $response = handleResponse($result);

        curl_close($curl);

        echo json_encode($response, JSON_PRETTY_PRINT);
        die();
    } else {
        foreach ($result as $row) {
            $api_url = $row['api_url'];
            try {
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $api_url . '/v2/checkout/orders/' . $orderId . '/capture',
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => array(
                        'Authorization: Bearer ' . $accessToken,
                        'Content-Type: application/json'
                    )
                ));

                $result = curl_exec($curl);

                if (!$result) {
                    echo json_encode(
                        array(
                            'msg' => 'cURL returned an error',
                            'error' => curl_error($curl)
                        )
                    );
                }
            } catch (Exception $e) {
                echo json_encode(
                    array(
                        'msg' => 'cURL returned an error',
                        'error' => $e->getMessage()
                    )
                );
            }

            $response = handleResponse($result);

            curl_close($curl);

            echo json_encode($response, JSON_PRETTY_PRINT);
            die();
        }
    }
}

function updateRequestStatus()
{
    if (isset($_REQUEST['request_id']) && isset($_REQUEST['status'])) {
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->prefix . "Paypal_Request",
            array(
                'status' => $_REQUEST['status'],
            ),
            array(
                'request_id' => $_REQUEST['request_id']
            )
        );
        if ($result) {
            echo json_encode(
                array(
                    'msg' => 'Updated request status'
                ),
                JSON_PRETTY_PRINT
            );
            die();
        } else {
            echo json_encode(
                array(
                    'msg' => 'Cannot updated request status',
                    'error' => $wpdb->last_error
                ),
                JSON_PRETTY_PRINT
            );
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
    }
}

function createPayout()
{
    if (isset($_REQUEST['requester']) && isset($_REQUEST['amount'])) {
        global $wpdb;

        $requester =  $_REQUEST['requester'];
        $amount = $_REQUEST['amount'];

        $result = $wpdb->get_results('SELECT * FROM ' . $wpdb->prefix . 'Paypal_Settings LIMIT 1', ARRAY_A);

        $accessToken = generateAccessToken();

        if (empty($result)) {
            $currency = PAYPAL_DEFAULT_CURRENCY;
            try {
                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => PAYPAL_API_URL . '/v1/payments/payouts',
                    CURLOPT_POST => true,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => array(
                        'Authorization: Bearer ' . $accessToken,
                        'Content-Type: application/json'
                    ),
                    CURLOPT_POSTFIELDS => json_encode(array(
                        'sender_batch_header' => array(
                            'sender_batch_id' => uniqid(),
                            'recipient_type' => 'EMAIL',
                            'email_subject' => 'You have a payout!',
                            'email_message' => 'You have received a payout! Thanks for using our service!',
                        ),
                        'items' => array(
                            array(
                                'amount' => array(
                                    'currency' => $currency,
                                    'value' => $amount
                                ),
                                'sender_item_id' => uniqid() . '001',
                                'recipient_wallet' => 'PAYPAL',
                                'receiver' => $requester,
                            )
                        )
                    ))
                ));

                $result = curl_exec($curl);

                if (!$result) {
                    echo json_encode(
                        array(
                            'msg' => 'cURL returned an error',
                            'error' => curl_error($curl)
                        )
                    );
                }
            } catch (Exception $e) {
                echo json_encode(
                    array(
                        'msg' => 'cURL returned an error',
                        'error' => $e->getMessage()
                    )
                );
            }

            $response = json_decode($result);
            curl_close($curl);

            echo json_encode($response, JSON_PRETTY_PRINT);
            die();
        } else {
            foreach ($result as $row) {
                $currency = $row['currency'];
                $api_url = $row['api_url'];

                try {
                    $curl = curl_init();
                    curl_setopt_array($curl, array(
                        CURLOPT_URL => $api_url . '/v1/payments/payouts',
                        CURLOPT_POST => true,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER => array(
                            'Authorization: Bearer ' . $accessToken,
                            'Content-Type: application/json'
                        ),
                        CURLOPT_POSTFIELDS => json_encode(array(
                            'sender_batch_header' => array(
                                'sender_batch_id' => uniqid(),
                                'email_subject' => 'You have a payout!',
                                'email_message' => 'You have received a payout! Thanks for using our service!',
                            ),
                            'items' => array(
                                array(
                                    'amount' => array(
                                        'currency' => $currency,
                                        'value' => $amount
                                    ),
                                    'sender_item_id' => uniqid() . '001',
                                    'recipient_type' => 'EMAIL',
                                    'recipient_wallet' => 'PAYPAL',
                                    'receiver' => $requester,
                                )
                            )
                        ))
                    ));

                    $result = curl_exec($curl);

                    if (!$result) {
                        echo json_encode(
                            array(
                                'msg' => 'cURL returned an error',
                                'error' => curl_error($curl)
                            )
                        );
                    }
                } catch (Exception $e) {
                    echo json_encode(
                        array(
                            'msg' => 'cURL returned an error',
                            'error' => $e->getMessage()
                        )
                    );
                }

                $response = json_decode($result);

                curl_close($curl);

                echo json_encode($response, JSON_PRETTY_PRINT);
                die();
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
}
