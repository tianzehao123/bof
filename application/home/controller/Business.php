<?php
/**
 * Created by PhpStorm.
 * User: ovo
 * Date: 2017/7/21
 * Time: 上午9:32
 */
namespace app\home\controller;

use think\Controller;
use Service\Rerformance;
use Service\Useractivate;
use Service\Stock;
use Service\Nineservice;
#业务管理
class Business extends Base
{
    private $uid;
    private $uidcode;
    const Amount  = ['0'=>0,'1'=>100,"2"=>300,"3"=>500,"4"=>1000,"5"=>3000,"6"=>5000,"7"=>10000];
    const CLASSS  = ['0'=>'免费会员','1'=>"普卡","2"=>"银卡","3"=>"金卡","4"=>"白金卡","5"=>"黑金卡","6"=>"钻卡","7"=>"蓝钻"];
    const IsLock =  ['1'=>'正常','2'=>'冻结'];
    public function _initialize()
    {
        $this->uid = session('home.user')['id'];
        // $this->uid = 48;
        $this->uidcode = db('users')->where('id',$this->uid)->value('code');

    }

    #列表
    public function list()
    {
        $num = input('num')?input('num'):'7';      #每页显示条数
        // var_dump($num);exit;
        $page = input('page')?input('page'):1;     #当前页数
        $start = input('start')?strtotime(input('start').'0:00:00'):'';        #开始日期
        $end = input('end')?strtotime(input('end').'23:59:59'):'';            #结束日期
        $code = input('code')?input('code'):'';          #会员编号
        $class = input('class')?input('class'):'';        #等级
        $where = '';
        
        if(!empty($code)){
           $where =$where." code like '%".$code."%' and ";
        }
        if(!empty($class)) {
            $where = $where.' class = '.$class.' and ';
        }
        if(!empty($start) and empty($end)){
            $where = $where.' created_at >= '.$start.' and ';
        }
        if(empty($start) and !empty($end)){
           $where = $where.' created_at <= '.$end.' and ';
        }
        if(!empty($start) and !empty($end)){
            $where = $where.'created_at >= '.$start.' and created_at <= '.$end.' and ';
        }
        $where = $where.' nids = '.$this->uid.' and status = 3 ';
       // return $where;
        $total  = db('users')->where($where)->count();                  #总数
        // var_dump($total);
        if($total==0){
            return ajax('-200','没有数据');
        }
        $PageCount = ceil($total/$num);                                 #总页数
        #判断当前页数是否大于总页数
        if($page > $PageCount){
            $page = $PageCount;
        }
        #当前页数是否小于1
        if($page < 1){
            $page = 1;
        }
        $offset = $num*($page-1);    
     
        #偏移量
        $need = ['id','code','truename','phone','class','pid','nid','bd_id','created_at','status'];       #需要的数据
        $info = db('users')->where($where)->limit($offset,$num)->field($need)->order('created_at desc')->select();
        if(!empty($info)){
            #替换
            foreach($info as $key=>$value){
                $info[$key]['created_at'] = date('Y-m-d',$value['created_at']);             #注册时间
                $info[$key]['classs']     = $this::CLASSS[$value['class']];
                $info[$key]['reg_score']  = $this::Amount[$value['class']];                      #所需积分
                $info[$key]['ptruename']  = db('users')->where('id',$value['pid'])->value('truename');
                $info[$key]['ntruename']  = db('users')->where('id',$value['nid'])->value('truename');
            }
            $data['class']=$this::CLASSS;
            $data['amount']=$this::Amount;
            $data['info']=$info;
            $data['pagecount'] = $PageCount;
            $data['total'] = $total;
            $data['page'] = $page;
            $data['integral'] = db('users')->where('id',$this->uid)->value('reg_score');        #现在有的注册积分
            return ajax('1','获取成功',$data);
        }else{
            return ajax('2','没有数据');
        }
    }

    public function index()
    {
       $integral = db('users')->where('id',$this->uid)->value('reg_score');  
       return ajax('1','ok',$inintegral);
    }

    #会员激活
    public function activation()
    {
        $need = input('need_integral');                     #需要积分
        $odd = input('integral');                           #账户现在积分
        // $odd = db('users')->where('id',$this->uid)->value('reg_score');             #账户现在积分
        // return $odd;
        if($need == '' || $odd == ''){
            return ajax('-1','非法请求');
        }
        $phone = input('phone');                            #被激活者的手机号
        if($need > $odd){
            return ajax('-1','你的积分不足');
        }
        $status = db('users')->where('phone',$phone)->value('status');
        if($status != '3'){
            return ajax('此用户已经激活');
        }
        #扣除后的积分
        $surplus = $odd - $need;
        db('users')->where('id',$this->uid)->update(['reg_score'=>$surplus,'updated_at'=>time()]);
        db('users')->where('id',input('id'))->update(['updated_at'=>time(),'status'=>'1']);
        #存到account表
        $account = [
            'from_uid'  =>  input('id'),                //获取的用户id
            'score'     =>  -$need,                          //积分
            'cur_score' =>  $surplus,                        //当前的积分
            'remark'    =>  '激活用户',                      //备注
            'class'     =>  '1',                             //积分
            'is_add'    =>  '2',                             //支出
            'type'      =>  '1',                             //注册积分
            'uid'       =>  $this->uid,                          //来自用户的id
            'source'    =>  '11',
            'created_at'=>  time()
        ];
        db('account')->insert($account);
        if (!db("disc")->count()) {
            $market_price = db("config")->where(["name" => "current_price"])->value("value");
        } else {
            $market_price = db("disc")->order("id", "desc")->find()["market_price"];
        }
        $Useractivate = new Useractivate(input('id'),$market_price,0,'');
        $Useractivate->loadUser();
        $Rerformance = new Rerformance(input('id'),input('class'));
        $Rerformance->loadUser();
        // $Stock = new Stock();
        // $Stock->loadAccount();
        $Nineservice = new Nineservice(input('id'));
        $Nineservice->loadUser();
        // 发送短信
        // $send = NewSms($phone);
        // // 发送成功 存库里
        // $arr = [
        //     'phone' =>  $phone,                 #电话号
        //     'time'  =>  time(),                 #发送时间
        //     'code'  =>  $send['code'],          #验证码
        //     'type'  =>  '3',                    #类型 激活用户
        //     'uid'   =>  input('id'),        #用户id
        //     'status'=>  '1',
        // ];
        // db('sms')->insert($arr);
        return ajax('1','激活成功');
    }


