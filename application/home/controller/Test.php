<?php
namespace app\home\controller;

class Test{
    #测试登录
    public function login(){
        $user = db('users')->find();
        session('home.user',$user);
        return json(['status'=>1,'message'=>'登录成功']);
    }
}