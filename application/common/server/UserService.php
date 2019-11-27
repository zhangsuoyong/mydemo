<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/19
 * Time: 20:57
 */

namespace app\common\server;


use app\common\model\User;

Interface UserService extends BaseService
{
  //更改个人信息
  function update_info();

  //更改密码
  function update_password();

  //更改二级密码
  function update_paypassword();

  //操作日志
  function log();

  //资产日志
  function account_log();

  //更改资产
  function account_edit();

  //查看团队体系
  function team_list();

  //新增用户
  function user_add();

  //生成激活码或二维码
  function qc_codeAndInvite(User $id);

}