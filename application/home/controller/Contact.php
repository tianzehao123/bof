<?php
namespace app\home\controller;

use app\backend\model\ArticleModel;
use think\Controller;

class Contact extends Controller{

    #联系我们
    public function index(){
        $config = unserialize(file_get_contents('./config'));
        $return['kefu_weixin'] = $config['kefu_ma'];
        $return['kefu_phone'] = $config['kefu_phone'];
        $return['kefu_qq'] = $config['gongzuo_time'];
        return ajax('1','完成',$return);
    }

    #新闻列表
    public function about(){
        $type = input('type');
        $search = '公司简介';
        if($type == 'novice'){
            $search = '新手指南';
        }
        if($type == 'news'){
            $search = '最新公告';
        }
        if($type == 'answer'){
            $search = '疑义解答';
        }
        $return= db('article')->where(['type'=>$search])->order('id desc')->select();

        foreach($return as $k=>$v){
            $return[$k]['created_at'] = date('Y-m-d H:i:s',$v['created_at']);
        }

        return ajax('1','完成',$return);
    }

    #文章详情
    public function article(){
        #需要文章id
        $return['article'] = db('article')->where(['id'=>input('id')])->find();
        if($return['article']['type'] == '最新公告'){
            if(!empty(session('home.user')['id'])){
                $uid = session('home.user')['id'];
                $gid = db('users')->where(['id'=>$uid])->value('gid');
                $yidu = explode(',',$gid);
                if(!in_array(input('id'),$yidu)){
                    $up['gid'] = trim($gid.','.input('id'),',');
                    db('users')->where(['id'=>$uid])->update($up);
                }
            }
        }
        return json(['status'=>1,'message'=>'完成','data'=>$return]);
    }
}