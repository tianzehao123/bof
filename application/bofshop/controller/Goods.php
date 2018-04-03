<?php
namespace app\bofshop\controller;

use think\Controller;
use think\Db;
use think\Request;
use think\Session;
use app\home\validate\WhereValidate;
use app\backend\model\GoodsModel;

//商品控制器
class Goods extends Controller {
	 private $state = [1=>'待支付',2=>'待发货',3=>'待收货',4=>'已完成',5=>'已取消',6=>'已删除'];
	 // 报单专区列表页
	 public function index()
	 {
	 	  $model = new GoodsModel();
	 	  $list = $model->field(['id','name','receive','price','img'])->where(['cid'=>92])->select();
	 	  return ajax(1,'查询成功',$list);
	 }

	 //首页消费全返商品
	 public function consumption(){
	 	  $model = new GoodsModel();
	 	  $list = $model->field(['id','name','receive','price','img'])->where(['cid'=>101])->limit('0,4')->select();
	 	  return ajax(1,'查询成功',$list);	
	 }

	 //商品搜索
	 public function searchGoods(){
	 	 if(empty(input('name'))) return ajax(2,'请输入您要搜索的商品名称');
	 	 $goodsName = ['like','%'.input('name').'%'];
	 	 $goods = Db::name('goods')->field(['id','name','receive','price','img','cid','receive','consumption'])->where(['name'=>$goodsName])->select();
	 	 //区分报单和消费全返商品
	 	 $form        = [];
	 	 $fint = 0;
	 	 $consumption = [];
	 	 $cint = 0;
	 	 foreach($goods as $key=>$value){
	 	 	 if($value['cid']==92){
	 	 	 	 //报单产品
	 	 	 	 $form[$fint]['id']      	 = $value['id'];
	 	 	 	 $form[$fint]['name']    	 = $value['name'];
	 	 	 	 $form[$fint]['receive'] 	 = $value['receive'];
	 	 	 	 $form[$fint]['price']   	 = $value['price'];
	 	 	 	 $form[$fint]['img']	     = $value['img'];
	 	 	 	 $form[$fint]['receive'] 	 = $value['receive'];
	 	 	 	 $form[$fint]['consumption'] = $value['consumption'];
	 	 	 	 $fint++;
	 	 	 }else{
	 	 	 	// 消费全返产品
	 	 	 	 $consumption[$cint]['id']      	 = $value['id'];
	 	 	 	 $consumption[$cint]['name']    	 = $value['name'];
	 	 	 	 $consumption[$cint]['receive'] 	 = $value['receive'];
	 	 	 	 $consumption[$cint]['price']   	 = $value['price'];
	 	 	 	 $consumption[$cint]['img']	     = $value['img'];
	 	 	 	 $consumption[$cint]['receive'] 	 = $value['receive'];
	 	 	 	 $consumption[$cint]['consumption'] = $value['consumption'];
	 	 	 	 $cint++;
	 	 	 }

	 	 }

	 	 $data['form']         = $form;
	 	 $data['consumption']  = $consumption;


	 	 return ajax(1,'查询成功',$data);
	 }




	 //商品详情
	 public function GoodsDetails()
	 {
	 	if(empty(input('id')) && !is_numeric(input('id'))){
	 		return ajax(2,'请重新选择商品');
	 	}
	 	//查询商品信息
 		 $model = new GoodsModel();
 		 $list = $model->field(['id','name','price','receive','img','remark','market_price','description'])->where(['id'=>input('id')])->find();
 		 // return json($list);
 		 if(empty($list) && $list !==false){
 		 	return ajax(2,'您选择的商品不存在');
 		 }
 		 //查询轮播图信息
		 $lunbo = Db::name('lunbo')->field(['imgurl'])->where(['gid'=>input('id')])->order('sort')->select();
		 $data['lunbo'] = $lunbo;
		 $data['list'] = $list;
		 return ajax(1,'查询成功',$data);
	 }

