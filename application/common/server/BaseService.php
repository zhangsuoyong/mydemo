<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/19
 * Time: 21:09
 */

namespace app\common\server;


interface BaseService
{
  function getList($where, $query);
}