<?php
namespace app\home\controller;
use app\backend\model\UserModel;
use app\backend\model\Config;
use think\Db;
use think\Request;
use think\Session;
use app\backend\model\RewardModel;
use app\backend\model\AwardModel;
//积分管理
class Integral extends Base
{

	const CLASSS      = [1=>'注册积分',2=>'游戏积分',3=>'奖励积分',4=>'消费积分',5=>'电子积分',6=>'获赠积分',7=>'购物积分',8=>'bof',9=>'基金币',10=>'复投积分']; 
	
	const TYPE        = [1=>'注册积分',2=>'奖励积分',3=>'获赠积分',4=>'消费积分',5=>'游戏积分',6=>'购物积分'];

	// 可以转换的积分	
	const SWITCH      = [2,3,4,6];
	// 支持转化到什么类型的积分 
	const SWITCHTYPE  = [6 => [4 , 5],      
						 2 => [1 , 4 , 5],   
						 3 => [1 , 4], 
						 4 => [5] 
						]; 
	// 转换比例
	const SWITCHRATIO = [ 6 => [ 4 => [1 , 6.5], 5 => [1 , 1]], 
						  2 => [ 1 => [1 , 1] , 4 => [1 , 6.5] , 5 => [1 , 1]], 
						  3 => [ 1 => [1 , 1] , 4 => [1 , 6.5]],
						  4 => [ 5 => [6.5 , 1]]
						];
	const TYPENAME  = [1=>'reg_score',2=>'prize_score',3=>'receive_score',4=>'con_score',5=>'game_score',6=>'pay_score']; //积分字段名称

	//搜索条件
	private $where = [];
	//分页设置
	private $limit = [];


	//初始化
	public function  _initialize()
	{

		parent::_initialize();

		//拼接公共搜索条
		$param = input('param.');
	    if(!empty($param['start']) and empty($param['end'])){
            $this->where['created_at'] = ['>=',strtotime($param['start'])];
        }
        if(empty($param['start']) and !empty($param['end'])){
            $this->where['created_at'] = ['<=',strtotime($param['end']."23:59:59")];
        }
        if(!empty($param['start']) and !empty($param['end'])){
            $this->where['created_at'] = ['between',[strtotime($param['start']),strtotime($param['end']."23:59:59")]];
        }

        //分页
        if(empty(input('pageInt'))){
        	$this->limit['pageInt'] = 12;
        }else{
        	if(!is_numeric(input('pageInt'))){
        		die;
        	}
        	$this->limit['pageInt'] = intval(input('pageInt'));
        }
        // 设置分页
        if(empty(input('pageSize'))){
        	$this->limit['pageSize'] = 0; 
        }else{
        	if(!is_numeric(input('pageSize'))){
        		die;
        	}
        	$this->limit['pageSize'] = (intval(input('pageSize')) - 1) * $this->limit['pageInt'];       	
        }

	}



	 /**
     * 积分明细
     * @return 
     */
	public function integralAccount()
	{	
	    $uid = Session::get('home.user')['id'];
	    if(empty($uid)) return ajax(2,'请登录');
	    $AwardModel = new AwardModel();
	    $this->where['uid'] = $uid;
	 
	    $field = ['id','ucode','truename','declaration_form','total','contribution','balance','openup','date']; 
	    $list  = $AwardModel->field($field)->where($this->where)->limit($this->limit['pageSize'],$this->limit['pageInt'])->order('id desc')->select();
	    $count = $AwardModel->where($this->where)->count();
	    $data['list']  = $list;
	    $data['count'] = $count;
	    return ajax(1,'获取成功',$data);
	}