	 //加入购物车
	 public function AddToCart()
	 {

	 	 if(empty(Session::get('home.user'))){
	 	 	return ajax(2,'请登录');
	 	 }else{
	 	 	 $model = new GoodsModel();
	 	 	 //检查商品是否存在
	 	 	 if(empty(input('id')) || !is_numeric(input('id'))){
	 	 	 	return ajax(2,'请选择商品');
	 	 	 }else{
	 	 	 	$isNull = $model->where(['id'=>input('id')])->count();
	 	 	 	if($isNull===false  || empty($isNull)){
	 	 	 		return ajax(2,'您选择的商品不存在');
	 	 	 	}
	 	 	 }
	 	 	 //检查数量是否正常
	 	 	 if(empty(input('number')) || !is_numeric(input('number')) || input('number')<1){
	 	 	 	  return ajax(2,'请选择商品数量');
	 	 	 }
 	 		 if(count(explode('.',input('number')))>1){
 	 		 	 return ajax(2,'您选择的商品数量不正确');
 	 		 }
 	 		 //查询登录者的购物车是否有同一件商品
 	 		 $id = Session::get('home.user')['id'];
 	 		 $list = Db::name('cart')->where(['uid'=>$id,'gid'=>input('id')])->find();
 	 		 if(empty($list)){
	 		 	 $data['gid'] = input('id');
	 		 	 $data['num'] = input('number');
	 		 	 $data['uid'] = Session::get('home.user')['id'];
	 		 	 $data['created_at'] = time();
	 		 	 $result = Db::name('cart')->insert($data);
	 		 	 $sqlId = Db::name('cart')->getLastInsID();
 	 		 }else{
 	 		 	 $list['num'] = $list['num']+input('number');
 	 		 	 $result = Db::name('cart')->where(['id'=>$list['id']])->update($list);
 	 		 	 $sqlId = $list['id'];
 	 		 }

 		 	 if($result!==false){
 		 	 	return ajax(1,'添加成功',$sqlId);
 		 	 }else{
 		 	 	return ajax(2,'添加失败');
 		 	 }
    	 }

	 }


	 //获取购物车中的商品信息
	 public function GetToCart()
	 {
	 	 if(empty(Session::get('home.user'))) return ajax(2,'请登录');
	 	
 	 	 if(empty($_GET['id'])){
 	 	 	 $id = input('id');
 	 	 }else{
	 	 	 if(!is_array($_GET['id'])){
	 	 	 	 $id = $_GET['id'];
	 	 	 }else{
	 	 	 	 $id = implode(',',$_GET['id']);
	 	 	 }
 	 	 }

 	 	 if(empty($id)) return ajax(2,'错误查询');

 	 	 $where['uid'] = Session::get('home.user')['id'];
 	 	 
 	 	 $where['id'] = ['in',$id];


 	 	 if(!is_array($id)){
 	 	 	 $goods = Db::name('cart')->field(['id','gid','num'])->where($where)->find();
 	 	 	 if(empty($goods)) return ajax(2,'您购物车没有该商品');
 	 	 	 $row   = Db::name('goods')->field(['id','name','price','receive','img'])->where(['id'=>$goods['gid']])->find();
 	 	 	 $row['num'] = $goods['num'];
 	 	 	 $row['id']  = $goods['id'];
 	 	 }else{
 	 	 	$goods =  Db::name('cart')->field(['id','gid','num'])->where($where)->select();
	 	 	 
	 	 	 foreach($goods as $key=>$value){
	 	 	 	 $goodsId[] = $value['gid'];
	 	 	 }
	 	 	 $gid = implode(',',$goodsId); 	 	 
	 	 	 $row = Db::name('goods')->field(['id','name','price','receive','img'])->where(['id'=>['in',$gid]])->select();
	 	 	 
	 	 	 foreach($row as $key=>$value){
	 	 	 	 foreach($goods as $k=>$v){	
	 	 	 	 	 if($v['gid']==$value['id']){
	 	 	 	 	 	$row[$key]['num'] = $v['num'];
	 	 	 	 	 	$row[$key]['id'] = $v['id'];
	 	 	 	 	 }
	 	 	 	 }
	 	 	 }

 	 	 }
 	 	 
 	 	 if($row!==false){
 	 	 	return ajax(1,'查询成功',$row);
 	 	 }else{
 	 	 	return ajax(2,'查询失败');
 	 	 }
	 }


