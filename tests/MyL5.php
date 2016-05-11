<?php
/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 2016/5/11 0011
 * Time: 17:25
 */

namespace Level5Test;


use Level5\L5;


class MyL5 extends L5 {

    protected function getRemoteConf($iSid) {
        if ($iSid != 9527) {
            return[];
        }
        return [
            [ip2long('127.0.0.1'), 9527, 5]
            ,[ip2long('192.168.1.200'), 9527,3]
            ,[ip2long('192.168.1.200'), 9528,4]
        ];
    }

    public function log($msg) {
        $msg = trim($msg)."\n";
        file_put_contents(__DIR__.'/log.txt', date('Y-m-d H:i:s').' '.$msg, FILE_APPEND);
    }

}