	 /**
     * 积分明细
     * @return 
     */
	public function IncomeDetail(){
	    $uid = Session::get('home.user')['id'];
	    if(empty($uid)) return ajax(2,'请登录');
	    if(!empty(input('type')) && input('type')>0 && input('type') <5 )  $this->where['type'] = input('type');
	    if(!empty(input('date')))  $this->where['addtime'] = ['like',input('date').'%'];

	    $this->where['uid'] = $uid;

	    $RewardModel = new RewardModel();
	    $field = ['id','ucode','truename','type','actual_money','con_score','prize_score','fund_gold','addtime','remarks'];
	    $list  = $RewardModel->field($field)->where($this->where)->limit($this->limit['pageSize'],$this->limit['pageInt'])->order('id desc')->select();
	    $count = $RewardModel->where($this->where)->count();

	    $type = [1=>'D类',2=>'A类',3=>'B类',4=>'C类'];
	    foreach($list as $key=>$value){
	    	 $list[$key]['type'] = $type[$value['type']];
	    	 $list[$key]['prize_score'] = round($value['prize_score'],2);
	    	 $list[$key]['con_score']	= round($value['con_score'],2); 
	    	 $list[$key]['fund_gold']   = round($value['fund_gold'],2); 
	    }

	    $data['list']  = $list;
	    $data['count'] = $count;
	    $data['page']  =  ceil($this->limit['pageSize'] / $this->limit['pageInt']) + 1; 
	    $data['countPage'] = ceil( $count / $this->limit['pageInt']);
	    return ajax(1,'获取成功',$data);
	}



	//积分转换明细
	public function convertAccount()
	{	
		$type = [1=>1,2=>3,3=>6,4=>4,5=>2,6=>7];
		$param = input('param.');
		if(!empty(input('intoClass'))){
			if(!isset(self::TYPE[input('intoClass')])){
				 return ajax(2,'您搜索的转出积分类型不存在');
			}else{
				$this->where['intoClass'] = $type[input('intoClass')];
			}
		}
		if(!empty(input('turnoutClass'))){
			if(!isset(self::TYPE[input('turnoutClass')])){
				 return ajax(2,'您搜索的转入积分类型不存在');
			}else{
				$this->where['turnoutClass'] = $type[input('turnoutClass')];
			}
		}

		$this->where['uid'] = Session::get('home.user')['id'];
		$list = Db::name('inte_account')->order('id desc')->where($this->where)->limit($this->limit['pageSize'],$this->limit['pageInt'])->select();
		$count = Db::name('inte_account')->where($this->where)->count();
		if($list !==false){
			foreach($list as $key=>$value){		
				if(isset($value['created_at']) && !empty($value['created_at'])){
					 $list[$key]['created_at'] = date('Y-m-d H:i:s',$value['created_at']);
				}
				if(isset($value['intoClass']) && !empty($value['intoClass'])){
					$list[$key]['intoClass'] = self::CLASSS[$value['intoClass']];
				}
				if(isset($value['turnoutClass']) && !empty($value['turnoutClass'])){
					$list[$key]['turnoutClass'] = self::CLASSS[$value['turnoutClass']];
				}

			}
			$data['count'] = $count;
			$data['list']  =  $list;  //数据集合	
			$data['countPage'] = ceil($count/$this->limit['pageInt']); //总页数
			$data['page'] =  ($this->limit['pageSize']/$this->limit['pageInt'])+1;  //当前第几页
			return ajax(1,'查询成功',$data);
		}else{
			return ajax(2,'查询失败');
		}
	}


