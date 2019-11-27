<?php


namespace app\common\model;


use think\Model;

class Chat extends Model
{
  /**
   * [addMessage 添加一条信息]
   * @param [type] $transactionId [交易订单ID]
   * @param [type] $uid           [用户ID]
   * @param [type] $content       [文字信息]
   * @param [type] $img           [图片信息]
   * @param [type] $type          [类型;1文字，2图片，3申诉]
   */
  public function addMessage($transactionId, $uid, $content, $img, $type) {
    $chat['transaction_id'] = $transactionId;
    $chat['uid'] = $uid;
    $chat['content'] = $content;
    $chat['img'] = $img;
    $chat['type'] = $type;
    $this->insert($chat);
  }

  public function getTypeAttr($value) {
    $status = ['1' => '文字信息', '2' => '图片信息', '3' => "申诉信息"];
    return ['value' => $value, 'msg' => $status[$value]];
  }

  public function getCreateTimeAttr($time) {
    return $time;
  }

  public function getUserInfo($uid)
  {
   $user= User::get($uid);
   $info['user']=$user['user'];
   $info['id']=$user['id'];
   $info['nickname']=$user['nickname'];
   $info['picpath']=$user['picpath'];
   $info['picname']=$user['picname'];
   return $info;
  }
}