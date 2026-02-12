<?php
/**
 * XY Alipay Gateway - Final Stable Version
 * 优化：全屏遮罩丝滑跳转，防止侧边栏按钮闪烁
 */

if (!defined("WHMCS")) die("Access Denied");

function xy_alipay_MetaData() {
    return [
        'DisplayName' => 'XY 支付宝官方接口 (商业收银台版)',
        'APIVersion' => '1.1',
    ];
}

function xy_alipay_config() {
    return [
        'FriendlyName' => ['Type' => 'System', 'Value' => 'XY 支付宝 (官方原版接口)'],
        'appId' => ['FriendlyName' => '应用 APPID', 'Type' => 'text', 'Size' => '30'],
        'merchantPrivateKey' => ['FriendlyName' => '应用私钥 (RSA2)', 'Type' => 'textarea', 'Rows' => '5', 'Description' => '纯文本，不含头尾标记'],
        'alipayPublicKey' => ['FriendlyName' => '支付宝公钥 (RSA2)', 'Type' => 'textarea', 'Rows' => '5', 'Description' => '注意：是【支付宝公钥】，非应用公钥'],
        'payMethod' => [
            'FriendlyName' => '支付模式',
            'Type' => 'dropdown',
            'Options' => [
                'f2f' => '当面付 (扫码模式 - 推荐)',
                'page' => '电脑网站支付 (跳转模式)',
            ],
        ],
    ];
}

function xy_alipay_link($params) {
    $invoiceId = $params['invoiceid'];
    $systemUrl = rtrim($params['systemurl'], '/');
    $payUrl = $systemUrl . '/modules/gateways/callback/xy_alipay_pay.php?invoiceid=' . $invoiceId;

    // 全屏遮罩跳转逻辑
    $code = '
    <style>
        #alipay-loading-mask {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255,255,255,0.98); z-index: 999999;
            display: flex; align-items: center; justify-content: center;
        }
        .alipay-loader {
            border: 3px solid #f3f3f3; border-top: 3px solid #00a3ff;
            border-radius: 50%; width: 40px; height: 40px;
            animation: alipay-spin 1s linear infinite; margin-bottom: 15px;
        }
        @keyframes alipay-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
    
    <div id="alipay-loading-mask">
        <div style="text-align: center;">
            <div class="alipay-loader" style="margin: 0 auto 15px;"></div>
            <div style="color: #666; font-size: 16px; font-family: sans-serif;">正在连接安全收银台...</div>
        </div>
    </div>

    <script>
        (function() {
            var payUrl = "'.$payUrl.'";
            // 只有在账单查看页才执行强制跳转，防止后台预览崩溃
            if (window.location.href.indexOf("viewinvoice.php") !== -1) {
                window.location.href = payUrl;
            } else {
                document.getElementById("alipay-loading-mask").style.display = "none";
            }
        })();
    </script>
    
    <noscript>
        <div style="padding: 20px; text-align: center;">
            <a href="'.$payUrl.'" class="btn btn-primary">点击此处进入支付页面</a>
        </div>
    </noscript>';

    return $code;
}