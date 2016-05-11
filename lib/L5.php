<?php
/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 2016/5/11 0011
 * Time: 11:09
 */

namespace Level5;


/**
 * Class L5
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
 * |-----------------------128+65536*4+65536*12*5(平均每个sid有5个ip:port block)
 */
abstract class l5 {

    protected $iBlockAddrMask = 0x00ffffff;

    protected $iFirstFreeBlockAddrAt = 0;
    protected $iSidAddrStartAt = 128;
    protected $iSidCount = 65536;
    protected $iBlockStartAt;
    protected $iBlockCount;//65536*5;

    protected $iBlockInitFlagAt = 4;

    /**
     * @var ShmAccessor
     */
    protected $oShmAccessor;

    private $lasterr = '';

    /**
     * @var Block
     */
    private $sBlockName;

    /**
     * L5 constructor.
     * @param $oShmAccessor ShmAccessor
     * @param $sBlockName string
     */
    public function __construct($oShmAccessor, $sBlockName) {
        /**
         * @var $sBlockName Block
         */
        $this->sBlockName = $sBlockName;
        $this->iBlockStartAt = $this->iSidAddrStartAt + ($this->iSidCount * 4);
        $this->iBlockCount = floor(($oShmAccessor->getSize() - $this->iBlockStartAt) / $sBlockName::getSize());

        $this->oShmAccessor = $oShmAccessor;
        if (0 == $this->getInitFlag()) {
            $mtx = Mutex::getInstance($this->iBlockInitFlagAt);
            $mtx->lock();
            try {
                if (0 == $this->getInitFlag()) {
                    $this->setInitFlag(1);
                    $this->initBlocks();
                }
            } catch (Exception $e) {
                $this->lasterr = 'blocks init error : ' . $e->getMessage();
                $this->log($this->lasterr);
                $this->setInitFlag(0);
            }
            $mtx->unlock();
        }
    }

    /**
     * @return int
     */
    protected function getInitFlag() {
        $bData = $this->oShmAccessor->read($this->iBlockInitFlagAt, 4);
        $aData = unpack('NiFlag', $bData);
        return $aData['iFlag'];
    }

    protected function setInitFlag($iFlag) {
        $bData = pack('N', $iFlag);
        return $this->oShmAccessor->write($bData, $this->iBlockInitFlagAt);
    }

    protected function initBlocks() {
        $sBlockName = $this->sBlockName;

        $iAddr = $this->iBlockStartAt;
        $this->setFirstFreeBlockAddr($iAddr);

        $oBlock = null;
        for (; $this->addrInBlockArea($iAddr);) {
            $iNextAddr = $iAddr + $sBlockName::getSize();

            $oBlock = new Block($iAddr, $this);
            $oBlock->iIp = 0;
            $oBlock->iPort = 0;
            $oBlock->iWeight = 0;
            $oBlock->iUint8 = 0;
            $oBlock->iNextAddr = $iNextAddr < $this->oShmAccessor->getSize() ? $iNextAddr : 0;
            $oBlock->save();

            $iAddr += $oBlock->getSize();
        }
    }

    protected $iShmMaxAddr;

    public function getShmMaxAddr() {
        if (!$this->iShmMaxAddr) {
            $this->iShmMaxAddr = $this->oShmAccessor->getSize() - 1;
        }
        return $this->iShmMaxAddr;
    }

    /**
     * @param $iAddr
     * @return bool
     */
    public function addrInBlockArea($iAddr) {
        if ($iAddr < $this->iBlockStartAt || $iAddr > $this->getShmMaxAddr()) {
            return false;
        }
        return true;
    }