	 //购物车
	 public function ShoppingCart()
	 {
	 	 if(empty(Session::get('home.user'))){
	 	 	return ajax(2,'请登录');
	 	 }

        //分页
        if(empty(input('pageInt'))){
        	$this->limit['pageInt'] = 12;
        }else{
        	if(!is_numeric(input('pageInt'))){
        		return ajax(2,'页数必须是数字');
        	}
        	$this->limit['pageInt'] = intval(input('pageInt'));
        }
        // 设置分页
        if(empty(input('pageSize'))){
        	$this->limit['pageSize'] = 0; 
        }else{
        	if(!is_numeric(input('pageSize'))){
        		return ajax(2,'页数必须是数字');
        	}
        	$this->limit['pageSize'] = (intval(input('pageSize')) - 1) * $this->limit['pageSize'];
        }


        //查询数据
        $id = Session::get('home.user')['id'];
        $cart = Db::name('cart')->field(['id','gid','num'])->where(['uid'=>$id])->limit($this->limit['pageSize'],$this->limit['pageInt'])->select();
        $count = Db::name('cart')->where(['uid'=>$id])->count();
        if($cart===false) return ajax(2,'查询失败');
        
        // 获得所有商品id
        foreach($cart as $key=>$value){
        	$goodsId[] = $value['gid'];
        }
        if(!isset($goodsId)) return ajax(1,'查询成功','');
        
        $model = new GoodsModel();
        $goods = $model->field(['id','name','price','receive','price','img'])->where(['id'=>['in',$goodsId]])->select();
        if($goods!=false){
	        //商品信息复位
	        foreach($cart as $key=>$value)
	        {
	        	 foreach($goods as $k=>$v)
	        	 {
	        	 	if($value['gid']===$v['id'])
	        	 	{
	        	 		$cart[$key]['goods'] = $v;
	        	 		$cart[$key]['goods']['price'] = (int)$v['price'];
	        	 	}
	        	 }

	        	 $cart[$key]['num'] = (int)$value['num'];
	        }

	        $data['cart']  = $cart;
	        $data['count'] = $count;

	        return	ajax(1,'查询成功',$data);
        }else{
        	return ajax(2,'查询失败');
        }

	 }

	 //购物车的商品数量
	 public function shoppingCartNum(){
	 	 if(empty(Session::get('home.user'))){
	 	 	return ajax(2,'请登录');
	 	 } 	 
 	 	 $where['uid'] = Session::get('home.user')['id'];
 	 	 $row =  Db::name('cart')->where($where)->count();
 	 	 return ajax(1,'完成',$row);
	 }

	 //增加减少购物车商品数量
	 public function shopAdd(){
	 	 $arr = [1,2];//1增加2删除
	 	 if(empty(input('isadd')) || !in_array(input('isadd'),$arr)) return ajax(2,'请选择操作');
	 	
	 	 if(empty(input('id')) || !is_numeric(input('id'))) return ajax(2,'请选择商品');
	 	
	 	 $where['uid'] = Session::get('home.user')['id'];
	 	 $where['id'] = input('id'); 
	 	 $num = Db::name('cart')->where($where)->value('num');

	 	 if(empty($num)) return ajax(2,'您购物车内没有此商品'); 
	 	 //判断增加或者减少
	 	 if(input('isadd')==1){
	 	 	$data['num'] = $num + 1;
	 	 	$str = '增加';
	 	 }else{
	 	 	if($num<=1) return ajax(2,'数量不能少于1');
	 	 	$data['num'] = $num - 1;
	 	 	$str = '减少';
	 	 }
	 	 $return = Db::name('cart')->where(['id'=>input('id')])->update($data);
	 	 if($return!==false){
	 	 	return ajax(1,$str.'成功');
	 	 }else{
	 	 	return ajax(2,$str.'失败');
	 	 }
	 }

	//删除购物车内的商品
	public function delcart(){

	     if(empty($_GET['id'])) return ajax(2,'请选择商品');
	     $id = $_GET['id'];
	 	 if(!is_array($id)){
	 	 	$where['id'] = $id;
	 	 }else{
		 	 foreach($id as $key=>$value){
		 	 	 if(!is_numeric($value)) return ajax(2,'请选择要删除的商品');
	 	 	 }
	 	 	 $id = implode(',',$id);
	 	 	 $where['id'] = ['in',$id];
	 	 }

	 	 $where['uid'] = Session::get('home.user')['id'];
	 	 
	 	 $num = Db::name('cart')->where($where)->count();
	 	 if($num<1) return ajax(2,'您要删除的商品不存在');
	 	 $return = Db::name('cart')->where($where)->delete();
	 	 if($return!==false){
	 	 	 return ajax(1,'删除成功');
	 	 }else{
	 	 	 return ajax(2,'删除失败');
	 	 }
	}



	 //获取当前登录用户的积分数量
	 public function getIntegral(){
	 	 if(empty(Session::get('home.user'))) return ajax(2,'请登录');
	 	 $uid = Session::get('home.user')['id'];
	 	 $list = Db::name('users')->field(['reg_score','prize_score','receive_score','con_score','game_score','pay_score'])->where(['id'=>$uid])->find();
	 	 return ajax(1,'查询成功',$list);
	 }