    public function userdel(){
        $id = input('id');
        db('users')->where('id',$id)->delete();
        return ajax('1','删除成功');
    }

    #子账号列表
    public function subaccount()
    {
        $num = input('num')?input('num'):'7';      #每页显示条数
        $page = input('page')?input('page'):1;     #当前页数
        $start = input('start')?strtotime(input('start').'0:00:00'):'';        #开始日期
        $end = input('end')?strtotime(input('end').'23:59:59'):'';            #结束日期
        $code = input('code')?input('code'):'';          #会员编号
        $class = input('class')?input('class'):'';        #等级
        $where = '';
        if(!empty($code)){
           $where =$where." code like '%".$code."%' and ";
        }
        if(!empty($class)) {
            $where = $where.' class = '.$class.' and ';
        }
        if(!empty($start) and empty($end)){
            $where = $where.' created_at >= '.$start.' and ';
        }
        if(empty($start) and !empty($end)){
           $where = $where.' created_at <= '.$end.' and ';
        }
        if(!empty($start) and !empty($end)){
            $where = $where.' created_at >= '.$start.' and created_at <= '.$end.' and ';
        }
        $uidcode = db('users')->where('id',$this->uid)->value('code');
        // return $uidcode;
        $where = $where." `cascade` = 2 and `cascade_code`  = '".$uidcode."'";
        $total  = db('users')->where($where)->count();                  #总数
        // return $total;
        if($total==0){
            return ajax('-1','没有数据');
        }
        $PageCount = ceil($total/$num);                                 #总页数
        #判断当前页数是否大于总页数
        if($page > $PageCount){
            $page = $PageCount;
        }
        #当前页数是否小于1
        if($page < 1){
            $page = 1;
        }
        $offset = $num*($page-1);                                               #偏移量
        $need = ['id','code','class','pay_score','prize_score','con_score','game_score','ele_score','pid','nid','bd_id','created_at','islock'];       #需要的数据
        $info = db('users')->where($where)->limit($offset,$num)->field($need)->select();
        if(!empty($info)){
            #替换
            foreach($info as $key=>$value){
                $info[$key]['created_at'] = date('Y-m-d',$value['created_at']);             #注册时间
                $info[$key]['class']    = $this::CLASSS[$value['class']];
                $info[$key]['ptruename'] = db('users')->where('id',$value['pid'])->value('truename');
                $info[$key]['ntruename'] = db('users')->where('id',$value['nid'])->value('truename');
                $info[$key]['bd_id'] = db('form_core')->where('id',$value['bd_id'])->value('ucode')??'暂无';
                $info[$key]['islocks'] = $this::IsLock[$value['islock']];
                $info[$key]['pay_score'] = $value['pay_score'] == 0 ? '0.00':$value['pay_score'];
                $info[$key]['prize_score'] = $value['prize_score'] == 0 ? '0.00':$value['prize_score'];
                $info[$key]['con_score'] = $value['con_score'] == 0 ? '0.00':$value['con_score'];
                $info[$key]['game_score'] = $value['game_score'] == 0 ? '0.00':$value['game_score'];
                $info[$key]['ele_score'] = $value['ele_score'] == 0 ? '0.00':$value['ele_score'];

            }
                // var_dump($info);
            // return ajax('1','获取成功',$info);
            // exit;
            $data['class']=$this::CLASSS;
            $data['amount']=$this::Amount;
            $data['info']=$info;
            $data['pagecount'] = $PageCount;
            $data['total'] = $total;
            $data['page'] = $page;
            $data['isliok'] = $this::IsLock;
            return ajax('1','获取成功',$data);
        }else{
            return ajax('-1','没有数据');
        }
    }

    #登录子账号
    public function hornlogin()
    {   
        // return $this->uidcode;
        $id = input('id');
        if($id == '' && !is_numeric($id)){
            return ajax('-1','操作失败');
        }
        $arr = db('users')->where('`cascade` = 2 and cascade_code = "'.$this->uidcode.'"')->field('id')->select();
        if(empty($arr)){
            return ajax('-1','非法操作');
        }
        $info = [];
        foreach($arr as $key=>$val){
            $info[] = $val['id'];
        }
        if(!in_array($id,$info)){
            return ajax('-1','操作失败');
        }
        $users=db('users')->where('id',$id)->find();
        session(null);
        session('home.user',$users);
        return ajax('1','ok',['id'=>$users['id'],'fid'=>$this->uid]);
    }

}