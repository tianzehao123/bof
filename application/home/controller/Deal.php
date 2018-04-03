<?php
namespace app\home\controller;

use think\Db;
use app\backend\model\UserModel;

class Deal extends Base{
    private $uid;   

    function _initialize(){
        $this->uid = session('home.user')['id'];
        // $this->uid = 1;#TODO
    }

    #积分交易
    //提交需要post   type:交易类型(0银行卡,1支付宝,2微信) score:交易金额   remark:备注
    public function score_deal(){
        $userModel = new UserModel();

        $user = db('users')->where(['id'=>$this->uid])->find();

        $return = [
            'ucode' => $user['code'],           //用户编号
            'score' => round($user['prize_score'],2),    //奖励积分
            'uphone' => $user['phone'],          //联系电话
            'p_phone' => db('users')->where(['id'=>$user['pid']])->value('phone'),  //推荐人电话
            'bank_name' => $user['bank_name'],              //银行名称
            'bank_user' => $user['bank_user'],              //开户人
            'bank_account' => $user['bank_account'],        //银行卡号
            'bank_branch' => $user['bank_branch'],              //开户支行
            'zhifubao' => $user['zhifubao'],              //绑定支付宝
            'weixin' => $user['weixin'],              //绑定微信
        ];
        
        //判断用户能挂售的级别
        if(in_array($user["class"],[1,2,3])){
            $return['class'] = [100];
        }else if($user["class"] == 4){
            $return['class'] = [100,300,400];
        }else if($user["class"] == 5){
            $return['class'] = [300,400,500];
        }else if(in_array($user["class"],[6,7])){
            $return['class'] = [300,400,500,600];
        }else{
            $return['class'] = [0];
        }
        //积分交易提交
        if(request()->isPost()){
            if($userModel->getMerchant($this->uid)){
                return ajax('0','报单中心不能挂售积分!');
            }

            //type:0.银行卡,1.支付宝,2.微信
            if(!input('score')){
                return ajax('0','卖出积分数量不能为空!');
            }else{
                if(in_array($user["class"],[1,2,3])){
                    if(input('score') != 100){
                        return ajax('0','普卡,银卡,金卡只能挂售100奖励积分');
                    }
                }else if($user["class"] == 4){
                    if(input('score') != 100 && input('score') != 300 && input('score') != 400){
                        return ajax('0','白金卡只能挂售100,300,400奖励积分');
                    }
                }else if($user["class"] == 5){
                    if(input('score') != 500 && input('score') != 300 && input('score') != 400){
                        return ajax('0','黑金卡只能挂售300,400,500奖励积分');
                    }
                }else if(in_array($user["class"],[6,7])){
                    if(input('score') != 500 && input('score') != 300 && input('score') != 400 && input('score') != 600){
                        return ajax('0','钻卡,蓝钻卡只能挂售300,400,500,600奖励积分');
                    }
                }else{
                     return ajax('0','请稍后重试');
                }
                //限制最大和最小交易积分
                // if(!is_numeric(input('score')) || input('score') < 300 || input('score') > 600 ){
                //     return ajax('0','请输入正确的积分数量!');
                // }
                if(input('score') > $user['prize_score']){
                    return ajax('0','您的奖励积分数量不足!');
                }
            }
            //今天的开始和结束时间戳
            $day_time = getDay();
            if(db('score_deal')->where(['created_at'=>['between',$day_time],'uid'=>$user['id']])->find()){
                return ajax('0','奖励积分每天限交易一次!');
            }
            $time = time();
            $insert = [
                'uid' => $user['id'],
                'type' => input('type')??'0',
                'score' => input('score'),
                'rest_score' => $user['prize_score'] - input('score'),
                'true_score' => input('score'),
                'remark' => htmlspecialchars(input('remark')),
                'created_at' => $time,
                'updated_at' => $time
            ];

            if($user["sell_trial"] == 1){
                $insert["status"] = 7;
            }else{
                $insert["status"] = 1;
            }
            unset($return["class"]);
            Db::startTrans();
            $insert = array_merge($return,$insert);
            $res = db('score_deal')->insert($insert);
            $res1 = add_account($user['id'],-$insert['score'],$user['prize_score']-$insert['score'],'出售奖励积分','支出','奖励积分',$user['id'],'10');
            if($res && $res1){
                Db::commit();
                return ajax('1','提交成功');
            }else{
                Db::rollback();
                return ajax('0','提交失败');
            }
        }
        return ajax('1','查询成功',$return);
    }

