<?php
namespace app\backend\controller;
use \think\Db;
class Gconfig extends Base
{
    public function index()
    {   
        $conf = unserialize(file_get_contents('./config'));
//        dump($conf);exit;
        $this->assign("conf",$conf);
//         var_dump($conf);die;
        return $this->fetch();
    }

    public function saveConfig(){
        $param = input('param.');
        $param = parseParams($param['data']);
//         var_dump($param);die;
         $param = serialize($param);
         file_put_contents('./config',$param);
        return json(['code'=>1,'msg'=>'修改成功']);
    }


}
