<?php
	namespace app\backend\controller;

	use app\backend\model\UserModel;
	use app\backend\model\ApplyModel;
	use think\Request;
	use think\Db;
	use Service\Useractivate;
	use Service\Stock;
	use Service\Rerformance;
	use Service\Nineservice;
	use app\backend\model\UsertestingModel;
	/*
	* 业绩评估
	*/
	class Hometesting extends Base{


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
	    	 foreach($list as $key=>$value){
	    	 	foreach($list as $k=>$v){
	    	 		if($value['id']!=$uid &&  $value['id']==$v['nid']){
	    	 			 //判断是需要在增加左区还是右区
	    	 			 $data[$key]['id']                                 = $value['id'];
	    	 			 $data[$key]['region']                       = 'left_ach';
	    	 			 if($v['region']==2) $data[$key]['region']   = 'right_ach';
	    	 			 $data[$key]['code'] 						 = $value['code'];
	    	 			 $user_id[] 								 = $value['id'];
	    	 		}
	    	 	}
	    	 }

	    	 
	    	 if(empty($data)) return true;
	    	 //查询出当天 和 当月的 用户业绩记录
	    	 $UsertestingModel = new UsertestingModel();
	    	 $day    =  date('Y-m-d',time());         //当天时间
	    	 $month  =  date('Y-m',time());           //当月时间

	    	 $field	= ['id','uid','total_ach','left_ach','right_ach'];	
	    	 $dayList    = $UsertestingModel->where(['uid'=>['in',$user_id],'current_time'=>$day,'type'=>1])->select();   
	    	 //获取所有上级的今天记录
	    	 $monthList  = $UsertestingModel->where(['uid'=>['in',$user_id],'current_time'=>$month,'type'=>2])->select(); 
	    	 //获取所有上级的本月的记录

	    	 $day_user      = [];  //今天存在记录的用户
	    	 $month_user    = [];  //这个月存在记录的用户
             $day_data      = [];  //今天的记录修改后
             $month_data    = [];  //本月的记录修改后


	    	 //获取不存在记录的用户id,修改存在的用户记录
	    	 foreach($data as $key=>$value){
	    	 	 // 今天
		    	 foreach($dayList	as $k => $v){
		    	 	 if($value['id']==$v['uid']){
		    	 	 	  $day_data[$key]['id']                =  $v['id'];
		    	 	 	  $day_data[$key]['total_ach']   	   =  $v['total_ach'] + $money;
		    	 	 	  $day_data[$key][$value['region']]    =  $v[$value['region']]+ $money;
		    			  $day_data[$key]['updatetime']        =  date('Y-m-d h:i:s',time());
		    			  $day_user[]                          =  $value['id'];
		    	 	 }
		    	 }
		    	 //本月
		    	 foreach($monthList as $a=>$b){
		    	 	 if($value['id']==$b['uid']){
		    	 	 	  $month_data[$key]['id']                =  $b['id'];
		    	 	 	  $month_data[$key]['total_ach']   	     =  $b['total_ach'] + $money;
		    	 	 	  $month_data[$key][$value['region']]    =  $b[$value['region']]+ $money;
		    			  $month_data[$key]['updatetime']        =  date('Y-m-d h:i:s',time());
		    			  $month_user[]                          =  $value['id'];
		    	 	 }
		    	 }
	    	 }

             
             $day_add   = [];
             $month_add = [];
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
             			$month_add[$key]['updatetime']	 	= date('Y-m-d h:i:s',time());
             			$month_add[$key]['addtime']	     	= date('Y-m-d h:i:s',time());
             			$month_add[$key]['current_time']	= $month;
             		}
             }


             //执行修改记录
             $UsertestingModel->startTrans();
	         try{
	         	$result1 =  $UsertestingModel->saveAll($day_add);
  				$result2 =  $UsertestingModel->saveAll($month_add);
  				$result3 =  $UsertestingModel->saveAll($day_data);
  				$result4 =  $UsertestingModel->saveAll($month_data);

 				if($result1!==false && $result2!==false && $result3!==false && $result4!==false){
 					$UsertestingModel->commit();
 					return true;
 				}else{
 					$UsertestingModel->rollBack();
 					return false;
 				}
	         }catch (PDOException $e){
					
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