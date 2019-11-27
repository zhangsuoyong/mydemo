<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/19
 * Time: 21:44
 */

namespace app\common\server;


interface DemandService extends BaseService
{
  //取消需求
  function demand_cancel($id);

  //查看聊天记录
  function chat_log();

  //确认收款
  function receive_confirm();

  //查看申诉
  function appeal_log();



}