<?php
namespace app\home\controller;

use app\backend\model\UserModel;
use app\backend\model\AccountModel;
use app\backend\model\Config;
use app\backend\model\RewardModel;
use app\backend\model\AwardModel;
use think\Db;
use think\Exception;
use think\Log;
use think\Controller;
use app\home\controller\Distribution;

class Distribution extends Controller{



    /**
     * 执行 添加记录 分配积分
     * @param int   $uid       用户id 
     * @param int   $fid       来源id
     * @param int   $money     金额    
     * @param int   $type      类型
     * @param int   $remarks   备注 
     * @return bool
    */
	public function implement($uid,$fid,$money,$type,$remarks=null){

		 
		  if(empty($money) || empty($uid) || empty($fid) || empty($type)) return false;

		  $UserModel = new UserModel();

		  //获取用户信息
		  $uList = $UserModel->field(['id','code','truename','prize_score','con_score','fund_gold'])->where(['id'=>$uid])->find();

		  if(empty($uList)) return false;
		  $data = [];
		  //计算增加金额
		  $prize_score   =   ($money  *  0.98 ) * 0.8 ;
		  $con_score     =   (( $money *  0.98 ) * 0.2 ) * 6.5 ;  
		  $fund_gold     =   $money   *  0.02 ;   

		  $integral['prize_score'] =  $prize_score;
		  $integral['con_score']   =  $con_score;
		  $integral['fund_gold']   =  $fund_gold; 

		  $data['prize_score'] = $uList['prize_score']  +  $prize_score;   	          //奖励积分 
		  $data['con_score']   = $uList['con_score']    +  $con_score;                //消费积分 
		  $data['fund_gold']   = $uList['fund_gold']    +  $fund_gold;                //慈善基金

		  $AccountModel 	 = new AccountModel();       //资金明细
		  $RewardModel 		 = new RewardModel();        //奖励明细
		  $AwardModel        = new AwardModel();         //奖励记录

		  //增加奖金明细
		  $data2['uid']          = $uid;
		  $data2['fid']          = $fid;
		  $data2['ucode']        = $uList['code']; 
		  $data2['truename']     = $uList['truename'];
		  $data2['type']     	 = $type;
		  $data2['actual_money'] = $money;
		  $data2['con_score']    = $con_score;
		  $data2['prize_score']  = $prize_score;
		  $data2['fund_gold']	 = $fund_gold;
		  $data2['addtime']	  = date('Y-m-d h:i:s',time());
		  $data2['remarks']	  = $remarks;
		

		  $typeName = [1=>'declaration_form',2=>'openup',3=>'balance',4=>'contribution'];
		  if(empty($typeName[$type])) return false;

		  $UserModel->startTrans();
		  $AccountModel->startTrans();
		  $RewardModel->startTrans();
		  $AwardModel->startTrans();

	      try{
			  //增加奖金
			  $result  = $UserModel->where(['id'=>$uid])->update($data);

			  //增加记录
			  $result1 = $RewardModel->insert($data2);
			  if($result===false || $result1===false){
			  	  $UserModel->rollBack();
			  	  $RewardModel->rollBack();
			  	  return false;
			  }

			  $date = date('Y-m-d',time());
			  //判断当天奖金记录是否存在,不存在则添加 存在则修改
			  $Award  = $AwardModel->field(['id',$typeName[$type],'total'])->where(['uid'=>$uid,'created_at'=>$date])->find();
			  if(empty($Award)){
			  	  $data3['created_at']  	=  date('Y-m-d',time());
			  	  $data3['uid']	  			=  $uid;
			  	  $data3['fid']   			=  $fid;
			  	  $data3[$typeName[$type]]  =  $money;
			  	  $data3['total']           =  $money;
			  	  $data3['ucode']			=  $uList['code'];
			  	  $data3['truename']		=  $uList['truename'];

			  	  $result2 = $AwardModel->insert($data3);
			  }else{
			  	  $data3['id']                   = $Award['id'];
			  	  $data3[$typeName[$type]]       = $Award[$typeName[$type]] + $money;
			  	  $data3['total']    			= $Award['total'] + $money;
			  	  $result2  = $AwardModel->update($data3);
			  }

			  if(!isset($result2) || $result2===false){
			  	  $UserModel->rollBack();
			  	  $RewardModel->rollBack();
			  	  $AwardModel->rollBack();
			  	  return false;
			  }


			  $types    = ['prize_score'=>3,'con_score'=>4,'fund_gold'=>9];

			  $jiangli  = [1=>15,2=>1,3=>2,4=>3]; 

			  $data4['from_uid'] = $fid;
			  $data4['source']   = $jiangli[$type];
			  $data4['class']    = 1;
			  $data4['is_add']   = 1;
			  $data4['uid']      = $uid;
			  $data4['remark']   = $remarks;
			  //增加每个积分的记录

			  foreach($integral as $key=>$value){
			        $data4['type']	     = $types[$key];
			        $data4['score'] 	 = $value;
			  		$data4['cur_score']  = $uList[$key]+$value; 
			  		$data4['status']     = 1;
			  		$data4['created_at'] = time();

			  		$result3 = $AccountModel->insert($data4);
			  		if($result3===false){
				  	   $UserModel->rollBack();
				  	   $RewardModel->rollBack();
				  	   $AwardModel->rollBack();
				  	   $AccountModel->rollBack();
			           return  false;
			  		}

			  }


			   // 提交事务
	  	       $UserModel->commit();
	  	       $RewardModel->commit();
	  	       $AwardModel->commit();
	  	       $AccountModel->commit();
               return  true;
	       }catch( PDOException $e){
		  	   $UserModel->rollBack();
		  	   $RewardModel->rollBack();
		  	   $AwardModel->rollBack();
		  	   $AccountModel->rollBack();
	           return  false;
	       }
	 }







}