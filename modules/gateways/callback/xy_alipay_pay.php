<?php
/**
 * XY Alipay - 终极智能路由收银台 V6.0
 * 逻辑：根据后台配置与设备环境自动切换 PagePay / F2F 渲染模式
 */
require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';

$invoiceId = isset($_REQUEST['invoiceid']) ? (int)$_REQUEST['invoiceid'] : 0;
if ($invoiceId <= 0) die("Access Denied");

// 1. Ajax 状态检查
if (isset($_GET['action']) && $_GET['action'] == 'check') {
    $invoiceData = \WHMCS\Database\Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
    echo json_encode(['status' => ($invoiceData && $invoiceData->status == 'Paid') ? 'paid' : 'unpaid']);
    exit;
}

$GATEWAY = getGatewayVariables("xy_alipay");
$invoice = \WHMCS\Database\Capsule::table('tblinvoices')->where('id', $invoiceId)->first();

if (!$invoice || $invoice->status == 'Paid') {
    header("Location: ../../../viewinvoice.php?id=" . $invoiceId);
    exit;
}

// 2. 环境与配置识别
$isMobile = preg_match('/(alipay|android|iphone|ipad|mobile)/i', strtolower($_SERVER['HTTP_USER_AGENT']));
$amount = number_format($invoice->total, 2, '.', '');
$systemUrl = rtrim($GATEWAY['systemurl'], '/');
$endpoint = 'https://openapi.alipay.com/gateway.do';
$payMethod = $GATEWAY['payMethod']; // 后台配置：page 或 f2f

// 3. 构造基础参数
$commonParams = [
    'app_id'      => trim($GATEWAY['appId']),
    'format'      => 'JSON',
    'charset'     => 'utf-8',
    'sign_type'   => 'RSA2',
    'timestamp'   => date('Y-m-d H:i:s'),
    'version'     => '1.0',
    'notify_url'  => $systemUrl . '/modules/gateways/callback/xy_alipay.php',
];

$bizContent = [
    'out_trade_no' => $invoiceId . 'T' . date('His'),
    'total_amount' => $amount,
    'subject'      => '账单支付 #' . $invoiceId,
];

// --- 核心智能路由逻辑 ---

// 场景 A：PC端 且 后台配置为“电脑网站支付” -> 直接跳转支付宝
if (!$isMobile && $payMethod === 'page') {
    $commonParams['method'] = 'alipay.trade.page.pay';
    $commonParams['return_url'] = $systemUrl . '/viewinvoice.php?id=' . $invoiceId;
    $bizContent['product_code'] = 'FAST_INSTANT_TRADE_PAY';
    $commonParams['biz_content'] = json_encode($bizContent, JSON_UNESCAPED_UNICODE);
    $commonParams['sign'] = xy_alipay_gen_sign($commonParams, $GATEWAY['merchantPrivateKey']);

    echo '<html><head><meta charset="utf-8"></head><body>';
    echo '<form id="pc_jump" method="POST" action="'.$endpoint.'?charset=utf-8">';
    foreach ($commonParams as $k => $v) { echo '<input type="hidden" name="'.htmlspecialchars($k).'" value="'.htmlspecialchars($v).'">'; }
    echo '</form><script>document.getElementById("pc_jump").submit();</script></body></html>';
    exit;
}

// 场景 B：其他所有情况（PC扫码模式 或 移动端唤起模式） -> 统一调用当面付接口
$commonParams['method'] = 'alipay.trade.precreate';
$commonParams['biz_content'] = json_encode($bizContent, JSON_UNESCAPED_UNICODE);
$commonParams['sign'] = xy_alipay_gen_sign($commonParams, $GATEWAY['merchantPrivateKey']);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $endpoint . '?' . http_build_query(['charset' => 'utf-8']));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($commonParams));
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);
$qrCode = $result['alipay_trade_precreate_response']['qr_code'] ?? '';

if (!$qrCode) die("支付接口响应异常: " . ($result['alipay_trade_precreate_response']['sub_msg'] ?? '请检查配置'));

