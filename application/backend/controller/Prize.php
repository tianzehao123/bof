<?php
/**
 * Created by PhpStorm.
 * User: ovo
 * Date: 2017/7/10
 * Time: 下午6:08
 * 奖励积分交易
 */
namespace app\backend\controller;

use app\backend\model\PrizeModel;
use think\Db;

class Prize extends Base{
    //奖励积分交易记录
    public function index()
    {
        $status = config('score_deal_status');
        $type = [
            '0' => '银行卡',
            '1' => '支付宝',
            '2' => '微信'
        ];
        if(request()->isAjax()){

            $param = input('param.');

            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;

            $where = [];
            $and_where = [];
            if (isset($param['user_code']) && !empty($param['user_code'])) {
                $uid = db('users')->where(['code'=>$param['user_code']])->value('id');
                $where['uid'] = $uid;
            }
            //分类
            if (isset($param['status']) && !empty($param['status'])) {
                $where['status'] = $param['status'];
            }
            //类型
//            if (isset($param['type']) && !empty($param['type'])) {
//                $where['type'] = $param['type'];
//            }
            //时间
            if (isset($param['start']) && !empty($param['start'])) {
                $param['start'] = strtotime($param['start']);
                $where['created_at'] = ['>=',$param['start']];
            }
            if (isset($param['end']) && !empty($param['end'])) {
                $param['end'] = strtotime($param['end'].' 23:59:59');
                $and_where['created_at'] = ['<=',$param['end']];
            }
            $user = new PrizeModel();
            $selectResult = $user->getPrizeByWhere($where,$and_where,$offset, $limit);
            foreach($selectResult as $key=>$vo){
                $this_status = $vo['status'];
                $selectResult[$key]['order_sn'] = $vo['created_at'].$vo['id'];
                $selectResult[$key]['old_score'] = $vo['rest_score'] + $vo['score'];
                $selectResult[$key]['type'] = $type[$vo['type']];
                $selectResult[$key]['status'] = $status[$vo['status']];
                $selectResult[$key]['created_at'] = date('Y-m-d H:i:s',$vo['created_at']);
                $selectResult[$key]['updated_at'] = !empty($vo['updated_at'])?date('Y-m-d H:i:s',$vo['updated_at']):'';
                $pay_detail = db('score_deal_detail')->where(['sid'=>$vo['id'],'status'=>['in','1,2']])->field(['id','mcode'])->order('id desc')->find();
                $selectResult[$key]['pay_code'] = "<b onclick='pay_detail(".$pay_detail['id'].")'>{$pay_detail['mcode']}</b>";
                $selectResult[$key]['ucode'] =  "<b onclick='sell_detail(".$vo['id'].")'>{$vo['ucode']}</b>";
                $operate = [];
                if($this_status == '1'){
                    $operate = [
                        '取消'=>"javascript:operation(".$vo['id'].",1)",
                    ];
                }elseif($this_status == '5'){
                    $operate = [
                        '确认'=>"javascript:operation(".$vo['id'].",2)",
                    ];
                }

                $selectResult[$key]['operation'] = showOperate($operate);
            }

            if(isset($param['excel']) && $param['excel'] == 'to_excel'){    //导出到excel
                $content = objToArray($selectResult);
                foreach($content as $k=>$v){
                    unset($content[$k]['is_add']);
                    unset($content[$k]['source']);
                    unset($content[$k]['updated_at']);
                }
                $excel = new Excel();
                $first = ['A1'=>'编号','B1'=>'用户id','C1'=>'收入/支出数量','D1'=>'剩余数量','E1'=>'原因','F1'=>'分类','G1'=>'类型','H1'=>'来自用户编号','I1'=>'发放时间','J1'=>'用户编号'];
                $excel->toExcel('订单列表',$content,$first);
                return json(['code'=>1]);
            }

            $return['total'] = $user->getAllPrize($where);  //总数据
            $return['rows'] = $selectResult;

            return json($return);
        }
        return $this->fetch();
    }

