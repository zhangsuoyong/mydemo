<?php


namespace app\common\model;


use think\Model;

class UserActionLog extends Model
{
  /**
   * [addActionLog 添加操作日志]
   * @param [type] $uid [uid]
   * @param [type] $msg [msg]
   */
  public static function addActionLog($uid, $msg) {
    $model=new UserActionLog();
    $actionLog['uid'] = $uid;
    $actionLog['msg'] = $msg;
    $model->insert($actionLog);
  }
  
  public static function delActionLog($uid){
  	self::where('uid',$uid)->where ('msg','like','120小时%')->delete ();
  }
  
}