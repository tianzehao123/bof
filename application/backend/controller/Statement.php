<?php
/**
 * Created by PhpStorm.
 * User: ovo
 * Date: 2017/7/10
 * Time: 下午6:08
 */
namespace app\backend\controller;

use app\backend\model\OrderModel;
use app\backend\model\WithdrawModel;
use app\backend\model\UserModel;
use app\backend\model\RechargeModel;
use think\Db;
class Statement extends Base{
    const USER = 'users';//用户表
    const ACCOUNT = 'account';//账户明细表
    const ORDER = 'order';//账户明细表
    const ADDRESS = 'address';
    const DETAIL = 'order_detail';
    const GOODS = 'goods';
   #余额支付
    public function out_acc(){
        $status = config('order_status');
        $payment = config('payment');
        if(request()->isAjax()){
            $param = input('param.');
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;

            $where = [];
            $where['payment'] = 3;
            if (isset($param['name']) && !empty($param['name'])) {      //收货人查询
                $where['address_name'] = ['like','%'.$param['name'].'%'];
            }
            if (isset($param['phone']) && !empty($param['phone'])) {      //手机号查询
                $where['address_phone'] = ['like','%'.$param['phone'].'%'];
            }
            //订单状态
            if (isset($param['status']) && !empty($param['status'])) {
                $where['status'] = $param['status'];
            }
            //下单时间段
            if (isset($param['start']) && !empty($param['start'])) {
                $param['start'] = strtotime($param['start']);
                $where['created_at'] = ['>=',$param['start']];
            }
            if (isset($param['end']) && !empty($param['end'])) {
                $param['end'] = strtotime($param['end'].' 23:59:59');
                $where['created_at'] = ['<=',$param['end']];
            }

            $order = new OrderModel();
            if(isset($param['excel']) && $param['excel'] == 'to_excel'){
                $offset = 0;
                $limit = 9999;
            }
            $where['status'] = ['in',[2,3,4,5,6]];
            $selectResult = $order->getOrdersByWhere($where, $offset, $limit);

            foreach($selectResult as $key=>$vo){
                if($param['excel'] != 'to_excel'){
                    $selectResult[$key]['order_sn'] = '<a href="javascript:getOrder('.$vo['id'].')">'.$vo['order_sn'].'</a>';
                }
                $selectResult[$key]['user_name'] = $vo['address_name'].'/'.db(self::USER)->where(['id'=>$vo['uid']])->value('nickname');
                $selectResult[$key]['user_phone'] = $vo['address_phone'];
                $selectResult[$key]['user_address'] = $vo['city'].'-'.$vo['address_detail'];
                $selectResult[$key]['created_at'] = date('Y-m-d H:i',$vo['created_at']);
                $selectResult[$key]['status'] = $status[$vo['status']];
                $selectResult[$key]['payment'] = '余额';
           
            }
            if(isset($param['excel']) && $param['excel'] == 'to_excel'){    //导出到excel
                $content = json_decode(json_encode($selectResult),true);
                foreach($content as $k=>$v){
                    unset($content[$k]['id']);
                    unset($content[$k]['uid']);
                    unset($content[$k]['message']);
                    unset($content[$k]['updated_at']);
                    unset($content[$k]['city']);
                    unset($content[$k]['address_detail']);
                    unset($content[$k]['address_phone']);
                    unset($content[$k]['address_name']);
                    unset($content[$k]['money']);
                }
//                dump($content);exit;
                $excel = new Excel();
                $first = ['A1'=>'编号','B1'=>'需要金额','C1'=>'使用积分','D1'=>'状态','E1'=>'支付类型','F1'=>'创建时间','G1'=>'发货单号','H1'=>'收货人/购买人','I1'=>'收货人手机号','J1'=>'收货地址'];
                $excel->toExcel('订单列表',$content,$first);
                return json(['code'=>1]);
            }
            $return['total'] = $order->getAllOrders($where);  //总数据
            $return['rows'] = $selectResult;

            return json($return);
        }

        $this->assign([
            'status' => $status,
            'payment' => $payment
        ]);
        return $this->fetch();
    }
   #所有订单 
    public function in_acc(){
        $status = config('order_status');
        $payment = config('payment');
        if(request()->isAjax()){
            $param = input('param.');
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;

            $where = [];
            $where['status'] = ['>',1];
            if (isset($param['name']) && !empty($param['name'])) {      //收货人查询
                $where['address_name'] = ['like','%'.$param['name'].'%'];
            }
            if (isset($param['phone']) && !empty($param['phone'])) {      //手机号查询
                $where['address_phone'] = ['like','%'.$param['phone'].'%'];
            }
            //支付类型
            if (isset($param['payment']) && !empty($param['payment'])) {
                $where['payment'] = $param['payment'];
            }
            //订单状态
            if (isset($param['status']) && !empty($param['status'])) {
                $where['status'] = $param['status'];
            }
            //下单时间段
            if (isset($param['start']) && !empty($param['start'])) {
                $param['start'] = strtotime($param['start']);
                $where['created_at'] = ['>=',$param['start']];
            }
            if (isset($param['end']) && !empty($param['end'])) {
                $param['end'] = strtotime($param['end'].' 23:59:59');
                $where['created_at'] = ['<=',$param['end']];
            }

            $order = new OrderModel();
            if(isset($param['excel']) && $param['excel'] == 'to_excel'){
                $offset = 0;
                $limit = 9999;
            }
            $where['status'] = ['in',[2,3,4,5,6]];
            $selectResult = $order->getOrdersByWhere($where, $offset, $limit);

            foreach($selectResult as $key=>$vo){
                if($param['excel'] != 'to_excel'){
                    $selectResult[$key]['order_sn'] = '<a href="javascript:getOrder('.$vo['id'].')">'.$vo['order_sn'].'</a>';
                }
                $selectResult[$key]['user_name'] = $vo['address_name'].'/'.db(self::USER)->where(['id'=>$vo['uid']])->value('nickname');
                $selectResult[$key]['user_phone'] = $vo['address_phone'];
                $selectResult[$key]['user_address'] = $vo['city'].'-'.$vo['address_detail'];
                $selectResult[$key]['created_at'] = date('Y-m-d H:i',$vo['created_at']);
                $selectResult[$key]['status'] = $status[$vo['status']];
                if($vo['payment']){
                    $selectResult[$key]['payment'] = $payment[$vo['payment']];
                }else{
                    $selectResult[$key]['payment'] = '未定义';
                }
            }
            if(isset($param['excel']) && $param['excel'] == 'to_excel'){    //导出到excel
                $content = json_decode(json_encode($selectResult),true);
                foreach($content as $k=>$v){
                    unset($content[$k]['id']);
                    unset($content[$k]['uid']);
                    unset($content[$k]['message']);
                    unset($content[$k]['updated_at']);
                    unset($content[$k]['city']);
                    unset($content[$k]['address_detail']);
                    unset($content[$k]['address_phone']);
                    unset($content[$k]['address_name']);
                    unset($content[$k]['money']);
                }
//                dump($content);exit;
                $excel = new Excel();
                $first = ['A1'=>'编号','B1'=>'需要金额','C1'=>'使用积分','D1'=>'状态','E1'=>'支付类型','F1'=>'创建时间','G1'=>'发货单号','H1'=>'收货人/购买人','I1'=>'收货人手机号','J1'=>'收货地址'];
                $excel->toExcel('订单列表',$content,$first);
                return json(['code'=>1]);
            }
            $return['total'] = $order->getAllOrders($where);  //总数据
            $return['rows'] = $selectResult;

            return json($return);
        }

        $this->assign([
            'status' => $status,
            'payment' => $payment
        ]);
        return $this->fetch();
    }
    #支付宝支付订单
    public function apply(){
         $status = config('order_status');
        $payment = config('payment');
        if(request()->isAjax()){
            $param = input('param.');
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;

            $where = [];
            $where['payment'] = 1;
            if (isset($param['name']) && !empty($param['name'])) {      //收货人查询
                $where['address_name'] = ['like','%'.$param['name'].'%'];
            }
            if (isset($param['phone']) && !empty($param['phone'])) {      //手机号查询
                $where['address_phone'] = ['like','%'.$param['phone'].'%'];
            }
            /*//支付类型
            if (isset($param['payment']) && !empty($param['payment'])) {
                $where['payment'] = $param['payment'];
            }*/
            //订单状态
            if (isset($param['status']) && !empty($param['status'])) {
                $where['status'] = $param['status'];
            }
            //下单时间段
            if (isset($param['start']) && !empty($param['start'])) {
                $param['start'] = strtotime($param['start']);
                $where['created_at'] = ['>=',$param['start']];
            }
            if (isset($param['end']) && !empty($param['end'])) {
                $param['end'] = strtotime($param['end'].' 23:59:59');
                $where['created_at'] = ['<=',$param['end']];
            }

            $order = new OrderModel();
            if(isset($param['excel']) && $param['excel'] == 'to_excel'){
                $offset = 0;
                $limit = 9999;
            }
            $where['status'] = ['in',[2,3,4,5,6]];
            $selectResult = $order->getOrdersByWhere($where, $offset, $limit);

            foreach($selectResult as $key=>$vo){
                if($param['excel'] != 'to_excel'){
                    $selectResult[$key]['order_sn'] = '<a href="javascript:getOrder('.$vo['id'].')">'.$vo['order_sn'].'</a>';
                }
                $selectResult[$key]['user_name'] = $vo['address_name'].'/'.db(self::USER)->where(['id'=>$vo['uid']])->value('nickname');
                $selectResult[$key]['user_phone'] = $vo['address_phone'];
                $selectResult[$key]['user_address'] = $vo['city'].'-'.$vo['address_detail'];
                $selectResult[$key]['created_at'] = date('Y-m-d H:i',$vo['created_at']);
                $selectResult[$key]['status'] = $status[$vo['status']];
                $selectResult[$key]['payment'] = '支付宝';
                
            }
            if(isset($param['excel']) && $param['excel'] == 'to_excel'){    //导出到excel
                $content = json_decode(json_encode($selectResult),true);
                foreach($content as $k=>$v){
                    unset($content[$k]['id']);
                    unset($content[$k]['uid']);
                    unset($content[$k]['message']);
                    unset($content[$k]['updated_at']);
                    unset($content[$k]['city']);
                    unset($content[$k]['address_detail']);
                    unset($content[$k]['address_phone']);
                    unset($content[$k]['address_name']);
                    unset($content[$k]['money']);
                }
//                dump($content);exit;
                $excel = new Excel();
                $first = ['A1'=>'编号','B1'=>'需要金额','C1'=>'使用积分','D1'=>'状态','E1'=>'支付类型','F1'=>'创建时间','G1'=>'发货单号','H1'=>'收货人/购买人','I1'=>'收货人手机号','J1'=>'收货地址'];
                $excel->toExcel('订单列表',$content,$first);
                return json(['code'=>1]);
            }
            $return['total'] = $order->getAllOrders($where);  //总数据
            $return['rows'] = $selectResult;

            return json($return);
        }

        $this->assign([
            'status' => $status,
            'payment' => $payment
        ]);
        return $this->fetch();
    }
    #微信支付订单
    public function recharge(){
        $status = config('order_status');
        $payment = config('payment');
        if(request()->isAjax()){
            $param = input('param.');
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;

            $where = [];
            $where['payment'] = 2;
            if (isset($param['name']) && !empty($param['name'])) {      //收货人查询
                $where['address_name'] = ['like','%'.$param['name'].'%'];
            }
            if (isset($param['phone']) && !empty($param['phone'])) {      //手机号查询
                $where['address_phone'] = ['like','%'.$param['phone'].'%'];
            }
            
            //订单状态
            if (isset($param['status']) && !empty($param['status'])) {
                $where['status'] = $param['status'];
            }
            //下单时间段
            if (isset($param['start']) && !empty($param['start'])) {
                $param['start'] = strtotime($param['start']);
                $where['created_at'] = ['>=',$param['start']];
            }
            if (isset($param['end']) && !empty($param['end'])) {
                $param['end'] = strtotime($param['end'].' 23:59:59');
                $where['created_at'] = ['<=',$param['end']];
            }

            $order = new OrderModel();
            if(isset($param['excel']) && $param['excel'] == 'to_excel'){
                $offset = 0;
                $limit = 9999;
            }
            $where['status'] = ['in',[2,3,4,5,6]];
            $selectResult = $order->getOrdersByWhere($where, $offset, $limit);

            foreach($selectResult as $key=>$vo){
                if($param['excel'] != 'to_excel'){
                    $selectResult[$key]['order_sn'] = '<a href="javascript:getOrder('.$vo['id'].')">'.$vo['order_sn'].'</a>';
                }
                $selectResult[$key]['user_name'] = $vo['address_name'].'/'.db(self::USER)->where(['id'=>$vo['uid']])->value('nickname');
                $selectResult[$key]['user_phone'] = $vo['address_phone'];
                $selectResult[$key]['user_address'] = $vo['city'].'-'.$vo['address_detail'];
                $selectResult[$key]['created_at'] = date('Y-m-d H:i',$vo['created_at']);
                $selectResult[$key]['status'] = $status[$vo['status']];
                $selectResult[$key]['payment'] = '微信';
                
            }
            if(isset($param['excel']) && $param['excel'] == 'to_excel'){    //导出到excel
                $content = json_decode(json_encode($selectResult),true);
                foreach($content as $k=>$v){
                    unset($content[$k]['id']);
                    unset($content[$k]['uid']);
                    unset($content[$k]['message']);
                    unset($content[$k]['updated_at']);
                    unset($content[$k]['city']);
                    unset($content[$k]['address_detail']);
                    unset($content[$k]['address_phone']);
                    unset($content[$k]['address_name']);
                    unset($content[$k]['money']);
                }
//                dump($content);exit;
                $excel = new Excel();
                $first = ['A1'=>'编号','B1'=>'需要金额','C1'=>'使用积分','D1'=>'状态','E1'=>'支付类型','F1'=>'创建时间','G1'=>'发货单号','H1'=>'收货人/购买人','I1'=>'收货人手机号','J1'=>'收货地址'];
                $excel->toExcel('订单列表',$content,$first);
                return json(['code'=>1]);
            }
            $return['total'] = $order->getAllOrders($where);  //总数据
            $return['rows'] = $selectResult;

            return json($return);
        }

        $this->assign([
            'status' => $status,
            'payment' => $payment
        ]);
        return $this->fetch();
    }


