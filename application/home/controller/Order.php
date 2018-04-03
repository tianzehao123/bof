<?php
namespace app\home\controller;

use  app\home\controller\Row;

use app\backend\model\AddressModel;
use app\backend\model\OrderDetailModel;
use app\backend\model\OrderModel;
use app\backend\model\UserModel;
use app\backend\model\Config;
use think\Db;

/**
 * @property int status
 */
class Order extends Base {
	const ADDRESS = 'address_id';
	const ORDERMONEY = 'order_all_money';
	const CART = 'cart_all_id';
	const ORDER = 'order';

	public function order_type(){
		$id = input('id');
		// return json(['status' => 1, 'message' => '查询成功', 'data'=>1]);
		$order = db(self::ORDER)->where(['id'=>$id])->find();
		if(!$order){
			return json(['status' => 3, 'message' => '非法操作',]);
		}else{
			$detail = db('order_detail')->where(['oid'=>$order['id']])->find();
			$type = in_array($detail['gid'],[1,2,3])?1:2;
			return json(['status' => 1, 'message' => '查询成功', 'data'=>$type]);
		}
	}
	#订单确认页
	public function order_detail() {
		session('goods_num', null);
		session('goods_id', null);
		session(self::ORDERMONEY, null);
		// 需要商品id,商品数量
		$gid = input('id');
		$goods_num = input('num');
		if (empty($goods_num) || $goods_num < 1) {
			return json(['status' => 3, 'message' => '非法操作']);
		}
		$return['goods'] = db('goods')->where(['id' => $gid])->find();
		if (empty($return['goods'])) {
			return json(['status' => 3, 'message' => '商品不存在']);
		}
		$uid = session('home.user')['id'];
		// $return['user'] = UserModel::get($uid)->field('id,nickname,phone,aid');
		$return['user'] = UserModel::get($uid);
		###收货地址
		if (!empty(session(self::ADDRESS))) {
			$return['user']->address = AddressModel::get(session(self::ADDRESS));
		} else {
			if ($return['user']->aid) {
				$return['user']->address = $return['user']->beaddress;
			} else {
				$return['user']->address;
			}
		}
		if (empty($return['user']->address)) {
			return json(['status' => 2, 'message' => '请先编辑收货地址', 'data' => '']);
		}
		$return['order_all_money'] = round($goods_num * $return['goods']['price'], 2);
		session(self::ORDERMONEY, $return['order_all_money']);
		session('goods_num', $goods_num);
		session('goods_id', $gid);
		return json(['status' => 1, 'message' => '查询成功', 'data' => $return]);
	}

	#提交订单
	public function add_order() {
		$id = input('ids') ? explode(',', trim(input('ids'), ',')) : null;

		if (!(!empty($id) || (!empty(input('gid')) && !empty('gnum')))) {
			return json(['status' => 0, 'message' => '请求信息不完善']);
		}

		$uid = session('home.user')['id'];

		$user = UserModel::get($uid);
		//计算商品总价格
		$money = (!empty($id)) ? getCartMoney($id) : getGoodsMoney(input('gid'), input('gnum'));

		$order = new OrderModel();

		$order->uid = $uid;
		$order->order_sn = orderNum();
		$order->status = 1;
		$order->price = $money;
		$order->created_at = time();
		$order->updated_at = time();

		$order->save();
		if ($order) {
			$order_detail = new OrderDetailModel();
			if (!empty(input('gid'))) {
				$goods = db('goods')->where(['id' => input('gid')])->find();

				$add_order_detail = [
					'gid' => $goods['id'],
					'g_num' => input('gnum'),
					'oid' => $order->id,
					'gname' => $goods['name'],
					'gimg' => $goods['img'],
					'gprice' => $goods['price'],
				];
				$order_detail->save($add_order_detail);
			} else {
				foreach ($id as $k => $v) {
					$cart = db('cart')->where(['id' => $v])->find();
					$goods = db('goods')->where(['id' => $cart['gid']])->find();
					$add_order_detail[] = [
						'gid' => $goods['id'],
						'g_num' => $cart['num'],
						'oid' => $order->id,
						'gname' => $goods['name'],
						'gimg' => $goods['img'],
						'gprice' => $goods['price'],
					];
					db('cart')->where(['id' => $v])->delete();
				}
				$order_detail->saveAll($add_order_detail);
			}
			return json(['status' => 1, 'message' => '订单提交成功', 'data' => $order->id]);
		} else {
			return json(['status' => 0, 'message' => '订单提交失败']);
		}
	}

