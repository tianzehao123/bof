<?php

namespace app\backend\model;

use think\Model;

class GoodsModel extends Model
{
    protected $table = 'sql_goods';

    public function getClass()
    {
        return $this->belongsTo('ClassModel', 'cid', 'id');
    }

    public function lunbo()
    {
        return $this->hasMany('LunboModel', 'gid', 'id');
    }

    public function orderDetail()
    {
        return $this->hasMany('OrderDetailModel', 'gid', 'id');
    }

    /**
     * 根据搜索条件获取商品列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getGoodsByWhere($where, $offset, $limit)
    {
        return $this->where($where)->limit($offset, $limit)->order('id desc')->select();
    }

    /**
     * 根据搜索条件获取所有的商品数量
     * @param $where
     */
    public function getAllGoods($where)
    {
        return $this->where($where)->count();
    }

    /**
     * 插入商品
     * @param $param
     */
    public function insertGoods($param)
    {
        try {
            // var_dump($param);die;
            // $result =  $this->validate('UserValidate')->save($param);
            $result = $this->insert($param);

            if (false === $result) {
                // 验证失败 输出错误信息
                return ['code' => -1, 'data' => '', 'msg' => $this->getError()];
            } else {

                return ['code' => 1, 'data' => '', 'msg' => '添加商品成功'];
            }
        } catch (PDOException $e) {

            return ['code' => -2, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

    /**
     * 编辑商品信息
     * @param $param
     */
    public function editGoods($param)
    {
        $id = $param["id"];
        unset($param["fm"]);
        unset($param["gp"]);
        unset($param["id"]);
        try {

            $result = $this->save($param, ['id' => $id]);

            if (false === $result) {
                // 验证失败 输出错误信息
                return ['code' => 0, 'data' => '', 'msg' => $this->getError()];
            } else {

                return ['code' => 1, 'data' => '', 'msg' => '编辑商品成功'];
            }
        } catch (PDOException $e) {
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

    /**
     * 根据商品id获取商品信息
     * @param $id
     */
    public function getOneGoods($id)
    {
        return $this->where('id', $id)->find();
    }


    /**
     * 删除商品
     * @param $id
     */
    public function delGoods($id)
    {
        try {
            $data['is_delete'] = 2;
            $this->where('id', $id)->update($data);
            return ['code' => 1, 'data' => '', 'msg' => '删除商品成功'];

        } catch (PDOException $e) {
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
}