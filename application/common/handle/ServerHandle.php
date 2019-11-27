<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/17
 * Time: 9:48
 */

namespace app\common\handle;


use app\common\exception\AuthException;
use app\common\exception\LoginException;
use app\common\exception\ServerException;
use app\common\exception\ValidateException;
use Exception;
use think\exception\Handle;
use think\Response;

class ServerHandle extends Handle
{
  public function render(Exception $e)
  {
    if ($e instanceof ServerException) {
      return returnJson(0, $e->getMessage());
    }

    if ($e instanceof LoginException) {
      return returnJson(2, $e->getMessage());
    }

    if ($e instanceof AuthException) {
      $msg=$e->getMessage();
      $data= "<script>alert('{$msg}');history.back()</script>";
      $response=Response::create($data,'html');
      return $response;
    }

    if ($e instanceof ValidateException) {
      return returnJson(0, $e->getMessage());
    }
    //可以在此交由系统处理
    return parent::render($e);
  }

}