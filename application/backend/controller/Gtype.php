<?php
/**
 * Created by PhpStorm.
 * User: ovo
 * Date: 2017/7/10
 * Time: 下午6:08
 */
namespace app\backend\controller;

use app\backend\model\GtypeModel;
use think\File;
use think\Request;
use think\Db;
//商品分类
class Gtype extends Base{

    private static $table = 'class'; //类别表

    //分类列表
    public function index()
    {
    	if(Request()->isAjax()){
            $one = Db::name(self::$table)->order('sort')->where(['pid'=>0])->select();
            $two= Db::name(self::$table)->order('sort')->select();
            foreach($one as $key=>$value)
            {  
                $int= 0;
                foreach($two as $k=>$v)
                {
                    if($value['id']===$v['pid'])
                    {   
                        $one[$key]['children'][$int]['text'] = $v['class']."<i class='glyphicon glyphicon-remove  myi' onclick=\"del(".$v['id'].",'".$v['class']."')\"  title='删除' style='margin-left:50px;'></i>";
                        $one[$key]['children'][$int]['icon'] = 'glyphicon glyphicon-bookmark';
                        $int++;
                    }
                }
                $one[$key]['text'] = $value['class']."<i class='glyphicon glyphicon-remove myi' onclick=\"del(".$value['id'].",'".$value['class']."')\" title='删除' style='margin-left:50px;'></i>";
                $one[$key]['icon'] = 'glyphicon glyphicon-bookmark';
            }

            return ajax(1,'查询成功',$one);
    	}else{
            $list = Db::name('class')->where(['pid'=>0])->order('sort')->select();

            $this->assign(['list'=>$list]);
    		return $this->fetch();
    	}
    }


    //添加分类
    public function gtypeadd()
    {   
        $model = new GtypeModel();

        if(empty(input('name'))){
            return '分类名称不能为空';
        }else{
            $data['class'] = input('name');
        }

        if(empty(input('pid')) && input('pid')!=0){
             return '父级不能为空';
        }else{
            $data['pid'] = input('pid');
            if($data['pid']!=0){
               $data['pname'] = Db::name('class')->where(['id'=>input('pid')])->value('class');
            }else{
               $data['pname'] = '顶级分类';
            }
        }
        if(!empty(input('sort'))){
            if(!is_numeric(input('sort'))){
                return '排序必须为数字';
            }else{
                $sort = input('sort');
            }
        }else{
           $sort =  Db::name(self::$table)->order('sort desc')->where(['pid'=>$data['pid']])->value('sort');
           if(empty($sort)){
              $sort = 1;
           }else{
              $sort=$sort+1;
           }
        }
               Db::name(self::$table)->insert($data);
        //添加数据并排序
        $typeId = Db::name(self::$table)->getLastInsID();

        if(!empty($typeId)){
            $result = $this->sort($typeId,intval($sort));
            if($result===true){
                 return  '添加成功';
            }else{
                 return  $result;
            }
        }else{
             return  '添加失败';
        }
    }


    //删除分类
    public function gtypedel()
    {
        if(!is_numeric(input('id')) || empty(input('id')))
        {
            return '请选择重新您要删除的分类';
        }
        $son = Db::name(self::$table)->where(['pid'=>input('id')])->count();
        if(!empty($son) || $son>0){
            return '不能删除有子级的分类';
        }
        $goods = Db::name('goods')->where(['cid'=>input('id')])->count();
        if(!empty($goods) || $goods>0){
            return '不能删除有商品的分类';
        }
        $result = Db::name(self::$table)->delete(input('id'));
        if($result!==false){
            return '删除成功';
        }else{
            return '删除失败';
        }
    }


    /**
    * 排序
    * @param $id    需要排序的数据ID 
    * @param $sort  排到第几位
    * @return 
    */
    public function sort($id,$sort=null)
    {   
        if(!is_numeric($sort)){
            return '排序必须填写数字';
        }
        if(!is_numeric($id)){
            return 'id必须是数字';
        }

        $pid = Db::name(self::$table)->where(['id'=>$id])->value('pid');
        if($pid!==0){
            if($pid===false || empty($pid)){
                return '您需要排序的数据不存在';
            }    
        }

        //如果相同父元素下有相同或者大于排序数值的将会被加一并更新否则直接添加
        $large = Db::name(self::$table)->where(['sort'=>$sort,'pid'=>$pid])->count();
        $list = Db::name(self::$table)->field(['id','sort'])->order('sort')->where(['pid'=>$pid,'sort'=>['>=',$sort]])->select();
        // 相同父元素下没有相同排序值 将直接更新排序
        if(empty($large) || count($large)<1){
             if(Db::name(self::$table)->where(['id'=>$id])->update(['sort'=>$sort])!==false){
                 return true;
             }else{
                 return '排序失败';
             }
        }else{

             // 如果大于当前添加排序数值中间有空缺的话 后面的数值将不会再被增大 直到被补满
             foreach($list as $key=>$value){
                 if(isset($this->v)){
                     if($this->v!=$value['sort']){
                        break;
                     }
                 }
                 $list[$key]['sort'] = $value['sort']+1;
                 $this->v = $value['sort']+1;
             }
                

             $list[$id]['sort'] = $sort;
             $list[$id]['id'] = $id;

             $model = new GtypeModel();
             //批量更新大于要排序的值的数据

             if($model->saveAll($list)!==false){
                 return true;
             }else{
                 return "排序失败";
             }             
        }
    }

}