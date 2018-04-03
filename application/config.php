<?php

return [
    'url_route_on' => true,
    'trace' => [
        'type' => 'html', // 支持 socket trace file
    ],
    //各模块公用配置
    'extra_config_list' => ['database', 'route', 'validate'],
    //临时关闭日志写入
    'log' => [
        'type' => 'test',
    ],

    'app_debug' => true,
    'default_filter' => ['strip_tags', 'htmlspecialchars'],

    // +----------------------------------------------------------------------
    // | 缓存设置
    // +----------------------------------------------------------------------
    'cache' => [
        // 驱动方式
        'type' => 'file',
        // 缓存保存目录
        'path' => CACHE_PATH,
        // 缓存前缀
        'prefix' => '',
        // 缓存有效期 0表示永久缓存
        'expire' => 0,
        'port' => 11211,
    ],
    // 'cache' => false,
    // 'tpl_cache' => false,

    //加密串
    'salt' => 'wZPb~yxvA!ir38&Z',

    //备份数据地址
    'back_path' => APP_PATH .'../back/',

    //支付宝支付配置
    "Alipay"           =>   [
        # appid
        'app_id'=>'2017112300112052',
        # 商户私钥，您的原始格式RSA私钥
        'merchant_private_key'=>'MIICXgIBAAKBgQCnHtct1pGB00rROHxwzfiF1rOwOekxIYjTltUjae0E4lUtuFpqBfbjF3/8kYudYzd/7JOPXcR2Z4/0evBvbM0Ohk7WAJQaZlLUT4813chrZZ9ZbAYhctyQZC0THb1nijy74yL0Xi/K2OfuLoUwMI0Ir0sdYU9QoHnUQraNZFSeNwIDAQABAoGBAJYChe4cWzIowlC5HmJ3UCx7A4IdCWfTjSk4jAskyt+GdiT7BRWEUw+XCYhh8OuOosPOTMn0xiPuY/Z04Bt0kz6Rg3qMalXoY48/KxkzefkJbWHpGiNo3ivXk1L3qIT0JVAwr4tca87Zck8T2ct1xdoL5aVY/1Y6Lmd0CVdJGtRBAkEA2qHvAPp2UlqdyglKohc4sL19vlSzjeeA0jSzbhTQM8TeKQdq0Sp+BXjoYClPnQhHB5qqjI3EkZBDBHJs1q3/IQJBAMOvCwo/11+KOEs+OgX5N5m9FZxzhgQb3vU9z+rueSAEny3r8knsvxNSKN4NRTZR/oGtmNuwkwcJjeP1qw3nqlcCQACtXbsoC3PH2hNFIhkMb12EyL3tLz0ySWDdTuz5XYe7hWClKDyLoCZOMJITrq5y1x176ilTSxeUVdDOte2YTmECQQC9/6rXg89Ju7oXDM9n12guBRMDuoOIX8tnEWJc+Llw5ivseajiMFYCm4aEvBvUt15HaVf/D35imdtEWNeCg8q3AkEAtNYWCO80ScLheWE7LrZNMosnl7a/zZ3NV3s2QWBfxTJEH9hDcQp9sXUiJfr9LGaKQ//iOkyuzEWYV2NcwfZHWw==',
        # 异步通知地址
        'notify_url'=> "http://api.szcxdzsw.com/home/payment/alipay_notify",
        # 同步跳转
        'return_url' => "http://api.szcxdzsw.com/home/payment/redir",
        # 编码格式
        'charset'=>'UTF-8',
        # 签名方式
        'sign_type' => 'RSA',
        # 支付宝网关
        'gatewayUrl'=>"https://openapi.alipay.com/gateway.do",
        # 支付宝私钥文件
        // 'rsa_private_key' => ROOT_PATH.'\private\siyao.txt',
        'rsa_private_key' => "MIICXgIBAAKBgQCnHtct1pGB00rROHxwzfiF1rOwOekxIYjTltUjae0E4lUtuFpqBfbjF3/8kYudYzd/7JOPXcR2Z4/0evBvbM0Ohk7WAJQaZlLUT4813chrZZ9ZbAYhctyQZC0THb1nijy74yL0Xi/K2OfuLoUwMI0Ir0sdYU9QoHnUQraNZFSeNwIDAQABAoGBAJYChe4cWzIowlC5HmJ3UCx7A4IdCWfTjSk4jAskyt+GdiT7BRWEUw+XCYhh8OuOosPOTMn0xiPuY/Z04Bt0kz6Rg3qMalXoY48/KxkzefkJbWHpGiNo3ivXk1L3qIT0JVAwr4tca87Zck8T2ct1xdoL5aVY/1Y6Lmd0CVdJGtRBAkEA2qHvAPp2UlqdyglKohc4sL19vlSzjeeA0jSzbhTQM8TeKQdq0Sp+BXjoYClPnQhHB5qqjI3EkZBDBHJs1q3/IQJBAMOvCwo/11+KOEs+OgX5N5m9FZxzhgQb3vU9z+rueSAEny3r8knsvxNSKN4NRTZR/oGtmNuwkwcJjeP1qw3nqlcCQACtXbsoC3PH2hNFIhkMb12EyL3tLz0ySWDdTuz5XYe7hWClKDyLoCZOMJITrq5y1x176ilTSxeUVdDOte2YTmECQQC9/6rXg89Ju7oXDM9n12guBRMDuoOIX8tnEWJc+Llw5ivseajiMFYCm4aEvBvUt15HaVf/D35imdtEWNeCg8q3AkEAtNYWCO80ScLheWE7LrZNMosnl7a/zZ3NV3s2QWBfxTJEH9hDcQp9sXUiJfr9LGaKQ//iOkyuzEWYV2NcwfZHWw==",
        # 支付宝公钥
        'ali_public_key' => "MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDDI6d306Q8fIfCOaTXyiUeJHkrIvYISRcc73s3vF1ZT7XN8RNPwJxo8pWaJMmvyTn9N4HQ632qJBVHf8sxHi/fEsraprwCtzvzQETrNRwVxLO5jVmRGi60j8Ue1efIlzPXV9je9mkjzOmdssymZkh2QhUrCmZYI/FCEa3/cNMW0QIDAQAB"

    ],
    //微信配置
    "Wechat"            =>   [
        # 微信的appid
        'appid'=>'wxf737ec4f5147b1c9',#
        # 公众号的secret
        'secret'=>'d8876c2e3a3dc1500a1d9abe67f374f3',#
        # 登录操作函数回调链接
        // 'callback'=> DOMIAN."/home/login/wechat_shop",
        # 授权成功的回调链接
        'login_success_callback'=> '',
        # 微信支付key
        'pay_key'=>'725ca368c3cb709eba07c52166de007c',
        # 商户id
        'mchid' => '1495048572',#
        #通知回调地址
        'notify_url'=> 'http://api.szcxdzsw.com/home/payment/native',
        #token定义
        'TOKEN'=>"szcx",#
    ],

    #用户类型
    'user_class' => [
        '1' => '普通会员',
        '2' => 'vip1',
        '3' => 'vip2',
        '4' => 'vip3'
    ],

    #用户类型
    't_class' => [
        '1' => '经理',
        '2' => '总监',
        '3' => '董事',
        '4' => '银董',
        '5' => '金董',
        '6' => '皇冠',
    ],

    #资金明细类别
    'account_type' => [
        '1' => '注册积分',
        '2' => '游戏积分',
        '3' => '奖励积分',
        '4' => '消费积分',
        '5' => '电子积分',
        '6' => '获赠积分',
        '7' => '购物积分',
        '8' => '蓝海积分',
        '9' => '基金币',
        '10' => '复投积分'
    ],

    #奖励积分出售状态
    'score_deal_status' => [
        '1' => '挂售中',
        '2' => '已完成',
        '3' => '失败',
        '4' => '取消',
        '5' => '交易中',
        '6' => '后台取消',
        '7' => '待审核',
    ],

    #奖励积分出售状态详情
    'score_deal_detail_status' => [
        '1' => '确认付款',
        '2' => '已完成',
        '3' => '失败',
        '4' => '买家取消',
        '5' => '卖家取消',
        '6' => '后台取消',
    ],

    //订单状态
    'order_status' => [
        '1' => '待付款',
        '2' => '待发货',
        '3' => '待收货',
        '4' => '已完成',
        '5' => '已取消',
        '6' => '已删除'
    ],
    // 默认模块名
    'default_module'         => 'backend',
    // 默认控制器名
    'default_controller'     => 'Login',
    // 默认操作名
    'default_action'         => 'index',
];
