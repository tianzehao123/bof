<?php
//奖励积分交易
namespace app\backend\model;

use think\Model;

class PrizeModel extends Model{
	protected $table = 'sql_score_deal';

    /**
     * 根据搜索条件获取记录列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getPrizeByWhere($where,$and_where = '',$offset, $limit)
    {
        return $this->where($where)->where($and_where)->limit($offset, $limit)->order('id desc')->select();
    }

    /**
     * 根据搜索条件获取所有的记录数量
     * @param $where
     */
    public function getAllPrize($where)
    {
        return $this->where($where)->count();
    }

}