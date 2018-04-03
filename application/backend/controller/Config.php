<?php
namespace app\backend\controller;
use app\backend\model\Config as Configs;
class Config extends Base
{
    public function index()
    {
        #获取所有配置参数
        $config = Configs::select();
        $operate = [];
        foreach ($config as $key => $value) {
            $config[$key]['description'] = $value['description'];
            $config[$key]['value'] = $value['value'];
            $config[$key]['updated_at'] = (Int) $value['updated_at'] ;
        	$operate[$key]['operate'] = showOperate1($this->makeButton($value->name),$value["name"]);
        }
        $this->assign([
        	'config'=>$config,
        	'operate'=>$operate,
        	]);
        return $this->fetch();
    }
    public function edit()
    {

        if (request()->isAjax() && input('type') == 2) {

            $data = input('param.');
            $config = [];
            $config['id'] = $data['id'];
            $config['value'] = $data['value'];
            if(!$data['value']){
                return json(['status'=>-1,'msg'=>'修改失败','data'=>'']);
            }
            
            $model = new Configs();
            
            $res = Configs::setConfig([$config['id'] =>$config['value']]);
            
            //self::getSql($model);
            
            if ($res == 1) {
                return json(['status'=>1,'msg'=>'修改成功','data'=>url('Config/index')]);
            }else{
                return json(['status'=>-1,'msg'=>'修改失败','data'=>'']);
            }
        }
        $id = input('param.id');
        $config = Configs::getConfigs($id);
        return json(['status'=>true,'msg'=>'修改成功','data'=>$config]);
    }
    /**
     * 拼装操作按钮
     * @param $table
     * @return array
     */
    private function makeButton($key,$name=0)
    {
        return [
            '修改' => [
                'auth' => 'config/edit',
                'href' => "javascript:edit('".$key."','".$name."')",
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
        ];
    }
}
