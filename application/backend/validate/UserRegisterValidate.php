<?php
namespace app\backend\validate;

use think\Validate;

class UserRegisterValidate extends Validate
{
    protected $rule = [
        'code'  =>  'unique:users|length:4,11',
        'repassword'    =>  'confirm:password',
    ];

    protected $message = [
        'code.unique'   =>  '此用户已注册',
        'code.length'   =>  '请输入4~11位的会员编号',
        'repassword.confirm'    =>  '登陆密码不一致',
    ];

}
