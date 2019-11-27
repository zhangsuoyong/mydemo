<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/19
 * Time: 21:42
 */

namespace app\common\server;


interface AccountService extends BaseService
{
  //更改用户资产
  function update_account();
}