    /**
     * 执行转换积分
     * @param  type   要转的积分 
     * @param  types  被转的积分
     * @param  Amount 转化的金额
     * @return 
     */
	public function shopintegralSwitch(){
		$type = [1=>1,2=>3,3=>6,4=>4,5=>2,6=>7];
		$post = Request()->post();
		if(empty($post['type']) || empty($post['types'])){
			return ajax(2,"请选择需要转换的积分种类");
		}else{
			$post['type'] =  intval($post['type']);
			$post['types'] = intval($post['types']);
			$post['Amount'] = intval($post['Amount']);
			if(!is_numeric($post['type']) || !is_numeric($post['types']) || $post['type']<1 || $post['type']>count(self::TYPE) || $post['types']>count(self::TYPE)  || $post['types']<1){
				return ajax(2,"您选择的积分种类不存在");
			}
		}

		
		//检查金额
		if(empty($post['Amount'])){
			return ajax(2,'请输入您要转换的金额');
		}else{

			 if(!is_numeric($post['Amount']) || $post['Amount']<1){
			 	 return ajax(2,'请输入正确的金额');
			 }
			 //获取选择需要转化的积分余额是否大于需求
			 $id = Session::get('home.user')['id'];
			 $model = new UserModel();
			 $Integral = $model->field([self::TYPENAME[$post['type']],self::TYPENAME[$post['types']],'two_password'])->where(['id'=>$id])->find();
			 //判断支付密码是否正确
			 if(empty(Request()->post('two_password'))) return ajax(2,'请输入支付密码');
			 if($Integral['two_password'] != md5(Request()->post('two_password'))) return ajax(2,'支付密码不正确');
			 if($post['Amount']>$Integral[self::TYPENAME[$post['type']]]){
			 	 return ajax(2,'您的'.self::TYPE[$post['type']].'余额不足');
			 }
			 //只能转为整数
			 if(!is_int($post['Amount'])){
			 	return ajax(2,'必须转入整数积分');
			 }
		}

		//判断是否为可转换积分类型
		if(!in_array($post['type'],self::SWITCH)){
			return ajax(2,self::TYPE[$post['type']].'不可以转换其他积分');
		}
		if(!in_array($post['types'],self::SWITCHTYPE[$post['type']])){
			return ajax(2,self::TYPE[$post['type']].'不可以转化'.self::TYPE[$post['types']]);
		}
		//执行转换
		$Amount = (($post['Amount']/self::SWITCHRATIO[$post['type']][$post['types']][0]) *  (self::SWITCHRATIO[$post['type']][$post['types']][1]));
		//计算转换后金额
		$data[self::TYPENAME[$post['type']]] = $Integral[self::TYPENAME[$post['type']]] - $post['Amount'];

		$data[self::TYPENAME[$post['types']]] = $Integral[self::TYPENAME[$post['types']]] + $Amount;
		//扣除记录
		$data1['uid']       = $id; 
		$data1['score']     = "-".$post['Amount'];
		$data1['cur_score'] = $data[self::TYPENAME[$post['type']]];
		$data1['remark']    = self::TYPE[$post['type']]."转换".self::TYPE[$post['types']];
		$data1['type']      = $type[$post['type']];
		$data1['is_add']	= 2;
		$data1['class']     = 1;
		$data1['from_uid']  = $id;
		$data1['created_at'] = time();
		$data1['source'] =12;
		//添加记录
		$data2['uid']       = $id; 
		$data2['score']     = $Amount;
		$data2['cur_score'] = $data[self::TYPENAME[$post['types']]];
		$data2['remark']    = self::TYPE[$post['type']]."转换".self::TYPE[$post['types']];
		$data2['type']      = $type[$post['types']];
		$data2['is_add']	= 1;
		$data2['class']     = 1;
		$data2['from_uid']  = $id;		
		$data2['created_at'] = time();
		$data2['source'] =12;
		//积分转换记录
		$data3['uid'] = $id;
		$data3['turnoutClass'] = $type[$post['type']];
		$data3['turnoutAmount'] = $post['Amount'];
		$data3['intoClass'] = $type[$post['types']];
		$data3['intoAmount'] = $Amount;
		$data3['front'] = $Integral[self::TYPENAME[$post['types']]];
		$data3['after'] = $data[self::TYPENAME[$post['types']]];
		$data3['created_at'] = time();

		//执行转化
		Db::startTrans();
		try{
		    $row  = Db::name('users')->where('id',$id)->update($data); //执行转化
		    $row1 = Db::name('account')->insert($data1);  //添加扣除记录
		    $row2 = Db::name('account')->insert($data2); //添加新增记录
		    $row3 = Db::name('inte_account')->insert($data3);
		    // 提交事务
		    if($row!==false && $row1!==false && $row2!==false && $row3 !==false){
		    	Db::commit();
		    	return ajax(1,'转化成功','');
		    }else{
		 	 	Db::rollback();
		     }
		} catch (\Exception $e){
		    // 回滚事务
		    Db::rollback();
		    return ajax(2,'转换失败','');
		}
	}	


