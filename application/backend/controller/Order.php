<?php
/**
 * Created by PhpStorm.
 * Order: ovo
 * Date: 2017/7/10
 * Time: 下午6:08
 */
namespace app\backend\controller;

use app\backend\model\OrderModel;
use app\backend\model\GoodsModel;
use think\Db;

class Order extends Base{
    const USER = 'users';
    const ADDRESS = 'address';
    const DETAIL = 'order_detail';
    const GOODS = 'goods';
    //用户列表
    public function index()
    {
        $status = config('order_status');
        $payment = config('payment');
        if(request()->isAjax()){
            $param = input('param.');
//            dump($param);exit;
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;

            $where = [];
            if (isset($param['name']) && !empty($param['name'])) {      //收货人查询
                $where['address_name'] = ['like','%'.$param['name'].'%'];
            }
            if (isset($param['phone']) && !empty($param['phone'])) {      //手机号查询
                $where['address_phone'] = ['like','%'.$param['phone'].'%'];
            }
            if (isset($param['gm_phone']) && !empty($param['gm_phone'])) {      //购买人手机号查询
                $where['uid'] = db(self::USER)->where(['phone'=>$param['gm_phone']])->value('id');
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
            $selectResult = $order->getOrdersByWhere($where, $offset, $limit);
            $payment = config('payment');
            foreach($selectResult as $key=>$vo){
                $user = db(self::USER)->where(['id'=>$vo['uid']])->find();
                if($param['excel'] != 'to_excel'){
                    $selectResult[$key]['order_sn'] = '<a href="javascript:getOrder('.$vo['id'].')">'.$vo['order_sn'].'</a>';
                }
                $gm_nickname = !empty($user['nickanme'])?$user['nickname']:$user['phone'];
                $selectResult[$key]['user_name'] = $vo['address_name'].'/'.$gm_nickname;
                $selectResult[$key]['user_phone'] = $vo['address_phone'];
                $selectResult[$key]['gm_phone'] = $user['phone'];
                $selectResult[$key]['user_address'] = $vo['city'].'-'.$vo['address_detail'];
                $selectResult[$key]['created_at'] = date('Y-m-d H:i',$vo['created_at']);
                // $selectResult[$key]['is_new'] = $vo['is_new'] == 1?'首单':'复销';
                $selectResult[$key]['status'] = $status[$vo['status']];
                if($vo['payment']){
                    $selectResult[$key]['payment'] = $payment[$vo['payment']];
                }else{
                    $selectResult[$key]['payment'] = '线下';
                }
                if($vo['status'] == '待发货'){
                    $operate = [
                        '去发货' => "javascript:orderEdit('".$vo['id']."')"
                    ];
                    $selectResult[$key]['operate'] = showOperate($operate);
                }else{
                    $selectResult[$key]['operate']  = '-';
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
                    unset($content[$k]['operate']);
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

    //去发货
    public function orderEdit()
    {
        $order = new OrderModel();

        $save['id'] = input('id');
        $save['status'] = 3;
        $save['ship'] = input('ship');
        $save['logistics_name'] = input('logistics_name');
        $save['freight'] = input('freight');
        $save['ship']  = input('ship');
        //=============================分佣开始=====================================//

        //==============================分佣结束====================================//
        $flag = $order->editOrder($save);
        return json($flag);
    }

    //查看订单详情
    public function getOneOrder(){
        $id = input('id');
        $return['order'] = OrderModel::get($id);
        $detail = $return['order']->details;
        foreach($detail as $k=>$v){
            $detail[$k]['goods'] = GoodsModel::get(['id'=>$v->gid]);
            $detail[$k]['goods']->goods_type =  $detail[$k]['goods']->getClass->class;//分类名
        }

        return json(['code'=>1,'data'=>$return]);
    }

    //获得补差价信息
    public function tail_money(){
        $id = input('id');
        $return['order'] = OrderModel::get($id);
        $return['order']->tailMoney;
        return json(['code'=>1,'data'=>$return]);
    }

    //订单审核
    public function tail_money_edit(){

        $order = new OrderModel();
        $save['id'] = input('id');
        $save['updated_at'] = time();
        if(input('status') == 3){
            $save['message'] = '已驳回';
            $flag = $order->editOrder($save);
            return json($flag);
        }elseif(input('status') == 4){
            $save['status'] = input('status');
            $save['message'] = '审核通过';
            $save['fenhong'] = 1;
            //=============================算法开始=====================================//
            $config = file_get_contents('./config');
            // $config = unserialize($config)['conf'];
            $orders = db('order')->where(['id'=>$save['id']])->find();
            if($orders['payment'] != 4){
                $user = db('users')->where(['id'=>$orders['uid']])->find();
                $price = $orders['price'];
                Db::startTrans();
                try{
                    //====================进行分销====================//
                    $p1 = db('users')->where(['id'=>$user['pid']])->find();
                    if($p1){
                        if($price > 0){
                            // $bili = $p1['class'] * 3 - 3;
                            $fan = round($price * $config['one_scale'] / 100,2);
                            add_account($p1['id'],$fan,'下级用户'.$user['nickname'].'的分销奖金',3,$user['id']);
                        }
                        $p2 = db('users')->where(['id'=>$p1['pid']])->find();
                        if($p2){
                            if($price > 0){
                                // $bili = $p2['class'] * 3 - 2;
                                $fan = round($price * $config['two_scale'] / 100,2);
                                add_account($p2['id'],$fan,'下级用户'.$user['nickname'].'的分销奖金',3,$user['id']);
                            }
                           $p3 = db('users')->where(['id'=>$p2['pid']])->find();
                           if($p3){
                               if($price > 0){
                                   // $bili = $p3['class'] * 3 - 1;
                                   $fan = round($price * $config['three_scale'] / 100,2);
                                   add_account($p3['id'],$fan,'下级用户'.$user['nickname'].'的分销奖金',3,$user['id']);
                               }
                               $p4 = db('users')->where(['id'=>$p3['pid']])->find();
                               if($p4){
                                    //===============见点奖==============
                                    jiandianjiang($user['id'],$orders['id']);
                                    //==============见点奖结束============
                               }
                           }
                        }
                    }
                    //====================分销结束====================//
                    // 提交事务
                    Db::commit();
                    $flag = $order->editOrder($save);
                    return json($flag);
                } catch (\Exception $e) {
                    // 回滚事务
                    Db::rollback();
                    return json(['code'=>0,'data'=>'','msg' =>'提交失败']);
                }
            }
                //==============================算法结束====================================//
        }

    }


}