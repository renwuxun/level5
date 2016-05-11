纯php版L5

依赖: shmop、semaphore

/**
 * @package Level5
 *
 * @dependent shmop,semaphore
 *
 * 主治:
 * 1、名字服务
 * 2、负载均衡
 * 3、故障容错
 * 4、过载保护
 *
 *
 * 内存分布图：
 * |-----------------------0
 * |管理数据区
 * |-----------------------128
 * |sid对应Block地址区
 * |-----------------------128+65536*4
 * |Block数据区
 * |-----------------------128+65536*4+65536*12*5(估计平均每个sid有5个ip:port block)
 */


服用方法：

<?php

use Level5\L5;
use Level5\ShmAccessor;

class MyL5 extends L5 {
	// 根据sid取远程取对应的ip、port、weight配置
	// 约束：
	//     sid    uint16_t
	//     ip     int32_t
	//     port   uint16_t
	//     weight uint8_t
    protected function getRemoteConf($iSid) {
        return [
            [ip2long('127.0.0.1'), 9527, 3]
            ,[ip2long('192.168.1.200'), 9527,1]
            ,[ip2long('192.168.1.200'), 9528,2]
        ];
    }

    // 根据自己的情况重载
    public function log($msg) {
        $msg = trim($msg)."\n";
        file_put_contents(__DIR__.'/log.txt', date('Y-m-d H:i:s').' '.$msg, FILE_APPEND);
    }

}

// 首次运行需要10秒左右时间初始化共享内存，具体情况由硬件情况决定
$iKey = 0x00002537;
$oShmAccessor = new ShmAccessor($iKey, 128+65536*4+65536*12*5);
$testl5 = new MyL5($oShmAccessor, 'Level5\\Block');

$row = $testl5->getRoute(9527);
echo long2ip($row[0]),':',$row[1],"\n";

$row = $testl5->getRoute(9527);
echo long2ip($row[0]),':',$row[1],"\n";
$row = $testl5->getRoute(9527);
echo long2ip($row[0]),':',$row[1],"\n";
$row = $testl5->getRoute(9527);
echo long2ip($row[0]),':',$row[1],"\n";
$row = $testl5->getRoute(9527);
echo long2ip($row[0]),':',$row[1],"\n";
$row = $testl5->getRoute(9527);
echo long2ip($row[0]),':',$row[1],"\n";
$row = $testl5->getRoute(9527);
echo long2ip($row[0]),':',$row[1],"\n";
$row = $testl5->getRoute(9527);



//@todo: 故障转移、过载保护