	 //收货地址
	 public function goodsReceipt()
	 {
	 	 if(empty(Session::get('home.user'))){
	 	 	return ajax(2,'请登录');
	 	 }
	 	 $id = Session::get('home.user')['id'];
	 	
	 	 $list = Db::name('address')->where(['uid'=>$id])->select();
	 	 if($list!==false){
	 	 	return ajax(1,'查询成功',$list);
	 	 }else{
	 	 	return ajax(2,'查询失败');
	 	 }
	 }

 	 //查询商品
 	 public function SelectGoods(){
 	 	if(empty(input('goodsId')) || !is_numeric(input('goodsId'))){
 	 		return ajax(2,'请您选择商品');
 	 	}
	 	 $list = Db::name('goods')->where(['id'=>input('goodsId')])->field(['id','name','price','receive','price','img'])->find();
	 	 if($list!==false && !empty($list))
	 	 {
	 	 	return ajax(1,'查询成功',$list);
	 	 }else{
	 	 	return  ajax(2,'您查询的商品不存在');
	 	 }
 	 }



 	/**
     * //立即购买提交订单
     * @param goodsId    商品id
     * @param number     商品数量
     * @param ressId     收货地址ID
     * @return 成功返回订单ID 否则返回false
     */
	 public function purchase()
	 {	
	 	 if(empty(Session::get('home.user'))){
	 	 	return ajax(2,'请登录');
	 	 }
	 	 if(empty(input('goodsId')) || !is_numeric(input('goodsId'))){
	 	 	 return ajax(2,'请选择商品');
	 	 }
	 	 if(empty(input('number')) || !is_numeric(input('number')) || input('number')<1){
	 	 	 return ajax(2,'商品数量不能为空');
	 	 }
	 	 if(empty(input('ressId')) || !is_numeric(input('ressId'))){
	 	 	return ajax(2,'请选择收货地址');
	 	 }

  		 $type  = (empty(input('type')))?2:input('type');

	 	 // 生成订单
	 	 $goods[] = ['id'=>input('goodsId'),'number'=>input('number')];
	 	 $result = $this->AddOrders($goods,input('ressId'),$type);
	 	 if(is_numeric($result)){
	 	 	 return ajax(1,'订单生成成功',$result);
	 	 }else{
	 	 	 return ajax(2,$result);
	 	 }
	 }


 	/**
     * 购物车确认订单信息
     * @param goods   购物车id集合(JSON格式)
     * @param ressId   收货地址ID
     * @return 成功返回订单ID 否则返回false
     */
     public function CartSettlement()
     {	 


	 	 if(empty(Session::get('home.user')))  return ajax(2,'请登录');
	 	 
	 	 if(empty(input('goods'))) return ajax(2,'请选择商品');
	 	 
	 	 if(empty(input('ressId')) || !is_numeric(input('ressId'))) return ajax(2,'请选择收货地址');
	 	 $arr = trim(input('goods'),'&quot;');
	 	 $arr = explode(',',$arr);
	 	 if(empty($arr) || !is_array($arr)) return ajax(2,'请选择商品');

	 	 foreach($arr as $key=>$value){

	 	 	 if(!is_numeric($value)) return ajax(2,'商品格式错误');
	 	 }

	 	 //遍历购物车id获取商品id信息
	 	 if(empty($arr) || !is_array($arr)) return ajax(2,'请选择商品');

	 	 $goods = Db::name('cart')->field(['gid','num'])->where(['id'=>['in',$arr]])->select();
	 	 if($goods===false || count($goods)<1) return ajax(2,'购物车内没有该商品');
	 	 foreach ($goods as $key => $value) {
	 	 	$goodsInfo[$key]['id']     = $value['gid'];
	 	 	$goodsInfo[$key]['number'] = $value['num'];
	 	 }
	 	//生成订单
	 	$result	 = $this->AddOrders($goodsInfo,input('ressId'));
	 	if(!is_numeric($result))  return ajax(2,$result);
	 	
	 	//删除购物车内购物车内已下订单商品
	 	$row = Db::name('cart')->where(['id'=>['in',$arr]])->delete();
	 	if($row!==false){
	 		return ajax(1,'生成订单成功',$result);
	 	}else{
	 		foreach($arr as $key=>$value)
	 		{
	 			Db::name('cart')->where(['id'=>$value])->delete();
	 		}
	 	}
     }



