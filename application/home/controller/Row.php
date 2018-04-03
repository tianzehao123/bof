<?php
namespace app\home\controller;

// use app\backend\model\Config;
use think\Db;
//排位算法 + 开心奖
	/* `uid` `pid` `level` `node`  `num` `created_at` */
class Row
{	
	/**请求接口进行公排
	 * @param bool $uid
	 * @return bool
	 * @throws \think\Exception
     */
	public function rowApi($uid=false,$money=0)
	{
		// die('1');
		if(!$uid)$uid=input("uid");
		$row = db('row')->where(['uid'=>$uid])->find();
		if($row)return false;
		$insert = [];
		#传入uid，如果是顶级节点，自动生成下级3个位置 即1，2 层
		$user = db('users')->where(['id'=>$uid])->find();
		#有上级，1 子节点未满 
		$id = $this->perch($user['pid']);
		// dump($id);die;
		$update = ['pid'=>$user['pid'],'uid'=>$uid,'created_at'=>time()];
		db('row')->where(['id'=>$id])->update($update);	
		$this->happy($uid,$money);
		return 'success';
	}

	public function test(){
		/*$data = db('order')->where(['status'=>['>',1]])->select();
		foreach ($data as $k => $v) {
			if(db('users')->where(['id'=>$v['uid']])->find()){
				dump($this->rowApi($v['uid'],$v['price']));
			}
		}*/
		// $this->rowApi(29,998);	
		// $this->happy(29,998);	
		// $this->rowApi(30,998);	
		// $this->rowApi(31,398);	
	}
	public function add_node(){
		$aa = db('row')->order('id desc')->find();
		$node = $aa['node']+1;
		for ($i=$node; $i <5000+$node ; $i++) { 
			$this->perch1($i);
		}	
	}

	/**添加占位 ,并返回第一个空位
	 * @param $pid
	 * @return mixed
     */
	private function perch($pid,$type=1)
	{
		$node = db('row')->where(['uid'=>$pid])->find();
		$data = db('row')->where(['node'=>$node['id']])->select();
		if(empty($data)){
			#1 节点下没有添加节点	
			$insert = [];
			$insert[] = ['node'=>$node['node'],'num'=>'1','level'=>$node['level']+1,'created_at'=>time()];
			$insert[] = ['node'=>$node['node'],'num'=>'2','level'=>$node['level']+1,'created_at'=>time()];
			$insert[] = ['node'=>$node['node'],'num'=>'3','level'=>$node['level']+1,'created_at'=>time()];
			db('row')->insertAll($insert);
			return $this->perch($pid);
		}else{
			$num = db('row')->where(['node'=>$node['id'],'uid'=>['<>',0]])->count();

			if($num < 3){
				#2 有节点还没用完
				return db('row')->where(['node'=>$node['id'],'uid'=>['=',0]])->order('id asc')->value('id');
			}else{
				#3 有节点用完了
				$judge = [];
				
				foreach ($data as $k => $v) {
					$judge[] = $this->perch($v['uid']);
				}
				$r = $judge[0];
				foreach ($judge as $ko=> $vo) {
					$r = $r<=$vo?$r:$vo;
				}
				return $r;
			}	
		}
	}

	private function perch1($node)
	{
		$data = db('row')->where(['id'=>$node])->find();
		#1 节点下没有添加节点	
		$insert = [];
		$insert[] = ['node'=>$data['node'],'num'=>'1','level'=>$data['level']+1,'created_at'=>time()];
		$insert[] = ['node'=>$data['node'],'num'=>'2','level'=>$data['level']+1,'created_at'=>time()];
		$insert[] = ['node'=>$data['node'],'num'=>'3','level'=>$data['level']+1,'created_at'=>time()];
		db('row')->insertAll($insert);
	}



	/**
	 *开心奖
	 * @param $uid
	 * @param $money
	 * @return bool
	 */
	private function happy($uid,$money)
	{
		#进入公排后发放开心奖（投资额的百分比）
		#10%进入消费积分 
		$config = db('config')->where(['name'=>'happy_static'])->value('value');
		$scale = $config*$money*0.01; //投资额的百分比
		$parent = $this->parent($uid);
		// dump($parent);die();
		Db::startTrans();
		try{
			foreach($parent as $k => $v){
				if($v > 0){
					$insert[] = ['uid' => $v,'balance' => round($scale*0.9,2),'remark' => "开心奖",'class'=>2,'type' => 2,'from_uid' => $uid,'create_at' => time()];
					$insert[] = ['uid' => $v,'balance' => round($scale*0.1,2),'remark' => "开心奖",'class'=>1,'type' => 2,'from_uid' => $uid,'create_at' => time()];
					db('account')->insertAll($insert);
					db('users')->where(['id'=>$v])->setInc('balance',round($scale*0.9,2));
					db('users')->where(['id'=>$v])->setInc('score',round($scale*0.1,2));
				}
			}
			Db::commit();
			return true;
		}catch(\Exception $e){
			// dump($e);
			Db::rollback();
			return false;
		}
	}

	/**找三代节点
	 * @param $uid
	 * @return array
     */
	private function parent($uid)
	{
		$parent = ['one'=>0,'two'=>0,'three'=>0];
		$child = db('row')->where(['uid'=>$uid])->find();
		$parent1 = db('row')->where(['id'=>$child['node']])->find();
		if(!in_array($parent1['uid'],[0,$child['pid']])){
			$parent['one'] = $parent1['uid'];// 一代
			$parent2 = db('row')->where(['id'=>$parent1['node']])->find();
			if(!in_array($parent2['uid'],[0,$child['pid']])){
				$parent['two'] = $parent2['uid'];// 二代
				$parent3 = db('row')->where(['id'=>$parent2['node']])->find();
				if(!in_array($parent3['uid'],[0,$child['pid']])){
					$parent['three'] = $parent3['uid'];// 三代
				}
			}
		}
		return $parent;
	}
}