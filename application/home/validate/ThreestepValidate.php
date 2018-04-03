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

class ThreestepValidate extends Validate
{
    protected $rule = [
        'truename' 	    => 		'require',
        'identity' 	    => 		'require',
        'bank_name' 	=> 		'require',
        'bank_account' 	=> 		'require',
        'bank_user' 	=> 		'require',
    ];

    protected $message = [
    	'truename.require' 	    => 		'请输入您的真实姓名',
    	'identity.require' 	    => 		'请输入您的身份证号',
    	'bank_name.require' 	=> 		'请输入正确的开户银行',
    	'bank_account.require' 	=> 		'请输入正确的银行卡号',
    	'bank_user.require' 	=> 		'请输入正确的开户人',
    ];



}