<?php
/**
 * Created by PhpStorm.
 * User: ovo
 * Date: 2017/7/10
 * Time: 下午6:08
 */
namespace app\backend\controller;

use app\backend\model\LunboModel;

class Lunbo extends Base{
    const Lunbo = 'lunbo';
    //用户列表
    public function index()
    {
        if(request()->isAjax()){

            $param = input('param.');

            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;

            $where = [];
            $where['sort'] = 1;
            if (isset($param['class']) && !empty($param['class'])) {
                $where['class'] = ['like', '%' . $param['class'] . '%'];
            }

//            dump($where);exit;
            $lunbo = new LunboModel();
            $selectResult = $lunbo->getLunbosByWhere($where, $offset, $limit);


            foreach($selectResult as $key=>$vo){
                $selectResult[$key]['imgurl'] = '<img style="max-height:250px;" src="'.$vo['imgurl'].'" />';
                $operate = [
                    '删除' => "javascript:lunboDel('".$vo['id']."')"
                ];
                $selectResult[$key]['operate'] = showOperate($operate);

            }

            $return['total'] = $lunbo->getAllLunbos($where);  //总数据
            $return['rows'] = $selectResult;

            return json($return);
        }

        return $this->fetch();
    }

    //添加用户
    public function lunboAdd()
    {

        $insert['imgurl'] = input('imgurl');
        $insert['sort'] = 1;
        $insert['created_at'] = time();

        $lunbo = new LunboModel();
        $flag = $lunbo->insertLunbo($insert);

        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }

//    //编辑角色
//    public function lunboEdit()
//    {
//        $lunbo = new LunboModel();
//
//        if(request()->isPost()){
//
//            $param = input('post.');
//            $param = parseParams($param['data']);
//            $save['id'] = $param['id'];
//            $save['class'] = $param['class'];
//            $save['remark'] = $param['remark'];
//            if(isset($param['sort']) && !empty($param['sort'])){
//                $save['sort'] = $param['sort'];
//            }
//            $flag = $lunbo->editLunbo($save);
//            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
//        }
//
//        $id = input('param.id');
//        $this->assign([
//            'lunbo' => $lunbo->getOneLunbo($id),
//        ]);
//        return $this->fetch();
//    }

    //删除角色
    public function lunboDel()
    {
        $id = input('param.id');

        $lunbo = new LunboModel();
        $flag = $lunbo->delLunbo($id);
        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);
    }
}