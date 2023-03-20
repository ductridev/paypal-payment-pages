<?php
$path = preg_replace('/wp-content(?!.*wp-content).*/', '', __DIR__);

require_once($path . './wp-admin/includes/upgrade.php');
require_once($path . 'wp-load.php');

require_once 'config.php';
require_once 'paypal-api.php';

/**
 * Plugin Name: Paypal Payment Functions Into Page
 * Plugin URI: https://estar-solutions.com/
 * Description: Paypal payment page plugin with shortcode
 * Version: 1.0
 * Author: Hoang Duc Minh
 * Author URI: https://estar-solutions.com/
 */

add_shortcode('bestarhost_paypal_payment', 'payment_shortcode_content');
add_shortcode('bestarhost_paypal_payment_request', 'request_shortcode_content');
add_action('admin_menu', 'create_menu_bestarhost_paypal_payment');

add_action("wp_ajax_capture_payment", "capturePayment");
add_action("wp_ajax_create_orders", "createOrder");
add_action("wp_ajax_create_payouts", "createPayout");
add_action("wp_ajax_update_request_status", "updateRequestStatus");

add_action("wp_ajax_nopriv_capture_payment", "capturePayment");
add_action("wp_ajax_nopriv_create_orders", "createOrder");
add_action("wp_ajax_nopriv_create_payouts", "createPayout");
add_action("wp_ajax_nopriv_update_request_status", "updateRequestStatus");

function create_menu_bestarhost_paypal_payment()
{
    create_db();
    add_menu_page('Bestar Host Paypal Payment', 'Bestar Host Paypal Payment', 'manage_options', 'settings', 'display_settings');
}

function create_db()
{
    global $wpdb;

    $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "Paypal_Settings (id INT NOT NULL AUTO_INCREMENT, client_id VARCHAR(100) NOT NULL , app_sceret VARCHAR(100) NOT NULL, currency VARCHAR(5) NOT NULL, default_amount FLOAT(2) NOT NULL, PRIMARY KEY (id));";
    $wpdb->query($sql);

    $sql = "CREATE TABLE IF NOT EXISTS " . $wpdb->prefix . "Paypal_Request (id INT NOT NULL AUTO_INCREMENT, request_id VARCHAR(100) NOT NULL, email VARCHAR(100) NOT NULL, currency VARCHAR(5) NOT NULL, amount FLOAT(2) NOT NULL, request_url VARCHAR(100) NOT NULL, status VARCHAR(10) NOT NULL DEFAULT 'pending', PRIMARY KEY (id));";
    $wpdb->query($sql);
}

