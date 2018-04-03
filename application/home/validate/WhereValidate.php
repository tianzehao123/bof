<?php 
	namespace app\home\validate;
	use think\Validate;
	//公共条件验证
	class WhereValidate extends Validate
	{
		
	    protected $rule =   [
			'start_date'  	=>   'date',
			'end_date'		=>	 'date',
			'page'			=>   'integer'
			
	    ];
	    
	    protected $message  =   [
 			'start_date.date'	=>  '日期格式不正确',
 			'end_date.date'     =>  '日期格式不正确',
 			'page.integer'      =>  '分页格式不正确'
 		];		




	}