<?php
namespace app\home\controller;

use app\backend\model\AccountModel;
use app\backend\model\Config;
use app\backend\model\UserModel;
use app\backend\model\UpgradeModel;
use think\Controller;
use think\Db;
use think\Request;
use think\Session;
use app\home\validate\WhereValidate;
use Service\Useractivate;
use Service\Stock;
use Service\Rerformance;
use Service\Nineservice;
use app\backend\controller\Usertesting; 
use app\backend\model\UsertestingModel;
use app\home\behavior\Distribution;

//用户控制器
class Users extends Base {

	private $page = 12;  //每页显示多少条
	private $userId = []; //
	private $user;//用户信息
	private $currentPage = 1; //当前第几页
	const Amount  = ['0'=>0,'1'=>100,"2"=>300,"3"=>500,"4"=>1000,"5"=>3000,"6"=>5000,"7"=>10000];
	const CLASSS  = ['0'=>'免费会员','1'=>"普卡","2"=>"银卡","3"=>"金卡","4"=>"白金卡","5"=>"黑金卡","6"=>"钻卡","7"=>"蓝钻"];


	public function  _initialize()
	{

		parent::_initialize();
		$this->currentPage = empty(input("page"))?$this->currentPage:input("page");
		$this->user =  Session::get("home.user");

	}




    /**
     * 获取所有直推会员
     * @param integer    $page 第几页
     * @return array ([第几页,数据,总页数])
     */
	public function DirectPush()
	{

		//公共条件验证数据
		$validate = new WhereValidate();
		if (!$validate->check(input())) {
		    return ajax(2,$validate->getError(),"");
		}

		$model = new UserModel();
		
		$where = ["pid"=>$this->user['id']];
		$count = $model->where($where)->count();
		$page = $this->MyPage($this->page,$count);
		if($page===false){
			return ajax(2,'已经是最后一页了','');
		}

		$row = $model->field(['id','code','class','reg_score','created_at'])->where($where)->order("id desc")->limit($page['strip'],$this->page)->select();
		
		//每个账号下的所有团队业绩
		foreach($row as $key=>$value)
		{	
			$Amount = 0;
			if($this->SelectSon($value['id'],'pid',['id','class'])!==false){
				 if(count($this->userId)>0){
				 	 foreach($this->userId as $k => $v){ 
			 	 	 	   $Amount += $this::Amount[$v['class']];
				   	 }
				  }
			 }

	        if($value['class'] >= 0 and $value['class'] < count($this::CLASSS)){
	            $row[$key]['class'] = $this::CLASSS[$value['class']];
	        }else{
	            $row[$key]['class'] =  "错误级别";
	        }

			$row[$key]['Amount'] = $Amount;
			$this->userId = null;
		}

		// // 返回数据
		$data = [
			"page" 		=> $page['page'], 	 //当前页
			"row"  		=> $row,			 //数据集合
			"count" 	=> $count,			 //总条数
			"countPage" => ceil($count/$this->page)  //共几页
		];

		
		return ajax(1,'查询成功',$data);
	}


