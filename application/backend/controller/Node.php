<?php

namespace app\backend\controller;

use app\backend\model\NodeModel;

class Node extends Base
{
    // 节点列表
    public function index()
    {
        if(request()->isAjax()){

            $node = new NodeModel();
            $nodes = $node->getNodeList();

            $nodes = getTree1(objToArray($nodes), false);
            return json(msg(1, $nodes, 'ok'));
        }

        return $this->fetch();
    }

    // 添加节点
    public function nodeAdd()
    {
        $param = input('param.');

        $node = new NodeModel();
        $flag = $node->insertNode($param);

        return json(msg($flag['code'], $flag['data'], $flag['msg']));
    }

    // 编辑节点
    public function nodeEdit()
    {
        $param = input('param.');

        $node = new NodeModel();
        $flag = $node->editNode($param);

        return json(msg($flag['code'], $flag['data'], $flag['msg']));
    }

    // 删除节点
    public function nodeDel()
    {
        $id = input('param.id');

        $role = new NodeModel();
        $flag = $role->delNode($id);
        return json(msg($flag['code'], $flag['data'], $flag['msg']));
    }
}