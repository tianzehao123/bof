<?php
namespace app\home\controller;

use app\backend\model\OrderModel;
use app\backend\model\UserModel;
use app\backend\model\AccountModel;
use app\backend\model\RechargeModel;
use think\Db;
use org\Verify;
class Account extends Base{
// class Account{

    #绑定账户
    public function index(){
        //必须登录密码pwd，短信验证码code
        //支付宝需要alname支付宝姓名,zhifubao支付宝账号
        //微信绑定需要weixin微信绑定账号，wechat微信收款码
        $uid = session('home.user')['id'];
        $code = input('code');
        $user = db('users')->where(['id'=>$uid])->find();
        /*$verify = new Verify();
        if (!$verify->check(input('code'))) {
            $error = '验证码不正确';
            return json(['status'=>2,'message'=>$error]);
        }*/
        
        if(md5(input('password')) != $user['password']){

            return json(['status'=>2,'message'=>'手机号或密码错误']);
        }
        $param = input('post.');
        unset($param['password']);
        // dump($param);exit;
        if($code.'-'.input('phone') != session('home.code')){
           $error = '验证码不正确';
           return json(['status'=>2,'message'=>$error]);
        }
        if(input('phone') != $user['phone']){

            return json(['status'=>2,'message'=>'手机号或密码错误']);
        }
        
        db('users')->where(['id'=>$uid])->update(['alname'=>$param ['alname'],'zhifubao'=>$param['zhifubao']]);
        session('home.code',null);
        return json(['status'=>1,'message'=>'绑定成功']);
    }

    #解除绑定
    public function del_bang(){
        //必须登录密码pwd，短信验证码code
        //type==1支付宝
        //否则微信
        $uid = session('home.user')['id'];
        $user = UserModel::get(['id'=>$uid]);
         $code = input('code');
        if($code.'-'.input('phone') != session('home.code')){
           $error = '验证码不正确';
           return json(['status'=>2,'message'=>$error]);
        }
        /*$verify = new Verify();
        if (!$verify->check(input('code'))) {
            $error = '验证码不正确';
            return json(['status'=>2,'message'=>$error]);
        }*/
        if(md5(input('password')) != $user->password){
            return json(['status'=>2,'message'=>'密码错误']);
        }
        if(input('type') == 1){
            $param['zhifubao'] = '';
            $param['alname'] = '';
        }else{
            $param['weixin'] = '';
            $param['wechat'] = '';
        }
        $user->save($param,['id'=>$uid]);
        session('home.code',null);
        return json(['status'=>1,'message'=>'解绑成功']);
    }

    #删除银行卡
    public function del_bank(){
        $id = input('id');
        db('bank')->where(['id'=>$id])->delete();
        return json(['status'=>1,'message'=>'删除成功']);
    }

    #获取用户银行卡
    public function get_bank(){
        // dump('3213');exit;
        $uid = session('home.user')['id'];
        // $uid = 1;
        $bank = db('bank')->where(['uid'=>$uid])->select();
        $return = [];
        if($bank ){
           foreach ($bank as $key => $v) {
                $return[] = ['title'=>$v['bank_name'],'number'=>$v['bank'],'id'=>$v['id']];
            } 
        }
        return json(['status'=>1,'message'=>'查询成功','data'=>$return]);
    }
/*
`uid` int(10) unsigned NOT NULL,
  `bank` varchar(255) NOT NULL COMMENT '银行卡号',
  `bank_name` varchar(50) NOT NULL COMMENT '银行名称',
  `kaihuren` varchar(255) DEFAULT NULL COMMENT '开户人',
  `kaihuzhihang` varchar(255) DEFAULT NULL COMMENT '身份证',
  `created_at` varchar(50) NOT NULL,
*/
    #添加银行卡
    public function add_bank(){
//        需要bank银行卡号，bank_name银行名称，kaihuren开户人,kaihuzhihang 身份证
        $uid = session('home.user')['id'];
        // $uid = 1;
        $insert = [
            'bank'          => input('bank'),
            'bank_name'     => input('bank_name'),
            'kaihuren'      => input('kaihuren'),
            'kaihuzhihang'  => input('kaihuzhihang'),
            'uid'           => $uid,
            'created_at'    => time(),
        ];
        db('bank')->insert($insert);
        return json(['status'=>1,'message'=>'添加成功']);
    }
    #报单中心申请提交
    public function apply_sub(){
        $uid = session('home.user')['id'];
        $apply = db('apply')->where(['uid'=>$uid])->find();
         if($apply){
            if($apply['status'] == 1){
                return json(['status'=>2,'message'=>'提交审核中']);
            }else if($apply['status'] == 2){
                return json(['status'=>3,'message'=>'您已成为报单中心']);
            }
        }else{ 
            if(request()->isPost()) {
                $data = [
                  'uid'     => $uid,
                  'name'    => input('name'),
                  'phone'   => input('phone'),
                  'city'    => implode(' ',input('param.')['city']),
                  'detail'  => input('detail'),
                  'type'    => input('type'),  //1,2,3
                  // 'status' => ,
                  'created_at' =>time()
                ];
               
                $res = db('apply')->insertGetId($data);  
                if($res >0){
                    return json(['status'=>1,'message'=>'提交成功']);
                }else{
                    return json(['status'=>0,'message'=>'提交失败']);
                }
            }else{
                return json(['status'=>1,'message'=>'可以提交']);
            }     
        }
    }
    public function get_order(){
        $goods = db('goods')->where(['cid'=>1,'is_delete'=>1])->find();// 商品信息
        return json(['status'=>1,'message'=>'查询成功','data'=>$goods]);
    }    

