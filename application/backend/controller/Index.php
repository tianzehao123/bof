<?php
namespace app\backend\controller;

class Index extends Base
{
    public function index()
    {
        return $this->fetch('/index');
    }

    /**
     * 后台默认首页
     * @return mixed
     */
    public function indexPage()
    {

        $month = getMonth();
        $day = getDay();
        #累计会员总量
        $all_user = db('users')->count();  
        #历史累计收入总额
        $a = round(db('order')->where(['status'=>['>',3]])->sum('price'),2);
        $b = db('recharge')->where(['status'=>2])->sum('money');
        $all_money = $a+$b;
        #会员账户余额总计
        $user_all_money = round(db('users')->sum('balance'),2); 
        #今日新增会员量统计
        $day_user = db('users')->where(['created_at'=>['between',$day]])->count();
        #今日订单统计
        $day_order = db('order')->where(['status'=>['>',2],'created_at'=>['between',$day]])->sum('price');
        #今日积分购买订单统计
        $price_day_order = db('order')->where(['payment'=>3,'status'=>['>',3],'created_at'=>['between',$day]])->sum('price');
        #已发放提现总额
        $withdraw_true = db('withdraw')->where(['status'=>2])->sum('money');
        #今日提现总额：
        $day_withdraw_money = 0;
        #今日已驳回提现统计
        $day_withdraw_false = 0;
        $day_withdraw_user = db('withdraw')->where(['created_at'=>['between',$day]])->select();
        $day_withdraw_user_num = array();
        foreach($day_withdraw_user as $k=>$v){
            #今日提现总额：
           $day_withdraw_money += $v['money'];
            if(!in_array('uid',$day_withdraw_user_num)){
                $day_withdraw_user_num[] = $v['uid'];
            }
            if($v['status'] ==3){
                $day_withdraw_false++;
            }
        }
        #今日提现人数统计
        $day_withdraw_user_nums = count($day_withdraw_user_num);
        #未消费会员统计
        $one_user = db('users')->where(['class'=>1])->count();
        #积分支付统计

        #日波比：

        #总波比：

       /* $day_order = db('order')->where(['status'=>['>',3],'created_at'=>['between',$day]])->sum('price');//今日订单统计
        $zhifubao_day_order = db('order')->where(['payment'=>1,'status'=>['>',3],'created_at'=>['between',$day]])->sum('price');//今日支付宝购买订单
        $weixin_day_order = db('order')->where(['payment'=>2,'status'=>['>',3],'created_at'=>['between',$day]])->sum('price');//今日微信购买订单
        $price_day_order = db('order')->where(['payment'=>3,'status'=>['>',3],'created_at'=>['between',$day]])->sum('price');//今日余额购买订单
        $xianxia_day_order = db('order')->where(['payment'=>NULL,'status'=>['>',3],'created_at'=>['between',$day]])->sum('price');//今日线下购买订单
        $withdraw_true = db('withdraw')->where(['status'=>2])->sum('money');//已发放提现总额


        
        $guanlijiang = db('account')->where(['type'=>8])->sum('balance');//管理奖*/
        $orderMony = db('order')->whereIn('payment',[1,2])->sum('price');
        $this->assign([
            'day_user'=>$day_user,
            'all_user'=>$all_user,
            'all_money'=>$all_money,
            'user_all_money'=>$user_all_money,
            /*'day_order'=>$day_order,
            'zhifubao_day_order'=>$zhifubao_day_order,
            'weixin_day_order'=>$weixin_day_order,
            'price_day_order'=>$price_day_order,
            'xianxia_day_order'=>$xianxia_day_order,*/
            'withdraw_true'=>$withdraw_true,
            'day_withdraw_money'=>$day_withdraw_money,
            'day_withdraw_user_nums'=>$day_withdraw_user_nums,
            'day_withdraw_false'=>$day_withdraw_false,
            'one_user'=>$one_user,
            'orderMony'=>$orderMony,
            // 'guanlijiang'=>$guanlijiang,
        ]);
        return $this->fetch('index');
    }
}
