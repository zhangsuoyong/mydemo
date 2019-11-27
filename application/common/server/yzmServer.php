<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/17
 * Time: 17:16
 */

namespace app\common\server;

use app\common\exception\ServerException;
use think\Loader;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class yzmServer
{
  public function send_email($email, $code)
  {
    Loader::import('phpmail.PHPMailer');
    Loader::import('phpmail.SMTP');
    Loader::import('phpmail.Exception');
    //示例化PHPMailer核心类
    $mail = new PHPMailer();
    //使用smtp鉴权方式发送邮件，当然你可以选择pop方式 sendmail方式等 本文不做详解
    //可以参考http://phpmailer.github.io/PHPMailer/当中的详细介绍
    $mail->isSMTP();
    //smtp需要鉴权 这个必须是true
    $mail->SMTPAuth = true;
    //链接qq域名邮箱的服务器地址
    $mail->Host = 'smtp.163.com';
    //设置使用ssl加密方式登录鉴权
    $mail->SMTPSecure = 'ssl';
    //设置ssl连接smtp服务器的远程服务器端口号 可选465或587
    $mail->Port = 465;
    //设置发送的邮件的编码 可选GB2312 我喜欢utf-8 据说utf8在某些客户端收信下会乱码
    $mail->CharSet = 'UTF-8';
    //设置发件人姓名（昵称） 任意内容，显示在收件人邮件的发件人邮箱地址前的发件人姓名
    $mail->FromName = 'ctk管理';
    //smtp登录的账号 这里填入字符串格式的qq号即可
    $mail->Username = 'ctk_9999999@163.com';
    //smtp登录的密码 这里填入“独立密码” 若为设置“独立密码”则填入登录qq的密码 建议设置“独立密码”
    $mail->Password = 'Asxh1ctb4ZH8BGu5';
    //设置发件人邮箱地址 这里填入上述提到的“发件人邮箱”
    $mail->From = 'ctk_9999999@163.com';
    //邮件正文是否为html编码 注意此处是一个方法 不再是属性 true或false
    $mail->isHTML(true);
    //设置收件人邮箱地址 该方法有两个参数 第一个参数为收件人邮箱地址 第二参数为给该地址设置的昵称 不同的邮箱系统会自动进行处理变动 这里第二个参数的意义不大
    $mail->addAddress($email);
    //添加该邮件的主题
    $mail->Subject = 'ctk官方邮件';
    //添加邮件正文 上方将isHTML设置成了true，则可以是完整的html字符串 如：使用file_get_contents函数读取本地的html文件
    $mail->Body = "您的验证码为：$code";
    //为该邮件添加附件 该方法也有两个参数 第一个参数为附件存放的目录（相对目录、或绝对目录均可） 第二参数为在邮件附件中该附件的名称
    //发送命令 返回布尔值
    //PS：经过测试，要是收件人不存在，若不出现错误依然返回true 也就是说在发送之前 自己需要些方法实现检测该邮箱是否真实有效
    $status = $mail->send();

    //简单的判断与提示信息
    if ($status) {
      session('user', $email);
      session('code', $code);
      return returnJson(1, '发送成功');
    } else {
      return returnJson(0, '发送失败');
    }
  }

  public function sms($phone)
  {
    session('user', $phone);
    $this->send_sms($phone);
    return returnJson(1, '发送成功');
  }


  //将 xml数据转换为数组格式。
  private function xml_to_array($xml)
  {
    $reg = "/<(\w+)[^>]*>([\\x00-\\xFF]*)<\\/\\1>/";
    if (preg_match_all($reg, $xml, $matches)) {
      $count = count($matches[0]);
      for ($i = 0; $i < $count; $i++) {
        $subxml = $matches[2][$i];
        $key = $matches[1][$i];
        if (preg_match($reg, $subxml)) {
          $arr[$key] = $this->xml_to_array($subxml);
        } else {
          $arr[$key] = $subxml;
        }
      }
    }
    return $arr;
  }


  //防止恶意攻击
  private function sms_safe()
  {
    if ($GLOBALS['ihuyi']['is_open_send_limit'] != 1) {
      return;
    }
    if (!empty($_SESSION['sms_send_black']) && $_SESSION['sms_send_black'] + $GLOBALS['ihuyi']['sms_send_black_time'] > time()) {
      throw new ServerException('操作频繁,请' . ceil(($_SESSION['sms_send_black'] + $GLOBALS['ihuyi']['sms_send_black_time'] - time()) / 60) . '分钟后重试');
    }

    if (empty($_SESSION['sms_send_num'])) {
      $_SESSION['sms_send_num'] = 1;
    }

    if (!empty($_SESSION['sms_send_time']) && $_SESSION['sms_send_time'] + $GLOBALS['ihuyi']['sms_send_time'] > time()) {
      throw new ServerException('操作频繁,请' . ($_SESSION['sms_send_time'] + $GLOBALS['ihuyi']['sms_send_time'] - time()) . '秒后重试');
    }

    if ($_SESSION['sms_send_num'] > $GLOBALS['ihuyi']['sms_send_num']) {
      $_SESSION['sms_send_black'] = time();
      unset($_SESSION['sms_send_num']);
      unset($_SESSION['sms_send_time']);
      throw new ServerException('发送次数超过限制');
    }
  }

  //发送短信验证码
  public function send_sms($mobile, $content='')
  {

    $GLOBALS['ihuyi']['appid'] = 'C06964542';
    $GLOBALS['ihuyi']['appkey'] = '4010fd10c0b127617e9962c9a9f185c1';
    $GLOBALS['ihuyi']['sms_send_time'] = 60;
    $GLOBALS['ihuyi']['sms_send_num'] = 5;
    $GLOBALS['ihuyi']['sms_send_black_time'] = 600;
    $GLOBALS['ihuyi']['url'] = "http://106.ihuyi.cn/webservice/sms.php?method=Submit";
    $GLOBALS['ihuyi']['is_open_send_limit'] = 1;

    header("Content-type:text/html; charset=UTF-8");
    // 短信接口地址
    $target = $GLOBALS['ihuyi']['url'];
    //获取手机号
    $mobile_code = $code = mt_rand(1000, 9999);;
    session('code', $mobile_code);
    if (empty($mobile)) {
      throw new ServerException('手机号码不能为空');
    }

    $preg = "/^1[3456789]\d{9}$/";
    if (!preg_match($preg, $mobile)) {
      throw new ServerException('手机号码不正确');
    }


    if (!$content) {
      $content = "您的验证码是：" . $mobile_code . "。请不要把验证码泄露给其他人。";
    }

    $post_data = "account=" . $GLOBALS['ihuyi']['appid'] . "&password=" . $GLOBALS['ihuyi']['appkey'] . "&mobile=" . $mobile . "&content=" . rawurlencode($content);
    $gets = $this->xml_to_array(post($post_data, $target));
    if(!key_exists ('sms_send_num',$_SESSION)){
    	 $_SESSION['sms_send_num']=0;
    }
    
    if ($gets['SubmitResult']['code'] == 2) {
      $_SESSION['mobile'] = $mobile;
      $_SESSION['mobile_code'] = $mobile_code;
      $_SESSION['sms_send_time'] = time();
      $_SESSION['sms_send_num'] += 1;
    }
    return $gets['SubmitResult']['msg'];
  }

  public function sendSmsMatchFinish($phone,$name)
  {
    return $this->send_sms($phone,"尊敬的{$name}，您有匹配好的订单，请及时处理。");
  }
}