    /**
     * 会员升级列表
     * @param  integer  $page 第几页
     * @return array ([当前第几页,高于用户当前的级别,升级列表用条数,当前页的数据])
     */
	public function UpgradeList()
	{
		$model = new UserModel();
		$row = $model->field(['id','code','class','reg_score'])->where(['id'=>$this->user['id']])->find();
		//返回比用户当前等级高的级别
		$int = 0;
		$class = [];
		foreach($this::CLASSS as $key=>$value){
			if($key>$row['class']){
				$class[$int]['key'] = $key;
				$class[$int]['name'] = $value;
				$class[$int]['Amount'] = $this::Amount[$key] - $this::Amount[$row['class']];
				$int++;
			} 
		}

		//查询当前用户升级记录
		$Umodel = new UpgradeModel();
		$where = ['uid'=>$this->user['id']];
		$count = $Umodel->where($where)->count();
		$page = $this->MyPage($this->page,$count);
		if($page===false){
			return ajax(2,'已经是最后一页了','');
		}
		$list = $Umodel->order("id desc")->where($where)->limit($page['strip'],$this->page)->select();

		foreach($list as $key=>$value){
			$list[$key]['created_at'] = date('Y-m-d h:i:s',$value['created_at']);
			$list[$key]['state'] = '升级成功';
			$list[$key]['class'] = self::CLASSS[$value['class']];
			$list[$key]['class_at'] = self::CLASSS[$value['class_at']];
			if(!empty($value['updated_at'])){
				$list[$key]['updated_at'] = date('Y-m-d h:i:s',$value['updated_at']);
			 }
		}
		$data = [	
			 "page"      =>  $page['page'],   //当前第几页
			 "class"     =>	$class,			 //高于用户当前的级别
			 'class_two' =>  self::CLASSS[$row['class']],//用户当前级别
			 'reg_score' =>  $row['reg_score'],  //用户注册积分
			 "count"	 =>  $count,			 //升级列表用条数
			 "list"		 =>  $list, 			 //当前页的数据
			 "countPage" => ceil($count/$this->page)  //共几页
		];

		return ajax(1,"ok",$data);
	}


    /**
     * 执行升级
     * @param integer    $class 要升的级别

     * @return Boolean   
     */
	public function DoUpgrade()
	{
		if(empty(input('class')) or !is_numeric(input('class')) or input('class')<1 or input('class')>count($this::CLASSS)) return ajax(2,"无效选择,请重新选择","");
		//检查注册币是否足够升级

		$list = Db::name("users")->field(['class','reg_score','shopping_number'])->where(['id'=>$this->user['id']])->find();

		if($list['class']>=input("class")) return ajax(2,"请重新选择晋升级别","");
		if($list['reg_score'] < ($this::Amount[input('class')]-$this::Amount[$list['class']])) return ajax(2,"您的注册币不足,请充值","");

		// //检查购买商品额度是否达到要申请的级别
		// if($list['shopping_number'] < input('class')){
		// 	 $str = '您购买的最高级别为'.$this::CLASSS[$list['shopping_number']].'报单商品 不足升级';
		// 	 return ajax(2,$str);
		// }


		$row['reg_score'] = $list['reg_score'] - ($this::Amount[input("class")] - $this::Amount[$list['class']]);
		$row['class']  = input("class");
		//升级记录
		$data = [
			"uid"   			=> $this->user['id'],  //用户id
			"truename"			=> $this->user['truename'], //真实姓名
			"code"				=> $this->user['code'],   //会员编号
			"type"  			=> "会员升级",			 //备注
			"created_at"		=> time(),				//申请日期
			"class"				=> $list['class'],		//申请前级别
			"class_at"			=> input("class"),      //升级后级别
			"Amount"			=> $this::Amount[input("class")] - $this::Amount[$list['class']],
			'updated_at'		=> time(),              //晋升日期
			"state"				=> 1					
		];	
		//总记录
		$data2['uid']       = $this->user['id']; 
		$data2['score']     = "-".($this::Amount[input("class")] - $this::Amount[$list['class']]);
		$data2['cur_score'] = $row['reg_score'];
		$data2['remark']    = "会员升级";
		$data2['type']      = 1;
		$data2['is_add']	= 2;
		$data2['class']     = 1;
		$data2['from_uid']  = $this->user['id'];		
		$data2['created_at'] = time();
		$data2['source'] = 8;
		//执行升级扣币

		 Db::startTrans();
		 try{

		     $users   = Db::name('users')->where("id",$this->user['id'])->update($row);
		     $upgrade = Db::name('user_upgrade')->insert($data);
		     $account = Db::name('account')->insert($data2);
		     // 提交事务

		      if($users!==false and $upgrade!==false){
		     	// 查询当前股值
		        $disc = Db::name("disc")->order("id desc")->value(['market_price']);
		        if (!empty($disc) and count($disc) > 0) {
		            $market_price = $disc;
		        } else {
		            $market_price = Db::name("config")->where(['name' => 'current_price'])->value('value');
		        }

		        //判断用户是否已被激活
		        $user = Db::name('users')->field(['status', 'class'])->where(['id'=>$this->user['id']])->find();

		        if ($user === false || count($user) < 1) {
		            return ajax(2,'该用户不存在');
		        }


		        if (empty($market_price)) return ajax(2,"升级失败");



		        //分配BOF
		        $Useractivate = new Useractivate($this->user['id'], $market_price,($this::Amount[input("class")] - $this::Amount[$list['class']]),'sj');

		        $str = $Useractivate->loadUser();

		        //分配bof
		        if ($str != '已加入队列' && $str != '已激活') {
		        	 Db::rollback();
		             return ajax(2,$str,'');
		        }
		        // //涨幅

		        $Stock = new Stock();
		        $str1 = $Stock->loadAccount();

		        // 增加左右区业绩

		        $Rerformance = new Rerformance($this->user['id'],$user['class'],($this::Amount[input("class")] - $this::Amount[$list['class']]));

		        $Rerformance->loadUser();

		        //九级分销
		        $Nineservice = new Nineservice($this->user['id'],($this::Amount[input("class")] - $this::Amount[$list['class']]));

		        $oo = $Nineservice->loadUser();

		        //升级成功后分配报单奖
		        $model   = new UserModel();
		        $Fresult = $model->DeclarationForm(($this::Amount[input("class")] - $this::Amount[$list['class']]));

		        //增加业绩记录
		        $this->addAchievement($this->user['id'],($this::Amount[input("class")] - $this::Amount[$list['class']]));

		         if($Fresult===false){
		         	Db::rollback();
		 			return ajax(2,'分配报单奖失败');
		         }
		     	Db::commit();
		     	return ajax(1,'升级成功');

		     }else{
		  	 	Db::rollback();
		  	 	return ajax(2,'升级失败1');
		     }
		  }catch (\Exception $e){
		     // 回滚事务
		     Db::rollback();
		     return ajax(2,'升级失败','');
		 }
	}