    #会员报单
    public function sub_order(){
        $goods = db('goods')->where(['cid'=>1,'is_delete'=>1])->find();// 商品信息
        $d_user= db('users')->where(['phone'=>input('phone')])->find();//代用户信息
        $password = md5(input('password'));
        $user = db('users')->where(['id'=>session('home.user')['id']])->find();//用户信息
        if($user['two_password'] != $password){
            return json(['status'=>0,'message'=>'支付密码错误']);
        }
        $city = implode(',',input('param.')['city']);
        $money = $goods['price']*input('num');
        $order = [
              'order_sn'        => orderNum(),
              'uid'             => $d_user['id'],
              'city'            => $city,
              'address_detail'  => input('address_detail'),
              'address_phone'   => input('address_phone'),
              'address_name'    => input('address_name'),
              'price'           => $money,
              'bd_id'           => session('home.user')['id'],
              'status'          => 1,
              'payment'         => 4,
              'created_at'      => time()
        ];
        // 启动事务
        Db::startTrans();
        try{
            $res = db('order')->insertGetId($order);
            $order_detail = [
              'oid'     => $res,
              'gid'     => $goods['id'],
              'g_num'   => input('num'),
              'gname'   => $goods['name'],
              'gimg'    => $goods['img'],
              'gprice'  => $goods['price'],
              'created_at' => date('YmdHis')
            ];
            db('order_detail')->insertGetId($order_detail);
            // 提交事务
            Db::commit();
            return json(['status'=>1,'message'=>'订单提交成功','data'=>$res]);
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return json(['status'=>0,'message'=>'订单提交失败']);
        }      
    }
    #报单支付
    public function deal_order(){
        $uid = session('home.user')['id'];
        if(request()->isGet()){
            $return = [
                'price' => db('order')->where(['id'=>input('oid')])->value('price'),
                'cash'  => db('users')->where(['id'=>$uid])->value('cash')
            ];
            return json(['status'=>1,'message'=>'查询成功','data'=>$return]);
        }
        if(request()->isPost()){
            $order = OrderModel::get(input('oid'));
            $user  = UserModel::get($uid);
            if($order){
                // 启动事务
                Db::startTrans();
                try{
                    $order->status = 2;
                    if($user->cash < $order->price){
                        return json(['status'=>0,'message'=>'报单币不足，支付失败']);
                    }
                    $order->save();
                    $user->setDec('cash',$order->price);
                    // 提交事务
                    Db::commit();
                    $return = [
                        'account'=>db('users')->where(['id'=>$order->uid])->value('phone'),
                        'price'  =>$order->price
                    ];
                    return json(['status'=>1,'message'=>'支付成功','data'=>$return]);
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    return json(['status'=>0,'message'=>'支付失败']);
                }
            }else{
                return json(['status'=>0,'message'=>'订单信息获取失败']);
            }
        }
    }
   #充值提交
   public function sub_recharge(){
        $data = [
            'money'     => input('money'),
            'pay_cerl'  => input('pay_cerl'),
            'pay_type'  => input('pay_type'), //0=微信 1=支付宝 2=银行卡
            'order_sn'  => input('order_sn'),
            'created_at'=> time(),
            'uid' =>session('home.user')['id']
        ];
        if($data['money'] <=0){
            return json(['status'=>0,'message'=>'充值金额有误']);
        }
         $prg = "/\d{6}$/";
        if(($data['pay_type'] == 2) && (!preg_match($prg, $data['order_sn']))){
            return json(['status'=>0,'message'=>'银行交易号有误']);
        }
        if(db('recharge')->insertGetId($data))return json(['status'=>1,'message'=>'充值申请提交成功']);
   } 
   #见点奖
   public function point_award(){
        $id = session('home.user')['id'];
        $page = input('page')?:1;
        $offset = ($page-1)*20;
        $limit = 20;
        $bonus_money = db('account')->where(['type'=>11,'uid'=>$id])->sum('balance');
        $bonus = AccountModel::all(function($request) use ($id,$offset,$limit){
            $request->field('*,FROM_UNIXTIME(create_at,"%Y-%m-%d %H:%i:%s") as create_at')->with('users')->where(['type'=>11,'uid'=>$id])->order('id desc')->limit($offset,$limit);
        });      
        return json(['status'=>1,'message'=>'查询成功','data'=>$bonus,'money'=>$bonus_money]);    
   }
   #充值记录
    public function recharge_log(){
        $id = session('home.user')['id'];
        $page = input('page')?:1;
        $offset = ($page-1)*20;
        $limit = 20;
        $bonus = RechargeModel::all(function($request) use ($id,$offset,$limit){
            $request->field('*,FROM_UNIXTIME(created_at,"%Y-%m-%d %H:%i:%s") as created_at')->where(['uid'=>$id])->order('id desc')->limit($offset,$limit);
        });
        $status = ['审核中','同意','驳回'];
        foreach ($bonus as $key => $value) {
           $bonus[$key]['status'] = $status[$value['status']-1];
        }       
        return json(['status'=>1,'message'=>'查询成功','data'=>$bonus]);    
    }
    #推荐加盟奖
    /*累计积分  记录 */  
    public function join_log(){
        $id = session('home.user')['id'];
        $page = input('page')?:1;
        $offset = ($page-1)*20;
        $limit = 20;
        $bonus = AccountModel::all(function($request) use ($id,$offset,$limit){
            $request->field('*,FROM_UNIXTIME(created_at,"%Y-%m-%d %H:%i:%s") as created_at')->where(['uid'=>$id])->order('id desc')->limit($offset,$limit);
        });
        $status = ['审核中','同意','驳回'];
        foreach ($bonus as $key => $value) {
           $bonus[$key]['status'] = $status[$value['status']-1];
        }       
        return json(['status'=>1,'message'=>'查询成功','data'=>$bonus]); 
    }
}