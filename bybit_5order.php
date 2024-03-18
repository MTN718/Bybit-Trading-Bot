<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$maxDailyUpdated = 4;


$api_key = ''; # Input your API Key
$secret_key = ''; # Input your Secret Key
$url = "https://api.bybit.com";
$curl = curl_init();
$leverage = 2.5;

function http_req($endpoint, $method, $params, $symbol)
{
    global $api_key, $secret_key, $url, $curl;
    $timestamp = time() * 1000;
    $params_for_signature = $timestamp . $api_key . "5000" . $params;
    $signature = hash_hmac('sha256', $params_for_signature, $secret_key);
    if ($method == "GET") {
        $endpoint = $endpoint . "?" . $params;
    }
    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => $url . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_HTTPHEADER => array(
                "X-BAPI-API-KEY: $api_key",
                "X-BAPI-SIGN: $signature",
                "X-BAPI-SIGN-TYPE: 2",
                "X-BAPI-TIMESTAMP: $timestamp",
                "X-BAPI-RECV-WINDOW: 5000",
                "Content-Type: application/json"
            ),
        )
    );
    if ($method == "GET") {
        curl_setopt($curl, CURLOPT_HTTPGET, true);
    }
    //echo $Info . "\n";
    $response = curl_exec($curl);

    $log = "Response: " . $response . PHP_EOL;
    file_put_contents('./logs/log_' . date("j.n.Y") . '.log', $log, FILE_APPEND);

}

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if (!$length) {
        return true;
    }
    return substr($haystack, -$length) === $needle;
}

function createOrder($symbol, $price, $qtyStep)
{
    global $leverage;
    if (!endsWith(strtolower($symbol), 'usdt') || $qtyStep == 0) {
        return;
    }

    $balance = getBalance();
    $amount = $balance / 200;

    $profitPercent = 2;
    $slPercent = 2;
    $endpoint = "/v5/order/create";
    $method = "POST";
    $profiltPrice = $price + $price / 100 * $profitPercent;
    $slPrice = $price - $price / 100 * $slPercent;

    $qty = intval($amount * $leverage / $price / $qtyStep);
    $qty = $qty * $qtyStep;

    $params = '{"category":"linear","price": "' . $price . '", "triggerDirection": "1", "symbol": "' . $symbol . '","side": "Buy", "isLeverage": "1", "orderType": "Limit","qty": "' . $qty . '", "takeProfit": "' . $profiltPrice . '", "stopLoss": "' . $slPrice . '"}';

    $log = "Creating Buy Order: Symbol: " . $symbol . "--- Price: " . $price . "--- Qty: " . $qty . "--- Profit: " . $profitPercent . "%" . "---" . "Amount: " . $amount . "$" . PHP_EOL;
    file_put_contents('./logs/log_' . date("j.n.Y") . '.log', $log, FILE_APPEND);

    http_req("$endpoint", "$method", "$params", $symbol);

}


function updatePrice($data, $price, $isCreated = 0)
{
    $dbUser = "";
    $dbPassword = "";
    $dbName = "";

    $con = mysqli_connect("localhost", "$dbUser", "$dbPassword");
    mysqli_select_db($con, "$dbName");
    mysqli_set_charset($con, "utf8");

    if ($isCreated == 0) {
        $sql = "UPDATE bb_coins
    SET m1_price = '" . $data["m2_price"] . "', m2_price ='" . $data["m3_price"] . "', m3_price='" . $data["m4_price"] . "', m4_price='" . $price . "' WHERE symbol='" . $data["symbol"] . "'";
    } else {
        $sql = "UPDATE bb_coins
    SET m1_price = '" . $data["m2_price"] . "', m2_price ='" . $data["m3_price"] . "', m3_price='" . $data["m4_price"] . "', m4_price='" . $price . "', is_created='" . $isCreated . "' WHERE symbol='" . $data["symbol"] . "'";
    }
    $result = mysqli_query($con, $sql);
}


function sendTelegram($msg)
{
    //Check Time
    $hour = date("H");
    $weekOfday = date("l");
    if ((int) $hour < 6 || (int) $hour >= 22) {
        return;
    }
    if ($weekOfday == "Saturday") {
        return;
    }

    // $msg = $msg . "\n" . "     🤝🤝";
    $msg = $msg . "\n";

    $apiToken = "";

    $data = [
        "chat_id" => "",
        "text" => $msg
    ];


    $ch1 = curl_init();
    $url = "http://api.telegram.org/bot$apiToken/sendMessage?" . http_build_query($data);
    curl_setopt($ch1, CURLOPT_AUTOREFERER, TRUE);
    curl_setopt($ch1, CURLOPT_HEADER, 0);
    curl_setopt($ch1, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch1, CURLOPT_URL, $url);
    curl_setopt($ch1, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.1.2) Gecko/20090729 Firefox/3.5.2 GTB5');
    curl_setopt($ch1, CURLOPT_RETURNTRANSFER, 1);
    $response = json_decode(curl_exec($ch1));


    //$response = file_get_contents("http://api.telegram.org/bot$apiToken/sendMessage?" . http_build_query($data) ); 
}
;

