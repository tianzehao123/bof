<?php
// +----------------------------------------------------------------------
// | snake
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 http://baiyf.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: NickBai <1902822973@qq.com>
// +----------------------------------------------------------------------
namespace app\home\validate;

use think\Validate;

class RegValidate extends Validate
{
    protected $rule = [
        'code' 			    => 		'require|length:4,11',
        'phone' 			=> 		'require',
        'auth_code' 		=> 		'require',
        'password' 			=> 		'require|length:6,18',
        'repassword' 		=> 		'require|confirm:password|length:6,18',
        'two_password' 		=> 		'require',
        're_two_password'	=> 		'require|confirm:two_password',
    ];

    protected $message = [
    	'code.require' 			    => 		'用户名不能为空',
    	'code.length' 			    => 		'请输入四到十一位的会员编号',
    	'phone.require' 			=> 		'手机号不能为空',
    	'auth_code.require' 		=> 		'验证码不能为空',
        'password.require'          =>      '密码不能为空',
        'password.length'           =>      '请输入6~18位的密码',
    	'repassword.length' 		=> 		'请输入6~18位的密码',
    	'repassword.require' 		=> 		'重复密码不能为空',
    	'repassword.confirm' 		=> 		'登录密码不一致',
    	'two_password.require' 		=> 		'交易密码不能为空',
    	're_two_password.require' 	=> 		'重复交易密码不能为空',
    	're_two_password.confirm' 	=> 		'交易密码不一致',
    ];



}