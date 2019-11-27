<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/20
 * Time: 18:04
 */

namespace app\common\model;


use think\Model;

class System_config extends Model
{
  public function getList($where = [], $query = [])
  {
    $list = $this->where($where)
      ->paginate(100, false, ['query' => $query]);
    return $list;
  }

  public function getParamValue($attr) {
    return $this->where('name', $attr)->value('value');
  }

}