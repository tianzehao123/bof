<?php
namespace app\backend\model;

use think\Model;

class CartModel extends Model{
    protected $table = 'sql_cart';

    public function goods(){
        return $this->belongsTo('GoodsModel','gid','id');
    }
}