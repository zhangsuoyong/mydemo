<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/17
 * Time: 15:41
 */

namespace app\common\validate;


use think\Validate;

class UserValidate extends Validate
{
  protected $rule = [
    ['user|账号', 'require', '用户名不能为空'],
    ['nickname|昵称', 'require', '昵称不能为空'],
    ['password|密码', 'require|alphaNum|length:6,16', '密码不能为空|密码格式不符合要求|密码格式不符合要求'],
    ['oldpassword|密码', 'require|alphaNum|length:6,16', '密码不能为空|密码格式不符合要求|密码格式不符合要求'],
    ['repassword|确认密码', 'require|alphaNum|length:6,16|confirm:password', '确认密码不能为空|密码格式不符合要求|密码格式不符合要求|两次输入密码不一致'],
    ['invite|邀请码', 'require', '邀请码不能为空'],
    ['code|验证码', 'require', '验证码不能为空'],
    ['paypassword|', 'require|number|length:6', '支付密码不能为空|密码格式不符合要求|密码格式不符合要求'],
  ];
  // 场景设置
  protected $scene = [
    //登录
    'register' => ['user', 'password', 'paypassword', 'invite', 'nickname','code'],
    'forget' => ['user', 'password', 'repassword','code'],
    'repaypassword' => ['user', 'password', 'repassword','code'],
    'repassword' => ['oldpassword', 'password', 'repassword',],
    'login' => ['user', 'password','code'],
    'login_q' => ['user', 'password'],
    'admin_add' => ['user', 'password', 'ident', 'paypassword','nickname']
  ];
}