    //获取购买人信息
    public function pay_detail(){
        $id = input('id');
        $status = [
            '1' => '确认付款',
            '2' => '确认收款',
            '3' => '失败',
            '4' => '买家取消',
            '5' => '卖家取消',
            '6' => '后台审核中',
            '7' => '待审核'
        ];
        $detail = db('score_deal_detail')->where(['id'=>$id])->find();
        $score = db('score_deal')->where(['id'=>$detail['sid']])->value('score');
        $str = '';
        $str .= '编号:'.$detail['mcode'].'<br/>';
        $str .= '手机号:'.$detail['mphone'].'<br/>';
        $str .= '买前积分:'.$detail['remark'].'<br/>';
        $str .= '购买积分:'.$score.'<br/>';
        $str .= '买后积分:'.($detail['remark']+$score).'<br/>';
        $str .= '购买日期:'.date('Y-m-d H:i:s',$detail['created_at']).'<br/>';
        $str .= '操作日期:'.date('Y-m-d H:i:s',$detail['updated_at']).'<br/>';
        $str .= '订单状态:'.$status[$detail['status']].'<br/>';
        $str .= '打款凭证:<img style="height:350px;" src="'.$detail['murl'].'"><br/>';
        if($detail){
            return ajax('1','查询成功',$str);
        }else{
            return ajax('0','查询失败');
        }
    }

    //获取卖出人信息
    public function sell_detail(){
        $id = input('id');
        $status = config('score_deal_status');
        $type = [
            '0' => '银行卡',
            '1' => '支付宝',
            '2' => '微信'
        ];
        $order = db('score_deal')->where(['id'=>$id])->find();
        $str = '';
        $str .= '编号:'.$order['ucode'].'<br/>';
        $str .= '卖出积分:'.$order['score'].'<br/>';
        $str .= '联系电话:'.$order['uphone'].'<br/>';
        $str .= '推荐人手机号:'.$order['p_phone'].'<br/>';
        $str .= '收款类型:'.$type[$order['type']].'<br/>';
        $str .= '开户银行:'.$order['bank_name'].'<br/>';
        $str .= '开户支行:'.$order['bank_branch'].'<br/>';
        $str .= '开户人:'.$order['bank_user'].'<br/>';
        $str .= '银行卡号:'.$order['bank_account'].'<br/>';
        $str .= '支付宝:'.$order['zhifubao'].'<br/>';
        $str .= '微信:'.$order['weixin'].'<br/>';
        $str .= '状态:'.$status[$order['status']].'<br/>';
        $str .= '出售日期:'.date('Y-m-d H:i:s',$order['created_at']).'<br/>';
        $str .= '操作日期:'.date('Y-m-d H:i:s',$order['updated_at']).'<br/>';
        if($order){
            return ajax('1','查询成功',$str);
        }else{
            return ajax('0','查询失败');
        }
    }

    public function operation(){
        $id = input('id');
        $status = input('status');  //1:取消2:确认
        if($status == '1'){ //取消
            $order = db('score_deal')->where(['id'=>$id])->find();
            if($order){
                $detail = db('score_deal_detail')->where(['sid'=>$id,'status'=>['in','1,2']])->order('id desc')->find();
                $update = [
                    'status' => '6',
                    'reset_remark' => '管理员:'.session('username').'取消此次交易'
                ];
                db('score_deal')->where(['id'=>$id])->update($update);
                if($detail){
                    $d_update = [
                        'status' => '3',
                        'reset_remark' =>  '管理员:'.session('username').'取消此次交易'
                    ];
                    db('score_deal_detail')->where(['id'=>$detail['id']])->update($d_update);
                }
            }else{
                return ajax('0','订单不存在');
            }
        }elseif($status == '2'){    //确认
            $order = db('score_deal')->where(['id'=>$id])->find();
            if($order && $order['status'] == '5'){
                $detail = db('score_deal_detail')->where(['sid'=>$id,'status'=>['in','1,2']])->order('id desc')->find();
                $update = [
                    'status' => '2',
                    'reset_remark' => '管理员:'.session('username').'确认此次交易',
                    'updated_at' => time()
                ];
                if($detail){
                    $d_update = [
                        'status' => '2',
                        'reset_remark' =>  '管理员:'.session('username').'确认此次交易',
                        'updated_at' => time()
                    ];
                    $prize_score = db('users')->where(['id'=>$detail['mid']])->value('prize_score');
                    Db::startTrans();
                    $res = db('score_deal')->where(['id'=>$id])->update($update);
                    $res2 = db('score_deal_detail')->where(['id'=>$detail['id']])->update($d_update);
                    $res1 = add_account($detail['mid'],$order['score'],$prize_score+$order['score'],'购买奖励积分','收入','奖励积分',$order['uid']);
                    if($res && $res1 && $res2){
                        Db::commit();
                        return ajax('1','提交成功');
                    }else{
                        Db::rollback();
                        return ajax('0','失败');
                    }
                }
            }else{
                return ajax('0','订单类型错误');
            }
        }

    }

}