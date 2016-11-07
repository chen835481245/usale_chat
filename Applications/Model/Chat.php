<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/7/29
 * Time: 10:19
 */
namespace Applications\Model;
use GatewayWorker\Lib\Gateway;
use GatewayWorker\Lib\HttpRequest;
use GatewayWorker\Lib\Model;

class Chat
{
    /**
     * 登录
     * @param $client_id
     * @param $message_data
     * @return bool|void
     * @throws \Exception
     *{"cmd":"login","account":"13510000001","password":"e10adc3949ba59abbe56e057f20f883e"}
     */
    public function login($client_id,$message_data)
    {
        $db=new Model();
        if (empty($message_data['account']) || empty($message_data['password'])) {
            return Gateway::closeCurrentClient();
        }
        $userData = $db->table('store_user')->getField(array('user_id'), array('account' => $message_data['account'], 'password' => $message_data['password']));
        if (empty($userData)) {
            return Gateway::closeCurrentClient();
        }
        //绑定对应关系
        Gateway::bindUid($client_id, $userData['user_id']);
        $_SESSION['user_id'] = $userData['user_id'];
        $new_message = array('return_code' => 10,'return_data'=>$userData);
        Gateway::sendToCurrentClient(json_encode($new_message));

        return;
    }

    /**
     * 客户端向微信发送
     * @param $client_id
     * @param $message_data
     * @return bool|void
     * @throws \Exception
     * {"cmd":"say","content":"hello","openid":"oPgUYuHHiBl0FL-u9tQZ8ft8Ai1s","media_id":"","msg_type":"text"}
     */
    public function say($client_id,$message_data)
    {
        $db=new Model();
        if (!isset($_SESSION['user_id'])) {
            Gateway::sendToCurrentClient(json_encode(array('return_code' => -200)));
            return Gateway::closeCurrentClient();
        }
        $appid=$db->table('wx_members')->getField('appid',array('openid'=>$message_data['openid']));
        $access_token=$db->table('wx_app')->getField('authorizer_access_token',array('authorizer_appid'=>$appid));

        $httpObj = new HttpRequest();
        $url="https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=".$access_token;
        $new_message['touser']=$message_data['openid'];
        $new_message['msgtype']=$message_data['msg_type'];
        switch($message_data['msg_type'])
        {
            case 'text':
                $new_message['text']['content']=$message_data['content'];
                break;
            case 'image':
                $new_message['image']['media_id']=$message_data['media_id'];
                break;
            case 'voice':
                $new_message['voice']['media_id']=$message_data['media_id'];
                break;
            case 'video':
                $new_message['video']=array(
                    'media_id'=>$message_data['media_id'],
                    'thumb_media_id'=>$message_data['thumb_media_id'],
                    'title'=>$message_data['title'],
                    'description'=>$message_data['description'],
                );
                break;
            case 'news':
                //"articles": [{"title":"Happy Day", "description":"Is Really A Happy Day", "url":"URL", "picurl":"PIC_URL"},{"title":"Happy Day", "description":"Is Really A Happy Day", "url":"URL", "picurl":"PIC_URL"}]
                $new_message['articles']=$message_data['articles'];
                break;
        }
        $httpObj->getHttpResponsePOST($url, json_encode($new_message), true);
        Gateway::sendToCurrentClient(json_encode(array('return_code' => 0)));
        return;
    }

    /**
     * 微信发消息给客户端
     * @param $client_id
     * @param $message_data
     * @return bool|void
     * @throws \Exception
     */
    public function chat($client_id,$message_data)
    {
        $db=new Model();
        if (md5($message_data['create_time'] . 'usale') != $message_data['sign']) {
            return Gateway::closeCurrentClient();
        }
        $user_id = $db->table('wx_members')->getField('user_id', array('openid' => $message_data['openid']));
        if (!$user_id) {
            return Gateway::closeCurrentClient();
        }
        unset($message_data['sign']);
        if (Gateway::isUidOnline($user_id)) {//如果在线
            Gateway::sendToUid($user_id, json_encode($message_data));
            $status = 1;
        } else {
            $status = 0;
        }
        $content=isset($message_data['content'])?$message_data['content']:null;
        $media_id=isset($message_data['media_id'])?$message_data['media_id']:null;
        $thumb_media_id=isset($message_data['thumb_media_id'])?$message_data['thumb_media_id']:null;
        $row = array('msg_type' => $message_data['msg_type'], 'openid' => $message_data['openid'],
            'user_id' => $user_id,'content' => $content,'media_id'=>$media_id, 'from_type' => 0,
            'create_time' => time(), 'status' => $status, 'thumb_media_id'=>$thumb_media_id
        );
        $db->table('chat_message')->create($row);
        return;
    }
    public function addMember($client_id,$message_data)
    {
        if (md5($message_data['create_time'] . 'usale') != $message_data['sign']) {
            return Gateway::closeCurrentClient();
        }
        $user_id = $message_data['user_id'];
        unset($message_data['sign']);
        if (Gateway::isUidOnline($user_id)) {//如果在线
            Gateway::sendToUid($user_id, json_encode($message_data));
        }
        return;
    }


}