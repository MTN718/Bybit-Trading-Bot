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

    //Insert Database

    $info = json_decode($response);

    $info->result->orderId;
}

function endsWith($haystack, $needle)
{
    $length = strlen($needle);
    if (!$length) {
        return true;
    }
    return substr($haystack, -$length) === $needle;
}

function createBuyOrder($symbol, $price, $qtyStep, $highPercent, $isTPL = true)
{
    global $leverage;
    if (!endsWith(strtolower($symbol), 'usdt') || $qtyStep == 0) {
        return;
    }

    $hour = date("H");
    $balance = getBalance();
    $amount = $balance / 100;
    $profitPercent = min($highPercent / 2 * 100, 6);

    //$leverage = 5;
    $endpoint = "/v5/order/create";
    $method = "POST";
    $profiltPrice = $price + $price / 100 * $profitPercent;

    $qty = intval($amount * $leverage / $price / $qtyStep);
    $qty = $qty * $qtyStep;
    // if ((int) $hour < 6 || (int) $hour >= 21) {
    //     //$slPrice = $price - $price / 100 * $profitPercent;
    //     $slPrice = $price - $price / 100 * 20;
    //     $params = '{"category":"linear","symbol": "' . $symbol . '","side": "Buy", "isLeverage": "1", "orderType": "Market","qty": "' . $qty . '", "takeProfit": "' . $profiltPrice . '", "stopLoss": "' . $slPrice . '"}';
    // } else {        
    //     $params = '{"category":"linear","symbol": "' . $symbol . '","side": "Buy", "isLeverage": "1", "orderType": "Market","qty": "' . $qty . '", "takeProfit": "' . $profiltPrice . '"}';
    // }

    $params = '{"category":"linear","symbol": "' . $symbol . '","side": "Buy", "isLeverage": "1", "orderType": "Market","qty": "' . $qty . '", "takeProfit": "' . $profiltPrice . '"}';

    $log = "Creating Buy Order: Symbol: " . $symbol . "--- Price: " . $price . "--- Qty: " . $qty . "--- Profit: " . $profitPercent . "%" . "---" . "Amount: " . $amount . "$" . PHP_EOL;
    file_put_contents('./logs/log_' . date("j.n.Y") . '.log', $log, FILE_APPEND);

    http_req("$endpoint", "$method", "$params", $symbol);

}
function createOrder($symbol, $price, $qtyStep, $highPercent, $isTPL = true)
{
    global $leverage;
    if (!endsWith(strtolower($symbol), 'usdt') || $qtyStep == 0) {
        return;
    }
    $hour = date("H");
    if ($isTPL) {
        // if ((int) $hour < 6 || (int) $hour >= 21) {
        //     return;
        // }
    }
    $balance = getBalance();
    $amount = $balance / 40;
    if ($isTPL == false) {
        $amount = $balance / 100;
    }

    $profitPercent = min($highPercent / 6 * 100, 6);
    $slPercent = 20 - $profitPercent;
    //$leverage = 2.5;
    $endpoint = "/v5/order/create";
    $method = "POST";
    $profiltPrice = $price - $price / 100 * $profitPercent;

    $qty = intval($amount * $leverage / $price / $qtyStep);
    $qty = $qty * $qtyStep;
    //	if ($qty == 0) {
//		$qty = number_format((float) (0.8 * 10 / $price), 3, '.', '');
//	}
    $slPrice = $price + $price / 100 * $slPercent;
    // if ((int) $hour < 6 || (int) $hour >= 21) {
    //     $params = '{"category":"linear","symbol": "' . $symbol . '","side": "Sell", "isLeverage": "1", "orderType": "Market","qty": "' . $qty . '", "takeProfit": "' . $profiltPrice . '", "stopLoss": "' . $slPrice . '"}';
    // }
    // else {
    //     $params = '{"category":"linear","symbol": "' . $symbol . '","side": "Sell", "isLeverage": "1", "orderType": "Market","qty": "' . $qty . '", "takeProfit": "' . $profiltPrice . '"}';
    // }

    $params = '{"category":"linear","symbol": "' . $symbol . '","side": "Sell", "isLeverage": "1", "orderType": "Market","qty": "' . $qty . '", "takeProfit": "' . $profiltPrice . '"}';
    
    if ($isTPL == false) {
        $profiltPrice = $price - $price / 100 * $profitPercent * 6 / 4;
        // if ((int) $hour < 6 || (int) $hour >= 21) {
        //     // $slPrice = $price + $price / 100 * $profitPercent * 6 / 4;
        //     $slPrice = $price + $price / 100 * 20;
        //     $params = '{"category":"linear","symbol": "' . $symbol . '","side": "Sell", "isLeverage": "1", "orderType": "Market","qty": "' . $qty . '", "takeProfit": "' . $profiltPrice . '", "stopLoss": "' . $slPrice . '"}';
        // } else {
        //     $params = '{"category":"linear","symbol": "' . $symbol . '","side": "Sell", "isLeverage": "1", "orderType": "Market","qty": "' . $qty . '", "takeProfit": "' . $profiltPrice . '"}';
        // }
        $params = '{"category":"linear","symbol": "' . $symbol . '","side": "Sell", "isLeverage": "1", "orderType": "Market","qty": "' . $qty . '", "takeProfit": "' . $profiltPrice . '"}';
    }

    $log = "Creating Order: Symbol: " . $symbol . "--- Price: " . $price . "--- Qty: " . $qty . "--- Profit: " . $profitPercent . "%" . "---" . "Amount: " . $amount . "$" . PHP_EOL;
    file_put_contents('./logs/log_' . date("j.n.Y") . '.log', $log, FILE_APPEND);

    http_req("$endpoint", "$method", "$params", $symbol);

}


