<?php
namespace app\backend\model;

use think\Model;

class AccountModel extends Model{
	protected $table = 'sql_account';

	public function users()
	{
		return $this->belongsTo('UserModel','from_uid','id')->field('id,code,nickname,phone,headimgurl');
	}

	public function user()
	{
		return $this->belongsTo('UserModel','uid','id');
	}

    public function user1()
    {
         return $this->belongsTo('UserModel','from_uid','id');

    }

    /**
     * 根据搜索条件获取记录列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getAccountByWhere($where,$and_where = '',$offset, $limit)
    {
        return $this->where($where)->where($and_where)->limit($offset, $limit)->order('id desc')->select();
    }

    /**
     * 根据搜索条件获取所有的记录数量
     * @param $where
     */
    public function getAllAccount($where)
    {
        return $this->where($where)->count();
    }

}