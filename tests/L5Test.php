<?php
/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 2016/5/10 0010
 * Time: 18:35
 */



namespace Level5Test;

use Level5\ShmAccessor;
use PHPUnit_Framework_TestCase;




class L5Test extends PHPUnit_Framework_TestCase{
    public function testInitBlocks() {
        $iKey = 0x00002537;
        $oShmAccessor = new ShmAccessor($iKey, 4194432);
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

        //$this->assertEquals(true, $oShmAccessor->delete());
    }
}