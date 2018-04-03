<?php

namespace app\backend\controller;

use app\backend\model\GoodsModel;
class Goods extends Base
{
    //商品列表
    public function index(){
        if(request()->isAjax()){
            $param = input('param.');
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (isset($param['searchText']) && !empty($param['searchText'])) {
                $where['name'] = ['like', '%' . $param['searchText'] . '%'];
            }
            $good = new GoodsModel();
            $selectResult = $good->getGoodsByWhere($where, $offset, $limit);
            $status = config('goods_status');
            foreach($selectResult as $key=>$vo){
                $selectResult[$key]['is_delete'] = $status[$vo['is_delete']];
                $selectResult[$key]['type'] = db('class')->where(['id'=>$vo['cid']])->value('class');
                $selectResult[$key]['img'] = "<img src='".$vo['img']."' width='50px' height='50px' />";
                $operate = [
                    '轮播图' => "javascript:goodsthumbnail('".$vo['id']."')",
                    '编辑'   => url('goods/goodsEdit', ['id' => $vo['id']]),
                ];
                $selectResult[$key]['operate'] = showOperate($operate);
            }
            $return['total'] = $good->getAllGoods($where);  //总数据
            $return['rows'] = $selectResult;
            return json($return);
        }
        return $this->fetch();
    }

    //添加商品

    public function goodsAdd()
    {
        if(request()->isPost()){
            $param = input('param.');
            $param = parseParams($param['data']);
            $param['created_at'] = time();
            // var_dump($param);die;
            $good = new GoodsModel();
            $flag = $good->insertGoods($param);
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
        }
        $class = db('class')->select();
        $this->assign([
            'class'=>$class,
        ]);
        return $this->fetch();

    }

    //遍历商品的轮播图
    public function addGoodsThumbnail(){
         $param = input('param.');
         if (!empty($param['id'])) {
            $thumbnail = db('lunbo')->where(['gid'=>$param['id']])->select();
         }else{
            $thumbnail = [];
         }
        return json(['code' => 1, 'data' => $thumbnail,'id'=>$param['id'], 'msg' => 'success']);
    }

    //添加单个商品的轮播图
    public function savephoto(){
        $param = input('param.');
        $insert['gid'] = $param['gid'];
        $insert['imgurl'] = $param['imgurl'];
        $insert['created_at'] = time();
        $result = db('lunbo')->insert($insert);
        return json(['code' => $result]);
    }

    public function delThumbnail(){
        $id = input('param.id');
        $result = db('lunbo')->where("id=".$id)->delete();
        return json(['code' => $result]);
    }

    //编辑商品
    public function goodsEdit(){
        $good = new GoodsModel();
        if(request()->isPost()){
            $param = input('param.');
            $param = parseParams($param['data']);
            $param['updated_at'] = time();
            $flag = $good->editGoods($param);
            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);

        }
        $id = input('param.id');
        $class = db('class')->select();
        $goods = $good->getOneGoods($id);
        $this->assign([
            'goods' => $goods,
            'class' => $class,
        ]);
        return $this->fetch();
    }

    //删除角色
    public function goodsDel(){
        $id = input('param.goods_id');
        $good = new GoodsModel();
        $flag = $good->delGoods($id);
        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }

}