// 准备渲染数据
$qrImg = "https://api.qrserver.com/v1/create-qr-code/?size=280x280&data=" . urlencode($qrCode);
$alipayAppUrl = "alipays://platformapi/startapp?saId=10000007&qrcode=" . urlencode($qrCode);

// 签名函数
function xy_alipay_gen_sign($data, $privateKey) {
    ksort($data);
    $str = "";
    foreach ($data as $k => $v) { if ($v !== "" && !is_null($v)) $str .= ($str === "" ? "" : "&") . $k . "=" . $v; }
    $priKey = "-----BEGIN RSA PRIVATE KEY-----\n" . wordwrap(trim($privateKey), 64, "\n", true) . "\n-----END RSA PRIVATE KEY-----";
    $keyRes = openssl_get_privatekey($priKey);
    openssl_sign($str, $sign, $keyRes, OPENSSL_ALGO_SHA256);
    return base64_encode($sign);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>收银台 - 轩辕云</title>
    <style>
        :root { --ali-blue: #1677ff; }
        body { margin: 0; background: #0a0a0a; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: "PingFang SC", sans-serif; }
        .glass-card {
            background: rgba(255, 255, 255, 0.98); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border-radius: 32px; width: 92%; max-width: 400px; padding: 45px 35px;
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.3); text-align: center;
        }
        .price { font-size: 48px; font-weight: 800; color: #1a1a1a; margin: 15px 0 35px; }
        .price::before { content: "￥"; font-size: 22px; margin-right: 4px; }
        .qr-wrapper { position: relative; background: #fff; padding: 12px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); margin-bottom: 25px; display: inline-block; }
        .qr-wrapper::after { content: ""; position: absolute; left: 0; top: 0; width: 100%; height: 2px; background: var(--ali-blue); animation: scan 3s infinite; }
        @keyframes scan { 0%, 100% { top: 0; } 50% { top: 100%; } }
        .btn-pay { display: block; width: 100%; background: var(--ali-blue); color: #fff; text-decoration: none; padding: 18px; border-radius: 18px; font-size: 16px; font-weight: 600; box-shadow: 0 8px 25px rgba(22,119,255,0.3); }
        .status { margin-top: 25px; color: var(--ali-blue); font-size: 14px; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 500; }
        .dot { width: 6px; height: 6px; background: var(--ali-blue); border-radius: 50%; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
    </style>
</head>
<body>
<div class="glass-card">
    <img src="https://img.alicdn.com/tfs/TB19S_4qhGYBuNjy0FnXXX5lpXa-720-220.png" height="30" style="opacity: 0.9;">
    <div style="margin-top:25px; color:#999; font-size:12px; letter-spacing:1px;">PAYMENT AMOUNT</div>
    <div class="price"><?php echo $amount; ?></div>
    
    <?php if ($isMobile): ?>
        <a href="<?php echo $alipayAppUrl; ?>" class="btn-pay">立即打开支付宝付款</a>
    <?php else: ?>
        <div class="qr-wrapper"><img src="<?php echo $qrImg; ?>" width="220" height="220"></div>
    <?php endif; ?>

    <div class="status"><div class="dot"></div> 正在同步支付状态...</div>
    <div style="margin-top: 30px; font-size: 12px; color: #ccc; border-top: 1px solid #f2f2f2; padding-top: 20px;">
        账单: #<?php echo $invoiceId; ?> | 轩辕云提供技术支持
    </div>
</div>

<script>
    <?php if ($isMobile): ?>
    window.onload = function() { setTimeout(function() { window.location.href = "<?php echo $alipayAppUrl; ?>"; }, 600); };
    <?php endif; ?>

    setInterval(function() {
        fetch("?invoiceid=<?php echo $invoiceId; ?>&action=check")
            .then(res => res.json())
            .then(data => { if (data.status === 'paid') location.href = "../../../viewinvoice.php?id=<?php echo $invoiceId; ?>"; });
    }, 3000);
</script>
</body>
</html>