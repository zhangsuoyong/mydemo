<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/17
 * Time: 11:00
 */

namespace app\common\validate;


use think\Validate;

class AdminValidate extends Validate
{
  protected $rule = [
    ['username|用户名', 'require', '用户名不能为空'],
    ['password|密码', 'require|alphaNum|length:6,18', '密码不能为空|密码格式不符合要求|密码格式不符合要求'],
    ['repass|确认密码', 'require|alphaNum|length:6,18', '密码不能为空|密码格式不符合要求|密码格式不符合要求'],
  ];

  // 场景设置
  protected $scene = [
    //登录
    'add' => ['username', 'password'],
  ];

}