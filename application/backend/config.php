<?php

return [

    //模板参数替换
    'view_replace_str' => array(
        '__CSS__' => '/static/admin/css',
        '__JS__' => '/static/admin/js',
        '__IMG__' => '/static/admin/images',
    ),

    //管理员状态
    'user_status' => [
        '1' => '正常',
        '2' => '禁止登录'
    ],

    //角色状态
    'role_status' => [
        '1' => '启用',
        '2' => '禁用'
    ],
    //商品状态
    'goods_status' => [
        '1' => '销售中',
        '2' => '已下架'
    ],

    //支付类型
    'payment' => [
        '1' => '支付宝',
        '2' => '微信',
        '3' => '积分',
        '4' => '报单中心'
    ],
    #提现状态
    'Withdraw_status' => [
        '0' => '申请中',
        '1' => '同意',
        '2' => '已发放',
        '3' => '拒绝'
    ],
    #申请状态
    'apply_status' => [
        '1' => '申请中',
        '2' => '同意',
        '3' => '拒绝'
    ],
    #支付 提现方式
    'Withdraw_type' => [
        '0' => '微信',
        '1' => '支付宝',
        '2' => '银行卡'
    ],
    // '密保问题'
    'mb_problem' => [
        '0' => '你的生日',
        '1' => '你梦想的职业',
        '2' => '你喜欢的食物',
        '3' => '你的幸运数字'
    ],
    // 远点复位状态
    'status_origin' => [
        '1' => '审核中',
        '2' => '通过-已处理',
        '3' => '拒绝-已处理'
    ],
    #级别显示名称
    'name' => [
        '1' => "普卡",
        '2' => "银卡",
        '3' => "金卡",
        '4' => "白金卡",
        '5' => "黑金卡",
        '6' => "钻卡",
        '7' => "蓝钻"
    ],
    #BOF各级别注册积分
    'bof_reg_score' => [
        '1' => 100,
        '2' => 300,
        '3' => 500,
        '4' => 1000,
        '5' => 3000,
        '6' => 5000,
        '7' => 10000
    ],
    #BOF各级别注册积分
    'web_url' => "http://webbof.ewtouch.com",

];
