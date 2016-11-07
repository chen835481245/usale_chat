<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/7/29
 * Time: 10:35
 * @param $cmd
 * @param $data
 */
namespace Applications\Chat;
use Applications\Model\Chat;
class Route
{
    public static function run($client_id, $data)
    {
        $cmd=$data['cmd'];
        $rout=array(
            'login'=>array('c'=>'Chat','a'=>'login'),
        );
        if(!isset($rout[$cmd])){
            return ;
        }
        switch($rout[$cmd]['c']){
            case 'Chat':
                $obj=new Chat();
                break;
        }
        return $obj->$rout[$cmd]['a']($client_id,$data);
    }
}
