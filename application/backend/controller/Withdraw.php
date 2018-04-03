<?php
/**
 * Created by PhpStorm.
 * User: ovo
 * Date: 2017/7/10
 * Time: 下午6:08
 */
namespace app\backend\controller;

use app\backend\model\RechargeModel;
use app\backend\model\WithdrawModel;
use app\backend\model\UserModel;

use alipayment\Alipay;
class Withdraw extends Base{
    const WITHDRAW = 'withdraw';       //提现表
    const RECHARGE = 'recharge';       //充值表
    const ACCOUNT  = 'account';        //账户明细表
    const USER     = 'users';          //用户表
    //用户列表
    public function index(){
        $user_class = config('user_class');
        if(request()->isAjax()){
            $user = new UserModel();
            $param = input('param.');
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = $whereu = $uids = [];
            if (isset($param['truename']) && !empty($param['truename'])) {
                $whereu['nickname'] = ['like', '%' . $param['truename'] . '%'];
            }
            if (isset($param['phone']) && !empty($param['phone'])) {
                $whereu['phone'] = ['like', '%' . $param['phone'] . '%'];
            }
            if(!empty($whereu)){
                $uids = $user->where($whereu)->column('id');
                $where['uid'] = ['in',$uids];
            }

            if (isset($param['start']) && !empty($param['start']) && empty($param['end'])) {
                $param['start'] = strtotime($param['start']);
                $where['created_at'] = ['>=',$param['start']];
            }
            if (isset($param['end']) && !empty($param['end']) && empty($param['start'])) {
                $param['end'] = strtotime($param['end']);
                $where['created_at'] = ['<=',$param['end']];
            }

            if (isset($param['user_class']) && !empty($param['user_class'])) {
                $users = db('users')->where(['class'=>$param['user_class']])->column('id');
                $where['uid'] = ['in',$users];
            }

            if (isset($param['status']) && !empty($param['status']) && $param['status']!="未选择" || (int)$param['status'] == 0) {
                $where['status'] = ['=',$param['status']];
            }
            if($param['status']=="未选择"){
                unset($where['status']);
            }
            if (isset($param['end']) && !empty($param['end']) && isset($param['start']) && !empty($param['start'])) {
                $time[1] = strtotime($param['end'].' 23:59:59');
                $time[0] = strtotime($param['start'].' 00:00:00');
                $where['created_at'] = ['between',$time];
            }
            $withdraw = new WithdrawModel();
            /*if(isset($param['excel']) && $param['excel'] == 'to_excel'){
                $offset = 0;
                $limit = 9999;
            }*/
            $selectResult = $withdraw->getWithdrawByWhere($where, $offset, $limit);
            $status = config('Withdraw_status');
            $type = config('Withdraw_type');
            foreach($selectResult as $key=>$vo){
                $selectResult[$key]['created_at'] = date('Y-m-d H:i:s', $vo['created_at']);
                $selectResult[$key]['phone'] = $vo['users']['phone'];
                $selectResult[$key]['nickname'] = $vo['users']['nickname'];
                if($vo['status'] == 0){
                    $operate = [
                        '同意' => "javascript:agree('".$vo['id']."')",
                        '拒绝' => "javascript:down('".$vo['id']."')"
                    ];
                    $selectResult[$key]['operate'] = showOperate($operate);
                }elseif($vo['status'] == 1){
                    $operate = [
                        '发放' => "javascript:grant('".$vo['id']."')"
                    ];
                    $selectResult[$key]['operate'] = showOperate($operate);
                }else{
                    $selectResult[$key]['operate'] = '-';
                }
                /*elseif($vo['status'] == 1){
                    $operate = [
                        '发放' => "javascript:grant('".$vo['id']."')"
                    ];
                    $selectResult[$key]['operate'] = showOperate($operate);
                }*/
                $selectResult[$key]['status'] = $status[$vo['status']];
                if($vo['type'] == 0){   //微信
                    $selectResult[$key]['fafang'] = '微信号:'.$vo['users']['weixin'].'<br/>收款码:<span onclick="imgs(\''.$vo['users']['wechat'].'\')">点击查看</span>';
                }elseif($vo['type'] == 1){
                    $selectResult[$key]['fafang'] = '支付宝姓名:'.$vo['users']['alname'].'<br/>绑定支付宝:'.$vo['users']['zhifubao'];
                }elseif($vo['type'] == 2){
                    $bank = db('bank')->where(['id'=>$vo['b_id']])->find();
                    $selectResult[$key]['fafang'] = '开户人姓名:'.$bank['kaihuren'].'<br/>开户行:'.$bank['kaihuzhihang'].'<br/>银行卡号:'.$bank['bank'];
                }
                $selectResult[$key]['type'] = $type[$vo['type']];
                $selectResult[$key]['user_class'] = $user_class[$vo['users']['class']];
                

            }
            /*if(isset($param['excel']) && $param['excel'] == 'to_excel'){    //导出到excel
                $content = $selectResult;
                
                $content = json_decode(json_encode($content),true);
                foreach($content as $k=>$v){
                    unset($content[$k]['operate']);
                    unset($content[$k]['uid']);
                    unset($content[$k]['s_msg']);
                }
                $excel = new Excel();

                $first = ['A1'=>'编号','B1'=>'提现金额','C1'=>'提现留言','D1'=>'状态','E1'=>'提现申请时间','F1'=>'提现方式','G1'=>'手机号','H1'=>'用户名','I1'=>'手续费','J1'=>'到账金额'];
                $excel->toExcel('提现列表',$content,$first);
                return json(['code'=>1]);
            }*/

            $return['total'] = $withdraw->getAllWithdraw($where);  //总数据
            $return['rows'] = $selectResult;

            return json($return);
        }
        $this->assign('user_class',$user_class);
        return $this->fetch();
    }
    #同意提现
    public function agree(){
        #100的整数倍，扣除5%的手续费，扣5%费用进入消费积分
      $id = input('param.id');
      $withdraw = new WithdrawModel();
      if($withdraw->where(['id'=>$id])->value('status')==0){
        $flag = $withdraw->editWithdraw(['id'=>$id,'status'=>1]);
        return json($flag);
      }else{
        return json(['code' => 10, 'data' => '', 'msg' => '订单不支持此操作']);
      }  
    }
     #拒绝提现
    public function down(){
      $id = input('param.id');
      $withdraw = new WithdrawModel();
      $data = $withdraw->getOneWithdraw($id);
      $status = $withdraw->where(['id'=>$id])->value('status');
      if($status==0 || $status==1){
          $req = add_account($data['uid'],$data['money'],'提现被拒',10,0);
        if($req){
            $flag = $withdraw->editWithdraw(['id'=>$id,'status'=>3]); 
            return json($flag);
        }
      }else{
        return json(['code' => 10, 'data' => '', 'msg' => '操作失败']);
      }  
    }
    #发放返现
    public function grant(){
    #100的整数倍，扣除5%的手续费，扣5%费用进入消费积分
        $id = input('param.id');
        $withdraw = new WithdrawModel();
        $data = $withdraw->getOneWithdraw($id);
        if($withdraw->where(['id'=>$id])->value('status')==1){
           $result = self::cash($id,$data['type']);
           if($result['status']==1){
                $flag = $withdraw->editWithdraw(['id'=>$id,'status'=>2]);
                return json($flag);
           }else{
               return json(['code'=>0,'data'=>'','msg'=>$result['data']]);
           }
        }else{
            return json(['code' => 10, 'data' => '', 'msg' => '操作失败']);
        } 
    }
    #充值列表
    /**
     * @return mixed|\think\response\Json
     */
    public function recharge(){
        if(request()->isAjax()){
            $user = new UserModel();
            $param = input('param.');
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = $whereu = $uids = [];
            if (isset($param['truename']) && !empty($param['truename'])) {
                $whereu['nickname'] = ['like', '%' . $param['truename'] . '%'];
            }
            if (isset($param['phone']) && !empty($param['phone'])) {
                $whereu['phone'] = ['like', '%' . $param['phone'] . '%'];
            }
            if(!empty($whereu)){
                $uids = $user->where($whereu)->column('id');
                $where['uid'] = ['in',$uids];
            }

            if (isset($param['start']) && !empty($param['start']) && empty($param['end'])) {
                $param['start'] = strtotime($param['start']);
                $where['created_at'] = ['>=',$param['start']];
            }
            if (isset($param['end']) && !empty($param['end']) && empty($param['start'])) {
                $param['end'] = strtotime($param['end']);
                $where['created_at'] = ['<=',$param['end']];
            }
            if (isset($param['status']) && !empty($param['status']) && $param['status']!="未选择" || (int)$param['status'] == 0) {
                $where['status'] = ['=',$param['status']];
            }
            if($param['status']=="未选择"){
                unset($where['status']);
            }
            if (isset($param['end']) && !empty($param['end']) && isset($param['start']) && !empty($param['start'])) {
                $time[1] = strtotime($param['end'].' 23:59:59');
                $time[0] = strtotime($param['start'].' 00:00:00');
                $where['created_at'] = ['between',$time];
            }
            $withdraw = new RechargeModel();

            $selectResult = $withdraw->getRechargeByWhere($where, $offset, $limit);
            $status = config('apply_status');
            $type   = config('Withdraw_type');

            foreach($selectResult as $key=>$vo){
                $selectResult[$key]['created_at'] = date('Y-m-d H:i:s', $vo['created_at']);
                $selectResult[$key]['phone'] = $vo['users']['phone'];
                $selectResult[$key]['nickname'] = $vo['users']['nickname'];
                $selectResult[$key]['status'] = $status[$vo['status']];
                if($vo['type'] == 2){   //银行卡
                    $selectResult[$key]['pay_cerl'] = $vo['order_sn'];
                }else{
                    $selectResult[$key]['pay_cerl'] = '<span onclick="imgs(\''.$vo['pay_cerl'].'\')">点击查看</span>';
                }
                $selectResult[$key]['pay_type'] = $type[$vo['pay_type']];
                if($vo['status'] == '申请中'){
                    $operate = [
                        '同意' => "javascript:agree('".$vo['id']."')",
                        '拒绝' => "javascript:down('".$vo['id']."')"
                    ];
                    $selectResult[$key]['operate'] = showOperate($operate);
                }else{
                    $selectResult[$key]['operate'] = '-';
                }
            }
            $return['total'] = $withdraw->getAllRecharge($where);  //总数据
            $return['rows'] = $selectResult;
            return json($return);
        }
        return $this->fetch();
    }
    #同意充值
    public function agree_recharge(){
        $id = input('param.id');
        $recharge = new RechargeModel();
        $data = $recharge->getOneRecharge($id);
        $status = $recharge->where(['id'=>$id])->value('status');
        if($status==1){
            $req = add_account($data['uid'],$data['money'],'充值成功',3,0);
            if($req){
                $flag = $recharge->editRecharge(['id'=>$id,'status'=>2]); 
                return json($flag);
            }
        }else{
            return json(['code' => 10, 'data' => '', 'msg' => '操作失败']);
        }  
    }
    #驳回充值
    public function refuse_recharge(){
        $id = input('param.id');
        $recharge = new RechargeModel();
        $data = $recharge->getOneRecharge($id);
        $status = $recharge->where(['id'=>$id])->value('status');
        if($status==1){
            $flag = $recharge->editRecharge(['id'=>$id,'status'=>3]); 
            return json($flag);
        }else{
            return json(['code' => 10, 'data' => '', 'msg' => '操作失败']);
        }  
    }