	/**
     * 团队架构
     * @param stript 	UserId 用户名称
     * @param integer   layer  第几层 默认3
     * @return array ([下一页,当前跳过条数])
     */
	public function MemberNode()
	{

		$field = ['id','nid','code','class','left_all_ach','right_all_ach','left_ach','right_ach','region'];

		if(empty(input("UserId"))){
			$user = Db::name("users")->field($field)->where(['id'=>$this->user['id']])->find();
		}else{
			if(input('UserId')!=$this->user['code']){
				$all_nid = "%".$this->user['id'].",%";
				$user = Db::name("users")->field($field)->where(['code'=>input('UserId'),'all_nid'=>['like',$all_nid]])->find();
				if($user===false || count($user)<1 || empty($user)){
					return ajax(2,input('UserId')."不存在","");
				}

			}else{
				$user = Db::name('users')->field($field)->where(['id'=>$this->user['id']])->find();
			}
		}



		if($user===false || count($user)<1 || empty($user)){
			return ajax(2,input('UserId')."不存在","");
		}


		$layer = empty(input("layer"))?3:input("layer");
		if(!is_numeric($layer) or $layer<0 or $layer>3){
			return ajax(2,"层级输入错误,请重新选择","");
		}

		$Son = $this->SelectSon($user['id'],'nid',$field,$layer,true);
		if($Son===false){
			$data[0] = $user;
			return ajax(1,"查询成功",$data);
		}



		$user['class'] 			= self::CLASSS[$user['class']];
		$user['left_all_ach']   = ceil($user['left_all_ach'] / 100);
		$user['right_all_ach']  = ceil($user['right_all_ach'] / 100);
		$user['left_ach']       = ceil($user['left_ach'] / 100);
		$user['right_ach']      = ceil($user['right_ach'] / 100);

		$region = ["1"=>"left","2"=>"right"];
		foreach($this->userId as $key=>$value)
		{
			foreach($value as $k=>$v)
			{
				$this->userId[$key][$k]['left_all_ach']   = ceil($v['left_all_ach'] / 100);
				$this->userId[$key][$k]['right_all_ach']  = ceil($v['right_all_ach'] / 100);
				$this->userId[$key][$k]['left_ach']       = ceil($v['left_ach'] / 100);
				$this->userId[$key][$k]['right_ach']      = ceil($v['right_ach'] / 100);
				$this->userId[$key][$k]['region'] = $region[$v['region']];
				$this->userId[$key][$k]['class'] = $this::CLASSS[$v['class']];
			}
		}

		$this->userId[0] = $user;
		return ajax(1,"ok",$this->userId);
	}