    #交易大厅
    //需要开始时间start_time,结束时间stop_time,最大金额max_score,最小金额min_score,当前页数page
    public function deal_hall(){
        $num = 12;     //一页数量
        $page = input('page')??'1';
        $start_time = input('start_time');
        $stop_time = input('stop_time');
        $min_score = input('min_score');
        $max_score = input('max_score');
        $where = [];
        $and_where = [];
        $where['status'] = 1;
        if(!empty($start_time)){
            $where['created_at'] = ['>=',strtotime($start_time.' 00:00:00')];
        }
        if(!empty($stop_time)){
            $and_where['created_at'] = ['<=',strtotime($stop_time.' 23:59:59')];
        }
        if(!empty($min_score)){
            $where['score'] = ['>=',$min_score];
        }
        if(!empty($max_score)){
            $and_where['score'] = ['<=',$max_score];
        }
        $return['all_num'] = db('score_deal')->where($where)->where($and_where)->count();
        $return['all_page'] = ceil($return['all_num'] / $num);
        if($page > $return['all_page']){
            $page = $return['all_page'];
        }
        if($page < '1'){
            $page = '1';
        }
        $return['page'] = $page;
        //需要的参数
        $need = ['id','created_at','ucode','score','true_score','status'];
        $list = db('score_deal')->where($where)->where($and_where)->limit($num * ($page - 1),$num)->field($need)->order('id desc')->select();

        $status = config('score_deal_status');
        foreach($list as $k=>$v){
            $list[$k]['order_sn'] = $v['created_at'].$v['id'];
            $list[$k]['created_at'] = date('Y-m-d H:i:s',$v['created_at']);
            $list[$k]['num_status'] = $v['status'];
            $list[$k]['status'] = $status[$v['status']];
        }
        $return['list'] = $list;
//        $return['score'] = db('users')->where(['id'=>$this->uid])->value('prize_score');    //用户积分数量
        return ajax('1','查询成功',$return);
    }

    #获取积分交易卖家的详细信息(点击购买)
    //需要交易id
    public function score_deal_detail_sell(){
        $id = input('id');
        if(!$id){
            return ajax('0','非法操作!');
        }
        $return = db('score_deal')->where(['id'=>$id])->find();
        $return['order_sn'] = $return['created_at'].$id;
        return ajax('1','查询成功',$return);
    }

    #立即购买奖励积分
    //需要订单id
    public function pay_score(){
        $id = input('id');
        if(!$id){
            return ajax('0','非法操作!');
        }
        $order = db('score_deal')->where(['id'=>$id])->find();
        if(!$order){
            return ajax('0','不存在的订单!');
        }
        if($order['uid'] == $this->uid){
            return ajax('0','不能购买自己挂售的积分!');
        }
        if($order['status'] == 5){
            return ajax('0','该交易已在进行中!');
        }
        if($order['status'] != 1){
            return ajax('0','该订单状态无法购买!');
        }
        $user_need = ['id','code','phone','reg_score'];
        $user = db('users')->where(['id'=>$this->uid])->field($user_need)->find();
        Db::startTrans();
        //将订单改为交易中状态
        db('score_deal')->where(['id'=>$id])->update(['status'=>5]);
        $time = time();
        $insert = [
            'sid' => $id,
            'mid' => $user['id'],
            'mcode' => $user['code'],
            'mphone' => $user['phone'],
            'remark' => $user['reg_score'],
            'status' => '1',
            'created_at' => $time,
            'updated_at' => $time
        ];
        $res = db('score_deal_detail')->insert($insert);
        if($res){
            Db::commit();
            return ajax('1','提交成功');
        }else{
            Db::rollback();
            return ajax('0','提交失败');
        }
    }

