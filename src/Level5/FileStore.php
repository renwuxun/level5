<?php

/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 2017/2/10 0010
 * Time: 14:18
 */
class Level5_FileStore implements Level5_Store {

    /**
     * @var string
     */
    protected $filename;

    /**
     * @var resource
     */
    protected $fp;

    /**
     * Level5_FileStore constructor.
     * 准备工作：
     * 65536*(4+4)+4+4+65536*16*5 = 5767176
     * dd if=/dev/zero of=./data bs=5767176 count=1
     */
    public function __construct() {
        $this->filename = __DIR__.'/data';
        $this->open();
    }

    public function __destruct() {
        $this->close();
    }

    public function open() {
        $this->fp = fopen($this->filename, 'r+');
        return is_resource($this->fp);
    }

    /**
     * @return void
     */
    public function close() {
        if (is_resource($this->fp)) {
            fclose($this->fp);
        }
    }

    /**
     * @param $iOffset
     * @param $iLength
     * @return string
     *
     * @throws Exception
     */
    public function read($iOffset, $iLength) {
        if (-1 == fseek($this->fp, $iOffset)) {
            throw new Exception('fseek error on file: '.$this->filename);
        }
        return fread($this->fp, $iLength);
    }

    /**
     * @param $sData
     * @param $iOffset
     * @return int
     *
     * @throws Exception
     */
    public function write($sData, $iOffset) {
        if (-1 == fseek($this->fp, $iOffset)) {
            throw new Exception('fseek error on file: '.$this->filename);
        }
        return fwrite($this->fp, $sData);
    }
}