	/**
     * 申请报单中心
     * @param
     * @return array
     */
	public function formCoreList()
	{
		$user = Db::name("users")->field(['id','nid','code','class','left_all_ach','right_all_ach'])->where(['id'=>$this->user['id']])->find();
		$count = Db::name("users")->where(['pid'=>$this->user['id'],'class'=>['>=',6]])->count();
		if($user['class']>(count(self::CLASSS)-1)){
			return ajax(2,"会员级别错误");
		}

		$user['class'] = $this::CLASSS[$user['class']];

		if($user['left_all_ach']>$user['right_all_ach']){
			$ach = $user['right_all_ach'];
		}else{
			$ach = $user['left_all_ach'];
		}

		$data = [
		   "ach"   => $ach,
		   "user"  => $user,
		   "count" => $count
		];
		return ajax(1,"ok",$data);
	}


	/**
     * 处理报单中心申请
     * @param  integer  报单中心级别
     * @param  string   详细地址
     * @return bool
     */
	public function DoFormCore()
	{

		$row = Db::name('form_core')->where(['uid'=>$this->user['id'],'status'=>['in',[1,2,4]]])->count();
		if($row>0){
			return ajax(2,"您已经申请过了","");
		}
		$user = Db::name("users")->field(['id','nid','code','class','left_all_ach','right_all_ach'])->where(['id'=>$this->user['id']])->find();
		$count = Db::name("users")->where(['pid'=>$this->user['id'],'class'=>['>=',6]])->count();
		if($user['class']<6){
			return ajax(2,"您的级别不够","");
		}
		if($count<10){
			return ajax(2,"您推荐钻卡级别会员数量不足","");
		}
 		if(empty(input('class')) or !is_numeric(input('class'))){
 			return ajax(2,"请选择您的级别");
 		}else{
 			if(input('class')<1 || input('class')>3){
 				return ajax(2,'您选择的级别不正确');
 			}
 		}
		if(!empty(input('address'))){
			$data['address'] 	= input('address');
		}
		if($user['left_all_ach']>$user['right_all_ach']){
			if($user['right_all_ach']<100000){
				return ajax(2,"您的小区业绩不足");
			}
		}else{
			if($user['left_all_ach']<100000){
				return ajax(2,"您的小区业绩不足");
			}
		}

		$data = [
			"uid" 	  	=> $user['id'],
			"ucode"   	=> $user['code'],
			'class'		=> $user['class'],
			"from_class"=> input('class'),
			"status"  	=> 1,
			"created_at" => time()
		];


		//提交申请
		Db::startTrans();
		try{
		    $row = Db::name('form_core')->insert($data);
		    // 提交事务
		    if($row!==false){
		    	Db::commit();
		    	return ajax(1,'申请成功','');
		    }else{
		 	 	Db::rollback();
		     }
		} catch (\Exception $e){
		    // 回滚事务
		    Db::rollback();
		    return ajax(2,'申请失败','');
		}

	}