 	/**
     * 生成订单
     * @param $goods  商品id和商品数量的数组集合
     * @param $city   收货地址ID
     * @return 成功返回订单ID 否则返回false
     */
     public function AddOrders($goods,$cityId,$type=null)
     {
	 	if(empty(Session::get('home.user'))){
 	 		return ajax(2,'请登录');
 		}
     	if(empty($goods) || !is_array($goods)){
     		return '请选择商品';
     	}
     	if(empty($cityId)){
     		return '请选择收货地址';
     	}
     	//查询收货地址
     	$uid = Session::get('home.user')['id'];
     	$city = Db::name('address')->where(['id'=>$cityId,'uid'=>$uid])->find(); 
     	if($city===false || empty($city)){
     		return '请输入正确的收货地址';
     	}

     	if(!isset($city['city']) || !isset($city['description']) || !isset($city['phone']) || !isset($city['name'])) return '请补全收货地址';

	 	 // 遍历出所有的商品id;
	 	 foreach($goods as $key=>$value)
	 	 { if(isset($value['id'])){
	 	 	 $goodsId[] = $value['id'];
	 	 	}else{
	 	 		return '商品信息不完整';
	 	 	}
	 	 }
	 	 //根据所有的商品id 查出所有的商品信息
	 	 if(!isset($goodsId)){
	 	 	return '请选择商品';
	 	 }
	 	 $glist = Db::name('goods')->field(['id','name','img','price','receive','cid','market_price'])->where(['id'=>['in',$goodsId]])->select();
	 	 //每一个商品生成一条记录
	 	 if($glist===false || empty($glist)){
	 	 	return '查询商品失败';
	 	 }

	 	 $Amount = 0;
		 //根据商品信息遍历赋值订单详情
	 	 foreach($goods as $key=>$value)
	 	 {
	 	 	foreach($glist as $k=>$v){
	 	 		if($value['id']==$v['id'])
	 	 		{
			 	 	$name = 'data'.$key;
				 	 //生成订单详情
				 	$$name['gid'] = $v['id'];
				 	$$name['g_num'] = $value['number'];
				 	$$name['gname'] = $v['name'];
				 	$$name['gimg'] = $v['img'];
				 	$$name['gprice'] = $v['price']*$value['number'];
				 	$$name['created_at'] = time();
				 	$nameArr[] = $name;
				 	if($v['cid']==101){
				 		// 判断消费全返是否为全额购买或打折购买
				 		if($type==2){
				 			$Amount	 += $value['number'] * $v['price'];
				 		}else{
				 			$Amount	 += $value['number'] * $v['market_price'];
				 		}
				 	}else{
				 		$Amount += $value['number'] * $v['price'];
				 	}
	 	 		}
	 	 	}

	 	 }


	 	if(!isset($nameArr))  return '商品信息补全失败';
	 	
	 	 //开始生成订单
	 	 $data['uid'] = Session::get('home.user')['id'];
	 	 $data['order_sn']   = orderNum();
	 	 $data['city']  = $city['city'];  //收货地址
	 	 $data['address_detail'] =  $city['description'];   //收货详细地址
	 	 $data['address_phone']  =   $city['phone'];  //收货人电话
	 	 $data['address_name']  = 	 $city['name'];  //收货人电话
	 	 $data['price']   =  $Amount;
	 	 $data['status'] = 1;
	 	 $data['style']  = input("style")??1;
	 	 $data['created_at'] = time();
	 	 $data['is_new'] = 1;
	 	 $data['payment_method'] = input("pyment_method")??1;
	 	 //订单生成成功返回订单ID
		Db::startTrans();
		try{

		 	$result  = Db::name('order')->insert($data);
		 	$orderId = Db::name('order')->getLastInsID();
		 	//根据每一个商品生成订单详情
		 	foreach($nameArr as $value){
		 	   if(isset($$value)){
			 		$$value['oid'] = $orderId;
			    	$result1 = Db::name('order_detail')->insert($$value);  //添加扣除记录
			    	if($result1===false){
			    		Db::rollback();
			 	 		return '订单生成失败';
			    	}
		 	   }		
		 	}

		    // 提交事务
		    if($result!==false){
		    	Db::commit();
		    	return $orderId;
		    }else{
		 	 	Db::rollback();
		 	 	return '订单生成失败';
		     }
		} catch (\Exception $e){
		    // 回滚事务
		    Db::rollback();
		    return '订单生成失败';
		}

     }