	#支付界面
	public function order_pay() {
		//需要订单id
		$uid = session('home.user')['id'];
		$id = input('id');
		if (empty($id)) {
			return json(['status' => 0, 'message' => '商品id不能为空']);
		}
		$return['order'] = OrderModel::get(['id' => $id]);
		if ($return['order']->status != 1) {
			return json(['status' => 2, 'message' => '订单状态不符']);
		}
		$return['user'] = UserModel::field('id,aid,balance,score')->find(['id' => $uid]);
		if (empty(session('order_address'))) {
			if ($return['user']->aid) {
				$return['user']->beaddress;
			} else {
				$return['user']->address;
			}
		} else {
			$return['user']['address'] = session('order_address');
		}

		$return['order']->details;
		return json(['status' => 1, 'message' => '查询成功', 'data' => $return]);
	}

	//修改这次购买收货地址
	public function edit_order_address() {
		if (!empty(input('id'))) {
			$address = db('address')->where(['id' => input('id')])->find();
			session('order_address', $address);
		}
		return json(['status' => 1, 'message' => '修改成功']);
	}

	#订单余额支付
	public function balance_pay() {
		//需要订单id  ,支付密码pwd
		$id = input('id');
		$pwd = md5(input('pwd'));
		$uid = session('home.user')['id'];
		if (empty(input('city'))) {
			return json(['status' => 2, 'message' => '请完善收货地址']);
		} else {
			if (empty(input('uid')) || input('uid') != $uid) {
				return json(['status' => 2, 'message' => '收货地址异常']);
			}
		}
		$user = db('users')->where(['id' => $uid])->find();
		$order = db('order')->where(['id' => $id, 'status' => 1])->find();
		if (empty($order)) {
			return json(['status' => 3, 'message' => '该订单信息不正确']);
		} else {
			if ($order['uid'] != $uid) {
				return json(['status' => 3, 'message' => '请确认用户信息']);
			}
		}
		$money = $order['price'];
		if ($pwd != $user['two_password']) {
			return json(['status' => 2, 'message' => '支付密码错误']);
		}

		if ($user['score'] < $money) {
			return json(['status' => 2, 'message' => '您的账户积分不足，请尝试使用其他支付方式']);
		}

		#开启事务
		Db::startTrans();
		$upOrder = [
			'city' => input('city'),
			'address_detail' => !empty(input('description')) ? input('description') : '',
			'address_phone' => !empty(input('phone')) ? input('phone') : '',
			'address_name' => !empty(input('name')) ? input('name') : '',
			'status' => '2',
			'payment' => '3',
			'updated_at' => time(),
			'score' => $money,
		];

		$res1 = db(self::ORDER)->where(['id' => $id])->update($upOrder);

		// $order = db('order')->where(['id'=>$id,'status'=>1])->find();
		$res2 = add_account_score($uid, '-' . $money, '商品消费', 1, $uid);
		$is_one = db('order')->where(['uid'=>$uid,'status'=>['>',1]])->find();

		if ($res1 && $res2) {
			$price = $this->fenxiao($id);
			$row = new Row();
			$row->rowApi($uid,$price);
			#提交事务
			Db::commit();
			return json(['status' => 1, 'message' => '积分支付成功']);
		} else {
			#事务回滚
			Db::rollback();
			return json(['status' => 2, 'message' => '支付失败']);
		}
	}

	#线下支付页面
	public function down_pay() {
//        需要订单id
		$id = input('id');
		$order = db('order')->where(['id' => $id])->find();
//        dump($order);exit;
		$money = $order['price'] - $order['money'];
		return json(['status' => 1, 'message' => '查询成功', 'data' => $money]);
	}

	#线下支付
	public function down_pay_log() {
//        需要订单id，支付凭证pingzheng
		$id = input('id');
		if (!input('pingzheng')) {
			return json(['status' => 2, 'message' => '支付凭证不能为空']);
		}
		$order = db('order')->where(['id' => $id])->find();
		if ($order['status'] < 4) {
			$insert['money'] = $order['price'] - $order['money'];
			$insert['type'] = 1;
			$insert['certificate'] = input('pingzheng');
			$insert['oid'] = $id;
			$insert['status'] = 1;
			$insert['created_at'] = time();
			$res = db('tail_money')->insert($insert);

			$up['status'] = 3;
			$up['message'] = '已提交凭证';
			db('order')->where(['id' => $id])->update($up);
			return json(['status' => 1, 'message' => '提交成功，请等待管理员审核']);
		} else {
			return json(['status' => 2, 'message' => '状态有误']);
		}
	}

