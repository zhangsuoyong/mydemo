<?php

namespace app\common\model;

use think\Model;
use think\Request;
use think\Session;


class AdminLog extends Model
{
  /**
   *$user 默认为登录的session
   *添加用户日志
   */
  public static function add_log($str)
  {
    $log = new AdminLog();
    $request = Request::instance();
    if (Session::has('user')) {
      $data = [
        'log' => $str,
        'user' => session('user'),
        'ip' => $request->ip(),
        'method' => $request->method(),
        'agent' => $request->header('user-agent'),
        'create_time' => date('Y-m-d H:i:s', time())
      ];
      $log->insert($data);
    }
  }

  public function getCreateTimeAttr($time)
  {
    return $time;
  }
}
