<?php
namespace app\backend\model;

use think\Model;

/**
 * @property \think\model\relation\HasOne users
 */
class WithdrawModel extends Model
{
    protected $table = 'sql_withdraw';

    public function users(){
        return $this->belongsTo('UserModel','uid','id')->field('id,nickname,phone,headimgurl,alname,zhifubao,class');
    }
    /**
     * 根据搜索条件获取提现列表信息
     * @param $where
     * @param $offset
     * @param $limit
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getWithdrawByWhere($where, $offset, $limit)
    {
        return $this->where($where)->with('users')->limit($offset, $limit)->order('id desc')->select();
    }

    /**
     * 根据搜索条件获取所有的提现数量
     * @param $where
     * @return int|string
     */
    public function getAllWithdraw($where)
    {
        return $this->where($where)->count();
    }

    /**
     * 插入管理员信息
     * @param $param
     * @return array
     */
    public function insertWithdraw($param)
    {
        try{

            $result =  $this->save($param);
            if(false === $result){
                // 验证失败 输出错误信息
                return ['code' => -1, 'data' => '', 'msg' => $this->getError()];
            }else{

                return ['code' => 1, 'data' => '', 'msg' => '添加提现成功'];
            }
        }catch( PDOException $e){

            return ['code' => -2, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

    /**
     * 编辑管理员信息
     * @param $param
     * @return array
     */
    public function editWithdraw($param)
    {
        try{

            $result =  $this->save($param, ['id' => $param['id']]);

            if(false === $result){
                // 验证失败 输出错误信息
                return ['code' => 0, 'data' => '', 'msg' => $this->getError()];
            }else{

                return ['code' => 1, 'data' => '', 'msg' => '编辑提现成功'];
            }
        }catch( PDOException $e){
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

    /**
     * 根据管理员id获取角色信息
     * @param $id
     * @return array|false|\PDOStatement|string|Model
     */
    public function getOneWithdraw($id)
    {
        return $this->with('users')->find($id);
    }

    /**
     * 删除管理员
     * @param $id
     * @return array
     */
    public function delWithdraw($id)
    {
        try{

            $this->where('id', $id)->delete();
            return ['code' => 1, 'data' => '', 'msg' => '删除管理员成功'];

        }catch( PDOException $e){
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
}
