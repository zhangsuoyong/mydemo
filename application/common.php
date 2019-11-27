<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------
date_default_timezone_set('PRC'); //设置中国时区
// 应用公共文件


function returnJson($code, $msg = null, $data = null)
{
  return json(['code' => $code, 'msg' => $msg, 'data' => $data]);
}

function encrypt($key)
{
  return md5($key . md5('tkf'));
}


function getRandCode($num = 6)
{
  $array = array('A', 'B', 'C', 'D', 'E', 'F', 'a', 'b', 'c', 'd', 'e', 'f', '1', '2', '3', '4', '5', '6', '7', '8', '9');
  $tmpstr = '';
  $max = count($array);
  for ($i = 1; $i <= $num; $i++) {
    $key = rand(0, $max - 1);
    $tmpstr .= $array[$key];
  }
  return $tmpstr;
}


function vender($path = '')
{
  //允许两种路径表达方式
  $path = str_replace('.', '/', $path);
  //若省略文件文件后缀就给补上
  if (!strpos($path, '.php')) {
    $path .= '.php';
  }
  //这个路径是要看你们自己定的来改
  require_once "../extend/{$path}";
}

function up($pic, $path = "", $ya = false, $tianchong = 1, $ext = false)
{
  // 获取表单上传文件 例如上传了001.jpg
  $file = request()->file($pic);
  // 移动到框架应用根目录/public/uploads/ 目录下
  if ($file) {
    if ($ext) {
      $info = $file->validate($ext)->rule('uniqid')->move(ROOT_PATH . 'public/uploads/' . $path);
    } else {
      $info = $file->validate(['ext' => 'jpeg,jpg,png,gif'])->rule('uniqid')->move(ROOT_PATH . 'public/uploads/' . $path);
    }
    if ($info) {
      if ($ya) {
        //图片压缩
        $image = \think\Image::open($info->getpathName());
        if ($tianchong == 1) {
          //填充
          $image->thumb(375, 375, \think\Image::THUMB_FILLED)->save(ROOT_PATH . 'public/uploads/' . $path . "/x_" . $info->getFilename());
        } else {
          //不填充 等比缩放
          $image->thumb(375, 375, \think\Image::THUMB_SCALING)->save(ROOT_PATH . 'public/uploads/' . $path . "/x_" . $info->getFilename());
        }
      }
      $returnArr['code'] = 1;
      $returnArr['msg'] = $info->getFilename();
      return $returnArr;
    } else {
      // 上传失败获取错误信息
      $returnArr['code'] = 0;
      $returnArr['msg'] = $file->getError();
      return $returnArr;
    }
  } else {
    $returnArr['code'] = 0;
    $returnArr['msg'] = "未上传文件";
    return $returnArr;
  }
}


function isEmail($email)
{
  $mode = '/\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*/';
  if (preg_match($mode, $email)) {
    return true;
  } else {
    return false;
  }
}


function RSA($data, $type = "encode")
{
  $pub = "-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDT2F0SpH2SiEW8I/t0vozvckiJ
GziCe2LCxuACxJQX/TQyg18u7/Q8Uu6aCg32P5B3dfgw8kARnbJKcJjyA4oeDKiP
l2F5NXiU1OhTmN996wny+JE33QPZ4DU2Bl0rVVuK1GgWNZQI9c3OwR3EcautFdVt
2HWj3PGI6jxfHtjDZwIDAQAB
-----END PUBLIC KEY-----
";
  $pri = "-----BEGIN PRIVATE KEY-----
MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBANPYXRKkfZKIRbwj
+3S+jO9ySIkbOIJ7YsLG4ALElBf9NDKDXy7v9DxS7poKDfY/kHd1+DDyQBGdskpw
mPIDih4MqI+XYXk1eJTU6FOY333rCfL4kTfdA9ngNTYGXStVW4rUaBY1lAj1zc7B
HcRxq60V1W3YdaPc8YjqPF8e2MNnAgMBAAECgYBpTA8fGBGuhvuag4wWQCyPTA/P
zm7tNGUniXCJD6rIrbuHLBNgojaU0Wf1uu+rqXamWkXOFmtQFkErjQIIsUextboz
AVuFaEN9SjHceKLEcgU0UGhaO+YDnSl58LnLFQoDCGhKtYCwyGXPKQMx/JXwfZ2S
g1OeLXlonkeO3p8eEQJBAPxYVQQjAuNoDrzGr7Ss+vcS6/qR8LKjR+qxhMvX5/0k
cMebf4u9wpM6mK6yjtCQIToD3GRtQywJyA6n8stMQ68CQQDW6dzCrO1Z+Kb+Xn5Q
i2bR+Hh/69iVIwwrbs+J0Vx4b37wFKMQ8vMMvpxlDxrBBdDw3y+64vM/s+R56M1B
VxHJAkBE5EixjG1pcCs11nh5tw/9DCloixdPbcxggn5iuFsZfS1dEVLM782DLGgq
qYzb2712fT9aG4pPJ4x6k9dxMSz5AkAIDcuQIBrk/ESF09S4AAFibQVXBeef7yhN
mGF+sLHecY84QA28XN5u49XIk8BU63rhC/wl7Mtg38T4LJlEkZbBAkEAqngYaHGj
WoMihFHksNGhZlpi6Dq35hz2J0WRDQaCwAHgEAuYNSIwhFsjWBozGUYUoG1vUZcK
YeCH3Eif6kNmMA==
-----END PRIVATE KEY-----";
  if (empty($data)) {
    return returnJson(0, "加密解密数据不能为空");
  }
  if ($type == "encode") {
    $public_is_use = openssl_pkey_get_public($pub);
    if (!$public_is_use) {
      return returnJson(0, "公钥不可用");
    }
    openssl_public_encrypt($data, $crypted, $public_is_use);
    $crypted = base64_encode($crypted);
    
    return ['code' => 1, 'msg' => $crypted];
  }
  if ($type == "decode") {
    $private_is_use = openssl_pkey_get_private($pri);
    if (!$private_is_use) {
      return returnJson(0, "私钥不可用");
    }
    openssl_private_decrypt(base64_decode($data), $decrypted, $private_is_use);
    return ['code' => 1, 'msg' => $decrypted];
  }
}

function post($curlPost, $url)
{
  $curl = curl_init();
  curl_setopt($curl, CURLOPT_URL, $url);
  curl_setopt($curl, CURLOPT_HEADER, false);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_NOBODY, true);
  curl_setopt($curl, CURLOPT_POST, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS, $curlPost);
  $return_str = curl_exec($curl);
  curl_close($curl);
  return $return_str;
}

//判断是否是命令行执行
function is_cli(){
    return preg_match("/cli/i", php_sapi_name()) ? true : false;
}