	public function fenxiao($orderid=''){
        if(!empty($orderid)){
            //=============================算法开始=====================================//
            $config['distribution1'] = db('config')->where(['name'=>'distribution1'])->value('value');
            $config['distribution2'] = db('config')->where(['name'=>'distribution2'])->value('value');
            $config['distribution3'] = db('config')->where(['name'=>'distribution3'])->value('value');
            // $config = unserialize($config)['conf'];
            $orders = db('order')->where(['id'=>$orderid])->find();
            if($orders['payment']){
                $user = db('users')->where(['id'=>$orders['uid']])->find();
                $price = 0;
                $class = $user['class'];
                $order_detail = db('order_detail')->where(['oid'=>$orderid])->select();
                $gao_taocan = 0;

                foreach($order_detail as $k=>$v){
                	if($v['gname'] == 'V1尊享优惠套餐' || $v['gname'] == 'V2尊享优惠套餐' || $v['gname'] == 'V3尊享优惠套餐'){
                		$price += $v['gprice'] * $v['g_num'];
                	}
                	if(in_array($v['gid'],[1,2,3])){
				      $class = $class > ($v['gid']+1) ? $class : ($v['gid']+1);
				      $gao_taocan = $v['gprice'] > $gao_taocan ? $v['gprice'] : $gao_taocan;
				    }
                }
                if($class > $user['class']){
		            db('users')->where(['id'=>$orders['uid']])->update(['class'=>$class]);
		        }
                // file_put_contents('./1.txt', $price);
                    //====================进行分销====================//
                $p1 = db('users')->where(['id'=>$user['pid']])->find();
                if($p1){
                    if($price > 0 && $p1['class'] > 1){
                        // $bili = $p1['class'] * 3 - 3;
                        $fan = round($price * $config['distribution1'] / 100,2);
                        add_account($p1['id'],$fan,'下级用户'.$user['nickname'].'的分销奖金',3,$user['id']);
                    }
                    $p2 = db('users')->where(['id'=>$p1['pid']])->find();
                    if($p2){
                        if($price > 0 && $p2['class'] > 1){
                            // $bili = $p2['class'] * 3 - 2;
                            $fan = round($price * $config['distribution2'] / 100,2);
                            add_account($p2['id'],$fan,'下级用户'.$user['nickname'].'的分销奖金',3,$user['id']);
                        }
                       $p3 = db('users')->where(['id'=>$p2['pid']])->find();
                       if($p3){
                           if($price > 0 && $p2['class'] > 1){
                               // $bili = $p3['class'] * 3 - 1;
                               $fan = round($price * $config['distribution3'] / 100,2);
                               add_account($p3['id'],$fan,'下级用户'.$user['nickname'].'的分销奖金',3,$user['id']);
                           }
                       }
                    }
                }

                $is_one = db('static_order')->where(['uid'=>$user['id']])->find();
                if(!$is_one){
                	$real_money = 0;
                	if($gao_taocan == '998'){
                        $real_money = Config::getConfigs('cash_v1');
                    }elseif($gao_taocan == '1998'){
                        $real_money = Config::getConfigs('cash_v2');
                    }elseif($gao_taocan == '2998'){
                        $real_money = Config::getConfigs('cash_v3');
                    }
                   if($gao_taocan > 300){
                     $static = [
                          'uid' => $user['id'],
                          'order_id'=>$orderid,
                          'money' => $gao_taocan,
                          'real_money'=>$real_money,
                          'created_at' => date('Y-m-d H:i:s'),
                          'updated_at' => date('Y-m-d H:i:s')
                      ];
                      db('static_order')->insert($static);
                      file_put_contents('fen.txt',$user['id'].'---',FILE_APPEND);
                  } 
                }
            }
                //==============================算法结束====================================//
            return $price;
        }
	}
}

function getCartMoney($ids) {
	$money = 0;
	foreach ($ids as $v) {
		$cart = db('cart')->where(['id' => $v])->find();
		$goods = db('goods')->where(['id' => $cart['gid']])->find();
		$money += $goods['price'] * $cart['num'];
	}
	return $money;
}

function getGoodsMoney($gid, $num) {
	$goods = db('goods')->where(['id' => $gid])->find();
	return $goods['price'] * $num;
}