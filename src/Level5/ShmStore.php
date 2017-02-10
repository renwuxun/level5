<?php

/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 2017/2/8 0008
 * Time: 16:52
 */

class Level5_ShmStore implements Level5_Store{

    private $iShmid = 0;
    private $iKey;
    private $iSize;

    /**
     * Level5_ShmStore constructor.
     * 准备工作：
     * 65536*(4+4)+4+4+65536*16*5 = 5767176
     * 生成iKey
     */
    public function __construct() {
        $this->iKey = 0x00002537;
        $this->iSize = 5767176;
        $this->open();
    }

    public function __destruct() {
        $this->close();
    }

    /**
     * "a" for access (sets SHM_RDONLY for shmat) use this flag when you need to open an existing shared memory segment for read only
     * "c" for create (sets IPC_CREATE) use this flag when you need to create a new shared memory segment or if a segment with the same key exists, try to open it for read and write
     * "w" for read & write access use this flag when you need to read and write to a shared memory segment, use this flag in most cases.
     * "n" create a new memory segment (sets IPC_CREATE|IPC_EXCL) use this flag when you want to create a new shared memory segment but if one already exists with the same flag, fail. This is useful for security purposes, using this you can prevent race condition exploits.
     *
     * @param string $sFlag
     * @return bool
     */
    public function open($sFlag = 'c') {
        $this->iShmid = shmop_open($this->iKey, $sFlag, 0644, $this->iSize);
        return false!==$this->iShmid;
    }

    /**
     * @return void
     */
    public function close() {
        if (is_resource($this->iShmid)) {
            shmop_close($this->iShmid);
        }
    }

    /**
     * @param $iOffset
     * @param $iLength
     * @return string
     */
    public function read($iOffset, $iLength) {
        return shmop_read($this->iShmid, $iOffset, $iLength);
    }

    /**
     * @param $sData
     * @param $iOffset
     * @return int
     */
    public function write($sData, $iOffset) {
        return shmop_write($this->iShmid, $sData, $iOffset);
    }
}