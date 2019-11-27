<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/19
 * Time: 16:43
 */

namespace app\common\model;


use think\Model;

class Role extends Model
{
  public function getList()
  {
    return $this->order('create_time desc')->paginate(15, false);
  }
}