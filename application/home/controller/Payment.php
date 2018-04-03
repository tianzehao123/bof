<?php
namespace app\home\controller;

use Payment\NotifyContext;
use alipayment\Alipay;
use wechats\Wechat;
use wechats\Wxapp;
use think\Db;
use think\Controller;
use app\home\controller\Row;
use app\backend\model\Config;
class Payment extends Controller
{
    const RECHARGE = 'recharge';    //充值升级表
    const USER = 'users';            //用户表
    const ACCOUNT = 'account';      //记录表
    const ORDER = 'order';          //订单表
// ---------------------------------------------------判断是否微信---------------------------------------------------------------// 
    public function wxOrWeb(){
        if (is_weixin()) {
            return json(['status'=>0,'msg'=>'微信']);
        } else {
            return json(['status'=>1,'msg'=>'浏览器']);
        }
    }
// ---------------------------------------------------订单处理---------------------------------------------------------------//	
    public function order_deal(){
        $id = input('id');
        $uid = session('home.user')['id'];
        $user = db('users')->where(['id'=>$uid])->find();
        $order = db('order')->where(['id'=>$id,'status'=>1])->find();
        if (empty(input('city'))) {
            return json(['status' => 2, 'message' => '请完善收货地址']);
        } else {
            if (empty(input('uid')) || input('uid') != $uid) {
                return json(['status' => 2, 'message' => '收货地址异常']);
            }
        }
        if (empty($order)) {
            return json(['status' => 3, 'message' => '该订单信息不正确']);
        } else {
            if ($order['uid'] != $uid) {
                return json(['status' => 3, 'message' => '请确认用户信息']);
            }
        }
        if(empty($user['openid']) && input('class')=='wx' && is_weixin()){
            return json(['status'=>4,'message'=>'需绑定微信才能进行微信支付']); 
        }
        $money = $order['price'];
/*        if ($pwd != $user['two_password']) {
            return json(['status' => 2, 'message' => '支付密码错误']);
        }
*/

        $upOrder = [
            'city' => input('city'),
            'address_detail' => !empty(input('description')) ? input('description') : '',
            'address_phone' => !empty(input('phone')) ? input('phone') : '',
            'address_name' => !empty(input('name')) ? input('name') : '',
            'payment' => input('payment'),
            'updated_at' => time(),
            'score' => $money,
        ];

        //2微信 1支付宝
        $re = db('order')->where(['id'=>$id])->update($upOrder);
        $res = db('order')->where(['id'=>$id])->find();
        $class = input('class');
        // dump($class);exit;
        if($class == 'ali'){
            // $class = 'ali';
            $body = '订单支付';
            // return json(['status'=>1,'message'=>'提交成功','data'=>$this->payRoute($class,$res['order_sn'],$res['price'],$body)]);
            return json(['status'=>1,'message'=>'提交成功','data'=>$this->payRoute($class,$res['order_sn'],$money,$body)]);
        }else{
            if(is_weixin()){
                $cs = self::wxPay($order['order_sn'],$money);
                return json(['status'=>1,'message'=>'提交成功','data'=>$cs]);
            }else{
                return json(['status'=>2,'message'=>'请在微信公众号中打开']);                
            }
            // dump($cs);
        }
        
    }
// -------------------------------------------------支付路由---------------------------------------------------------------//	
    private function payRoute($type,$order_sn,$money,$body){
        #支付宝支付、微信支付、支付宝app支付、微信app支付、支付宝app支付接口对接、微信app支付接口对接、
        switch ($type) {
            case 'ali':
                return $this->aliPay($order_sn,$money,$body);
                break;
            case 'ali_app':
                return $this->aliAppPay($order_sn,$money,$body);
                break;
            case 'ali_app_api':
                return $this->aliAppPay($order_sn,$money,$body);
                break;
            case 'wx':
                return $this->wxPay($order_sn,$money,$body);
                break;
            case 'wx_app':
                return $this->wxAppPay($order_sn,$money,$body);
                break;
            case 'wx_app_api':
                return $this->wxAppPay($order_sn,$money,$body,'api');
                break;          
        }
    }
// -------------------------------------------------------支付宝支付-------------------------------------------------------//    
    private function aliPay($order_sn,$money,$body='支付宝支付')
    { 
       $data = [
            'order_no'      => $order_sn,
            // 'amount'        => 0.01,
            'amount'        => $money,
            'body'          => $body,
            'subject'       => $body,
            'timeout_express'   => (time() + 1800),
            'return_param'      => 'buy'
        ];
        $res = Alipay::create($data);  //提交订单
        if(is_weixin()){
            $data = DOMAIN."/home/payment/ydy.html?sss=".$res;
            return $data;
            // header("location:".DOMAIN."/home/alipays/ydy.html?sss=".$res);
        }else{
            // header("location:".$res);
            return $res;
        }
    }
// ----------------------------------------------------支付宝app支付-----------------------------------------------------//	
     private function aliAppPay($order_sn,$money,$body='支付宝支付')
    { 
        $data = [
            'order_no'      => $order_sn,
            // 'amount'        => 0.01,
            'amount'        => $money,
            'body'          => $body,
            'subject'       => $body,
            'timeout_express'   => (time() + 1800),
            'return_param'      => 'buy'
        ];
        $res = Alipay::create($data,'ali_app');
        return $res;
    }
// ----------------------------------------------------微信支付--------------------------------------------------------------//	
    private function wxPay($order_sn,$money,$body='微信支付')
    {
        # 定义下单内容
        $data = [
            'body' => $body,
               // 'total_fee'=>1,
            'total_fee' => ($money * 100),
            'openid' => session('home.user')['openid'],
            'trade_type' => 'JSAPI',
            'out_trade_no' => $order_sn,
            'notify_url' => config('Wechat')['notify_url']
        ];
        # 获取JSAPI
        $jsapi = Wechat::get_jsapi_config(['chooseWXPay'], false, true);

        # 获取微信支付配置
        $payConfig = Wechat::ChooseWXPay($data, false);
        // dump($payConfig);exit;
        return json_decode($payConfig);
    }
// ------------------------------------------微信app支付---------------------------------------------------------------//		
	public function wxAppPay($order_sn,$money,$body='支付宝支付',$type=false){
    	#微信app支付对接接口
    	if($type == 'api'){
    		return Wxapp::sendRequest1($order_sn,$money);
    	}else{#微信app支付
    		return  Wxapp::sendRequest($order_sn,$money);;
    	}
	}
// ------------------------------------------支付宝回调---------------------------------------------------------------//
    public function alipay_notify()
    {
        $result = new NotifyContext();
        $data = [
            'app_id'            => config('Alipay')['app_id'], 
            'notify_url'        => config('Alipay')['notify_url'],
            'return_url'        => config('Alipay')['return_url'], 
            'sign_type'         => config('Alipay')['sign_type'], 
            'ali_public_key'    => config('Alipay')['ali_public_key'], 
            'rsa_private_key'   => config('Alipay')['rsa_private_key']
        ];
        # 校验信息
        $result->initNotify('ali_charge', $data);
        # 接受返回信息
        $information = $result->getNotifyData();
        if ($information['trade_status'] == 'TRADE_SUCCESS') {
            $pay_order = (String)$information['out_trade_no'];
            $total_fee = $information['total_amount'];
            $res = deal_with($pay_order,$total_fee);
            if($res == 'success'){

                echo "success";exit;
            }
            echo "fail";exit;
        }else{
            echo "fail";exit;
        }
    }

// ------------------------------------------微信回调-----------------------------------------------------------------//	
    public function native()
    {
        # 监听回调通知
        Wechat::notitfy(function ($notify) {
            $pay_order_num = (String)$notify['out_trade_no'];
            $total_fee = $notify['total_fee'];

            $res = deal_with($pay_order_num,$total_fee/100);

            if($res == 'success'){
                echo "success";exit;
            }
            echo "fail";exit;
        });
    }
// ------------------------------------------支付宝同步跳转----------------------------------------------------------------//	
    public function redir()
    {
        header("location:http://www.szcxdzsw.com/pay_success.html");exit;
        // dump('充值完成');
    }
  
