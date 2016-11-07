<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2016/7/29
 * Time: 17:55
 */
namespace Applications\Model;
class PublicFunction
{
    public static function saveEmoji($text)
    {
        $tmpStr = json_encode($text); //暴露出unicode
        $tmpStr = preg_replace("#(\\\ue[0-9a-f]{3})#ie","addslashes('\\1')",$tmpStr); //将emoji的unicode留下，其他不动
        $text = json_decode($tmpStr);
        return $text;
    }
    public static function sendEmoji($text)
    {
        $text = preg_replace("#\\\u([0-9a-f]+)#ie","iconv('UCS-2','UTF-8', pack('H4', '\\1'))",$text); //对emoji unicode进行二进制pack并转utf8
        return $text;
    }
    public static function filterEmoji($str)
    {
        $str = preg_replace_callback(
            '/./u',
            function (array $match) {
                return strlen($match[0]) >= 4 ? '' : $match[0];
            },
            $str);

        return $str;
    }

}