<?php

namespace app\common\model;

use think\Model;

class RobotLogs extends Model
{
    protected $except=[];


    public function addLog($rid,$result){
		$this->rid=$rid;
		$this->result=$result;
		$this->save ();

    }
    
   public function user(){

    	return $this->belongsTo('User','rid','id');
    }
    
    public function getList(array $where, $query){

    	return $this->where ($where)->order('created_at','desc')->paginate (20,false,['query'=>$query]);
	}


}