    /**
     * 系统执行积分转换
     * @param  type   要转的积分 
     * @param  types  被转的积分
     * @param  Amount 转化的金额
     * @return 
     */
	public function  integralSwitch()
	{
		$type = [1=>1,2=>3,3=>6,4=>4,5=>2,6=>7];
		$post = Request()->post();
		if(empty($post['type']) || empty($post['types'])){
			return ajax(2,"请选择需要转换的积分种类");
		}else{
			$post['type'] =  intval($post['type']);
			$post['types'] = intval($post['types']);
			$post['Amount'] = intval($post['Amount']);
			if(!is_numeric($post['type']) || !is_numeric($post['types']) || $post['type']<1 || $post['type']>count(self::TYPE) || $post['types']>count(self::TYPE)  || $post['types']<1){
				return ajax(2,"您选择的积分种类不存在");
			}
		}


		//检查金额
		if(empty($post['Amount'])){
			return ajax(2,'请输入您要转换的金额');
		}else{

			 if(!is_numeric($post['Amount']) || $post['Amount']<1){
			 	 return ajax(2,'请输入正确的金额');
			 }
			 //获取选择需要转化的积分余额是否大于需求
			 $id = Session::get('home.user')['id'];
			 $model = new UserModel();
			 $Integral = $model->field([self::TYPENAME[$post['type']],self::TYPENAME[$post['types']]])->where(['id'=>$id])->find();
			 if($post['Amount']>$Integral[self::TYPENAME[$post['type']]]){
			 	 return ajax(2,'您的'.self::TYPE[$post['type']].'余额不足');
			 }
			 //只能转为整数
			 if(!is_int($post['Amount'])){
			 	return ajax(2,'必须转入整数积分');
			 }
		}

		//判断是否为可转换积分类型
		if(!in_array($post['type'],self::SWITCH)){
			return ajax(2,self::TYPE[$post['type']].'不可以转换其他积分');
		}
		if(!in_array($post['types'],self::SWITCHTYPE[$post['type']])){
			return ajajx(2,self::TYPE[$post['type']].'不可以转化'.self::TYPE[$post['types']]);
		}
		//执行转换
		$Amount = (($post['Amount']/self::SWITCHRATIO[$post['type']][$post['types']][0]) *  (self::SWITCHRATIO[$post['type']][$post['types']][1]));
		//计算转换后金额
		$data[self::TYPENAME[$post['type']]] = $Integral[self::TYPENAME[$post['type']]] - $post['Amount'];

		$data[self::TYPENAME[$post['types']]] = $Integral[self::TYPENAME[$post['types']]] + $Amount;
		//扣除记录
		$data1['uid']       = $id; 
		$data1['score']     = "-".$post['Amount'];
		$data1['cur_score'] = $data[self::TYPENAME[$post['type']]];
		$data1['remark']    = self::TYPE[$post['type']]."转换".self::TYPE[$post['types']];
		$data1['type']      = $type[$post['type']];
		$data1['is_add']	= 2;
		$data1['class']     = 1;
		$data1['from_uid']  = $id;
		$data1['created_at'] = time();
		$data1['source'] =12;
		//添加记录
		$data2['uid']       = $id; 
		$data2['score']     = $Amount;
		$data2['cur_score'] = $data[self::TYPENAME[$post['types']]];
		$data2['remark']    = self::TYPE[$post['type']]."转换".self::TYPE[$post['types']];
		$data2['type']      = $type[$post['types']];
		$data2['is_add']	= 1;
		$data2['class']     = 1;
		$data2['from_uid']  = $id;		
		$data2['created_at'] = time();
		$data2['source'] =12;
		//积分转换记录
		$data3['uid'] = $id;
		$data3['turnoutClass'] = $type[$post['type']];
		$data3['turnoutAmount'] = $post['Amount'];
		$data3['intoClass'] = $type[$post['types']];
		$data3['intoAmount'] = $Amount;
		$data3['front'] = $Integral[self::TYPENAME[$post['types']]];
		$data3['after'] = $data[self::TYPENAME[$post['types']]];
		$data3['created_at'] = time();

		//执行转化
		Db::startTrans();
		try{
		    $row  = Db::name('users')->where('id',$id)->update($data); //执行转化
		    $row1 = Db::name('account')->insert($data1);  //添加扣除记录
		    $row2 = Db::name('account')->insert($data2); //添加新增记录
		    $row3 = Db::name('inte_account')->insert($data3);
		    // 提交事务
		    if($row!==false && $row1!==false && $row2!==false && $row3 !==false){
		    	Db::commit();
		    	return ajax(1,'转化成功','');
		    }else{
		 	 	Db::rollback();
		     }
		} catch (\Exception $e){
		    // 回滚事务
		    Db::rollback();
		    return ajax(2,'转换失败','');
		}
	}


