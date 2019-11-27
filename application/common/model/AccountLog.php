<?php


namespace app\common\model;


use think\Model;

class AccountLog extends Model
{
  public static function addCoinLog($uid, $type, $num, $sign, $msg)
  {
    $log['uid'] = $uid;
    $log['type'] = $type;
    $log['num'] = $num;
    $log['sign'] = $sign;
    $log['msg'] = $msg;
    AccountLog::create($log);
  }
  public function getTypeAttr($value)
  {
    $status=['1'=>'互助积分','2'=>'冻结积分','3'=>'奖金','4'=>'排单币','5'=>'激活码','6'=>'通证',7=>'天使券','金种子'];
    return ['value'=>$value,'msg'=>$status[$value]];
  }

  public function user()
  {
    return $this->hasOne('user','id','uid');
  }

  public function getUserAttr()
  {
    return $this->user()->value('user');
  }

  public function getNickNameAttr()
  {
    return $this->user()->value('nickname');
  }

  public function getRealNameAttr()
  {
    return $this->user()->value('real_name');
  }
}