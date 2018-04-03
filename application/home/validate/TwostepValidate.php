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

class TwostepValidate extends Validate
{
    protected $rule = [
        'pcode' 	=> 		'require',
        'ncode' 	=> 		'require',
        'bd_id'     =>      'require',
    ];

    protected $message = [
    	'pcode.require' 	=> 		'推荐人编号不能为空',
    	'ncode.require' 	=> 		'接点人编号不能为空',
    	'bd_id.require' 	=> 		'报单中心不能为空',
    ];



}