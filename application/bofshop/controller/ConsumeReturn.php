<?php
namespace app\bofshop\controller;
use think\Controller;

class Consumereturn extends Controller
{
    public function index()
    {
        $info = db('goods')->where('cid','101')->field('id,img,name,price,market_price')->order('price desc')->select();
        if(empty($info)){
            return ajax('-1','没有商品');
        }
        return ajax('1','加载成功',$info);
    }

    #消费全返商品详情
    public function consumeinfo()
    {
        $id = input('gid');
        if($id == '' || !is_numeric($id)){
            return ajax('-1','加载失败');
        }
        $need = ['name','id','price','cu_price','remark','description','img','market_price'];
        $where = " id = $id and cid = '101' and is_delete  = '1' ";
        $data['info'] = db('goods')->where($where)->field($need)->find();
        if(empty($data['info'])){
            return ajax('-1','加载失败');
        }
        // $data['info']['cu_prices'] = $data['info']['cu_price'] * $data['info']['price'];
        $data['infoimg']  = db('lunbo')->where('id',$id)->where('sort','2')->field('imgurl')->select();
        $data['like'] = db('goods')->where(" id != $id and cid = '101' ")->limit('5')->order('id desc')->field('id,img,name,price')->select();
        return ajax('1','加载成功',$data);
    }

    #订单页面
    public function orderbuy()
    {   
        if(empty(session('home.user')['id'])){
            $url = "/bofshop/login/login";
            return ajax('-1','请先登录',$url);
        }
        $id = input('id');
        if($id == '' || !is_numeric($id)){
            return ajax('-1','非法操作');
        }
        $type = input('type');
        #$type = 2 零元购买  3 折扣购买
        $need = ['name','id','img'];
        if($type == 2){
            array_push($need,'market_price');
        }elseif($type == 3){
            array_push($need,'price');
        }
        $where = " id = $id and cid = '101' and is_delete  = '1' ";
        $goods = db('goods')->where($where)->field($need)->find();
        if(empty($goods)){
            return ajax('-1','没有此商品');
        }
        $data = [
            'name'  =>  $goods['name'],
            'id'    =>  $goods['id'],
            'img'   =>  $goods['img'],
            'price' =>  $goods['price']??$goods['market_price'],
            'receive'   =>  $type==2?(($goods['market_price'])*input("num")).'.00':'0.00',
        ];
        // $address = db('address')->where('id',session('home.user')['id'])->select();
        return json(['status'=>'1','message'=>'查询成功','data'=>$data]);
    }

   
    public function placeorder()
    {   
        if(empty(session('home.user')['id'])){
            $url = "/bofshop/login/login";  
            return ajax('-1','请先登录',$url);
        }
        $oid = input('oid');
        $orders = db('order_detail')->where('oid',$oid)->find();
        if(empty($orders)){
            return ajax('-1','非法操作');
        }
        $type = db('order')->where('id',$oid)->value('style');   # 2  0元购买  3折扣购买
        $num = input('num')??$orders['g_num'];        
        if($type == '2'){
            $rebate =  $orders['gprice'] * $num;            #返利积分
            $orders['subtotal'] = round(($orders['gprice'] * $num), 2);   #小计
        }elseif($type == '3'){
            $discount = db('goods')->where('id',$orders['gid'])->value('cu_price');                    #折扣
            $rebate =  0.00;
            $orders['subtotal'] = ($orders['gprice'] * ( $discount / 10 ) ) * $num;     #小计
        }
        $orders['g_num'] = $num;
        $orders['rebate'] = $rebate;
        $address = db('address')->where('id',session('bofshop.user')['id'])->select();
        return ajax('1','加载成功',['orders'=>$orders,'address'=>$address]);
    }

    
    #提交订单
    public function submitorder()
    {
        $oid = input('oid');
        if(!is_numeric($oid) || $oid == ''){
            return ajax('-1','非法操作');
        }
        $num = input('num');
        if($num < 1 || $num == '' || !is_numeric($num)){
            return ajax('-1','非法操作');
        }
        $order_detail = db('order_detail')->where('oid',$oid)->field('gid,gprice')->find();      #商品单价
        if(empty($order_detail)){
            return ajax('-1','此商品不存在');
        }
        $order = db('order')->where('id',$oid)->field('style,uid')->find();
        if(empty($order)){
            return ajax('-1','非法操作');
        }
        if($order['uid'] != session('bofshop.user')['id']){
            return ajax('-1','非法操作');
        }
        $data = [
              'city'            =>  input('city'),                          #收货地址
              'address_detail'  =>  input('address_detail'),                #收货详细地址
              'address_phone'   =>  input('address_phone'),                 #收货手机号
              'address_name'    =>  input('address_name'),                  #收货人姓名
              'status'          =>  '1',
              'updated_at'      =>  time(),
        ];
        if($order['style'] == '3'){
            $cu_price = db('goods')->where('id',$order_detail['gid'])->value('cu_price');        #促销折扣
            $data['price'] = ( $order_detail['gprice'] * ( $cu_price / 10 ) ) * $num;
        }elseif($order['style'] == '2'){
            $data['price'] = $order_detail['gprice'] * $num;
        }
        if(db('order')->where('id',$oid)->update($data)){
            db('order_detail')->where('oid',$oid)->update(['g_num'=>$num]);
            return ajax('1','订单提交成功',['oid'=>$oid]);
        }else{
            return ajax('-1','订单提交失败');
        }
    }

    #支付页面
    public function orderpay()
    {
        if(empty(session('bofshop.user')['id'])){
            $url = "/bofshop/login/login";
            return ajax('-1','请先登录',$url);
        }
        $oid = input('oid');
        if($oid == '' || !is_numeric($oid)){
            return ajax('-1','非法操作');
        }
        $order = db('order')->where('id',$oid)->field('uid,order_sn,price,address_detail')->find();
        if(!empty($order) || $order['uid'] != session('bofshop.user')['id']){
            return ajax('-1','非法操作');
        }
        $gname = db('order_detail')->where('oid',$oid)->value('gname');
        
    }
}