function updatePrice($data, $price, $percent)
{
    $dbUser = "";
    $dbPassword = "";
    $dbName = "";

    $con = mysqli_connect("localhost", "$dbUser", "$dbPassword");
    mysqli_select_db($con, "$dbName");
    mysqli_set_charset($con, "utf8");

    $sql = "UPDATE bb_coins
            SET perc1 = '" . $data["perc2"] . "', perc2 ='" . $data["perc3"] . "', perc3='" . $price . "', updated_at=now(), pt_24='" . $percent . "', is_created=0
        WHERE symbol='" . $data["symbol"] . "'";
    $result = mysqli_query($con, $sql);
}
;


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
    $limitPercent = 7;

    $openedOrders = getOrders();

    foreach ($json->result->list as $item) {
        $symbol = $item->symbol;
        $price = $item->lastPrice;
        $percent = $item->price24hPcnt;

        if (array_key_exists($symbol, $coinDatas)) {
            if ($coinDatas[$symbol]["perc1"] == 0 || $coinDatas[$symbol]["perc2"] == 0 || $coinDatas[$symbol]["perc3"] == 0) {
                updatePrice($coinDatas[$symbol], $price, $percent);
            } else {
                // if ($coinDatas[$symbol]["pt_24"] < 0.2 && $percent > 0.2) {
                //     $isSendTelegram = true;
                //     $ptFormat = number_format((float) ($percent * 100), 2, '.', '');
                //     $msg = $msg . "\xF0\x9F\x9A\x80 " . $symbol . " over " . $ptFormat . "% in 24 hours" . "\n";
                // }
                $candlePerc1 = ($price / $coinDatas[$symbol]["perc3"]) - 1;
                $candlePerc2 = ($coinDatas[$symbol]["perc3"] / $coinDatas[$symbol]["perc2"]) - 1;
                $candlePerc3 = ($coinDatas[$symbol]["perc2"] / $coinDatas[$symbol]["perc1"]) - 1;

                $tPercent = $candlePerc1 + $candlePerc2 + $candlePerc3;
                $tPercent = number_format((float) ($tPercent * 100), 2, '.', '');
                $tPercent1 = number_format((float) (($candlePerc1 + $candlePerc2) * 100), 2, '.', '');

                // if ($candlePerc1 > 0.05) {
                //     $ptFormat = number_format((float) ($candlePerc1 * 100), 2, '.', '');
                //     $msg = $msg . "\xE2\x8F\xB0 " . $symbol . " is rising " . $ptFormat . "% in 15mins rapidly  " . "\xF0\x9F\x9A\x80" . "\n";
                // }

                if (
                    (
                        $candlePerc1 > 0 && $candlePerc2 > 0 && $candlePerc3 > 0 && $tPercent > $limitPercent &&
                        $coinDatas[$symbol]["pt_24"] > 0.20
                    )
                ) {
                    if (array_key_exists($symbol, $openedOrders)) {
                        $msg = $msg . "🚧" . $symbol . " rised " . $tPercent . "% in 45mins\n";
                    }
                    else {
                        createOrder($symbol, $price, $coinDatas[$symbol]["qty_step"], $percent);
                        $msg = $msg . "⛳" . $symbol . " rised " . $tPercent . "% in 45mins\n";
                    }                    
                    $isSendTelegram = true;
                    updatePrice($coinDatas[$symbol], $price, $percent);
                } else if (
                    (
                        $candlePerc1 > 0 && $candlePerc2 > 0 && $candlePerc3 > 0 
                        // && $tPercent > ($limitPercent * 1.5) 
                        && $tPercent > $limitPercent                        
                        && $candlePerc1 > 0.03
                    )
                ) {
                    if (array_key_exists($symbol, $openedOrders)) {
                        $msg = $msg . "🚧" . $symbol . " rised " . $tPercent . "% in 45mins rapidly\n";
                    }
                    else {
                        createOrder($symbol, $price, $coinDatas[$symbol]["qty_step"], $candlePerc1  / 2 * 4, false);
                        $msg = $msg . "⛳" . $symbol . " rised " . $tPercent . "% in 45mins rapidly\n";
                    }                    
                    $isSendTelegram = true;                    
                    updatePrice($coinDatas[$symbol], $price, $percent);
                } else if (
                    $candlePerc1 > 0 && $candlePerc2 > 0 &&
                    $tPercent1 > $limitPercent * 1.5 &&
                    $coinDatas[$symbol]["pt_24"] > 0.20
                ) {
                    if (array_key_exists($symbol, $openedOrders)) {
                        $msg = $msg . "🚧" . $symbol . " rised " . $tPercent1 . "% in 30mins\n";
                    }
                    else {
                        createOrder($symbol, $price, $coinDatas[$symbol]["qty_step"], $percent);
                        $msg = $msg . "⛳" . $symbol . " rised " . $tPercent1 . "% in 30mins\n";
                    }                    
                    $isSendTelegram = true;
                    updatePrice($coinDatas[$symbol], $price, $percent);
                } else if (
                    (
                        $candlePerc1 < 0 && $candlePerc2 < 0 && $candlePerc3 < 0 
                        && abs($candlePerc1) > 0.03
                    )
                ) {
                    if (array_key_exists($symbol, $openedOrders)) {
                        $msg = $msg . "🚧" . $symbol . " falling " . $tPercent . "% in 45mins rapidly\n";
                    }
                    else {
                        createBuyOrder($symbol, $price, $coinDatas[$symbol]["qty_step"], abs($candlePerc1), false);
                        $msg = $msg . "⛳" . $symbol . " falling " . $tPercent . "% in 45mins rapidly\n";
                    }
                    $isSendTelegram = true;                    
                    updatePrice($coinDatas[$symbol], $price, $percent);
                } else {
                    updatePrice($coinDatas[$symbol], $price, $percent);
                }
            }
        } else {
            //Insert New
            $sql = "INSERT INTO bb_coins (symbol, perc1, perc2, perc3) VALUES ('" . $symbol . "', '0', '0', '" . $price . "')";
            $result = mysqli_query($con, $sql);
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