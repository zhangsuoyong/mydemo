<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/20
 * Time: 17:51
 */

namespace app\tkfadmin\controller;


use app\common\exception\ServerException;
use app\common\model\AdminLog;
use app\common\model\System_config;

class SystemConfig extends Base
{
  public function index()
  {
    $model = new System_config();
    $where='class is null';
    $list = $model->getlist($where);
    return view('index', ['list' => $list]);
  }

  //编辑单个参数值
  public function update()
  {
    if (!request()->isPost()) {
      return returnJson(0, '非法入侵');
    }
    //接受传值
    $post = input('post.');
    $where=['name' => $post['name']];
    if(input('post.class')){
      $where['class']=input('post.class');
    }
    //判断是否设置
    $config = System_config::get($where);
    if ($config) {
      AdminLog::add_log("更改参数配置:{$config['msg']}为 {$post['value']}");
      if ($config->save($post)) {
        return returnJson(1, '设置成功');
      } else {
        return returnJson(0, '设置失败');
      }
    }
  }

  public function rule()
  {
    if (!input('name')) {
      throw new ServerException('参数错误');
    }
    $list = System_config::get(['name' => input('name')]);
    return view('rule', ['list' => $list]);
  }

  public function level()
  {

    $model = new System_config();
    $where['class']=['in',"(zt_bl,zt_ds,zt_rs,zt_vip,zt_jj,zt_bl,)"];
    $list = $model->getlist($where);
    return view('level',['list' => $list]);
  }
}