    #用户账户
    public function user_account()
    {
        if(request()->isAjax()){

            $param = input('param.');

            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;

            $where = [];
            if (isset($param['nickname']) && !empty($param['nickname'])) {
                $where['nickname'] = ['like', '%' . $param['nickname'] . '%'];
            }

            if (isset($param['phone']) && !empty($param['phone'])) {
                $where['phone'] = ['like', '%' . $param['phone'] . '%'];
            }
            $user = new UserModel();
            $selectResult = $user->getUsersByWhere($where, $offset, $limit);

            foreach($selectResult as $key=>$vo){
                $selectResult[$key]['ali'] = self::payMoney($vo->id,1,'price'); //支付宝
                $selectResult[$key]['wecahta'] = self::payMoney($vo->id,2,'price'); //微信
                $selectResult[$key]['integral'] = self::payMoney($vo->id,3,'score'); //积分
                $selectResult[$key]['operate'] = '';
            }
            $return['total'] = $user->getAllUsers($where);  //总数据
            $return['rows'] = $selectResult;

            return json($return);
        }
        return $this->fetch();
    }



    #消费
    public static function payMoney($id,$type,$moneyType)
    {
        return Db::table('sql_order')->where(['uid'=>$id,'status'=>2,'payment'=>$type])->sum("$moneyType");
    }

}