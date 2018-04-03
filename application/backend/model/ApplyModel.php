<?php
namespace app\backend\model;

use think\Model;

class ApplyModel extends Model
{
    protected $table = 'sql_apply';

    public function users(){
        return $this->hasOne('UserModel','id','uid')->field('id,nickname,phone,headimgurl');
    }

    /**
     * 根据搜索条件获取用户列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getApplyByWhere($where, $offset, $limit)
    {
        return $this->where($where)->limit($offset, $limit)->order('id desc')->select();
    }
    /**
     * 根据搜索条件获取所有的用户数量
     * @param $where
     */
    public function getAllApply($where)
    {
        return $this->where($where)->count();
    }

   
}