	/**
     * 申请签约商家
     * @param
     * @param
     * @return
     */
	public function business()
	{
		$arr = ['jpg','jpeg','png','gif'];
		if(empty(input("img"))){
			return ajax(2,"商家凭证不能为空","");
		}else{
			$ex = explode(".",input("img"));
			if(count($ex)<1){
				return ajax(2,"商家凭证不能为空","");
			}else{
				if(in_array($ex[count($ex)-1],$arr)){
					  $data['img']=input("img");
				}else{
					 return ajax(2,"图片上传仅支持jpg,jpeg,png.gif类型","");
				}

			}

		}

		//删除视频链接前缀

		$data['img'] = explode('/',trim($data['img'],'http://'));
		$data['img'] = '/'.implode('/',array_splice($data['img'],1));

		// 检查是否申请过
		$row =  Db::name('business')->where(['uid'=>$this->user['id'],'status'=>['<',2]])->count();
		if($row>0){
			return ajax(2,"您已经申请过了");
		}

		$data['uid'] = $this->user['id'];
		$data['ucode'] = $this->user['code'];
		$data['status'] = 1;
		$data['created_at'] = time();
    	$row = Db::name("business")->insert($data);
    	if($row!==false){
    		return ajax(1,"申请成功","");
    	}else{
    		return ajax(2,"申请失败","");
    	}
	}


	/**
     * 分页处理
     * @param integer    $strip 一页多少条
     * @param integer    $count 一共多少条
     * @return array ([下一页,当前跳过条数])
     */
	private function MyPage($strip,$count)
	{
		if((($this->currentPage-1)*$strip)>$count){
			return false;
		}else{
			return ["page"=>$this->currentPage,"strip"=>(($this->currentPage-1)*$strip)];
		}
	}


    /**
     * 查询所有子级
     * @param integer    $UserID 用户编号
     * @param string     $where  按哪个字段条件查询
     * @param integer    $layer  第几级 (为空则查询所有下级)
     * @param array      $field  需要获取的字段
     * @param boolean    $type   是否按层排序
     * @param integer    $int    当前循环第几层(不可填)
     * @return Boolean
     */
	private function SelectSon($userId,$where,$field=['id'],$layer=null,$type=false,$int=1)
	{
        if(!empty($layer)) if($int>=$layer){return "查询完毕";}

		 $model = new UserModel();
		 if(empty($userId)){ return false;}
		 $row = $model->field($field)->where([$where=>$userId])->select();
		 if($row !==false and !empty($row)){
	 		 if($type!=false){
			 	 foreach ($row as $key => $value) {
			 	 	  $this->userId[$int][] = $value;
			 	 	  $this->SelectSon($value['id'],$where,$field,$layer,$type,$int+1);
			 	 }
	 		 }else{
			 	 foreach ($row as $key => $value) {
			 	 	  $this->userId[] = $value;
			 	 	  $this->SelectSon($value['id'],$where,$field,$layer,$type,$int+1);
			 	 }
	 		 }
		 }else{
		 	 return false;
		 }
	}


	//重新上传视频
	public function uploadVideo(){
		//判断用户宣誓视频是否通过,未通过可以修改通过后则不可修改
		if(empty($this->user['id'])) return '请登录';
		$model 			= new UserModel();
		$is_uploadVideo = $model->where(['id'=>$this->user['id']])->value('type_status');
		if($is_uploadVideo==2) return ajax(2,'您已通过审核不可再修改');
		//修改视频
		if(empty(input('videourl'))) return ajax(2,'请上传视频');
		$data['videourl'] = input('videourl');
		// 删除地址前缀
		$data['videourl'] = explode('/',trim($data['videourl'],'http://'));
		$data['videourl'] = '/'.implode('/',array_splice($data['videourl'],1));
		//修改视频地址
		$row = $model->where(['id'=>$this->user['id']])->update($data);
		if($row!==false){
			return ajax(1,'修改成功');
		}else{
			return ajax(2,'修改失败');
		}
	}

	//播放地址
	public function obtainVideo(){
		if(empty($this->user['id'])) return '请登录';
		$model = new UserModel();
		$row = $model->where(['id'=>$this->user['id']])->value('videourl');
		$row = "http://webbof.ewtouch.com/".$row;
		if($row!==false && !empty($row)){
			return ajax(1,'获取成功',$row);
		}else if($row!==false && empty($row)){
			return ajax(2,'您还没有上传视频请立即上传');
		}else{
			return ajax(2,'获取播放地址失败');
		}
	}








