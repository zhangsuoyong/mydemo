<?php

namespace app\common\model;

use think\Model;

class Robot extends Model
{
	
	protected $except = [];
	// protected $autoWriteTimestamp;
	
	
	
	
	public function get48Robots(){

		return $this->all(['status'=>'1','due'=>48]);
	}
	public function get72Robots(){

		return $this->all(['status'=>'1','due'=>72]);
	}
	public function get96Robots(){

		return $this->all(['status'=>'1','due'=>96]);
	}
	
	//关联用户表
	public function user(){

		return $this->hasOne ('User','id','uid');
	}

	/**
	 * @param $where 条件
	 * @param $query url 参数
	 *
	 * @return \think\Paginator

	 */
	public function getList(array $where,$query){

	return	$this->where($where)->paginate (20,false,['query'=>$query]);
	}
	
	
	//获取自动排单数据
	public function get_due_data(){
		
		$now=time();
		// var_dump($now);
		// die;
		return $this->where('next_buy_at','<=',$now)->where('status','=',1)->select();
	}


}
