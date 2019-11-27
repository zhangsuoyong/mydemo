<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/20
 * Time: 11:16
 */

namespace app\tkfadmin\controller;

use app\common\exception\ServerException;
use app\common\model\AdminLog;

class Information extends Base
{

  protected $log;

  public function __construct(AdminLog $log)
  {
    parent::__construct();
    $this->log = $log;
  }

  //公告列表
  public function index()
  {
    $where = [];
    if (input('keyword')) {
      $keyword = input('keyword');
      $where['title'] = ['like', "%$keyword%"];
      $this->assign('keyword', input('keyword'));
    }
    $list = model('information')->where($where)->order('create_time desc')->paginate(15, false, ['type' => 'Bootstrap']);

    $this->assign('list', $list);

    return view('');
  }

  //添加公告
  public function notice_add()
  {
    return $this->fetch();
  }

  //添加公告
  public function notice_insert()
  {
    //判定
    if (!request()->isPost()) {
      return returnJson(0, '非法入侵');
    }
    $post = input('post.');
    unset($post['file']);
    //判断公告是否存在
    if (model('information')->where('title', $post['title'])->find()) {
      return returnJson(0, '该公告已存在');
    }

    $this->log->add_log('添加公告信息');
    //添加公告
    if(input('post.img')){
       $post['picname']=input('img');
       unset($post['img']);
    }else{
      throw new ServerException('请选择封面图');
    }


    if (model('information')->insert($post)) {
      return returnJson(1, '添加成功');
    } else {
      return returnJson(0, '添加失败');
    }
  }

  //删除公告
  public function notice_del()
  {
    //判定
    if (!request()->isPost()) {
      return returnJson(0, '非法入侵');
    }
    $post = input('post.');
    $this->log->add_log('删除公告');
    if (model('information')->where('id', $post['id'])->delete()) {
      return returnJson(1, '删除成功');
    } else {
      return returnJson(0, '添加失败');
    }
  }

  //编辑
  public function notice_edit()
  {
    //接受值
    $post = input('post.');
    //查询信息
    $list = model('information')->where('id', $post['id'])->find();
    //加载
    $this->assign('list', $list);
    return $this->fetch();

  }

  //编辑
  public function notice_update()
  {
    if (!request()->isPost()) {
      return returnJson(0, '非法入侵');
    }
    //接受值
    $post = input('post.');
    unset($post['file']);

    if(input('img')){
      $post['picname']=$post['img'];
    }
    unset($post['img']);
    $this->log->add_log('编辑公告');
    if (model('information')->where('id', $post['id'])->update($post)) {
      return returnJson(1, '编辑成功');
    } else {
      return returnJson(0, '编辑失败');
    }
  }

  //更改公告状态
  public function state()
  {
    if (!request()->isPost()) {
      return returnJson(0, '非法入侵');
    }
    $id = input('post.id');
    $state = input('post.state');
    if (model('information')->where('id', $id)->setField('state', $state)) {
      return returnJson(1, '编辑成功');
    } else {
      return returnJson(0, '编辑失败');
    }
  }

  //上传图片
  public function add_pic()
  {
    $img = up('file', 'information');
    if ($img['code'] == 1) {
      $data = '/uploads/information/' . $img['msg'];

      return '{"code":1,"msg":"成功上传","data":{"src":"' . $data . '"}}';

    } else {
      $arr['code'] = 1;
      $arr['msg'] = '上传失败';
      return json($arr);
    }
  }

  public function add_video()
  {
    $myfile = $_FILES["file"];
    $tmp = $myfile['tmp_name'];
    $a = 'uploads/' . time() . '.mp4';
    $path = ROOT_PATH . 'public/' . $a;

    if (!move_uploaded_file($tmp, $path)) die('视频上传失败');

    $path = "http://" . $_SERVER['HTTP_HOST'] . "/uploads/" . time() . '.mp4';
    $dataArr['data']['src'] = $path;
    $dataArr['data']['title'] = "成功";
    exit(json_encode($dataArr));
  }
}