<?php
namespace app\bofshop\controller;
use think\Controller;

#BOF商城登陆注册
class Login extends Controller
{	
	#商城登录
	public function login()
	{  
        // $phone = input('phone');
		$code = input('code');
		$password = input('password');
		if($code == '' || $password == ''){
            return ajax('-1','请填写完整信息');
        }
        $info = db('users')->where('code',$code)->find();
		if(!$info){
            return ajax('-1','此账号不存在');
        }
        if($info['password']!= md5($password)){
            return ajax('-1','您输入的密码有误');
        }
        session('home.user',$info);
        return ajax('1','登陆成功');
	}


	#商城注册
	public function register()
	{	
		$info = input();
		$result = $this->validate($info,'LoginValidate');
		if($result !== true){
			return ajax('-1',$result);
		}
		 // 根据手机号查询
        $phone = $info['phone'];
        $code = $info["code"];
        $pre = "/^[a-zA-Z][a-zA-Z0-9_]*$/";
        if(!preg_match($pre,$code)){
            return ajax('-5','编号只能字母数字组合');
        }
        if(strlen($code) > 6){
            return ajax('-5','编号长度不得超过6位');
        }

        #手机号是否已经注册
        $ress = db('users')->where(['code'=>$code])->find();
        if($ress){
            return ajax('-1','此编号已被注册');
        }
        // 今天的开始时间
        $start = strtotime('today');
        // 结束时间
        $end = $start+60*60*24;
        // 查询条件
        // $where = "phone = $phone and time <= $end and time >= $start and type = 4 and status = 0 ";
        $where = "phone = $phone and time <= $end and time >= $start and status = 0 ";
        // 查询出最近的一条数据
        $codes = db('sms')->where($where)->order('time','desc')->find();
        // #判断是否发送的有短信
        if(!$codes){
            return ajax('-1','您输入的验证码错误');
        }
        // //10分钟内有效
        $expiry = $codes['time']+60*10;
        if(time()>$expiry){
            db('sms')->where('id',$codes['id'])->update(['status'=>'2']);
            return ajax('-1','您输入的验证码错误');
        }
        // 对验证码判断
        if($info['auth_code'] != $codes['code']){
            return ajax('-1','您输入的验证码错误');
        }
        // if($info['auth_code'] != '123456'){
        //     return ajax('-1','您输入的验证码错误');
        // }       
        db('sms')->where('id',$codes['id'])->update(['status'=>'1']);
        $data = [
            'phone'         =>  $info['phone'],
        	'code' 		=> 	$info['code'],
        	'password' 		=>	md5($info['password']),
        	'step'			=>	'5',														
        	'created_at'	=>	time(),
            'two_password'  =>  md5($info['two_password']),
            'status'        =>  3
        ];
        db('users')->insert($data);
        return ajax('1','注册成功,请登录');
	}

	#发送验证码
	public function send()
    {
        $nums = input('num')??'3';
    	#获取传过来的类型 4=>商城注册 5=>商城忘记密码
    	$type = input('type');
        #获取手机号
        $phone = request()->param('phone');
        // 判断手机号是否为空
        if($phone == ''){
            return ajax('-1','请输入手机号');
        }
        // 正则匹配手机号
        $preg = '/^(13[0-9]|14[5|7]|15[0|1|2|3|5|6|7|8|9]|18[0|1|2|3|5|6|7|8|9]|177)\d{8}$/';
        if(!preg_match($preg,$phone)){
            return ajax('-1','不是正确的手机号');
        }
        #根据手机号在库里查询
        $res = db('users')->where('phone',$phone)->find();
        // if(($type==4) && $res){
        //     if($res['step']>=4){
        //         return ajax('-1','此手机号已被注册');
        //     }
        // }else
        if(($type==5) && !$res){
            return ajax('-1','此手机号暂未注册');
        }
        // 今天的开始时间
        $start = strtotime('today');
        // 结束时间
        $end = $start+60*60*24;
        // 条件 一天发一回
        $where = "phone = $phone and time <= $end and time >= $start and type = $type";
        // 统计数量
        $num=db('sms')->where($where)->count();
        // 进行判断
        if($num>=$nums){
            return ajax('-1','超过发送次数');
        }
        // 发送短信
        $send = NewSms($phone);
        // 是否发送成功
        if($send['code']>0){
            // 发送成功 存库里
            $arr = [
                'phone' =>  $phone,                 #电话号
                'time'  =>  time(),                 #发送时间
                'code'  =>  $send['code'],          #验证码
                'type'  =>  $type,                  #获取传过来的类型 4=>商城注册 5=>商城忘记密码
                'status'=>  0,                      #是否已 验证  0=>未验证 1=>已验证
            ];
         	#如果是商城忘记密码 需要用户的id
            if($type == 5){
                $uid = db('users')->where('phone',$phone)-field('id')->find();
                $arr['uid'] = $uid['id'];
            }
            db('sms')->insert($arr);
            return ajax('1','发送成功');
        }else{
            return ajax('-1','发送失败');
        }
    }

    #忘记密码
    public function forgetPwd()
    {
        # 获取手机号 验证码 密码 重复密码
        $phone = input('phone');
        $ver_code  = input('ver_code');
        $password  = input('password');
        $repassword = input('repassword');
        #手机号不能为空
        if($phone==''){
            return ajax('-1','请输入正确的手机号');
        }
        #密码需要一致
        if($password != $repassword){
            return ajax('-1','密码不一致');
        }
        // // 今天的开始时间
        // $start = strtotime('today');
        // // 结束时间
        // $end = $start+60*60*24;
        // #查询条件
        // $where = " time >= $start and time <= $end and type = '5' and phone = $phone and status = 0 ";
        // #查出发送的验证码
        // $code = db('sms')->where($where)->order('time','desc')->find();
        // #是否发送过验证码
        // if(!$code){
        //     return ajax('-1','请输入正确的短信验证码');
        // }
        // //10分钟内有效
        // $expiry = $code['time']+60*10;
        // if(time()>$expiry){
        //     db('sms')->where('id',$code['id'])->update(['status'=>'2']);
        //     return ajax('-1','请输入正确的短信验证码');
        // }
        #验证码是否正确
        // if($ver_code != $code['code']){
        //     return ajax('-1','请输入正确的短信验证码');
        // }
        if($ver_code != 123456){
            return ajax('-1','请输入正确的短信验证码');
        }
        #验证码正确  改变验证码状态
        // db('sms')->where('id',$code['id'])->update(['status'=>1]);
        #要修改的数据
        $data = [
            'password'      =>  md5($password),
            'updated_at'    =>  time(),
        ];
        #判断
        if(db('users')->where('phone',$phone)->update($data)){
            return ajax('1','密码修改成功');
        }else{
            return ajax('-1','密码修改失败');
        }
    }

    #退出登录
    public function logout()
    {
        session(null);
        return ajax(1,'成功');
    }

}