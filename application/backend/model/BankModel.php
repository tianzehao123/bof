<?php
namespace app\backend\model;

use think\Model;

class BankModel extends Model{
	protected $table = 'sql_bank';

	public function users()
	{
		return $this->belongsTo('UserModel','from_uid','id')->field('id,nickname,phone,headimgurl');
	}

}