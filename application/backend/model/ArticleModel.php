<?php

namespace app\backend\model;

use think\Model;

class ArticleModel extends Model
{
    protected $table = 'sql_article';
    protected $createTime = 'create_at';

    /**
     * 根据搜索条件获取文章列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getArticleByWhere($where, $offset, $limit)
    {
        return $this->where($where)->limit($offset, $limit)->order('id desc')->select();
    }

    /**
     * 根据搜索条件获取所有的文章数量
     * @param $where
     */
    public function getAllArticle($where)
    {
        return $this->where($where)->count();
    }

    /**
     * 插入文章
     * @param $param
     */
    public function insertArticle($param)
    {
        try {
            // $result =  $this->validate('UserValidate')->save($param);
            $result = $this->insert($param);
            if (false === $result) {
                // 验证失败 输出错误信息
                return ['code' => -1, 'data' => '', 'msg' => $this->getError()];
            } else {

                return ['code' => 1, 'data' => '', 'msg' => '添加文章成功'];
            }
        } catch (PDOException $e) {

            return ['code' => -2, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

    /**
     * 编辑文章信息
     * @param $param
     */
    public function editArticle($param)
    {
        try {

            $result = $this->save($param, ['id' => $param['id']]);

            if (false === $result) {
                // 验证失败 输出错误信息
                return ['code' => 0, 'data' => '', 'msg' => $this->getError()];
            } else {

                return ['code' => 1, 'data' => '', 'msg' => '编辑文章成功'];
            }
        } catch (PDOException $e) {
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

    /**
     * 根据文章id获取文章信息
     * @param $id
     */
    public function getOneArticle($id)
    {
        return $this->where('id', $id)->find();
    }


    /**
     * 删除文章
     * @param $id
     */
    public function delArticle($id)
    {
        try {
            $this->where('id', $id)->delete();
            return ['code' => 1, 'data' => '', 'msg' => '删除文章成功'];

        } catch (PDOException $e) {
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }
}