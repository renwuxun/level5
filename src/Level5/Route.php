<?php

/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 2017/2/8 0008
 * Time: 17:08
 */
class Level5_Route {

    /**
     * @var Level5_Store
     */
    protected $store;

    /**
     * @var Level5_Store
     */
    protected $counterStore;

    /**
     * @var resource[]
     */
    protected $aSemid = array();

    private static $ftokCache = array();
    public static function _ftok($file) {
        if (!isset(self::$ftokCache[$file])) {
            $st = @stat($file);
            if (!$st) {
                return 0;
            }
            self::$ftokCache[$file] = ($st['ino'] & 0xffff) | (($st['dev'] & 0xff) << 16);
        }

        return self::$ftokCache[$file];
    }

    /**
     * @param $file string
     * @param $id int
     * @return int
     */
    public static function ftok($file, $id) {
        $int = self::_ftok($file);
        if (0 === $int) {
            return -1;
        }
        return (int)sprintf("%u", $int | (($id & 0xff) << 24));
    }

    public static function addCrc32Tail($s) {
        return $s.pack('N', (int)sprintf('%u', crc32($s)));
    }

    public static function checkCrc32Tail($s) {
        $l = strlen($s);
        if ($l < 4) {
            return false;
        }

        $last4Char = substr($s, -4);

        $s = substr($s, 0, $l-4);
        $_crc32 = (int)sprintf('%u', crc32($s));

        $arr = unpack('Ncrc32', $last4Char);

        return ($arr['crc32'] == $_crc32);
    }

//    public function add($sid, $iIp, $iPort, $iWeight) {
//        if ($sid > 65536 || $sid <= 0) {
//            return false;
//        }
//
//        $ret = false;
//
//        // lock for w(write)
//        $iKey = self::ftok(__DIR__ . '/write.lock', ord('w'));
//        $semid = sem_get($iKey, 1);
//        sem_acquire($semid);
//
//        try {
//            do {
//                // get block data position
//                $s = $this->getStore()->read(8 * ($sid - 1), 8);
//                if (!self::checkCrc32Tail($s)) { // 运气不好，有人正在写到一半
//                    break;
//                }
//                //$arr = unpack('NiBlockOffset/NiCrc32', $s);
//                $arr = unpack('NiBlockOffset', $s);
//                $iOldBlockOffset = $arr['iBlockOffset'];
//
//                $sNewBlockData = pack('NnnN', $iIp, $iPort, $iWeight, $iOldBlockOffset);
//                $sNewBlockData .= self::addCrc32Tail($sNewBlockData);
//
//                $s = $this->getStore()->read(524288, 8);
//                if (!self::checkCrc32Tail($s)) { // 运气不好，有人正在写到一半
//                    break;
//                }
//                //$arr = unpack('NiTopFreeBlockOffset/NiCrc32', $s);
//                $arr = unpack('NiTopFreeBlockOffset', $s);
//                if ($arr['iTopFreeBlockOffset'] == 0) { // 没有空闲的block了
//                    break;
//                }
//                $iTopFreeBlockOffset = $arr['iTopFreeBlockOffset'];
//
//                $s = $this->getStore()->read($iOldBlockOffset, 16);
//                if (!self::checkCrc32Tail($s)) { // 运气不好，有人正在写到一半
//                    break;
//                }
//                //$block = unpack('NiIp/niPort/niWeight/NiNextBlockOffset/NiCrc32', $s);
//                $block = unpack('NiIp/niPort/niWeight/NiNextBlockOffset', $s);
//                // 弹出free block
//                $this->getStore()->write(self::addCrc32Tail(pack('N', $block['iNextBlockOffset'])), 524288);
//                // 挂到sid上
//                $this->getStore()->write(self::addCrc32Tail(pack('N', $iTopFreeBlockOffset)), 8 * ($sid - 1));
//                // 写新block
//                $this->getStore()->write($sNewBlockData, $iTopFreeBlockOffset);
//
//                $ret = true;
//            } while (0);
//        } catch (Exception $e) {
//            // @todo: log error
//        }
//
//        // unlock for w(write)
//        sem_release($semid);
//
//        return $ret;
//    }

    /**
     * @param $sid int
     * @return array
     */
    public function get($sid) {
        if ($sid > 65536 || $sid <= 0) {
            return array();
        }

        // get block data position
        $s = $this->getStore()->read(8 * ($sid - 1), 8);
        if (!self::checkCrc32Tail($s)) { // 运气不好，有人正在写到一半
            return array();
        }
        //$arr = unpack('NiBlockOffset/NiCrc32', $s);
        $arr = unpack('NiBlockOffset', $s);
        $iBlockOffset = $arr['iBlockOffset'];

        // read blocks
        $blocks = array();
        $iSumWeight = 0;
        do {
            $s = $this->getStore()->read($iBlockOffset, 16);
            //$block = unpack('NiIp/niPort/niWeight/NiNextBlockOffset/NiCrc32', $s);
            $block = unpack('NiIp/niPort/niWeight/NiNextBlockOffset', $s);
            if (!self::checkCrc32Tail($s)) { // 运气不好，有人正在写到一半
                break;
            }
            $iBlockOffset = $block['iNextBlockOffset'];
            if ($block['iWeight'] > 0) {
                $blocks[] = $block;
                $iSumWeight += $block['iWeight'];
            }
        } while ($iBlockOffset > 0);

        if ($iSumWeight < 1) {
            return array();
        }

        $counter = $this->incrCounter($sid);
        $counter = $counter % $iSumWeight;
        $iWeight = 0;
        foreach ($blocks as $block) {
            $iWeight += $block['iWeight'];
            if ($iWeight >= $counter) {
                break;
            }
        }

        return array(long2ip($block['iIp']), $block['iPort']);
    }

    protected function incrCounter($sid) {
        $semid = $this->getSemid($sid);
        sem_acquire($semid);

        $s = $this->getCounterStore()->read(4 * ($sid - 1), 4);
        $aData = unpack('NiCounter', $s);
        $s = pack('N', $aData['iCounter'] + 1);
        $this->getCounterStore()->write($s, 4 * ($sid - 1));

        sem_release($semid);

        return $aData['iCounter'] + 1;
    }

    protected function getSemid($incompleteKey) {
        $iKey = self::ftok(__DIR__.'/incr.lock', $incompleteKey%256);
        if (!isset($this->aSemid[$iKey]) || !is_resource($this->aSemid[$iKey])) {
            $this->aSemid[$iKey] = sem_get($iKey, 1);
        }
        return $this->aSemid[$iKey];
    }

    /**
     * @return Level5_Store
     */
    public function getStore() {
        return $this->store;
    }

    /**
     * @param Level5_Store $store
     * @return $this
     */
    public function setStore($store) {
        $this->store = $store;
        return $this;
    }

    /**
     * @return Level5_Store
     */
    public function getCounterStore() {
        return $this->counterStore;
    }

    /**
     * @param Level5_Store $counterStore
     * @return $this
     */
    public function setCounterStore($counterStore) {
        $this->counterStore = $counterStore;
        return $this;
    }

}