    #售出记录
    //需要开始时间start_time,结束时间stop_time,最大金额max_score,最小金额min_score,当前页数page
    public function sell_deal(){
        $num = 12;     //一页数量
        $page = input('page')??'1';
        $start_time = input('start_time');
        $stop_time = input('stop_time');
        $min_score = input('min_score');
        $max_score = input('max_score');
        $where = [];
        $where['uid'] = $this->uid;
        $where['status'] = ['in','1,2,5,7'];  //只显示 （挂售中，交易中，已完成,待审核的订单）
        $and_where = [];
        if(!empty($start_time)){
            $where['created_at'] = ['>=',strtotime($start_time.' 00:00:00')];
        }
        if(!empty($stop_time)){
            $and_where['created_at'] = ['<=',strtotime($stop_time.' 23:59:59')];
        }
        if(!empty($min_score)){
            $where['score'] = ['>=',$min_score];
        }
        if(!empty($max_score)){
            $and_where['score'] = ['<=',$max_score];
        }
        $return['all_num'] = db('score_deal')->where($where)->where($and_where)->count();
        $return['all_page'] = ceil($return['all_num'] / $num);
        if($page > $return['all_page']){
            $page = $return['all_page'];
        }
        if($page < '1'){
            $page = '1';
        }
        $return['page'] = $page;
        //需要的参数
        $need = ['id','ucode','created_at','updated_at','score','rest_score','true_score','status'];
        $list = db('score_deal')->where($where)->where($and_where)->limit($num * ($page - 1),$num)->field($need)->order('id desc')->select();

        $status = config('score_deal_status');
        foreach($list as $k=>$v){
            $list[$k]['order_sn'] = $v['created_at'].$v['id'];
            $list[$k]['created_at'] = date('Y-m-d H:i:s',$v['created_at']);
            $list[$k]['updated_at'] = date('Y-m-d H:i:s',$v['updated_at']);
            $list[$k]['pay_code'] = db('score_deal_detail')->where(['sid'=>$v['id'],'status'=>['in','1,2']])->order('id desc')->value('mcode');
            $list[$k]['status_num'] = $v['status'];
            $list[$k]['status'] = $status[$v['status']];
        }
        $return['list'] = $list;
        return ajax('1','查询成功',$return);
    }

    #获取积分交易买家的详细信息(查看详情)
    //需要交易id
    public function score_deal_detail_pay(){
        $id = input('id');
        if(!$id){
            return ajax('0','非法操作!');
        }
        $order = db('score_deal')->where(['id'=>$id])->find();
        $detail = db('score_deal_detail')->where(['sid'=>$id,'status'=>['in','1,2']])->order('id desc')->find();
        $return = [
            'order_sn' => $order['created_at'].$id,
            'pay_code' => $detail['mcode'],
            'pay_phone' => $detail['mphone'],
            'score' => $order['score'],
            'true_score' => $order['true_score'],
            'murl' => $detail['murl']
        ];
        return ajax('1','查询成功',$return);
    }

    #卖家取消订单
    //需要订单id
    public function remove_order(){
        $id = input('id');
        if(!$id){
            return ajax('0','非法操作!');
        }
        $order = db('score_deal')->where(['id'=>$id])->find();
        $prize_score = db('users')->where(['id'=>$this->uid])->value('prize_score');
        if($order['uid'] != $this->uid){
            return ajax('0','非法操作!');
        }
        $detail = db('score_deal_detail')->where(['sid'=>$id,'status'=>['in','1,2']])->order('id desc')->find();
        Db::startTrans();
        $res = db('score_deal')->where(['id'=>$id])->update(['status'=>4,'updated_at'=>time()]);
        if($detail){
            $res2 = db('score_deal_detail')->where(['id'=>$detail['id']])->update(['status'=>5,'updated_at'=>time()]);
        }
        $res1 = add_account($this->uid,$order['score'],$prize_score+$order['score'],'取消奖励积分出售退还','收入','奖励积分',$this->uid,'10');
        if($res && $res1){
            Db::commit();
            return ajax('1','取消成功');
        }else{
            Db::rollback();
            return ajax('0','取消失败');
        }
    }

