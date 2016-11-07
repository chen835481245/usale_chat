<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

/**
 * 聊天主逻辑
 * 主要是处理 onMessage onClose
 */
use Applications\Model\PublicFunction;
use Applications\Model\Push;
use \GatewayWorker\Lib\Gateway;
use GatewayWorker\Lib\HttpRequest;
use GatewayWorker\Lib\Model;

class Events
{
    /**
     * 有消息时
     * @param int $client_id
     * @param mixed $message
     */
    public static function onMessage($client_id, $message)
    {
        $myUrl = 'http://usale.19baba,com';
        // debug
        echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id session:" . json_encode($_SESSION) . " onMessage:" . $message . "\n";

        // 客户端传递的是json数据
        $message_data = json_decode($message, true);
        if (!$message_data) {
            return Gateway::closeCurrentClient();
        }

        $db = new Model();
        // 根据类型执行不同的业务
        switch ($message_data['cmd']) {
            // 客户端回应服务端的心跳
            case 'p'://{"cmd":"p"}
                return;
            // 客户端登录 message格式: {"cmd":"login","account":"18888888880","password":"e10adc3949ba59abbe56e057f20f883e"}
            case 'login':
                if (empty($message_data['account']) || empty($message_data['password'])) {
                    return Gateway::closeCurrentClient();
                }
                $userData = $db->table('store_user')->getField(array('user_id', 'channel_id', 'is_boss', 'nickname'),
                    array('account' => $message_data['account'], 'password' => $message_data['password']));
                if (empty($userData)) {
                    return Gateway::closeCurrentClient();
                }

                //绑定对应关系
                Gateway::bindUid($client_id, $userData['user_id']);
                $_SESSION['user_id'] = $userData['user_id'];
                if ($userData['is_boss'] == 1) {
                    $qrCode = $db->table('admin_channel')->getField('qr_code', array('id' => $userData['channel_id']));
                    $qrCodeUrl = $myUrl . '/' . $qrCode;
                    $urlContent = $myUrl . '/registerStoreUser/' . $userData['channel_id'] . '.html';
                } else {
                    $urlContent = '';
                    $qrCodeUrl = '';
                }
                $storeData = $db->table('store_info')->getField(array('store_name', 'store_id'),
                    array('user_id' => $userData['user_id']));
                $userData['qrCodeUrl'] = $qrCodeUrl;
                $userData['urlContent'] = $urlContent;
                $userData = array_merge($userData, $storeData);
                $new_message = array('code' => 0, 'cmd' => 'login', 'data' => $userData);
                Gateway::sendToCurrentClient(json_encode($new_message));

                return;
            //app to wx {"cmd":"say","content":"你好","openid":"oPgUYuHHiBl0FL-u9tQZ8ft8Ai1s","msg_type":"text","media_id":"4ZTfkoBghwUz8l2ZeMOH3yC8-fOcQTLVfjwoBYYpQkH2y9Y4XtJj9XApXVzx_cpc","msg_id":1}
            case 'say':
                // 非法请求
                if (!isset($_SESSION['user_id'])) {
                    Gateway::sendToCurrentClient(json_encode(array('code' => -200)));
                    return Gateway::closeCurrentClient();
                }
                $appid = $db->table('wx_members')->getField('appid', array('openid' => $message_data['openid']));
                $access_token = $db->table('wx_app')->getField('authorizer_access_token', array('authorizer_appid' => $appid));

                $httpObj = new HttpRequest();
                $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . $access_token;
                $new_message['touser'] = $message_data['openid'];
                $new_message['msgtype'] = $message_data['msg_type'];
                switch ($message_data['msg_type']) {
                    case 'text':
                        $new_message['text']['content'] = $message_data['content'];
                        break;
                    case 'image':
                        $new_message['image']['media_id'] = $message_data['media_id'];
                        break;
                    case 'voice':
                        $new_message['voice']['media_id'] = $message_data['media_id'];
                        break;
                    case 'video':
                        $new_message['video'] = array(
                            'media_id' => $message_data['media_id'],
                            'thumb_media_id' => $message_data['thumb_media_id'],
                            'title' => $message_data['title'],
                            'description' => $message_data['description'],
                        );
                        break;
                    case 'news':
                        //"articles": [{"title":"Happy Day", "description":"Is Really A Happy Day", "url":"URL", "picurl":"PIC_URL"},{"title":"Happy Day", "description":"Is Really A Happy Day", "url":"URL", "picurl":"PIC_URL"}]
                        $new_message['articles'] = json_decode($message_data['articles'],true);
                        break;
                }
                $message_data['msg_id']=isset($message_data['msg_id'])?$message_data['msg_id']:1;
                $httpObj->getHttpResponsePOST($url, urldecode(json_encode($new_message, JSON_UNESCAPED_UNICODE)), true);
                Gateway::sendToCurrentClient(json_encode(array('code' => 0, 'cmd' => 'say', 'msg_id' => $message_data['msg_id'])));
                $content = isset($message_data['content']) ? $message_data['content'] : null;
                $media_id = isset($message_data['media_id']) ? $message_data['media_id'] : null;
                $thumb_media_id = isset($message_data['thumb_media_id']) ? $message_data['thumb_media_id'] : null;
                $row = array('msg_type' => $message_data['msg_type'], 'openid' => $message_data['openid'],
                    'user_id' => $_SESSION['user_id'], 'content' => $content, 'media_id' => $media_id, 'from_type' => 1,
                    'create_time' => time(), 'status' => 1, 'thumb_media_id' => $thumb_media_id
                );
                $db->table('chat_message')->create($row);
                return;
            //wx to app
            case 'chat':
                if (md5($message_data['create_time'] . 'usale') != $message_data['sign']) {
                    return Gateway::closeCurrentClient();
                }
                $user_id = $db->table('wx_members')->getField('user_id', array('openid' => $message_data['openid']));
                if (!$user_id) {
                    return Gateway::closeCurrentClient();
                }
                $message_data['cmd'] = 'chat';
                unset($message_data['sign']);
                if (Gateway::isUidOnline($user_id)) {//如果在线
                    Gateway::sendToUid($user_id, urldecode(json_encode($message_data, JSON_UNESCAPED_UNICODE)));
                    $status = 1;
                } else {
                    //推送通知
                    $pushID=$db->table('store_user')->getField('push_id',array('user_id'=>$user_id));
                    if(!empty($pushID)){
                        $pushObj=new Push();
                        $content=$message_data['msg_type']=='text'?PublicFunction::filterEmoji($message_data['content']):'';
                        $pushObj->pushOne($pushID,$content,'你有新消息',array('push_type'=>'chat','openid'=>$message_data['openid']));
                    }
                    $status = 0;
                }
                $content = isset($message_data['content']) ? $message_data['content'] : null;
                if (isset($message_data['content'])) {
                    $content = PublicFunction::saveEmoji($content);
                }
                $media_id = isset($message_data['media_id']) ? $message_data['media_id'] : null;
                $url = isset($message_data['url']) ? $message_data['url'] : null;
                $thumb_media_id = isset($message_data['thumb_media_id']) ? $message_data['thumb_media_id'] : null;
                $row = array('msg_type' => $message_data['msg_type'], 'openid' => $message_data['openid'],
                    'user_id' => $user_id, 'content' => $content, 'media_id' => $media_id, 'from_type' => 0,
                    'create_time' => time(), 'status' => $status, 'thumb_media_id' => $thumb_media_id, 'url' => $url
                );
                $db->table('chat_message')->create($row);
                return;
            //群发 {"cmd":"groupSend","content":"groupSend","msg_type":"text","msg_id":1,"tag_id":0}
            case 'groupSend':
                // 非法请求
                if (!isset($_SESSION['user_id']) || empty($message_data['tag_id'])) {
                    Gateway::sendToCurrentClient(json_encode(array('code' => -200)));
                    return Gateway::closeCurrentClient();
                }

                if ($message_data['tag_id'] > 0) {
                    if (!$db->exist('wx_tag', array('tag_id' => $message_data['tag_id'], 'user_id' => $_SESSION['user_id']))) {
                        Gateway::sendToCurrentClient(json_encode(array('code' => -201, 'msg' => '此标签不存在', 'cmd' => 'groupSend')));
                        return;
                    }
                    $memberIdSql = "select member_id ,member_id from wx_member_tag where tag_id='" . $message_data['tag_id'] . "'";
                    $memberIdArr = $db->getArray($memberIdSql, 1);
                    if (empty($memberIdArr)) {
                        Gateway::sendToCurrentClient(json_encode(array('code' => -202, 'msg' => '未找到推送人员', 'cmd' => 'groupSend')));
                        return;
                    }
                    $memberIdArr = array_values($memberIdArr);
                    $memberIds = implode(',', $memberIdArr);
                    $openidSql = "select member_id,openid from wx_members where member_id in(" . $memberIds . ")
                    and status=1 and is_arrive=1";
                }
                if($message_data['tag_id']==-1){
                    $openidSql = "select member_id,openid from wx_members where user_id='".$_SESSION['user_id']."'
                    and status=1 and is_arrive=1";
                }

                $openidArr = $db->getArray($openidSql, 1);
                if (empty($openidArr)) {
                    Gateway::sendToCurrentClient(json_encode(array('code' => 0, 'cmd' => 'groupSend', 'msg_id' => $message_data['msg_id'])));
                    return;
                }
                foreach ($openidArr as $openidVal) {
                    $openid = $openidVal;
                    break;
                }
                $httpObj = new HttpRequest();
                $appid = $db->table('wx_members')->getField('appid', array('openid' => $openid));
                $access_token = $db->table('wx_app')->getField('authorizer_access_token', array('authorizer_appid' => $appid));

                $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=" . $access_token;
                $new_message['msgtype'] = $message_data['msg_type'];
                switch ($message_data['msg_type']) {
                    case 'text':
                        $new_message['text']['content'] = $message_data['content'];
                        break;
                    case 'image':
                        $new_message['image']['media_id'] = $message_data['media_id'];
                        break;
                    case 'voice':
                        $new_message['voice']['media_id'] = $message_data['media_id'];
                        break;
                    case 'video':
                        $new_message['video'] = array(
                            'media_id' => $message_data['media_id'],
                            'thumb_media_id' => $message_data['thumb_media_id'],
                            'title' => $message_data['title'],
                            'description' => $message_data['description'],
                        );
                        break;
                    case 'news':
                        //"articles": [{"title":"Happy Day", "description":"Is Really A Happy Day", "url":"URL", "picurl":"PIC_URL"},{"title":"Happy Day", "description":"Is Really A Happy Day", "url":"URL", "picurl":"PIC_URL"}]
                        $new_message['articles'] = $message_data['articles'];
                        break;
                }
                foreach ($openidArr as $openidVal) {
                    $new_message['touser'] = $openidVal;
                    $httpObj->getHttpResponsePOST($url, urldecode(json_encode($new_message, JSON_UNESCAPED_UNICODE)), true);
                }
                Gateway::sendToCurrentClient(json_encode(array('code' => 0, 'cmd' => 'groupSend', 'msg_id' => $message_data['msg_id'])));
                return;
            case 'addMember'://新增关注时推送
                if (md5($message_data['create_time'] . 'usale') != $message_data['sign']) {
                    return Gateway::closeCurrentClient();
                }
                $user_id = $message_data['user_id'];
                unset($message_data['sign']);
                if (Gateway::isUidOnline($user_id)) {//如果在线
                    Gateway::sendToUid($user_id, json_encode($message_data));
                }
                return;
            default :
                return Gateway::closeCurrentClient();
        }
    }

    /**
     * 当客户端断开连接时
     * @param integer $client_id 客户端id
     */
    public static function onClose($client_id)
    {
        // debug
        echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$client_id onClose:''\n";
    }

}