    public function checkPropertyRange($name, $value) {
        if ($value === 0) {
            return;
        }
        switch ($name) {
            case 'iIp':
                if (!is_numeric($value) || $value < 0) {
                    throw new Exception('iIp must be a number and with the range in [0,)');
                }
                break;
            case 'iPort':
                if ($value < 0 || $value > 65535) {
                    throw new Exception('iPort must in [0,65535]');
                }
                break;
            case 'iWeight':
                if ($value < 0 || $value > 255) {
                    throw new Exception('iWeight must in [0,255]');
                }
                break;
            case 'iUint8':
                if ($value < 0 || $value > 255) {
                    throw new Exception('iUint8 must in [0,255]');
                }
                break;
            case 'iNextAddr':
                if (!$this->addrInBlockArea($value)) {
                    throw new Exception('iNextAddr must in [' . $this->iBlockStartAt . ',' . $this->getShmMaxAddr() . '] got ' . $value);
                }
                break;
            default:
                throw new Exception('property [' . __CLASS__ . '::' . $name . '] access denied');
        }
    }

    /**
     * @param $iSid int uint16
     * @param $iAddr int uint32
     * @param $iCounter int uint8
     * @throws Exception
     */
    protected function set2sid($iSid, $iAddr, $iCounter) {
        $iMaxSid = $this->iSidCount - 1;
        if ($iSid < 0 || $iSid > $iMaxSid) {
            throw new Exception('iSid must in [0,' . $iMaxSid . ']');
        }
        if ($iAddr < $this->iBlockStartAt || $iAddr > $this->getShmMaxAddr()) {
            throw new Exception('iAddr must in [' . $this->iBlockStartAt . ',' . $this->getShmMaxAddr() . ']');
        }
        $iAddr = $iAddr | ($iCounter << 24);
        $this->oShmAccessor->write(pack('N', $iAddr), $iSid * 4);
    }

    /**
     * @param $iSid int uint16
     * @param $iCounter
     * @return int
     */
    protected function getSidFirstBlockAddr($iSid, &$iCounter = null) {
        $bData = $this->oShmAccessor->read($iSid * 4, 4);
        $aData = unpack('NiAddr', $bData);
        $iAddr = $aData['iAddr'];
        if (func_num_args() > 1) {
            $iCounter = $iAddr >> 24;
        }
        return $iAddr & $this->iBlockAddrMask;
    }

    /**
     * @param $iSid int uint16
     * @param null $iCounter
     * @return null|Block
     */
    protected function getSidFirstBlock($iSid, &$iCounter = null) {
        $oBlock = null;
        if (func_num_args() > 1) {
            $iAddr = $this->getSidFirstBlockAddr($iSid, $iCounter);
        } else {
            $iAddr = $this->getSidFirstBlockAddr($iSid);
        }

        if ($this->addrInBlockArea($iAddr)) {
            $oBlock = (new Block($iAddr, $this))->load();
        }
        return $oBlock;
    }

    /**
     * @param $iSid int uint16
     * @param $iCounter int uint8
     * @return int
     */
    protected function setSidCounter($iSid, $iCounter) {
        return $this->oShmAccessor->write(pack('C', $iCounter), $iSid * 4);
    }

    /**
     * @return ShmAccessor
     */
    public function getShmAccessor() {
        return $this->oShmAccessor;
    }

    /**
     * @return null|Block
     */
    protected function freeBlocksPeek() {
        $oBlock = null;
        $iAddr = $this->getFirstFreeBlockAddr();
        if ($this->addrInBlockArea($iAddr)) {
            $oBlock = (new Block($iAddr, $this))->load();
        }
        return $oBlock;
    }

    /**
     * @return Block|null
     */
    protected function freeBlocksShift() {
        $oBlock = $this->freeBlocksPeek();
        if ($oBlock) {
            $this->setFirstFreeBlockAddr($oBlock->iNextAddr);
        }

        return $oBlock;
    }

    /**
     * @param $oBlock Block
     * @return bool
     */
    protected function freeBlocksUnshift($oBlock) {
        $iLastAddr = $this->getFirstFreeBlockAddr();
        $oBlock->iNextAddr = $this->addrInBlockArea($iLastAddr) ? 0 : $iLastAddr;
        $oBlock->save();
        return 4 == $this->setFirstFreeBlockAddr($oBlock->getAddr());
    }