function display_settings()
{
    global $wpdb;
    $result = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "Paypal_Settings LIMIT 1", ARRAY_A);
    if (empty($result)) {
?>
        <h2>Bestar Host Paypal Payment Settings</h2>
        <p>If you leave these fields empty, plugin will use default value</p>
        <div style="display: flex; margin-bottom: 15px;">
            <label for="clientid" style="width: 100px;">ClientID</label>
            <input style="width: 80%;" id="clientid" value="<?php echo PAYPAL_DEFAULT_CLIENT_ID ?>">
        </div>
        <div style="display: flex; margin-bottom: 15px;">
            <label for="appsceret" style="width: 100px;">App Sceret</label>
            <input style="width: 80%;" id="appsceret" value="<?php echo PAYPAL_DEFAULT_APP_SECRET ?>">
        </div>
        <div style="display: flex; margin-bottom: 15px;">
            <label for="currency" style="width: 100px;">Currency</label>
            <input style="width: 80%;" id="currency" value="<?php echo PAYPAL_DEFAULT_CURRENCY ?>">
        </div>
        <div style="display: flex; margin-bottom: 15px;">
            <label for="defaultamount" style="width: 100px;">Default Amount</label>
            <input style="width: 80%;" id="defaultamount" value="<?php echo PAYPAL_ORDERS_DEFAULT_AMOUNT ?>">
        </div>
        <div style="display: flex; margin-bottom: 15px;">
            <label for="environment" style="width: 100px;">Environment</label>
            <select style="width: max-content;" id="environment" name="environment">
                <option value="test" selected>Test</option>
                <option value="live">Live</option>
            </select>
        </div>
        <?php
    } else {
        foreach ($result as $row) {
            $client_id = isset($row['client_id']) ? $row['client_id'] : PAYPAL_DEFAULT_CLIENT_ID;
            $app_sceret = isset($row['app_sceret']) ? $row['app_sceret'] : PAYPAL_DEFAULT_APP_SECRET;
            $currency = isset($row['currency']) ? $row['currency'] : PAYPAL_DEFAULT_CURRENCY;
            $default_amount = isset($row['default_amount']) ? $row['default_amount'] : PAYPAL_ORDERS_DEFAULT_AMOUNT;
        ?>
            <h2>Bestar Host Paypal Payment Settings</h2>
            <p>If you leave these fields empty, plugin will use default value</p>
            <div style="display: flex; margin-bottom: 15px;">
                <label for="clientid" style="width: 100px;">ClientID</label>
                <input style="width: 80%;" id="clientid" value="<?php echo $client_id ?>">
            </div>
            <div style="display: flex; margin-bottom: 15px;">
                <label for="appsceret" style="width: 100px;">App Sceret</label>
                <input style="width: 80%;" id="appsceret" value="<?php echo $app_sceret ?>">
            </div>
            <div style="display: flex; margin-bottom: 15px;">
                <label for="currency" style="width: 100px;">Currency</label>
                <input style="width: 80%;" id="currency" value="<?php echo $currency ?>">
            </div>
            <div style="display: flex; margin-bottom: 15px;">
                <label for="defaultamount" style="width: 100px;">Default Amount</label>
                <input style="width: 80%;" id="defaultamount" value="<?php echo $default_amount ?>">
            </div>
            <div style="display: flex; margin-bottom: 15px;">
                <label for="environment" style="width: 100px;">Environment</label>
                <select style="width: max-content;" id="environment" name="environment">
                    <option value="test" <?php echo $row['environment'] == 'test' ? 'selected' : '' ?>>Test</option>
                    <option value="live" <?php echo $row['environment'] == 'live' ? 'selected' : '' ?>>Live</option>
                </select>
            </div>
    <?php }
    } ?>
    <button onclick="submit(this)" type="submit">Save</button>
    <div id="error-logging" class="error"></div>
    <script>
        function submit(event) {
            let client_id = document.getElementById("clientid").value === '' ? '<?php echo PAYPAL_DEFAULT_CLIENT_ID ?>' : document.getElementById("clientid").value;
            let app_sceret = document.getElementById("appsceret").value === '' ? '<?php echo PAYPAL_DEFAULT_APP_SECRET ?>' : document.getElementById("appsceret").value;
            let currency = document.getElementById("currency").value === '' ? '<?php echo PAYPAL_DEFAULT_CURRENCY ?>' : document.getElementById("currency").value;
            let default_amount = document.getElementById("defaultamount").value === '' ? '<?php echo PAYPAL_ORDERS_DEFAULT_AMOUNT ?>' : document.getElementById("defaultamount").value;
            let environment = document.getElementById("environment").value === '' ? '<?php echo PAYPAL_API_URL ?>' : document.getElementById("environment").value;
            fetch("/wp-content/plugins/bestarhost-paypal-payment/save-settings.php", {
                method: "POST",
                body: JSON.stringify({
                    client_id: client_id,
                    app_sceret: app_sceret,
                    currency: currency,
                    default_amount: default_amount,
                    environment: environment
                })
            }).then((response) => console.log(response));
        }
    </script>
<?php
}

function payment_shortcode_content()
{
    global $wpdb;
    $result = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "Paypal_Settings LIMIT 1", ARRAY_A);
    ob_start();