    #确认收款
    //需要订单id
    public function sure_money(){
        $id = input('id');
        if(!$id){
            return ajax('0','非法操作!');
        }
        $order = db('score_deal')->where(['id'=>$id])->find();
        if($order['uid'] != $this->uid){
            return ajax('0','非法操作!');
        }
        if($order['status'] != 5){
            return ajax('0','订单状态不符!');
        }
        $detail = db('score_deal_detail')->where(['sid'=>$id,'status'=>['in','1,2']])->order('id desc')->find();
        if(!$detail['murl']){
            return ajax('0','该用户还没有提交打款凭证!');
        }
        $reg_score = db('users')->where(['id'=>$detail['mid']])->value('reg_score');
        Db::startTrans();
        $res = db('score_deal')->where(['id'=>$id])->update(['status'=>2,'updated_at'=>time()]);
        $res2 = db('score_deal_detail')->where(['id'=>$detail['id']])->update(['status'=>2,'updated_at'=>time()]);
        $res1 = add_account($detail['mid'],$order['score'],$reg_score+$order['score'],'购买积分','收入','注册积分',$this->uid,'10');
        if($res && $res1 && $res2){
            Db::commit();
            return ajax('1','收款成功');
        }else{
            Db::rollback();
            return ajax('0','收款失败');
        }
    }

    #买入记录
    //需要开始时间start_time,结束时间stop_time,最大金额max_score,最小金额min_score,当前页数page
    public function pay_log(){
        $num = 12;     //一页数量
        $page = input('page')??'1';
        $start_time = input('start_time');
        $stop_time = input('stop_time');
        $min_score = input('min_score');
        $max_score = input('max_score');
        $where = [];

        $and_where = [];
        if(!empty($start_time)){
            $where['created_at'] = ['>=',strtotime($start_time.' 00:00:00')];
        }
        if(!empty($stop_time)){
            $and_where['created_at'] = ['<=',strtotime($stop_time.' 23:59:59')];
        }
        if(!empty($min_score)){
            $where['score'] = ['>=',$min_score];
        }
        if(!empty($max_score)){
            $and_where['score'] = ['<=',$max_score];
        }
        $all_pay_order = db('score_deal_detail')->where(['mid'=>$this->uid,'status'=>['in','1,2']])->column('sid');
        $where['id'] = ['in',$all_pay_order];
        $return['all_num'] = db('score_deal')->where($where)->where($and_where)->count();
        $return['all_page'] = ceil($return['all_num'] / $num);
        if($page > $return['all_page']){
            $page = $return['all_page'];
        }
        if($page < '1'){
            $page = '1';
        }
        $return['page'] = $page;
        //需要的参数
        $need = ['id','created_at','updated_at','ucode','score','true_score','status'];
        $list = db('score_deal')->where($where)->where($and_where)->limit($num * ($page - 1),$num)->field($need)->order('id desc')->select();

        $status = config('score_deal_detail_status');
        foreach($list as $k=>$v){
            $detail = db('score_deal_detail')->where(['sid'=>$v['id'],'mid'=>$this->uid])->field(['remark','status'])->order('id desc')->find();
            $list[$k]['order_sn'] = $v['created_at'].$v['id'];
            $list[$k]['created_at'] = date('Y-m-d H:i:s',$v['created_at']);
            $list[$k]['updated_at'] = date('Y-m-d H:i:s',$v['updated_at']);
            $list[$k]['pay_front_score'] = $detail['remark'];
            $list[$k]['pay_after_score'] = $list[$k]['pay_front_score'] + $v['score'];
            $list[$k]['status_num'] = $detail['status'];
            $list[$k]['status'] = $status[$detail['status']];
        }
        $return['list'] = $list;
        return ajax('1','查询成功',$return);
    }