    /**
     * @param $iAddr int
     * @return int
     */
    protected function setFirstFreeBlockAddr($iAddr) {
        return $this->oShmAccessor->write(pack('N', $iAddr), $this->iFirstFreeBlockAddrAt);
    }

    /**
     * @return int
     */
    protected function getFirstFreeBlockAddr() {
        $bData = $this->oShmAccessor->read($this->iFirstFreeBlockAddrAt, 4);
        $aData = unpack('NiAddr', $bData);
        return $aData['iAddr'];
    }

    /**
     * @return int
     */
    public function getBlockStartAt() {
        return $this->iBlockStartAt;
    }

    /**
     * @return int
     */
    public function getBlockAddrMask() {
        return $this->iBlockAddrMask;
    }

    /**
     * @param $iSid int
     * @return [[iIp, iPort, iWeight],...]
     */
    abstract protected function getRemoteConf($iSid);

    /**
     * @param $msg string
     */
    abstract public function log($msg);


    //////////////////////////////////////////////////////////////////////////////
    /**
     * @param $iSid
     * @return array
     * @throws Exception
     */
    public function getRoute($iSid) {
        $oBlockHit = null;
        $oBlock = null;
        $iCounter = 0;
        $iWeightSum = 0;
        /**
         * @var $aBlocks Block[]
         */
        $aBlocks = [];

        $mutex = Mutex::getInstance($iSid);
        $mutex->lock();
        try {
            $oBlock = $this->getSidFirstBlock($iSid, $iCounter);
            for (; $oBlock;) {
                echo "shm get:",long2ip($oBlock->iIp),':',$oBlock->iPort,'-',$oBlock->iWeight,'-',$iCounter,"\n";
                $aBlocks[] = $oBlock;
                $iWeightSum += $oBlock->iWeight;
                if ($oBlockHit === null && $iWeightSum > $iCounter) {
                    $oBlockHit = $oBlock;
                }
                $oBlock = $oBlock->next();
            }
            $iCounter++;
            if ($iWeightSum > 0 && $iCounter >= $iWeightSum) {
                $iCounter = $iCounter % $iWeightSum;
            }
            $this->setSidCounter($iSid, $iCounter);
        } catch (Exception $e) {
        }
        $mutex->unlock();

        if ($oBlockHit) {
            echo "from shm\n";
            return [$oBlockHit->iIp, $oBlockHit->iPort];
        } else {
            echo "from remote\n";
            $aIpport = $this->getRemoteConf($iSid);
            if (!empty($aIpport)) {
                /**
                 * @var $aBlockNew Block[]
                 */
                $aBlockNew = [];
                $mutexFreeBlock = Mutex::getInstance($this->iFirstFreeBlockAddrAt);
                $mutexFreeBlock->lock();
                try {
                    foreach ($aIpport as $ipport) {
                        $oBlock = $this->freeBlocksShift();
                        if ($oBlock) {
                            $oBlock->iIp = (int)$ipport[0];
                            $oBlock->iPort = (int)$ipport[1];
                            $oBlock->iWeight = (int)$ipport[2];
                            $oBlock->iUint8 = 0;
                            $oBlock->iNextAddr = 0;
                            $aBlockNew[] = $oBlock;
                        }
                    }
                } catch (Exception $e) {
                }
                $mutexFreeBlock->unlock();

                if (!empty($aBlockNew)) {
                    $c = sizeof($aBlockNew);
                    for ($i = 0; $i < ($c - 1); $i++) {
                        $aBlockNew[$i]->iNextAddr = $aBlockNew[$i + 1]->getAddr();
                    }
                    foreach ($aBlockNew as $BlockNew) {
                        $BlockNew->save();
                    }
                    $mutex->lock();
                    $this->set2sid($iSid, $aBlockNew[0]->getAddr(), 1);
                    $mutex->unlock();
                }

                if (sizeof($aIpport) != sizeof($aBlockNew)) {
                    $this->lasterr = 'need more free blocks';
                    $this->log('need more free blocks');
                }

                return [(int)$aIpport[0][0], (int)$aIpport[0][1]];
            }
        }
        return [];
    }
}