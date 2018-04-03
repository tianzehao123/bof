<?php

return [

    //模板参数替换
    'view_replace_str' => array(
        '__CSS__' => '/static/home/css',
        '__JS__' => '/static/home/js',
        '__IMG__' => '/static/home/images',
        '__FONTS__' => '/static/home/fonts',
        '__JS2__' => '/static/admin/js'
    ),

    'sex' => [
        '1' => '男',
        '2' => '女',
        '0' => '未设置'
    ],

    #提现状态
    'Withdraw_status' => [
        '0' => '申请中',
        '1' => '同意',
        '2' => '已发放',
        '3' => '拒绝'
    ],
    #级别回购率
    'Level' => [
        '1' => 50,
        '2' => 52,
        '3' => 54,
        '4' => 56,
        '5' => 58,
        '6' => 60,
        '7' => 62
    ],
    #BOF总量封顶(销售总量)
    'bofAll' => [
        '0' => 0,
        '1' => 3000,
        '2' => 9000,
        '3' => 15000,
        '4' => 30000,
        '5' => 90000,
        '6' => 160000,
        '7' => 320000
    ],
    #BOF原点位激活(各级别直推人数)
    'bofLevel' => [
        '0' => db("config")->where(["name" => "pu_straight"])->value("value"),
        '1' => db("config")->where(["name" => "pu_straight"])->value("value"),
        '2' => db("config")->where(["name" => "yin_straight"])->value("value"),
        '3' => db("config")->where(["name" => "jin_straight"])->value("value"),
        '4' => db("config")->where(["name" => "baijin_straight"])->value("value"),
        '5' => db("config")->where(["name" => "heijin_straight"])->value("value"),
        '6' => db("config")->where(["name" => "zuan_straight"])->value("value"),
        '7' => db("config")->where(["name" => "lanzuan_straight"])->value("value")
    ],
    #BOF各级别注册积分
    'bof_reg_score' => [
        '0' => 0,
        '1' => 100,
        '2' => 300,
        '3' => 500,
        '4' => 1000,
        '5' => 3000,
        '6' => 5000,
        '7' => 10000
    ],
    #级别显示名称
    'name' => [
        '0' => "免费会员",
        '1' => "普卡",
        '2' => "银卡",
        '3' => "金卡",
        '4' => "白金卡",
        '5' => "黑金卡",
        '6' => "钻卡",
        '7' => "蓝钻"
    ],
    #bof出售状态
    'bof_deal_status' => [
        '1' => '已提交',
        '2' => '已审核',
        '3' => '失败',
        '4' => '取消',
        '5' => '后台取消',
        '6' => '未审核',
    ],

];
