<?php

namespace app\tkfadmin\controller;

use app\common\model\AdminLog;
use think\Session;

class Admin extends Base
{
  protected $log;

  public function __construct(AdminLog $log)
  {
    parent::__construct();
    $this->log = $log;
  }

  //管理员列表
  public function index()
  {
    $list = model('admin')
      ->alias('a')
      ->field('a.*,r.name')
      ->join('role r', 'r.id = a.roleid', 'left')
      ->order('id desc')
      ->paginate(15, false, ['type' => 'Bootstrap']);//查询管理员信息
    $this->log->add_log('浏览管理员列表', Session::get('user'));
    //加载
    $this->assign('list', $list);
    $this->assign('admin_id', Session::get('admin_id'));
    return view();
  }

  // 管理员日志
  public function log()
  {
    $where = '';
    if (input('?param.keyword')) {
      $where['user|log'] = ['like', '%' . input('param.keyword') . '%'];
      $this->assign('keyword', input('param.keyword'));
    }
    $user = Session::get('user');
    $list = model('adminlog')
      ->where($where)
      ->order('create_time desc')
      ->paginate(15, false, ['type' => 'Bootstrap', 'query' => request()->param()]);//查询该商家订单信息
    $this->log->add_log('查看管理员日志', Session::get('user'));
    $this->assign('list', $list);
    return view();
  }

  //添加管理员信息
  public function user_add()
  {
    //查询指定信息
    $list = model('role')->select();
    $this->assign('list', $list);
    return $this->fetch();
  }

  //添加管理员
  public function user_insert()
  {
    if (!request()->isPost()) {
      return returnJson(0, '非法入侵');
    }
    $post = input('post.');
    //验证
    $validate = validate('AdminValidate');
    if (!$validate->scene('add')->check($post)) {
      return returnJson(0, $validate->getError());
    }
    //判断账号是否存在
    if (model('admin')->where('username', $post['username'])->find()) {
      return returnJson(0, '该账号已存在');
    }
    //增加操作记录
    $this->log->add_log('添加管理员', Session::get('user'));
    //增加管理员
    $post['password'] = encrypt($post['password']);
    if (model('admin')->insert($post)) {
      return returnJson(1, '添加成功');
    } else {
      return returnJson(0, '添加失败');
    }
  }

  // 编辑管理员
  public function user_edit()
  {
    if (!request()->isPost()) {
      return returnJson(0, '非法入侵');
    }
    //管理员ID
    $admin_id = input('post.id');
    $user = model('admin')->where('id', $admin_id)->find();
    $role = model('role')->select();

    $this->assign('user', $user);
    $this->assign('role', $role);
    return $this->fetch();
  }

  //编辑
  public function user_update()
  {
    if (!request()->isPost()) {
      return returnJson(0, '非法入侵');
    }
    $post = input('post.');
    //更改信息
    model('admin')->where('id', $post['id'])->setField('roleid', 1111);
    $this->log->add_log('编辑管理员角色', Session::get('user'));
    if (model('admin')->where('id', $post['id'])->update($post)) {
      return returnJson(1, '管理员更改成功');
    } else {
      return returnJson(1, '管理员更改失败');
    }
  }

  //删除
  public function user_del()
  {
    if (!request()->isPost()) {
      return returnJson(0, '非法入侵');
    }
    $admin_id = input('post.id');
    if ($admin_id == session::get('admin_id')) {
      return returnJson(0, '不可删除本账号!');
    }
    $this->log->add_log('删除管理员', Session::get('user'));
    if (model('admin')->where('id', $admin_id)->delete()) {
      return returnJson(1, '删除成功');
    } else {
      return returnJson(0, '删除失败');
    }
  }

  public function pass()
  {
    return $this->fetch();
  }

}