    #执行返现
   private function cash($id,$type){
        $withdraw = new WithdrawModel();
        $data = $withdraw->getOneWithdraw($id); 
        if($type==0){
            #微信返现(待定)

            return ['status'=>0,'data'=>'系统暂不满足微信提现条件，请换种方式提现'];


        }else if($type == 1){
            #支付宝返现
            $account = db('users')->where(['id'=>$data['uid']])->find();
            $osn = orderNum();
            $payee_account = $account['zhifubao'];
            // $conf = unserialize(file_get_contents('./config'));
            $conf = db('config')->where(['name'=>'cash_charge'])->value('value');
            $amount = $data['money']*(100-$conf)*0.01; 
            // $amount = 0.1; 
            $content = [
                #单号
                'trans_no' => $osn,    
                'payee_type' => 'ALIPAY_LOGONID',
                #提现账号    
                'payee_account' => $payee_account,
                #提现金额    
                'amount' => $amount,
                #留言    
                'remark' => "金帮手的提现",
                #真实姓名    
                'payee_real_name' => $account['alname'],
                ];
            $msg = Alipay::querys($content);
            if (is_object($msg) && $msg->msg == 'Success') {
                return ['status'=>1,'data'=>"提现成功"];
            }else{
                return ['status'=>0,'data'=>$msg->msg];
            }
        }
   } 
}