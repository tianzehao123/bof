<?php
namespace app\home\controller;

use app\backend\model\AddressModel;
use app\backend\model\CartModel;//购物车
use app\backend\model\GoodsModel;
use app\backend\model\UserModel;

class Cart extends Base{
    const ADDRESS = 'address_id';
    const ORDERMONEY = 'order_all_money';
    const CART = 'cart_all_id';
    //===========================需登录==========================
    #购物车
    public function index(){
        $uid = session('home.user')['id'];
        $zhekou = 100;

        $CartModel = new CartModel();
        $cart = $CartModel::all(['uid'=>$uid]);

        $list = [];
        if($cart){
            $insert = [];
            foreach ($cart as $k => $v) {
                $insert[$v['gid']] = $CartModel->where(['uid'=>$uid,'gid'=>$v->gid])->sum('num');
            }
            $CartModel->where(['uid'=>$uid])->delete();
            foreach ($insert as $k=> $v) {
                $list[] = ['gid'=>$k,'uid'=>$uid,'num'=>$v,'created_at'=>time()];
            }
        }
        $CartModel->saveAll($list);

        $cart = $CartModel::all(['uid'=>$uid]);

        foreach($cart as $k=>$v){
            $v->goods;
        }
        return json(['status'=>1,'message'=>'完成','data'=>$cart,'zhekou'=>$zhekou]);
    }

    #加入购物车
    public function add_cart(){
        #需要商品id，购买数量
        $uid = session('home.user')['id'];
        if(!input('id') || !input('num')){
            return json(['status'=>0,'message'=>'信息不完善']);
        }
        if(input('num') < 1){
            return json(['status'=>0,'message'=>'数量不正确']);
        }
        if(empty(GoodsModel::get(['id'=>input('id')]))){
            return json(['status'=>0,'message'=>'商品不存在']);
        }
        $goods = db('goods')->where(['id'=>input('id')])->find();
        // $is_new = db('order')->where(['uid'=>$uid,'status'=>['in','4,5,6'],'is_new'=>1])->value('id');
        // if(empty($is_new)){
        //     if($goods['cid'] != 1){
        //         return json(['status'=>0,'message'=>'首次购买只能购买入会专区的商品']);
        //     }
        // }
        $cart = new CartModel();
        $cart->gid = input('id');
        $cart->uid = $uid;
        $cart->num = abs(input('num'));
        $cart->created_at = time();
        $cart->save();

        return json(['status'=>1,'message'=>'添加到购物车成功']);
    }

    #立即购买
    public function to_cart_end(){
        //需要商品id，购买数量
        $uid = session('home.user')['id'];
        if(!input('id') || !input('num')){
            return json(['status'=>0,'message'=>'信息不完善']);
        }
        if(input('num') < 1){
            return json(['status'=>0,'message'=>'数量不正确']);
        }
        if(empty(GoodsModel::get(['id'=>input('id')]))){
            return json(['status'=>0,'message'=>'商品不存在']);
        }
        $goods = db('goods')->where(['id'=>input('id')])->find();
        // $is_new = db('order')->where(['uid'=>$uid,'status'=>['in','4,5,6'],'is_new'=>1])->value('id');
        // if(empty($is_new)){
        //     if($goods['cid'] != 1){
        //         return json(['status'=>0,'message'=>'首次购买只能购买入会专区的商品']);
        //     }
        // }
        $cart = new CartModel();
        $cart->gid = input('id');
        $cart->uid = $uid;
        $cart->num = abs(input('num'));
        $cart->created_at = time();
        $cart->save();
        return json(['status'=>1,'message'=>'查询完成','data'=>$cart->id]);
    }

    #购物车删除
    public function del_cart(){
        #需要购物车id集合，中间用 ',' 隔开
        $all_id = trim(input('ids'),',');
        if(request()->isPost()){
            CartModel::destroy($all_id);
        }
        return json(['status'=>1,'message'=>'操作成功']);
    }

    #修改购物车商品数量
    public function edit_cart(){
        #需要购物车id，更改的数量
        $uid = session('home.user')['id'];
        $cart = CartModel::get(input('id'));
        if($cart->uid != $uid){
            return json(['status'=>0,'message'=>'非法操作']);
        }
        $cart->num = abs(input('num'));
        $cart->save();

        return json(['status'=>1,'message'=>'操作成功']);

    }

    #购物车---确认订单页面
    public function cart_end(){
        session(self::ORDERMONEY,null);
        session(self::CART,null);
        #需要购物车id集合ids
        $id = input('ids');
        if(empty($id)){
            return json(['status'=>3,'message'=>'购物车id不能为空','data'=>'']);
        }
        $uid = session('home.user')['id'];

        $return['user'] = UserModel::get($uid);
        ###收货地址
        if(!empty(session(self::ADDRESS))){
//            dump(1);exit;
            $return['user']->address = AddressModel::get(session(self::ADDRESS));
        }else{
//            dump(2);exit;
            if($return['user']->aid){
                $return['user']->address = $return['user']->beaddress;
            }else{
                $return['user']->address;
            }
        }
        dump($return['user']->address);exit;
        if(empty($return['user']->address)){
        }
        $return['cart'] = CartModel::all($id);
        $money = 0;
        $true_money = 0;
        foreach($return['cart'] as $v){
            $v->goods;

            $money += round($v->num * $v->goods->price,2);  //入会商品没有折扣

            $true_money += round($v->num * $v->goods->price,2);
        }
        $return['all_money'] = $money;
        $return['true_money'] = $true_money;
        session(self::ORDERMONEY,$money);
        session(self::CART,$id);
        return json(['status'=>1,'message'=>'查询数据成功','data'=>$return]);
    }

}