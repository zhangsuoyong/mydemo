<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/20
 * Time: 11:38
 */

namespace app\common\model;


use think\Model;

class Information extends Model
{

  public function getPicNameAttr($value)
  {
    return request()->domain(). $value;
  }
}