  public function test()
    {
        #修改静态分红已返金额
       /* $data = [34,35,36,40];
        foreach ($data as $k => $v) {
            $insert[] = [
                'uid'        =>$v,
                'balance'    => 9.98,
                'remark'     =>'粉丝奖励',
                'class'      =>1,
                'type'       =>12,
                'from_uid'   =>$v,
                'create_at' =>'1513353602',
                'updated_at'=>date('Y-m-d H:i:s')
            ];
        }
        foreach ($data as $k => $v) {
            $insert[] = [
                'uid'        =>$v,
                'balance'    => 9.98,
                'remark'     =>'粉丝奖励',
                'class'      =>1,
                'type'       =>12,
                'from_uid'   =>$v,
                'create_at' =>'1513440001',
                'updated_at'=>date('Y-m-d H:i:s')
            ];
        }
        foreach ($data as $k => $v) {
            $insert[] = [
                'uid'        =>$v,
                'balance'    => 9.98,
                'remark'     =>'粉丝奖励',
                'class'      =>1,
                'type'       =>12,
                'from_uid'   =>$v,
                'create_at' =>'1513526402',
                'updated_at'=>date('Y-m-d H:i:s')
            ];
        }
        // dump($insert);
        db('account')->insertAll($insert);*/
        
        /*$data = db('static_order')->select();
        foreach ($data as $k => $v) {
            if($v['already_money'] !=0){
                if(($v['already_money']/9.98)%1 == 0){
                    db('static_order')->where(['id'=>$v['id']])->update(['already_money'=>$v['already_money']/5]);
                    dump(1);
                }elseif(($v['already_money']/6.98)%1 == 0){
                    db('static_order')->where(['id'=>$v['id']])->update(['already_money'=>$v['already_money']/3]);
                    dump(2);
                }elseif(($v['already_money']/3.98)%1 == 0){
                    db('static_order')->where(['id'=>$v['id']])->update(['already_money'=>$v['already_money']/2]);
                    dump(3);
                }
            } 
        }*/
        #排查多返的积分
        /*$data = db('account')->where(['type'=>12])->select();
        foreach ($data as $k => $v) {
            if($v['balance'] == 9.98){
                //更新记录 -4倍
                // $money = round($v['balance']/5,2);
                // db('account')->where(['id'=>$v['id']])->update(['balance'=>$money]);
                $user = db('users')->where(['id'=>$v['uid']])->find();
                if($user['score'] >= $v['balance']*4){
                    // dump($v['balance']-$money);
                   db('users')->where(['id'=>$v['uid']])->setDec('score',$v['balance']*4);
                   dump(1);
                }else{
                    dump($v['uid']);
                    // db('users')->where()->update(['score'=>0]);
                }   

            }elseif($v['balance'] == 6.98){
                // $money = round($v['balance']/3,2);
                // db('account')->where(['id'=>$v['id']])->update(['balance'=>$money]);
                $user = db('users')->where(['id'=>$v['uid']])->find();
                if($user['score'] >= $v['balance']*2){
                    // dump($v['balance']-$money);
                    db('users')->where(['id'=>$v['uid']])->setDec('score',$v['balance']*2);
                    dump(2);
                }else{
                    dump(20);
                    // db('users')->where()->update(['score'=>0]);
                }

            }elseif($v['balance'] == 3.98){
                // $money = round($v['balance']/2,2);
                // db('account')->where(['id'=>$v['id']])->update(['balance'=>$money]);
                $user = db('users')->where(['id'=>$v['uid']])->find();
                if($user['score'] >= $v['balance']){
 // dump($v['balance']-$money);
                    db('users')->where(['id'=>$v['uid']])->setDec('score',$v['balance']);
                    dump(3);
                }else{
dump(30);
                    // db('users')->where()->update(['score'=>0]);
                }

            }
        }*/
      #排查未进入返积分的用户
       /* $data = db('order_detail')->where(['gid'=>['in',[1,2,3]]])->select();
        foreach ($data as $k => $v) {
            $order = db('order')->where(['id'=>$v['oid'],'status'=>['>',1]])->find();
            if(empty($order)){
                unset($data[$k]);//去除
            }else{
                $gao_taocan = $v['gprice'];
                #查询静态粉红表
                $is_one = db('static_order')->where(['uid'=>$order['uid']])->find();
                if(!$is_one){
                    // dump($order['uid']);
                    $real_money = 0;
                    if($gao_taocan == '398'){
                        $real_money = 398 * 2;
                    }elseif($gao_taocan == '698'){
                        $real_money = 698 * 3;
                    }elseif($gao_taocan == '998'){
                        $real_money = 998 * 5;
                    }
                    $static = [
                        'uid' => $order['uid'],
                        'order_id'=>$order['id'],
                        'money' => $gao_taocan,
                        'real_money'=>$real_money,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    // dump($static);
                    db('static_order')->insert($static);
                    file_put_contents('fen.txt',$order['uid'].'---',FILE_APPEND);
                }
            }
        }*/
        /*$data = db('order')->where(['uid'=>['in',[34,35,36,40,41]],'status'=>['>',1]])->select();
        foreach ($data as $k => $v) {
            dump($v['uid']);
            dump(date("Y-m-d H:i:s",$v['created_at']));
        }*/

        
    }
   
// -------------------------------------------------支付宝引导页判断-------------------------------------------------------//     
    public function ydy()
    {
        if(request()->isAjax()){
            if (is_weixin()) {
                return json(['status'=>0,'msg'=>'微信']);
            } else {
                return json(['status'=>1,'msg'=>'浏览器']);
            }
        }
        return view('html/ydy');
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
                $order_detail = db('order_detail')->where(['oid'=>$orderid])->select();
                $taocan1 = $taocan2 = $taocan3 = 0;
                foreach($order_detail as $k=>$v){
                	if($v['gname'] == 'V1尊享优惠套餐' || $v['gname'] == 'V2尊享优惠套餐' || $v['gname'] == 'V3尊享优惠套餐'){
                		$price += $v['gprice'] * $v['g_num'];
                	}
                    if($v['gname'] == 'V1尊享优惠套餐'){
                        $taocan1 += $v['gprice'] * $v['g_num'];
                    }elseif($v['gname'] == 'V2尊享优惠套餐'){
                        $taocan2 += $v['gprice'] * $v['g_num'];
                    }elseif($v['gname'] == 'V3尊享优惠套餐'){
                        $taocan3 += $v['gprice'] * $v['g_num'];
                    }
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
                $gao_taocan = max(max($taocan1,$taocan2),$taocan3);
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
                
                return $price;
            }
                //==============================算法结束====================================//
        }
	
}

}
// ------------------------------------------回调处理逻辑--------------------------------------------------------------------// 
function deal_with($order_sn,$total_fee){
  // file_put_contents('1.txt',$order_sn,FILE_APPEND);
    $order = db('order')->where(['order_sn'=>$order_sn])->find();
    if($order['status'] != 1){
        return "success";
    }else{
        $user = db('users')->where(['id'=>$order['uid']])->find();
        $class = $user['class'];
        $goods = db('order_detail')->where(['oid'=>$order['id']])->select();
        foreach($goods as $k=>$v){
            if(in_array($v['gid'],[1,2,3])){
              $class = $class > ($v['gid']+1) ? $class : ($v['gid']+1);
            }
        }
        if($class > $user['class']){
            db('users')->where(['id'=>$order['uid']])->update(['class'=>$class]);
        }
        // file_put_contents('1.txt','2',FILE_APPEND);
        $res = db('order')->where(['order_sn'=>$order_sn])->update(['status'=>2,'updated_at'=>time()]);
        $res2 = add_account1($order['uid'], '-' . $order['price'], '商品消费', 1, 0);
        $pay = new Payment();
        $price = $pay->fenxiao($order['id']);
        // Payment()->fenxiao($order['id']);
        $row = new Row();
        $row->rowApi($order['uid'],$order['price']);

        if($res){
            return 'success';
        }else{
            return 'fail';
        }
   
    }
}
function testxx(){
    $pay = new Payment();
    $pay->fenxiao(72);
    $row = new Row();
    $row->rowApi(14,998);
}