    /**
     * 增加业绩记录
     * @param int   $uid   用户id 
     * @param int   $money 增加的金额
     * @return bool
     */
    public function addAchievement($uid,$money){
    	 if(empty($uid) || empty($money)) return false;
    	 //查询出所有增加业绩的用户
    	 $list = $this->nodeLineSort($uid);
    	 if(empty($list)) return false;
    	 //计算出各自区域需要增加的金钱
    	 $data = [];
    	 $user_id = [];
    	 $region = [1=>'left_all_ach',2=>'right_all_ach'];
    	 foreach($list as $key=>$value){
    	 	foreach($list as $k=>$v){
    	 		if($value['id']!=$uid &&  $value['id']==$v['nid']){
    	 			 //判断是需要在增加左区还是右区
    	 			 $data[$key]['id']                           = $value['id'];
    	 			 $data[$key]['region']                       = 'left_ach';
    	 			 if($v['region']==2) $data[$key]['region']   = 'right_ach';
    	 			 $data[$key]['code'] 						 = $value['code'];
    	 			 $user_id[] 								 = $value['id'];
    	 			 $data[$key]['left_all_ach']				 = $value['left_all_ach'];
    	 			 $data[$key]['right_all_ach']				 = $value['right_all_ach'];
    	 			 $data[$key][$region[$v['region']]]			 = $value[$region[$v['region']]] + $money;
    	 		}
    	 	}
    	 }

    	 
    	 if(empty($data)) return true;
    	 //查询出当天 和 当月的 用户业绩记录
    	 $UsertestingModel = new UsertestingModel();
    	 $day    =  date('Y-m-d',time());         //当天时间
    	 $month  =  date('Y-m',time());           //当月时间

    	 $field	= ['id','uid','total_ach','left_ach','right_ach','left_all_ach','right_all_ach'];	
    	 $dayList     = $UsertestingModel->where(['uid'=>['in',$user_id],'current_time'=>$day,'type'=>1])->select();   
    	 //获取所有上级的今天记录
    	 $monthList   = $UsertestingModel->where(['uid'=>['in',$user_id],'current_time'=>$month,'type'=>2])->select(); 
    	 //获取所有上级的本月的记录
    	 $quarterList = $UsertestingModel->where(['uid'=>['in',$user_id],'current_time'=>$month,'type'=>3])->select();


    	 $day_user      = [];  //今天存在记录的用户
    	 $month_user    = [];  //这个月存在记录的用户
         $day_data      = [];  //今天的记录修改后
         $month_data    = [];  //本月的记录修改后
         $quarter_user  = [];  //这个季度存在记录的用户
         $quarter_data  = [];  //这个季度记录修改后

    	 //修改存在的用户记录
    	 foreach($data as $key=>$value){
    	 	$region = ['left_ach'=>'left_all_ach','right_ach'=>'right_all_ach'];
    	 	 // 今天
	    	 foreach($dayList	as $k => $v){
	    	 	 if($value['id']==$v['uid']){
	    	 	 	  $day_data[$key]['id']                			=  $v['id'];
	    	 	 	  $day_data[$key]['total_ach']   	   			=  $v['total_ach'] + $money;
	    	 	 	  $day_data[$key][$value['region']]    			=  $v[$value['region']]+ $money;
	    			  $day_data[$key]['updatetime']        			=  date('Y-m-d h:i:s',time());
	    	 	 	  $day_data[$key][$region[$value['region']]] 	=  $v[$region[$value['region']]]+ $money;
	    			  $day_user[]                          			=  $value['id'];
	    	 	 }
	    	 }
	    	 //本月
	    	 foreach($monthList as $a=>$b){
	    	 	 if($value['id']==$b['uid']){
	    	 	 	  $month_data[$key]['id']                	   =  $b['id'];
	    	 	 	  $month_data[$key]['total_ach']   	     	   =  $b['total_ach'] + $money;
	    	 	 	  $month_data[$key][$value['region']]    	   =  $b[$value['region']]+ $money;
	    			  $month_data[$key]['updatetime']        	   =  date('Y-m-d h:i:s',time());
	    	 	 	  $month_data[$key][$region[$value['region']]] =  $b[$region[$value['region']]]+ $money;
	    			  $month_user[]                          	   =  $value['id'];
	    	 	 }
	    	 }
	    	 
	    	 //本季度
	    	 foreach($quarterList as $c=>$d){
	    	 	 if($value['id']==$d['uid']){
	    	 	 	  $quarter_data[$key]['id']                  	 =  $d['id'];
	    	 	 	  $quarter_data[$key]['total_ach']   	   	 	 =  $d['total_ach'] + $money;
	    	 	 	  $quarter_data[$key][$value['region']]    		 =  $d[$value['region']]+ $money;
	    	 	 	  $quarter_data[$key]['total_ach']   	   		 =  $d['total_ach'] + $money;
	    	 	 	  $quarter_data[$key][$region[$value['region']]] =  $d[$region[$value['region']]]+ $money;
	    			  $quarter_data[$key]['updatetime']        		 =  date('Y-m-d h:i:s',time());
	    			  $quarter_user[]                          		 =  $value['id'];
	    	 	 }
	    	 }
    	 }

         
         $day_add     		= [];
         $month_add   		= [];
         $quarter_add 		= [];
         $quarter_add_user  = []; 

    	 // 获取本月时间
		 $thismonth = date('m');
		 $thisyear = date('Y');
    	 $date   =  $thisyear.'-'.$thismonth; //本月时间
    	 $top    =  $thismonth==1 ? 12 : $thismonth - 1;
    	 $date2  =  $thismonth.'-'.$top; 	     //上月时间
    	 $toptop =  $top==1 ? 12 : $top - 1;
    	 $date3  =  $thismonth.'-'.$toptop; 	 //上上月时间

         //添加不存在的记录
         foreach ($data as $key => $value){
         		//添加不存在的今天记录
         		if(!in_array($value['id'],$day_user)){
         			$day_add[$key]['uid']    		 = $value['id'];
         			$day_add[$key]['type']   		 = 1;
         			$day_add[$key]['u_code'] 		 = $value['code'];
         			$day_add[$key]['total_ach'] 	 = $money;
         			$day_add[$key]['left_ach']  	 = 0;
         			$day_add[$key]['right_ach'] 	 = 0;
         			$day_add[$key][$value['region']] = $money;
         			$day_add[$key]['left_all_ach']   = $value['left_all_ach'];
         			$day_add[$key]['right_all_ach']  = $value['right_all_ach'];
         			$day_add[$key]['updatetime']	 = date('Y-m-d h:i:s',time());
         			$day_add[$key]['addtime']	     = date('Y-m-d h:i:s',time());
         			$day_add[$key]['current_time']	 = $day;

         		}
         		//添加不存在的本月记录	
         		if(!in_array($value['id'],$month_user)){
         			$month_add[$key]['uid']    		 	= $value['id'];
         			$month_add[$key]['type']   		 	= 2;
         			$month_add[$key]['u_code'] 		 	= $value['code'];
         			$month_add[$key]['total_ach'] 	 	= $money;
         			$month_add[$key]['left_ach']  	 	= 0;
         			$month_add[$key]['right_ach'] 	 	= 0;
         			$month_add[$key][$value['region']]  = $money;
         			$month_add[$key]['left_all_ach']    = $value['left_all_ach'];
         			$month_add[$key]['right_all_ach']   = $value['right_all_ach'];
         			$month_add[$key]['updatetime']	 	= date('Y-m-d h:i:s',time());
         			$month_add[$key]['addtime']	     	= date('Y-m-d h:i:s',time());
         			$month_add[$key]['current_time']	= $month;
         		}

         		//获取不存在季度记录的用户id
         		if(!in_array($value['id'],$quarter_user)) $quarter_add_user[] = $value['id'];
         }


         //获取本季度没有记录的用户前两个月记录 并组合本季度的业绩
         if(!empty($quarter_add_user)){
         	     //获取本季度前两个月的业绩

         		 $where['uid'] 			= ['in',$quarter_add_user]; 
         	     $where['current_time'] = ['in',[$top,$toptop]];
         	     $list   = $UsertestingModel->field(['uid','left_ach','right_ach'])->where($where)->select();
         	     //获取总业绩

         	     $model  = new UserModel();
         	     $list2  = $model->field(['id','left_all_ach','right_all_ach','code'])->where(['id'=>['in',$quarter_add_user]])->select();

	 				 $region = ['left_ach'=>'left_all_ach','right_ach'=>'right_all_ach'];
	 				 // 如果前两个月没有业绩则直接添加一个空的新业绩

         	     foreach($quarter_add_user as $key=>$value){
         	     	 //组合总业绩
          	     	 foreach($list2 as $k=>$v){
         	     	 	 if($value==$v['id']){
         	     	 	 	 $quarter_add[$value]['left_all_ach']  = $v['left_all_ach'];
         	     	 	 	 $quarter_add[$value]['right_all_ach'] = $v['right_all_ach'];
         	     	 	 	 $quarter_add[$value]['u_code']	   = $v['code']; 
         	     	 	 }
         	     	 }
         	     	 // 初始化

         	     	 foreach($data as $k=>$v){
         	     	 	  if($v['id']==$value){
	             	     	  $quarter_add[$value]['uid']       		  = $value;
	             	     	  $quarter_add[$value]['left_ach']  		  = 0;
	             	     	  $quarter_add[$value]['right_ach'] 		  = 0;		
	             	     	  $quarter_add[$value]['type']	   			  = 3;
	             	     	  $quarter_add[$value]['updatetime']	   	  = date('Y-m-d h:i:s',time());
	             	     	  $quarter_add[$value]['addtime']	   		  = date('Y-m-d h:i:s',time());
	             	     	  $quarter_add[$value]['current_time']	   	  = $month;
	             	     	  $quarter_add[$value]['total_ach']			  = $money;
	             	     	  $quarter_add[$value][$v['region']]		  = $money;
	             	     	  $quarter_add[$value][$region[$v['region']]] += $money;
	             	     	  $quarter_add[$value]['type']				  = 3;
         	     	 	  }
         	     	 }
         	     } 
         }


         //执行修改记录
         $UsertestingModel->startTrans();
         try{
         	$result1 =  $UsertestingModel->saveAll($day_add);        //添加今天的业绩记录
				$result2 =  $UsertestingModel->saveAll($day_data);       //修改今天的业绩记录
				$result3 =  $UsertestingModel->saveAll($month_add);      //添加本月的业绩记录
				$result4 =  $UsertestingModel->saveAll($month_data);   	 //修改本月的业绩记录
				$result5 =  $UsertestingModel->saveAll($quarter_add);    //添加本月的业绩记录
				$result6 =  $UsertestingModel->saveAll($quarter_data);   //修改本月的业绩记录

				if($result1!==false && $result2!==false && $result3!==false && $result4!==false){
					$UsertestingModel->commit();
					return true;
				}else{
					$UsertestingModel->rollBack();
					return false;
				}
         }catch (PDOException $e){
		    $UsertestingModel->rollBack();
			return false;  
        }
    }


    /**
     * 返回所有上级信息
     * @param  int   $uid   用户id
     * @return bool
     */
    public function nodeLineSort($uid){
    	if(empty($uid)) return false;
    	$model = new UserModel();
    	//获取所有上级id
    	$all_nid = $model->where(['id'=>$uid])->value('all_nid');
    	//拼接自己的id 清除第一个系统id 0;
        if(empty($all_nid)) false;
        $all_nid = explode(',',$all_nid.$uid);
        array_shift($all_nid);
        //查看所有上级
        $field = ['id','nid','code','left_all_ach','right_all_ach','region'];
        $list  = $model->field($field)->where(['id'=>['in',$all_nid]])->select();
        return $list;
    }



   



}