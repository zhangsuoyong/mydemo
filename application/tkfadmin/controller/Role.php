<?php

namespace app\tkfadmin\controller;

use app\common\model\AdminLog;
use think\Session;
use think\Db;
use think\Request;

// 角色管理
class Role extends Base
{
  protected $log;

  public function __construct(AdminLog $log)
  {
    parent::__construct();
    $this->log = $log;
  }

  //角色列表
  public function role_list()
  {
    $role = model('Role')->paginate(15, false, ['type' => 'Bootstrap']);
    $this->assign('list', $role);
    $this->assign('admin_id', Session::get('user'));
    $this->log->add_log('浏览角色信息', Session::get('user'));
    return view();
  }

  //添加角色
  public function role_add()
  {
    return $this->fetch();
  }

  //添加
  public function role_insert()
  {
    if (!request()->isPost()) {
      return returnJson(0, '非法入侵');
    }
    $post = input('post.');
    //判断是否存在
    if (model('role')->where('name', $post['name'])->find()) {
      return returnJson(0, '该角色名已存在');
    }
    $this->log->add_log('添加角色', Session::get('user'));
    //添加角色信息
    if (model('role')->insert($post)) {

      return returnJson(1, '添加成功');
    } else {
      return returnJson(0, '添加失败');
    }
  }

  //编辑角色的模板
  public function role_edit()
  {
    if (!request()->isPost()) {
      return returnJson(0, '非法入侵');
    }
    $role_id = input('post.id');
    $list = model('role')->where('id', $role_id)->find();
    $this->assign('list', $list);
    return $this->fetch();
  }

  //编辑角色
  public function role_update()
  {
    if (!request()->isPost()) {
      return returnJson(0, '非法入侵');
    }
    $post = input('post.');
    //编辑角色信息
    $this->log->add_log('编辑角色信息', Session::get('user'));
    if (model('role')->where('id', $post['id'])->update($post)) {
      return returnJson(1, '编辑成功');
    } else {
      return returnJson(0, '编辑失败');
    }
  }

  //角色节点
  public function node()
  {
    //角色信息
    $rid = input('param.id');
    $roleres = model('role')->where('id', $rid)->find();
    //节点信息
    $list = model('node')->select();
    //控制器信息
    $controller = model('node_control')->select();
    //角色已拥有的权限
    $yesnode = model("role_node")->where("rid", $rid)->column("nid");

    //添加管理员操作
    $this->log->add_log('编辑角色信息', Session::get('user'));
    $this->assign('rid', $rid);
    $this->assign('list', $list);
    $this->assign("yesnode", $yesnode);
    $this->assign("roleres", $roleres);
    $this->assign('controller', $controller);
    return view();
  }

  //更改角色节点
  public function update_node()
  {
    $post = input("post.");
    //删除所有权限
    model("role_node")->where("rid", $post['rid'])->delete();

    //添加新权限
    if (isset($post['nid'])) {
      $nids = array_filter(explode(',', $post['nid']));
      foreach ($nids as $k => $v) {
        $data['rid'] = $post['rid'];
        $data['nid'] = $v;
        model("role_node")->insert($data);
      }
    }

    //编辑角色权限
    $this->log->add_log('编辑角色权限', Session::get('user'));
    $returnArr['code'] = 1;
    $returnArr['msg'] = "提交成功";
    return json($returnArr);
  }
}

?>