    #获取积分交易卖家的详细信息(上传凭证)
    //需要交易id
    public function upload_deal_detail_sell(){
        $id = input('id');
        if(!$id){
            return ajax('0','非法操作!');
        }
        $return = db('score_deal')->where(['id'=>$id])->find();
        $return['order_sn'] = $return['created_at'].$id;
        $detail = db('score_deal_detail')->where(['sid'=>$id])->order('id desc')->find();
        if($detail){
            $return['murl'] = $detail['murl'];
            $return['pay_phone'] = $detail['mphone'];
            $return['pay_code'] = $detail['mcode'];
        }else{
            $return['tankuang'] = 'yes';
        }
        return ajax('1','查询成功',$return);
    }

    #上传打款凭证
    //需要订单id，凭证url
    public function upload_proof(){
        $id = input('id');
        $url = input('url');
        if( empty($id) || empty($url) ){
            return ajax('0','参数错误');
        }
        $where = [
            'sid' => $id,
            'mid' => $this->uid,
        ];
        $order = db('score_deal')->where(['id'=>$id])->value('status');
        $detail = db('score_deal_detail')->where($where)->order('id desc')->value('status');
        if(!$detail){
            return ajax('0','订单不存在!');
        }
        if($detail != 1 || $order != 5 ){
            return ajax('0','该订单状态不能提交打款凭证!');
        }
        db('score_deal')->where(['id'=>$id])->update(['updated_at'=>time()]);     //提交凭证后记录订单的支付时间
        db('score_deal_detail')->where($where)->order('id desc')->update(['murl'=>$url,'updated_at'=>time()]);
        return ajax('1','提交成功!');
    }

    #买家取消
    //需要订单id
    public function remove_order_pay(){
        $id = input('id');
        $where = [
            'sid' => $id,
            'mid' => $this->uid,
        ];
        $detail = db('score_deal_detail')->where($where)->order('id desc')->find();
        if(!$detail){
            return ajax('0','订单不存在!');
        }
        if($detail['status'] != 1){
            return ajax('0','该状态不能取消!');
        }
        Db::startTrans();
        $res = db('score_deal_detail')->where(['id'=>$detail['id']])->update(['status'=>4,'updated_at'=>time()]);
        $res1 = db('score_deal')->where(['id'=>$id])->update(['status'=>1,'updated_at'=>time()]);
        if($res && $res1){
            Db::commit();
            return ajax('1','取消成功!');
        }else{
            Db::rollback();
            return ajax('0','取消失败!');
        }
    }

    #失效交易
    //需要开始时间start_time,结束时间stop_time,最大金额max_score,最小金额min_score,当前页数page
    public function err_order(){
        $num = 12;     //一页数量
        $page = input('page')??'1';
        $start_time = input('start_time');
        $stop_time = input('stop_time');
        $min_score = input('min_score');
        $max_score = input('max_score');
        $where = [];
        $where['status'] = ['in','3,4,6'];
        $and_where = [];
        if(!empty($start_time)){
            $where['created_at'] = ['>=',strtotime($start_time.' 00:00:00')];
        }
        if(!empty($stop_time)){
            $and_where['created_at'] = ['<=',strtotime($stop_time.' 23:59:59')];
        }
        if(!empty($min_score)){
            $where['score'] = ['>=',$min_score];
        }
        if(!empty($max_score)){
            $and_where['score'] = ['<=',$max_score];
        }
        $type = input('type') ?? '买入';
        if($type == '买入'){
            $all_pay_order = db('score_deal_detail')->where(['mid'=>$this->uid,'status'=>['in','3,4,5,6']])->column('sid');
//        dump($this->uid);
//        dump($all_pay_order);exit;
            $where['id'] = ['in',$all_pay_order];
            $status = config('score_deal_detail_status');
        }else{
            $where['uid'] = $this->uid;
            $status = config('score_deal_status');
        }

        $return['all_num'] = db('score_deal')->where($where)->where($and_where)->count();
        $return['all_page'] = ceil($return['all_num'] / $num);
        if($page > $return['all_page']){
            $page = $return['all_page'];
        }
        if($page < '1'){
            $page = '1';
        }
        $return['page'] = $page;
        //需要的参数
        $need = ['id','created_at','updated_at','ucode','score','true_score','status'];
        $list = db('score_deal')->where($where)->where($and_where)->limit($num * ($page - 1),$num)->field($need)->order('id desc')->select();

        foreach($list as $k=>$v){
            $list[$k]['order_sn'] = $v['created_at'].$v['id'];
            $list[$k]['updated_at'] = date('Y-m-d H:i:s',$v['updated_at']);
            $list[$k]['status_num'] = $v['status'];
            $list[$k]['status'] = $status[$v['status']];
        }

        $return['list'] = $list;
        return ajax('1','查询成功',$return);
    }