?>
    <div id="shortcode-content">
        <div style="display: flex; margin-bottom: 25px;">
            <div class="tablinks activeTab" onclick="changeTabs(this, 'pay')" style="margin-left: auto; margin-right: auto; cursor: pointer;">Pay</div>
            <div class="tablinks" onclick="changeTabs(this, 'request')" style="margin-left: auto; margin-right: auto; cursor: pointer;">Request pay from an
                Paypal id</div>
        </div>
        <?php
        if (empty($result)) {
        ?>
            <div style="display: flex; margin-bottom: 25px;">
                <label for="amount" style="margin-right: auto;"> Enter Amount (<?php echo PAYPAL_DEFAULT_CURRENCY ?>) </label>
                <input id="amount" type="text" name="amount" value="<?php echo PAYPAL_ORDERS_DEFAULT_AMOUNT ?>" style="margin-left: auto; margin-right: auto; border-radius: 7px; border: blue 1px solid;">
                <br />
            </div>
            <?php
        } else {
            foreach ($result as $row) {
            ?>
                <div style="display: flex; margin-bottom: 25px;">
                    <label for="amount" style="margin-right: auto;"> Enter Amount (<?php echo $row['currency'] ?>) </label>
                    <input id="amount" type="text" name="amount" value="<?php echo $row['default_amount'] ?>" style="margin-left: auto; margin-right: auto; border-radius: 7px; border: blue 1px solid;">
                    <br />
                </div>
        <?php
            }
        }
        ?>
        <div id="request" style="transition: all 1s linear; display: flex; flex-direction: column;" class="hidden visuallyhidden">
            <label for="paypal_email"> Paypal Email </label>
            <input id="paypal_email" type="email" name="paypal_email" style="margin-bottom: 15px; border: blue 1px solid; border-radius: 7px;" placeholder="Paypal Email">
            <label for="email_subject"> Email Subject </label>
            <input id="email_subject" type="text" name="email_subject" style="margin-bottom: 15px; border: blue 1px solid; border-radius: 7px;" placeholder="Email Subject">
            <label for="email_body"> Email Body (Can be formated in HTML)</label>
            <p>You can use: [amount], [currency], [request_url] to add the entered amount into email body</p>
            <textarea id="email_body" name="email_body" style="margin-bottom: 15px; border: blue 1px solid; border-radius: 7px; height: 300px;">Amount: [amount]
    Currency: [currency]
    Request link: <a href="[request_url]">link</a></textarea>
            <button style="width: fit-content; padding: 5px 25px; margin-left: auto; margin-right: auto; border-radius: 7px; border: blue 1px solid; background-color: white;" onclick="submitRequest(this)">Submit</button>
            <script>
                function submitRequest(event) {
                    let paypal_email = document.getElementById('paypal_email').value;
                    let email_subject = document.getElementById('email_subject').value;
                    let email_body = document.getElementById('email_body').value;

                    fetch("/wp-content/plugins/bestarhost-paypal-payment/submit-request.php", {
                            method: "POST",
                            body: JSON.stringify({
                                paypal_email: paypal_email,
                                email_subject: email_subject,
                                email_body: email_body,
                                amount: document.getElementById('amount').value
                            }),
                        })
                        .then((response) => response.json())
                        .then((response) => {
                            if (response.error) {
                                alert("There is an error, please try again later!");
                            } else {
                                var element = document.getElementById("shortcode-content");
                                const h3 = document.createElement("H3");
                                h3.innerText = "The recipient will soon receive the email and access through the link sent to!";
                                element.replaceWith(h3);
                            }
                        });
                }
            </script>
        </div>
        <div id="pay" style="transition: all 1s linear;">
            <div style="display: flex; margin-bottom: 25px;">
                <div>
                    <input style="margin-right: 5px;" name="payType" type="radio" onclick="changePay(this, 'admin')" checked>Pay to Admin</input>
                </div>
                <div style="margin-left: auto; margin-right: auto;">
                    <input style="margin-right: 5px;" name="payType" type="radio" onclick="changePay(this, 'custom')">Pay to Paypal ID</input>
                </div>
                <br />
            </div>
            <div id="admin" style="transition: all 1s linear;">
                <div id="paypal-button-container-admin"></div>
                <script src="https://www.paypal.com/sdk/js?client-id=ATUWdT_p5Y2HvkD-wft1RsC7U143RtSbOQf2gutNiCCzglFI7_lNgyJ2S26fdfU2aAUhHTP1wpA5Y46c&currency=USD&components=buttons&locale=en_US"></script>
                <script>
                    paypal
                        .Buttons({
                            createOrder: function(data, actions) {
                                return fetch("<?php echo admin_url('admin-ajax.php'); ?>?action=create_orders&amount=" + document.getElementById('amount').value, {
                                        method: "get"
                                    })
                                    .then((response) => response.json())
                                    .then((order) => order.id);
                            },
                            onApprove: async function(data, actions) {
                                return fetch("<?php echo admin_url('admin-ajax.php'); ?>?action=capture_payment&orderID=" + data.orderID, {
                                        method: "get"
                                    })
                                    .then((response) => response.json())
                                    .then((orderData) => {
                                        if (orderData.error) {
                                            alert("There is an error, please try again later!");
                                        } else {
                                            if (orderData.status.toUpperCase() === 'COMPLETED') {
                                                var element = document.getElementById("shortcode-content");
                                                const h3 = document.createElement("H3");
                                                h3.innerText = "Thank you for your payment!";
                                                element.replaceWith(h3);
                                            } else if (orderData.status.toUpperCase() === 'VOIDED') {
                                                var element = document.getElementById("shortcode-content");
                                                const h3 = document.createElement("H3");
                                                h3.innerText = "Your payment has been cancelled!";
                                                element.replaceWith(h3);
                                            } else {
                                                var element = document.getElementById("shortcode-content");
                                                const h3 = document.createElement("H3");
                                                h3.innerText = "Your payment is being processed!";
                                                element.replaceWith(h3);
                                            }
                                        }
                                    });
                            },
                        })
                        .render("#paypal-button-container-admin");
                </script>
            </div>
            <div id="custom" style="transition: all 1s linear;" class="hidden visuallyhidden">
                <label for="amount" style="margin-right: auto; margin-bottom: 7px;">Receiver Email</label>
                <br />
                <input id="requester" placeholder="Receiver Email" type="text" name="requester" style="margin-left: auto; margin-right: auto; border-radius: 7px; border: blue 1px solid; margin-bottom: 25px; width: 100%;">
                <br />
                <button style="width: fit-content; padding: 5px 25px; margin-left: auto; margin-right: auto; border-radius: 7px; border: blue 1px solid; background-color: white;" onclick="submitPayout(this)">Submit</button>
                <script src="https://www.paypal.com/sdk/js?client-id=ATUWdT_p5Y2HvkD-wft1RsC7U143RtSbOQf2gutNiCCzglFI7_lNgyJ2S26fdfU2aAUhHTP1wpA5Y46c&currency=USD&components=buttons&locale=en_US"></script>
                <script>
                    function submitPayout(event) {
                        let div = document.createElement('div');
                        div.id = 'paypal-button-container-custom';
                        event.replaceWith(div);

                        paypal
                            .Buttons({
                                createOrder: function(data, actions) {
                                    return fetch("<?php echo admin_url('admin-ajax.php'); ?>?action=create_orders&amount=" + document.getElementById('amount').value, {
                                            method: "get"
                                        })
                                        .then((response) => response.json())
                                        .then((order) => order.id);
                                },
                                onApprove: async function(data, actions) {
                                    return fetch("<?php echo admin_url('admin-ajax.php'); ?>?action=capture_payment&orderID=" + data.orderID, {
                                            method: "get"
                                        })
                                        .then((response) => response.json())
                                        .then((orderData) => {
                                            if (orderData.error) {
                                                alert("There is an error, please try again later!");
                                            } else {
                                                if (orderData.status.toUpperCase() === 'COMPLETED') {
                                                    fetch("<?php echo admin_url('admin-ajax.php'); ?>?action=create_payouts&requester=" + document.getElementById('requester').value + "&amount=" + orderData.purchase_units[0].payments.captures[0].seller_receivable_breakdown.net_amount.value, {
                                                            method: "get"
                                                        })
                                                        .then((response) => response.json())
                                                        .then((orderData) => {
                                                            if (orderData.error) {
                                                                alert("There is an error, please try again later!");
                                                            } else {
                                                                if (orderData.batch_header.batch_status.toUpperCase() === "PENDING") {
                                                                    var element = document.getElementById("shortcode-content");
                                                                    const h3 = document.createElement("H3");
                                                                    h3.innerText = "The Payment has been sent. Waiting for process. You can track payment with ID: '" + orderData.batch_header.payout_batch_id + "'!";
                                                                    element.replaceWith(h3);
                                                                }
                                                            }
                                                        });
                                                    var element = document.getElementById("shortcode-content");
                                                    const h3 = document.createElement("H3");
                                                    h3.innerText = "Thank you for your payment!";
                                                    element.replaceWith(h3);
                                                } else if (orderData.status.toUpperCase() === 'VOIDED') {
                                                    var element = document.getElementById("shortcode-content");
                                                    const h3 = document.createElement("H3");
                                                    h3.innerText = "Your payment has been cancelled!";
                                                    element.replaceWith(h3);
                                                } else {
                                                    var element = document.getElementById("shortcode-content");
                                                    const h3 = document.createElement("H3");
                                                    h3.innerText = "Your payment is being processed!";
                                                    element.replaceWith(h3);
                                                }
                                            }
                                        });
                                },
                            })
                            .render("#paypal-button-container-custom");
                    }
                </script>
            </div>
        </div>
        <div id="error-logging"></div>
        <style>
            .visuallyhidden {
                opacity: 0;
            }

            .hidden {
                display: none !important;
            }

            .activeTab {
                border-bottom: blue 1px solid;
            }
        </style>
        <script>
            function changeTabs(event, tab) {
                const tabs = document.getElementsByClassName('tablinks');
                tabs.forEach(function(tab) {
                    tab.classList.contains('activeTab');
                    tab.classList.remove('activeTab');
                })
                event.classList.add('activeTab');

                let collapseTab = tab === 'pay' ? 'request' : 'pay'

                if (document.getElementById(tab).classList.contains('hidden')) {
                    document.getElementById(tab).classList.remove('hidden');
                    setTimeout(function() {
                        document.getElementById(tab).classList.remove('visuallyhidden');
                    }, 20);
                }

                if (!document.getElementById(collapseTab).classList.contains('hidden')) {
                    document.getElementById(collapseTab).classList.add('visuallyhidden');
                    document.getElementById(collapseTab).addEventListener('transitionend', function(e) {
                        document.getElementById(collapseTab).classList.add('hidden');
                    }, {
                        capture: false,
                        once: true,
                        passive: false
                    });
                }
            }


            function changePay(event, type) {
                let collapseType = type === 'admin' ? 'custom' : 'admin'

                if (document.getElementById(type).classList.contains('hidden')) {
                    document.getElementById(type).classList.remove('hidden');
                    setTimeout(function() {
                        document.getElementById(type).classList.remove('visuallyhidden');
                    }, 20);
                }

                if (!document.getElementById(collapseType).classList.contains('hidden')) {
                    document.getElementById(collapseType).classList.add('visuallyhidden');
                    document.getElementById(collapseType).addEventListener('transitionend', function(e) {
                        document.getElementById(collapseType).classList.add('hidden');
                    }, {
                        capture: false,
                        once: true,
                        passive: false
                    });
                }
            }
        </script>
    </div>
    <?php
    $output = ob_get_contents();
    ob_clean();
    print $output;
}

