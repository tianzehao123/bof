<?php
namespace app\bofshop\validate;

use think\Validate;

class LoginValidate extends Validate
{
	protected $rule = [
		'phone' 		=> 	'require',
		'auth_code'		=>	'require',
		'password'		=>	'require|length:6,18',
		'repassword'	=>	'require|confirm:password',
		'two_password'	=>	'require',
	];

	protected $message = [
		'phone.require' 		=>	'请输入正确的手机号码',
		// 'phone.unique'			=>	'此手机号已注册',
		'auth_code.require'		=>	'验证码不能为空',
		'password.require'		=>	'请输入你的登录密码',
		'two_password.require'	=>	'请输入你的支付密码',
		'password.length'		=>	'请输入6~18位的密码',
		'repassword.require'	=>	'请确认的登录密码',
		'repassword.confirm'	=>	'两次密码不一致',
	];
}