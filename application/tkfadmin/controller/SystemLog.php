<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/20
 * Time: 17:10
 */

namespace app\tkfadmin\controller;


class SystemLog extends Base
{

  public function index()
  {
    $model = new \app\common\model\SystemLog();
    $list = $model->getList();
    return view('index', ['list' => $list]);
  }


}