	//获取登录用户积分数量
	public function Transfers()
	{		
		$id = Session::get('home.user')['id'];
		$result = Db::name('users')->field(['reg_score','prize_score','receive_score','con_score','prize_score','pay_score'])->where(['id'=>$id])->find();
		if($result!==false){
			return ajax(1,'查询成功',$result);
		}else{
			return ajax(2,'查询失败');
		}	
	}


	//购物积分互转 
	public function DoTransfers()
	{
		if(empty(input('UserId'))){
			 return ajax(2,'好友用户名不能为空');
		}else{
		   //不能转给自己
		   if(input('UserId')==Session::get('home.user')['code']){
		   	   return ajax(2,'不能给自己转账');
		   }
		   // 被转人信息
		   $user = Db::name('users')->field(['id','pay_score','code','all_nid'])->where(['code'=>input('UserId')])->find();	
		   if(count($user)<1 || $user===false){
		   		return ajax(2,'您输入的好友不存在');
		   }
		}
		//转账人信息
		$users = Db::name('users')->field(['id','pay_score','code','all_nid'])->where(['id'=>Session::get('home.user')['id']])->find();

		//检查被转人与转入人是否在一条线上
	
		if(strlen($users['all_nid'])>strlen($user['all_nid'])){
			$all_nid = $user['all_nid'].$user['id'];
			if(!(stripos($users['all_nid'],$all_nid)!==false)){
				 return ajax(2,"您输入的好友不存在");
			}
		}else{
			$all_nid = $users['all_nid'].$users['id'];
			if(!(stripos($user['all_nid'],$all_nid)!==false)){
				 return ajax(2,"您输入的好友不存在");
			}
		}

		//检查余额是否充足
		if(empty(input('Amount')) || !is_numeric(input('Amount')) || input('Amount')<1){
			return ajax(2,'请输入转出金额');
		}else{
			 if(input('Amount')>$users['pay_score']){
			 		return  ajax(2,'您的余额不足');
			 }
		}


		//转账人
		$data['pay_score'] = $users['pay_score'] - input('Amount'); 
		//被转人
		$data1['pay_score'] = $user['pay_score'] + input('Amount');
		//转账记录
		$data2['uid'] = Session::get('home.user')['id'];
		$data2['from_uid'] = $user['id'];
		$data2['score'] = '-'.input('Amount');
		$data2['cur_score'] = $data['pay_score'];
		$data2['remark'] = '会员转账';
		$data2['is_add'] = 2;
		$data2['class'] = 1;
		$data2['type'] = 7;
		$data2['created_at'] = time();
		$data2['source'] =9;
		//被转人记录
        $data3['uid'] = $user['id'];
        $data3['from_uid'] = Session::get('home.user')['id'];
        $data3['score'] = input('Amount');
        $data3['cur_score'] = $data1['pay_score'];
		$data3['remark'] = '会员转账';
		$data3['is_add'] = 1;
		$data3['class'] = 1;
		$data3['type'] = 7;
		$data3['created_at'] = time();
		$data3['source'] =9;

		//执行转化
		Db::startTrans();
		try{
		    $row  = Db::name('users')->where('id',$users['id'])->update($data); //执行转化
		    $row1 = Db::name('users')->where('id',$user['id'])->update($data1);
		    $row2 = Db::name('account')->insert($data2);  //添加扣除记录
		    $row3 = Db::name('account')->insert($data3); //添加新增记录
		    // 提交事务
		    if($row!==false && $row1!==false && $row2!==false && $row3 !==false){
		    	Db::commit();
		    	return ajax(1,'转出成功','');
		    }else{
		 	 	Db::rollback();
		     }
		} catch (\Exception $e){
		    // 回滚事务
		    Db::rollback();
		    return ajax(2,'转出失败','');
		}		
	}



