<?php
namespace app\home\controller;
use think\Controller;
#账户设置
class Setting extends Base
{
    private $uid;   //用户ID
    const CLASSS  = ['0'=>'免费会员','1'=>"普卡","2"=>"银卡","3"=>"金卡","4"=>"白金卡","5"=>"黑金卡","6"=>"钻卡","7"=>"蓝钻"];

    #初始化
    public function _initialize()
    {
        $this->uid = session('home.user')['id'];

    }
    #个人资料
    public function personalData()
    {
        
        $person=  db('users')
                ->where('id',$this->uid)
                ->field('nickname,code,class,pid,truename,identity,phone,bank_name,bank_account,zhifubao,weixin')
                ->find();
        $pnickname = db('users')->where('pid',$person['pid'])->value('truename');
        #需要的所有信息
        $person['pnickname'] = $pnickname;
        $person['class'] = $this::CLASSS[$person['class']];
        return ajax('1','ok',$person);
    }

    //修改支付宝密码或者微信号
    public function infoedit()
    {   
        #修改支付宝或者微信号
        $alipay = input('alipay');
        $wechat = input('wechat');
        db('users')->where('id',$this->uid)->update(['zhifubao'=>$alipay,'weixin'=>$wechat]);
        return ajax('1','修改成功');
    }

    /***
     * 修改登陆密码
     * @param oldpassword newpassword renewpassword
     * @return true or false
    */
    public function modifyLoginCode()
    {
        if(!input('oldpassword') || !input('newpassword') || !input('renewpassword')){
            return ajax('-1','请填写完整信息');
        }
        #判断新密码是否一致
        if(input('newpassword')!=input('renewpassword')){
            return ajax('-2','两次密码不一致');
        }
        #和库里的对比
        $password = db('users')->where('id',$this->uid)->value('password');
        if(md5(input('oldpassword')) != $password){
            return ajax('-3','密码错误');
        }
        if(md5(input('newpassword')) == $password){
            return ajax('-2','密码没有变动');
        }
        $data = [
            'password'      =>  md5(input('newpassword')),
            'updated_at'    =>  time(),
        ];
        db('users')->where('id',$this->uid)->update($data);
        return ajax('1','修改成功',$password);
    }
    public function index()
    {
        $phone = db('users')->where("id",$this->uid)->value('phone');
        return ajax('1','ok',$phone);
    }

    /***
     * 修改交易密码
     * @param  two_password re_two_password code phone
     * @return true or false
     */
    public function modifyTradeCode()
    {
        if(input('code')=='' || input('two_password')=='' || input('re_two_password')==''){
            return ajax('-1','请填写完整');
        }
        if(input('two_password')!=input('re_two_password')){
            return ajax('-2','两次密码不一致');
        }
        $otwopassword=db('users')->where('id',$this->uid)->find();
        if($otwopassword["two_password"] == md5(input('two_password')) ){
            return ajax('-1','密码没有变动');
        }
        $where = 'uid = '.$this->uid.' and type = 2 and phone = '.$otwopassword["phone"];
        #查出库里的验证码
        //$code= db('sms')->where($where)->order('time desc')->field('code,time')->limit(1)->find();
        $code = db('sms')->where($where)->order('time','desc')->find();
        #是否已经过期
        $expiry = $code['time']+60*10;
        if(time()>$expiry){
            db('sms')->where('id',$code['id'])->update(['status'=>'2']);
            return ajax('-3','此验证码已过期');
        }
        #判断验证码是否正确
        if($code['code']!=input('code')){
            return ajax('-4','验证码错误');
        }
        #验证成功 改验证码状态
        db('sms')->where('id',$code['id'])->update(['status'=>'1']);
        #要修改的数据
        $data = [
            'two_password'  =>  md5(input('two_password')),
            'updated_at'    =>  time(),
        ];

        db('users')->where('id',$this->uid)->update($data);
        return ajax('1','修改成功');
    }
}