     //读取订单信息
     public function OrderList()
     {	
     	 if(empty(Session::get('home.user'))) return ajax(2,'请登录');
     	 $id = input('id');
     	 if(empty($id)) return ajax(2,'该订单不存在');
     	 $uid = Session::get('home.user')['id'];
     	 //获取订单信息
     	 $field = ['id','order_sn','address_detail','address_phone','address_name','price','payment_method','logistics_name','freight','status','ship','created_at'];
 		 $result = Db::name('order')->field($field)->where(['id'=>$id,'uid'=>$uid])->find();
 		  if(empty($result)) return ajax(2,'该订单不存在');
	 		 //获取商品名称
 		 $goods = Db::name('order_detail')->field(['gid','gname','gimg','g_num','gprice'])->where(['oid'=>$id])->select();

 		 $result['status']  = $this->state[$result['status']];
 		 $result['created_at'] = date('Y-m-d h:i:s',$result['created_at']);
 		 if(count($goods)<=1){
	 		 $result['goodsName'] = $goods[0]['gname'];
	 		 $result['goodsImg']  = $goods[0]['gimg'];
	 		 $result['gprice']    = $goods[0]['gprice'];
	 		 $result['g_num']     = $goods[0]['g_num'];
	 		 $result['gid']       = $goods[0]['gid'];
	 		 //单价
	 		 $result['one_price'] = Db::name('goods')->where(['id'=>$goods[0]['gid']])->value('price');
 		 }else{
 		 	 foreach($goods as $key=>$value){
 		 	 	$result['goods'][$key]['goodsName'] = $value['gname'];
 		 	 	$result['goods'][$key]['goodsImg']  = $value['gimg'];
 		 	 	$result['goods'][$key]['gprice']    = $value['gprice'];
 		 	 	$result['goods'][$key]['g_num']     = $value['g_num'];
 		 	 	$result['goods'][$key]['gid']       = $value['gid'];
 		 	 	$goodsId[] =  $value['gid'];
 		 	 }

 		 	 //获取订单中所有商品的单价
 		 	 $goodsPrice = Db::name('goods')->field(['id','price'])->where(['id'=>['in',$goodsId]])->select();
 		 	 //组合商品单价
 		 	 foreach($result['goods'] as $key=>$value){
 		 	 	 foreach($goodsPrice as $k=>$v){
 		 	 	 	if($value['gid']==$v['id']){
 		 	 	 		$result['goods'][$key]['one_price'] = $v['price'];
 		 	 	 	}
 		 	 	 }
 		 	 }
 		 }

 		 if($result!==false){
 		 	return ajax(1,'查询成功',$result);
 		 }else{
 		 	return ajax(2,'该订单不存在');
 		 }
     }


