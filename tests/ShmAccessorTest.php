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




class ShmAccessorTest extends PHPUnit_Framework_TestCase{
    public function testShmAccessor() {
        $iKey = 0x00002537;
        $sa = new ShmAccessor($iKey, 1024);

        $this->assertInstanceOf('Level5\\ShmAccessor', $sa);

        $in = "abc";
        $inlen = strlen($in);

        $this->assertEquals($inlen, $sa->write($in, 128));

        $out = $sa->read(128, $inlen);

        $this->assertEquals($in, $out);

        $this->assertEquals(true, $sa->delete());
    }

    public function testMaxAddr() {
        $iKey = 0x00002537;
        $sa = new ShmAccessor($iKey, 1024);
        $this->assertEquals(2, $sa->write('abcd', 1022));
        $this->assertEquals(4, $sa->write('abcd', 1020));
        $this->assertEquals('abcd', $sa->read(1020, 4));
        $msg='';
        try{
            $sa->read(1024, 1);
        } catch (\Exception $e) {
            $msg = $e->getMessage();
        }
        $pos = strpos($msg, 'count is out of range');
        $this->assertNotEquals(false, $pos);
        $this->assertEquals(true, $sa->delete());
    }
}