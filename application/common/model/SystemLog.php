<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/20
 * Time: 17:11
 */

namespace app\common\model;


use think\Model;

class SystemLog extends Model
{
  public function getList($where = [], $query = [])
  {
    $list = $this->where($where)->order('create_time desc')
      ->paginate(15, false, ['query' => $query]);
    return $list;
  }
}