	 //支付
	 public function payment()
	 {			
	 	    // 测试
 	    if(empty(Session::get('home.user'))) return ajax(2,'请登录');
 		
 	 	$payment = [1=>'pay_score',2=>'con_score'];
 	 	$paymentNmae = [1=>'购物积分',2=>'消费积分'];
 	 	$status = ['1'=>'新订单','2'=>'已付款','3'=>'已发货','4'=>'已收货','5'=>'未提交'];
 	 	 if(empty(input('id')) || !is_numeric(input('id'))){
 	 	 	return ajax(2,'订单号不能为空');
 	 	 }
 	 	 if(empty(input('two_password'))){
 	 	 	return ajax(2,'支付密码不能为空');
 	 	 }
 	 	 //检查支付密码是否输入正确
 	 	 $uid = Session::get('home.user')['id'];
 	 	 $user = Db::name('users')->field(['pay_score','con_score','code','two_password'])->where(['id'=>$uid])->find();
 	 	 if($user['two_password']!=md5(input('two_password'))){
 	 	 	return ajax(2,'请输入正确的支付密码');
 	 	 }
 	 	 //查询订单是否存在
 	 	 $order = Db::name('order')->field(['id','price','order_sn','status','created_at','payment_method'])->where(['id'=>input('id'),'uid'=>$uid])->find();

 	 	 if(empty($order) || $order===false){
 	 	 	return ajax(2,'您要支付的订单不存在'); 
 	 	 }
 	 	 if($order['status']!=1){
 	 	 	$str = '该订单'.$status[$order['status']].'不能再支付';
 	 	 	return ajax(2,$str);
 	 	 }
 	 	 //判断余额是否足够支付
 	 	 if($order['price']>$user[$payment[$order['payment_method']]]){
 	 	 	$str = '您的'.$paymentNmae[$order['payment_method']].'余额不足';
 	 	 	return ajax(2,$str);
 	 	 }
 	 	 //扣除相应的积分
 	 	 $data[$payment[$order['payment_method']]] =  $user[$payment[$order['payment_method']]] - $order['price'];
 	 	 

 	 	 //修改订单状态
 	 	 $data2['status'] = 2;
 	 	 $data2['is_new'] = 2;
 	 	 $data2['updated_at'] = time();
 	 	 //写入记录
 	 	 $data3['uid'] = $uid;
 	 	 $data3['score'] = '-'.$order['price'];
 	 	 $data3['cur_score'] = $data[$payment[$order['payment_method']]];
 	 	 $data3['remark'] = $user['code'].'支付订单';
 	 	 $data3['class'] = 1;
 	 	 $data3['is_add'] = 2;
 	 	 $data3['type'] = $order['payment_method']==1?7:$order['payment_method'];
 	 	 $data3['source']  =4;
 	 	 $data3['from_uid'] = 0;
 	 	 $data3['created_at'] = time();
 	 	 

 	 	 //开始支付
	    Db::startTrans();
		try{

			 $result  = Db::name('users')->where(['id'=>$uid])->update($data);
			 $result2 = Db::name('order')->where(['id'=>input('id')])->update($data2);
			 $result3 = Db::name('account')->insert($data3);
		    // 提交事务
		  if($result!==false && $result2!==false && $result3!==false){
		  		// 判断订单类型
		        if($order['payment_method']==1) {
		        	//报单产品分配获赠积分 失败则回滚
		        	$return = $this->shopping_number(input('id'));
		        }elseif($order['payment_method']==2){
		        	//全返产品
		        	$return = $this->money_return(input('id'));
		        }

		        // 提交事务
	        	if(isset($return) && $return!==false){
			    	Db::commit();
			    	return ajax(1,'支付成功');
	        	}else{
		 	 	   	Db::rollback();
		 	 	    return ajax(2,'支付失败');
	        	}
		   }else{
		 	 	Db::rollback();
		 	 	return ajax(2,'支付失败');
		   }
		} catch (\Exception $e){
		   // 回滚事务
		   Db::rollback();
		   return ajax(2,'支付失败');
		}
 	 }



 	 //统计购物积分消费状况
 	 private function shopping_number($orderId)
 	 {	

	 	$result =  Db::name('order_detail')->field(['id','oid','gid','gprice','g_num'])->where(['oid'=>$orderId])->select();
	    if(empty($result)) return false;
	    //计算订单内商品价格总和 每个商品计算一次
	    $num = 0 ;	
	    foreach($result as $key=>$value){
	   	   $num += $value['gprice'];
	   	   $goodsId[] = $value['gid'];
	    }	

	
	    //赠送积分
	    if(!isset($goodsId) || $num==0) return false;
	    $goods = Db::name('goods')->field(['id','receive','class'])->where(['id'=>['in',$goodsId]])->select();
	    $receive = 0;
	    $class   = 0; 
	    foreach($goods as $key=>$value){
	    	foreach($result as $k=>$v){
	    		if($v['gid']==$value['id']){
	    			$receive += $value['receive']*$v['g_num'];
	    		}
	    	}
	    	if($value['class']>$class) $class = $value['class'];
	    }

	    //获取用户原有购买商品等级
	     $user = Db::name('users')->field(['receive_score','shopping_number'])->where(['id'=>Session::get('home.user')['id']])->find();

	     $data['receive_score']         =  $user['receive_score'] + $receive;
	     $data['shopping_number']       =  ($user['shopping_number']>$class)?$user['shopping_number']:$class;

	    //添加新增获赠积分记录
 	 	 $data2['uid']       = Session::get('home.user')['id']; //用户id
 	 	 $data2['score']     = '+'.$receive;                 
 	 	 $data2['cur_score'] = $user['receive_score'] + $receive;
 	 	 $data2['remark']    = '支付订单成功赠送获赠积分';   //备注
 	 	 $data2['class']     = 1;						   
 	 	 $data2['is_add']    = 1;
 	 	 $data2['type']      = 6;
 	 	 $data2['source']    = 4;
 	 	 $data2['from_uid']  = 0;
 	 	 $data2['created_at']= time();


		//增加消费记录总量 增加购物积分
		Db::startTrans();
		try{
		    $result1 = Db::name('users')->where(['id'=>Session::get('home.user')['id']])->update($data);
		    if($receive>0){
		    	$result2 = Db::name('account')->insert($data2);		    	
		    }else{
		    	$result2 =true;
		    }

		    
		    if($result1!==false && $result2!==false){
		    	Db::commit();
		    	return true;
		    }else{
		       Db::rollback();
		       return false;
		    }
		} catch (\Exception $e){
		    // 回滚事务
		    Db::rollback();
		    return false;
		}
 	 }