function request_shortcode_content()
{
    global $wpdb;
    $result = $wpdb->get_results("SELECT * FROM " . $wpdb->prefix . "Paypal_Request WHERE request_id='" . $_GET['request_id'] . "' LIMIT 1", ARRAY_A);
    ob_start();
    if (!empty($result)) {
        foreach ($result as $row) {
            if ($row['status'] == 'pending') {
    ?>
                <input type="hidden" id="amount" value="<?php echo $row['amount']; ?>">
                <input type="hidden" id="currency" value="<?php echo $row['currency']; ?>">
                <input type="hidden" id="request_id" value="<?php echo $_GET['request_id'] ?>">
                <div id="paypal-button-container"></div>
                <script src="https://www.paypal.com/sdk/js?client-id=ATUWdT_p5Y2HvkD-wft1RsC7U143RtSbOQf2gutNiCCzglFI7_lNgyJ2S26fdfU2aAUhHTP1wpA5Y46c&currency=USD&components=buttons&locale=en_US"></script>
                <script>
                    paypal
                        .Buttons({
                            createOrder: function(data, actions) {
                                return fetch("<?php echo admin_url('admin-ajax.php'); ?>?action=create_orders&amount=" + document.getElementById('amount').value + "&currency=" + document.getElementById('currency').value, {
                                        method: "get"
                                    })
                                    .then((response) => response.json())
                                    .then((order) => order.id);
                            },
                            onApprove: async function(data, actions) {
                                return fetch("<?php echo admin_url('admin-ajax.php'); ?>?action=capture_payment&orderID=" + data.orderID, {
                                        method: "get"
                                    })
                                    .then((response) => response.json())
                                    .then((orderData) => {
                                        if (orderData.error) {
                                            alert("There is an error, please try again later!");
                                        } else {
                                            fetch("<?php echo admin_url('admin-ajax.php'); ?>?action=update_request_status&request_id=" + document.getElementById('request_id').value + "&status=" + orderData.status.toLowerCase(), {
                                                    method: "get"
                                                })
                                                .then((response) => {
                                                    if (orderData.status.toUpperCase() === 'COMPLETED') {
                                                        var element = document.getElementById("paypal-button-container");
                                                        const h3 = document.createElement("H3");
                                                        h3.innerText = "Thank you for your payment!";
                                                        element.replaceWith(h3);
                                                    } else if (orderData.status.toUpperCase() === 'VOIDED') {
                                                        var element = document.getElementById("paypal-button-container");
                                                        const h3 = document.createElement("H3");
                                                        h3.innerText = "Your payment has been cancelled!";
                                                        element.replaceWith(h3);
                                                    } else {
                                                        var element = document.getElementById("paypal-button-container");
                                                        const h3 = document.createElement("H3");
                                                        h3.innerText = "Your payment is being processed!";
                                                        element.replaceWith(h3);
                                                    }
                                                })
                                        }
                                    });
                            },
                        })
                        .render("#paypal-button-container");
                </script>
            <?php
            } else if ($row['status'] == 'voided') {
            ?>
                <h3>Payment with request id: <?php echo $_GET['request_id']; ?> has been cancelled</h3>
            <?php
            } else if ($row['status'] == 'completed') {
            ?>
                <h3>Payment with request id: <?php echo $_GET['request_id']; ?> has been completed</h3>
            <?php
            } else {
            ?>
                <h3>Payment with request id: <?php echo $_GET['request_id']; ?> is being processed</h3>
        <?php
            }
        }
    } else {
        ?>
        <h3>No payment with request id: <?php echo $_GET['request_id']; ?></h3>
<?php
    }
    $output = ob_get_contents();
    ob_clean();
    print $output;
}