    //每一小时扫描一次，检查违规用户
    public function inspect_Illegal(){
        $time = time();
        $hours = 60 * 60;
        $stop = $time - $hours * 24;
        $start = ($time - $hours * 25) - 1;
        $inspect[0] = $start;
        $inspect[1] = $stop;
        //=============已付款但是未点确认收款==============//
        $detail = db('score_deal_detail')->where(['updated_at'=>['between',$inspect],'status'=>1,'murl'=>['neq','']])->column('sid');
        $where = [
            'id' => ['in',$detail],
            'updated_at' => ['between',$inspect],
            'status' => 5
        ];
        $deal = db('score_deal')->where($where)->select();
        foreach($deal as $k=>$v){
            db('score_deal')->where(['id'=>$v['id']])->update(['status'=>3,'updated_at'=>time()]);
            db('score_deal_detail')->where(['status'=>1,'murl'=>['neq',''],'sid'=>$v['id']])->order('id desc')->update(['status'=>3,'updated_at'=>time()]);
            $user = db('users')->where(['id'=>$v['uid']])->field(['prize_score','star'])->find;
            add_account($v['uid'],$v['score'],$user['prize_score']+$v['score'],'取消奖励积分出售退还','收入','奖励积分',$v['uid'],'10');
            $kou_fen = 0;
            $star = 0;
            switch($user['star']){
                case 5:
                    $kou_fen = -50;
                    $star = 4;
                    break;
                case 4:
                    $kou_fen = -100;
                    $star = 3;
                    break;
                case 3:
                    $kou_fen = -300;
                    $star = 2;
                    break;
                case 2:
                    $kou_fen = -400;
                    $star = 1;
                    break;
                case 1:
                    $kou_fen = -500;
                    $star = 0;
                    break;
            }
            add_account($v['uid'],$kou_fen,$user['prize_score']+$v['score']+$kou_fen,'违反积分出售规则','支出','奖励积分',$v['uid'],'10',$star);
        }
        //=============已付款但是未点确认收款==============//
        //=============拍下订单未汇款的==============//
        $where = [];
        $where = [
            'updated_at' => ['between',$inspect],
            'status' => 1,
            'murl' => ['eq','']
        ];
        $detail = db('score_deal_detail')->where($where)->select();

        foreach($detail as $k=>$v){
            $score = db('score_deal')->where(['id'=>$v['sid']])->value('score');
            db('score_deal_detail')->where(['id'=>$v['mid']])->update(['status'=>3,'updated_at'=>time()]);
            $user = db('users')->where(['id'=>$v['mid']])->field(['prize_score','star'])->find;

            $kou_fen = 0;
            $star = 0;
            switch($user['star']){
                case 5:
                    $kou_fen = -50;
                    $star = 4;
                    break;
                case 4:
                    $kou_fen = -100;
                    $star = 3;
                    break;
                case 3:
                    $kou_fen = -300;
                    $star = 2;
                    break;
                case 2:
                    $kou_fen = -400;
                    $star = 1;
                    break;
                case 1:
                    $kou_fen = -500;
                    $star = 0;
                    break;
            }
            add_account($v['mid'],$kou_fen,$user['prize_score']+$kou_fen,'违反积分购买规则','支出','奖励积分',$v['mid'],'10',$star);
        }
        //=============拍下订单未汇款的==============//
        echo '检查完成';
    }
}