<?php
/**
 * XY Alipay Callback Handler - Standard Base
 */

// 1. 初始化 WHMCS 环境
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

$gatewayModuleName = "xy_alipay";
$GATEWAY = getGatewayVariables($gatewayModuleName);
if (!$GATEWAY["type"]) die("Module Not Activated");

// 2. 清洗数据（重点：还原被 WHMCS 转义的字符）
$postData = [];
foreach ($_POST as $key => $value) {
    $postData[$key] = html_entity_decode(stripslashes($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

if (empty($postData) || !isset($postData['sign'])) {
    die("No Valid Data Received");
}

$sign = $postData['sign'];

// 3. 构建待验签字符串
unset($postData['sign'], $postData['sign_type']);
ksort($postData);

$signSourceData = "";
foreach ($postData as $key => $value) {
    if ($value !== "" && !is_null($value)) {
        $signSourceData .= ($signSourceData === "" ? "" : "&") . $key . "=" . $value;
    }
}

// 4. 获取公钥并执行验证
$pubKey = trim($GATEWAY['alipayPublicKey']);
if (strpos($pubKey, '-----BEGIN PUBLIC KEY-----') === false) {
    $pubKey = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($pubKey, 64, "\n", true) . "\n-----END PUBLIC KEY-----";
}

$res = openssl_get_publickey($pubKey);
$isVerified = openssl_verify($signSourceData, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);

if ($isVerified === 1) {
    // 5. 业务逻辑处理
    $outTradeNo = $postData['out_trade_no'];
    $invoiceId  = explode('T', $outTradeNo)[0];
    $transId    = $postData['trade_no'];
    $amount     = $postData['total_amount'];

    if ($postData['trade_status'] == 'TRADE_SUCCESS' || $postData['trade_status'] == 'TRADE_FINISHED') {
        
        $invoiceId = checkCbInvoiceID($invoiceId, $GATEWAY["name"]);
        checkCbTransID($transId); // 防止重复入账

        // 获取账单原始金额进行比对
        $invoiceData = \WHMCS\Database\Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
        if ($invoiceData && abs((float)$invoiceData->total - (float)$amount) < 0.01) {
            
            // 写入支付记录
            addInvoicePayment($invoiceId, $transId, $amount, 0, $gatewayModuleName);
            logTransaction($GATEWAY["name"], $_POST, "Payment Successful");
            
            echo "success"; // 必须输出 success 给支付宝
            exit;
        } else {
            logTransaction($GATEWAY["name"], $_POST, "Amount Mismatch Error");
        }
    }
} else {
    // 验签失败记录
    logTransaction($GATEWAY["name"], ["Data" => $signSourceData, "POST" => $_POST], "Signature Verification Failed");
}

echo "fail";