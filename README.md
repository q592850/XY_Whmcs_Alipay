## 轩辕云官网
[https://[[www.]()](http://www.globaliepl.com/)](https://www.globaliepl.com/)
主营：IPLC广港，IPLC沪港，IPLC沪日，IPLC沪美，深港IEPL，沪日IEPL，广州大带宽，深圳大带宽，江苏大带宽，深圳大带宽，温州建站VPS等等
TG交流群:https://t.me/cn_iepl

# XY_Whmcs_Alipay
轩辕云开发的WHMCS的支付宝插件，包含当面付和电脑网站支付

## 文件结构
/www/wwwroot/你的域名/
└── modules/
    └── gateways/
        ├── xy_alipay.php           <-- [主文件] 负责后台配置与前台丝滑跳转
        └── callback/
            ├── xy_alipay.php       <-- [回调页] 负责接收支付宝通知并入账
            └── xy_alipay_pay.php   <-- [收银台] 负责智能路由、UI展示与App唤起

## 食用方法
解压到对应目录，然后再后台激活插件即可，激活找不到就搜索xy或alipay 找到一个xy_alipay的就是了
