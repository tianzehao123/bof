<?php

namespace app\backend\controller;

use app\backend\model\AwardModel;

use app\backend\model\ApplyModel;

use think\Db;

class Award extends Base

{

  //奖励申请表

  public function apply(){

    if(request()->isAjax()){

      $param = input('param.');

      $limit = $param['pageSize'];

      $offset = ($param['pageNumber'] - 1) * $limit;

      $where = [];

      if (isset($param['searchText']) && !empty($param['searchText'])) {

          $where['uname'] = ['like', '%' . $param['searchText'] . '%'];

      }

      $good = new ApplyModel();

      $selectResult = $good->getApplyByWhere($where, $offset, $limit);

      $status = config('apply');

      $rid = config('rid');

      foreach($selectResult as $key=>$vo){

          $award = db('award')->where('id',$vo['aid'])->find();

          $selectResult[$key]['status'] = $status[$vo['status']];

          $selectResult[$key]['award'] = $award['award'];

          $selectResult[$key]['rid'] = $rid[$award['rid']];

          $selectResult[$key]['target'] = $award['target'].'万';

          $selectResult[$key]['stoptime'] = $award['stoptime'];

          $operate = [

              '同意'   => url('Award/applyEdit', ['id' => $vo['id'],'status' => 1]),

              '拒绝'   => url('Award/applyEdit', ['id' => $vo['id'],'status' => 2]),

          ];

          $selectResult[$key]['operate'] = showOperate($operate);

      }

      $return['total'] = $good->getAllApply($where);  //总数据

      $return['rows'] = $selectResult;

      return json($return);

    }

    return $this->fetch();

  }

  //申请编辑

  public function applyEdit(){

    if(request()->isGet()){

      $param = input('param.');

      $apply = new ApplyModel();

      $res = $apply->editApply($param);

      if($res){

          return json(['code' => $res['code'], 'data' => $res['data'], 'msg' => $res['msg']]);

      }



    }

  }

    //奖励列表

    public function index()

    {

        if(request()->isAjax()){

            $param = input('param.');

            $limit = $param['pageSize'];

            $offset = ($param['pageNumber'] - 1) * $limit;

            $where = [];

            if (isset($param['searchText']) && !empty($param['searchText'])) {

                $where['award'] = ['like', '%' . $param['searchText'] . '%'];

            }

            $good = new awardModel();

            $selectResult = $good->getawardByWhere($where, $offset, $limit);

            $rid = config('rid');

            foreach($selectResult as $key=>$vo){

                $selectResult[$key]['src'] = "<img src='".$vo['src']."' width='50px' height='50px' />";

                $selectResult[$key]['rid'] = $rid[$vo['rid']];

                $selectResult[$key]['target'] = $vo['target'].'万';

                $operate = [

                    '编辑'   => url('Award/awardEdit', ['id' => $vo['id']]),

                    '删除'   => "javascript:awardDel('".$vo['id']."')"

                ];

                $selectResult[$key]['operate'] = showOperate($operate);

            }



            $return['total'] = $good->getAllAward($where);  //总数据

            $return['rows'] = $selectResult;

            return json($return);

        }

        return $this->fetch();

    }



    //添加商品

    public function awardAdd()

    {

        if(request()->isPost()){

            $param = input('param.');

            $param = parseParams($param['data']);

            // dump($param);exit;

            $rank_name = Db::name('user_rank')->where('rank_id',$param['rid'])->value('rank_name');

            $good = new AwardModel();

            $flag = $good->insertAward($param);

            $message['mes_type'] = 3;

            $message['add_time'] = time();

            $message['title'] = "添加了新奖励".$param['award'];

            $message['description'] = "添加了新奖励".$param['award'];

            $message['content'] = "添加了新奖励".$param['award'].'截止时间'.$param['stoptime'].'。需要等级'.$rank_name;

            Db::name('message')->insert($message);

            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);

        }

        return $this->fetch();

    }

    //编辑商品

    public function awardEdit()

    {

        $good = new AwardModel();

        if(request()->isPost()){

            $param = input('param.');

            $param = parseParams($param['data']);

            $flag = $good->editAward($param);

            return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);

        }

        $id = input('param.id');

        $this->assign([

            'award' => $good->getOneAward($id),

            'status' => config('rid')

        ]);

        return $this->fetch();

    }



    //删除角色

    public function awardDel()

    {

        $id = input('param.id');

        $good = new awardModel();

        $flag = $good->delaward($id);

        return json(['code' => $flag['code'], 'data' => $flag['data'], 'msg' => $flag['msg']]);

    }

}