	//互转记录
	public function TransfersRecord()
	{

		if(!empty(input('code'))){
			$id = Db::name('users')->where(['code'=>input('code')])->value('id');
			if(empty($id) || $id===false){
				return ajax(2,'该用户不存在');
			}else{
				$this->where['from_uid'] = $id;
			}
		}
		$this->where['source'] = 9;
		$this->where['class'] = 1;
		$this->where['type'] = 7;
		//返回记录
		$row = $this->account();
		//计算转前余额
	    foreach($row['list'] as $key=>$value)
	    {
			if($value['is_add']==1){
				$row['list'][$key]['front_balance'] = $value['score']+$value['cur_score'];
			}else{
				$row['list'][$key]['front_balance'] = $value['cur_score']-$value['score'];
			}
	    }
		if($row!==false){
			return ajax(1,'查询成功',$row);
		}else{
			return ajax(2,'查询失败');
		}
	}





	//财务明细
	public function FinanceAccount()
	{	
		$arr = [1,2,3,6,7,8,9,10,11,12,13,14,15,16];
		//财务类型
		if(!empty(input('source')) && is_numeric(input('source'))){
			 if(!in_array(input('source'),$arr)){
			 	return ajax(2,'您搜索的财务类型不存在');
			 }
			 $this->where['source'] = input('source');
		}else{
			$this->where['source'] = ['in',$arr];
		}
		if(!empty(input('type')) || is_numeric(input('type'))){
			$this->where['type'] = input('type');
		}

		//返回记录
		$row = $this->account();
		if($row!==false){
			return ajax(1,'查询成功',$row);
		}else{
			return ajax(2,'查询失败');
		}

	}



	public function account(){

		$IsAdd = ['未知','收入','支出'];

		$this->where['uid'] = Session::get('home.user')['id'];
		$count = Db::name('account')->where($this->where)->count();
		$list = Db::name('account')->order('id desc')->where($this->where)->limit($this->limit['pageSize'],$this->limit['pageInt'])->select();

		//状态转换汉字
		if($list !==false){
			foreach($list as $key=>$value){		
				if(isset($value['created_at']) && !empty($value['created_at'])){
					 $list[$key]['created_at'] = date('Y-m-d H:i:s',$value['created_at']);
				}
				if(isset($value['type']) && !empty($value['type'])){
					$list[$key]['type'] = self::CLASSS[$value['type']];
				}
				if(!empty($value['is_add']) && isset($IsAdd[$value['is_add']])){
					$list[$key]['is_add'] = $IsAdd[$value['is_add']];
				}
				if(!empty($value['from_uid'])){
					$row[] = $value['from_uid'];
				}
			}

			//查询出所有用户编号
			if(!empty($row)){
				$result = Db::name('users')->field(['id','code'])->where(['id'=>['in',$row]])->select();
				if(!empty($result)){
					// 用户编号赋值
					foreach($list as $key=>$value)
					{
						foreach($result as $k=>$v){
							if($value['from_uid']===0){
								$list[$key]['from_code'] = '系统';
							}
							if($value['from_uid']==$v['id']){
								$list[$key]['from_code'] = $v['code'];
							}
						}
						//金额去掉小数位
						$list[$key]['score'] = sprintf("%.2f",$value['score']);
					    $list[$key]['cur_score'] = sprintf("%.2f",$value['cur_score']);
					}
				}
			}


			//拼装数据
			$data['count'] = $count;
			$data['list']  =  $list;  //数据集合	
			$data['countPage'] = ceil($count/$this->limit['pageInt']); //总页数
			$data['page'] =  ($this->limit['pageSize']/$this->limit['pageInt'])+1;  //当前第几页

			return $data;
		}else{
			return false;
		}

	}

}