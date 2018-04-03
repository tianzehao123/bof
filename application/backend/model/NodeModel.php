<?php

namespace app\backend\model;

use think\Model;

class NodeModel extends Model
{
    // 确定链接表名
    protected $table = 'sql_node';

    /**
     * 获取节点数据
     */
    public function getNodeInfo($id)
    {
        $result = $this->field('id,node_name,typeid')->select();
        $str = '';

        $role = new RoleModel();
        $rule = $role->getRuleById($id);

        if(!empty($rule)){
            $rule = explode(',', $rule);
        }

        foreach($result as $key=>$vo){
            $str .= '{ "id": "' . $vo['id'] . '", "pId":"' . $vo['typeid'] . '", "name":"' . $vo['node_name'].'"';

            if(!empty($rule) && in_array($vo['id'], $rule)){
                $str .= ' ,"checked":1';
            }

            $str .= '},';

        }

        return '[' . rtrim($str, ',') . ']';
    }

    /**
     * 根据节点数据获取对应的菜单
     * @param $nodeStr
     */
    public function getMenu($nodeStr = '')
    {
        // 超级管理员没有节点数组
        $where = empty($nodeStr) ? 'is_menu = 2' : 'is_menu = 2 and id in(' . $nodeStr . ')';

        $result = $this->field('id,node_name,typeid,control_name,action_name,style')
            ->where($where)->select();
        $menu = prepareMenu($result);

        return $menu;
    }

    /**
     * 根据条件获取访问权限节点数据
     * @param $where
     */
    public function getActions($where)
    {
        return $this->field('control_name,action_name')->where($where)->select();
    }

    /**
     * 获取节点数据
     * @return mixed
     */
    public function getNodeList()
    {
        // return $this->field('id,node_name name,typeid pid,is_menu,style,control_name,action_name')->select();
        return $this->field('id,node_name as name,typeid,is_menu,style,control_name,action_name')->select();
    }

    /**
     * 插入节点信息
     * @param $param
     */
    public function insertNode($param)
    {
        try{

            $this->allowField(true)->save($param);
            return msg(1, '', '添加节点成功');
        }catch(PDOException $e){

            return msg(-2, '', $e->getMessage());
        }
    }

    /**
     * 编辑节点
     * @param $param
     */
    public function editNode($param)
    {
        try{

            $this->save($param, ['id' => $param['id']]);
            return msg(1, '', '编辑节点成功');
        }catch(PDOException $e){

            return msg(-2, '', $e->getMessage());
        }
    }

    /**
     * 删除节点
     * @param $id
     */
    public function delNode($id)
    {
        try{

            $this->where('id', $id)->delete();
            return msg(1, '', '删除节点成功');

        }catch(PDOException $e){
            return msg(-1, '', $e->getMessage());
        }
    }
}