function reqBalance($endpoint, $method, $params, $Info)
{
    global $api_key, $secret_key, $url, $curl;
    $timestamp = time() * 1000;
    $params_for_signature = $timestamp . $api_key . "5000" . $params;
    $signature = hash_hmac('sha256', $params_for_signature, $secret_key);
    if ($method == "GET") {
        $endpoint = $endpoint . "?" . $params;
    }
    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => $url . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_HTTPHEADER => array(
                "X-BAPI-API-KEY: $api_key",
                "X-BAPI-SIGN: $signature",
                "X-BAPI-SIGN-TYPE: 2",
                "X-BAPI-TIMESTAMP: $timestamp",
                "X-BAPI-RECV-WINDOW: 5000",
                "Content-Type: application/json"
            ),
        )
    );
    if ($method == "GET") {
        curl_setopt($curl, CURLOPT_HTTPGET, true);
    }

    $response = curl_exec($curl);

    $data = json_decode($response);
    return $data->result->list[0]->totalWalletBalance;
}

function getBalance()
{
    $endpoint = "/v5/account/wallet-balance";
    $method = "GET";
    $params = "accountType=UNIFIED&coin=USDT";
    return reqBalance("$endpoint", "$method", "$params", "Wallet");
}

function reqOrder($endpoint, $method, $params, $Info)
{
    global $api_key, $secret_key, $url, $curl;
    $timestamp = time() * 1000;
    $params_for_signature = $timestamp . $api_key . "5000" . $params;
    $signature = hash_hmac('sha256', $params_for_signature, $secret_key);
    if ($method == "GET") {
        $endpoint = $endpoint . "?" . $params;
    }
    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => $url . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_HTTPHEADER => array(
                "X-BAPI-API-KEY: $api_key",
                "X-BAPI-SIGN: $signature",
                "X-BAPI-SIGN-TYPE: 2",
                "X-BAPI-TIMESTAMP: $timestamp",
                "X-BAPI-RECV-WINDOW: 5000",
                "Content-Type: application/json"
            ),
        )
    );
    if ($method == "GET") {
        curl_setopt($curl, CURLOPT_HTTPGET, true);
    }
    //echo $Info . "\n";
    $response = curl_exec($curl);
    $data = json_decode($response);
    $retData = array();
    foreach ($data->result->list as $item) {
        $retData[$item->symbol] = $item->triggerPrice;
    }
    return $retData;
}

function getOrders()
{
    $endpoint = "/v5/order/realtime";
    $method = "GET";
    $params = "category=linear&settleCoin=USDT";
    return reqOrder($endpoint, $method, $params, "List Order");
}

function getPrices()
{

    $dbUser = "";
    $dbPassword = "";
    $dbName = "";

    $con = mysqli_connect("localhost", "$dbUser", "$dbPassword");
    mysqli_select_db($con, "$dbName");
    mysqli_set_charset($con, "utf8");


    $url = "https://api.bybit.com/derivatives/v3/public/tickers";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.1.2) Gecko/20090729 Firefox/3.5.2 GTB5');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $json = json_decode(curl_exec($ch));


    //echo "<pre>";
    //print_r($json);

    $selectSQL = "SELECT * from bb_coins";
    $coinResult = mysqli_query($con, $selectSQL);
    $coinDatas = array();
    while ($content = mysqli_fetch_array($coinResult)) {
        $coinDatas[$content["symbol"]] = $content;
    }

    $msg = "";
    $isSendTelegram = false;
    $openedOrders = getOrders();

    foreach ($json->result->list as $item) {
        $symbol = $item->symbol;
        $price = $item->lastPrice;
        $isCreated = 0;
        if (array_key_exists($symbol, $coinDatas)) {
            if ($coinDatas[$symbol]["m1_price"] == 0) {
                updatePrice($coinDatas[$symbol], $price);
            } else {
                $candlePerc1 = ($coinDatas[$symbol]["perc2"] / $coinDatas[$symbol]["perc1"]) - 1;
                $candlePerc2 = ($coinDatas[$symbol]["perc3"] / $coinDatas[$symbol]["perc2"]) - 1;
                $c1 = ($price / $coinDatas[$symbol]["m1_price"]) - 1;
                $c2 = ($price / $coinDatas[$symbol]["m2_price"]) - 1;
                $c3 = ($price / $coinDatas[$symbol]["m3_price"]) - 1;
                $c4 = ($price / $coinDatas[$symbol]["m4_price"]) - 1;
                if (
                    $coinDatas[$symbol]["is_created"] == 0 && $candlePerc2 < 0 && $candlePerc1 < 0 &&
                    ($c1 > 0.04 || $c2 > 0.04 || $c3 > 0.04 || $c4 > 0.04)
                ) {
                    //Create Order
                    if (array_key_exists($symbol, $openedOrders)) {
                        $msg = $msg . "🚧" . $symbol . " position already created \n";    
                    }
                    else {
                        $msg = $msg . "⛳" . $symbol . " position created \n";
                    }
                    createOrder($symbol, $price, $coinDatas[$symbol]["qty_step"]);
                    $isSendTelegram = true;
                    $isCreated = 1;
                }
                updatePrice($coinDatas[$symbol], $price, $isCreated);
            }
        }
    }

    if ($isSendTelegram == true) {
        sendTelegram($msg);
    }

}
;

getPrices();

//createOrder("JASMYUSDT", 0.01116, 1);




?>