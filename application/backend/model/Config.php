<?php
namespace app\backend\model;
use think\Model;

class Config extends Model
{
	protected $table = 'sql_config';  
	/**
     * 获取 配置
     * @param $key 键名
     * @return mixed 配置项的值
     */
    public static function getConfigs($key)
    {
        # 判断是否为数组
        if(is_array($key)){
            # 定义结果集
            $result = [];
            foreach($key as $k=>$v){
                # 获取单键值
                $result[$v] = self::getConfigs($v);
            }
            # 返回结果
            return $result;
        }else{
            # 返回结果
            return self::where(['name'=>$key]) -> value('value');
        }

    }

    /**
     * 设置配置
     * @param $key 键
     * @return bool 是否成功
     */
    public static function setConfig($key)
    {
        # 判断是否为批量设置
        if(is_array($key)){
            # 循环要设置的内容
            foreach($key as $k=>$v){
                # 更新数据库内容
                $result[] = self::where(['name'=>$k]) -> update(['value'=>$v,'updated_at'=>time()]);
            }
            # 返回成功
            return true;
        }else{
            # 返回失败
            return false;
        }
    }
    
}