 	 //全返产品
 	 private function money_return($orderId){
 	 	#查出订单
 		$order = db("order")->where("id",$orderId)->find();
 		#style  2 全返商品 || 3 折扣购买
 		#折扣购买不需要返消费积分
 		if($order['style']==3){
 			return true;
 			exit;
 		}
 		#全返商品 查出商品id
 		$goodsid = db("order_detail")->where("oid",$orderId)->value("gid");
 		#得到返钱百分比
 		$consumption = db("goods")->where("id",$goodsid)->value("consumption");
 		#每次返还的钱数    
 		$money = round(($order['price'] * ($consumption / 100)),2); 				
 		#得到返钱的天数
 		$return_day = ceil($order['price'] / $money);
 		#剩余返还的钱数
 		$surplus_money = $order['price'];
 		$data = [
 			'uid'				=>		$order['uid'],							#用户id
 			'order_num'			=>		$order['order_sn'],						#订单号
 			'return_money'		=>		$order['price'],						#返钱总数
 			'surplus_money'		=>		$surplus_money,							#剩余返还的钱数
 			'return_days'		=>		$return_day,							#返还的天数 = 返钱总数 / 每次返还的金额
 			'money'				=>		$money,									#每次返还的金额 = 返钱总数 * (每次返钱的百分比 / 100)
 			'back_days'			=>		0,										#已返天数
 			'back_money'		=>		0.00,									#已返金额
 			'ends'				=>		0,										#是否还完
 			'created_at'		=>		time(),
 		];
 		#存库
		Db::startTrans();
		try{
		    $result = db('full_back')->insert($data);
		    if($result!==false){
		    	Db::commit();
		    	return true;
		    }else{
		       Db::rollback();
		       return false;
		    }
		} catch (\Exception $e){
		    // 回滚事务
		    Db::rollback();
		    return false;
		}

 	 }


 	#全返商品定时返钱
 	public function Returnmoney(){
 		#查出所有未还完的
 		$data = db("full_back")->where("ends",0)->select();
 		foreach($data as $key=>$value){
 			#需要还的钱
 			$money = $value['money'];
 			#返还的总天数
 			$return_days = $value['return_days'];
 			#已返天数
 			$back_days = $value['back_days'];
 			#需要返的总金额
 			$return_money = $value['return_money'];
 			#已还金额
 			$back_money = $value['back_money'];
 			#剩余返还钱数
 			$surplus_money = $value['surplus_money'];
 			
 			#存库
 			$data1 = [
 				'back_money' 		=> 	$money + $back_money,						#已还金额
 				'surplus_money'		=> 	$return_money - ($money + $back_money),		#剩余返还钱数
 				'back_days'			=>	$back_days + 1,								#已返天数
 			];
 			#判断
 			if(($data1['back_money'] >= $return_money) && ($data1['back_days'] >= $return_days)){
 				$data1['ends'] = 1;
 			}
 			#存account
 			$data2 = [
 				'uid'			=>  		$value['uid'],
 				'score'			=>			$money,								#多少积分
 				'cur_score'		=>			db('users')->where('id',$value['uid'])->value('con_score'),				#当前积分
 				'remark'		=>			'全返商品返还消费积分',
 				'class'			=>			'1',
 				'is_add'		=>			'1',
 				'type'			=>			'4',
 				'style'			=>			'1',
 				'created_at'	=>			time(),
 				'status'		=>			'2',
	 		];
	 		#
	 		$data3 = [
 				'uid'			=>  		'0',
 				'from_uid'		=>			$value['uid'],
 				'score'			=>			-$money,								#多少积分
 				'remark'		=>			'全返商品返还消费积分',
 				'class'			=>			'1',
 				'is_add'		=>			'2',
 				'type'			=>			'4',
 				'style'			=>			'1',
 				'created_at'	=>			time(),
 				'status'		=>			'2',
	 		];
	 		Db::startTrans();
			try{
			    $result = db('full_back')->where('id',$value['id'])->update($data1);
			    $result1 = db('account')->insert($data2);
			    $result1 = db('account')->insert($data3);
			    if($result!==false && $result2!==false && $result1 !==false){
			    	Db::commit();
			    	return true;
			    }else{
			       Db::rollback();
			       return false;
			    }
			} catch (\Exception $e){
			    // 回滚事务
			    Db::rollback();
			    return false;
			}
 		}
 	}











}