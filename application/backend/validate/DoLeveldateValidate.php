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
namespace app\backend\validate;

use think\Validate;

class DoLeveldateValidate extends Validate
{
	    protected $rule =   [
	        'id'   	 => 'require|number',
	        'level'  => 'require|number|in:1,2,3,4,5,6,7',
	        'date'   => 'require|number|elt:100|egt:1'   
	    ];
	    
	    protected $message  =   [
	    	'id.require'       =>     '请重新选择用户',
	    	'id.number'        =>     '请重新选择用户',
	        'level.require'    =>     '虚升的等级必须填写',
	    	'level.number'     =>     '虚升的等级必须填写',
	    	'level.in'         =>     '虚升的等级格式不正确',
	    	'date.require'	   =>     '时间必须填写',
	    	'date.number'	   =>     '天数必须是数字',
	    	'date.elt'	       =>     '天数不能大于100天',
	    	'date.egt'	       =>     '天数不能小于1天'
	    ];

}