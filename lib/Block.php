<?php
/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 2016/5/11 0011
 * Time: 11:14
 */

namespace Level5;


/**
 * Class Block
 * @package Level5
 *
 * 用于保存一条被调实例记录(ip port weight ...)
 *
 * @property $iIp uint32
 * @property $iPort uint16
 * @property $iWeight uint8
 * @property $iUint8 uint8 预留
 * @property $iNextAddr uint32
 */
class Block {

    private static $iSize = 12; // 4+2+1+1+4
    private static $aStructMembers = ['iIp', 'iPort', 'iWeight', 'iUint8', 'iNextAddr'];

    ///////////////////////////////////[
    /**
     * uint32
     * @var int
     */
    protected $iIp;
    /**
     * uint16
     * @var int
     */
    protected $iPort;
    /**
     * uint8
     * @var int
     */
    protected $iWeight;
    /**
     * uint8
     * @var int
     */
    protected $iUint8;
    /**
     * uint32
     * @var int
     */
    protected $iNextAddr;
    ///////////////////////////////////]

    private $lasterr='';


    /**
     * uint32
     * @var int
     */
    private $iAddr;

    /**
     * @var static
     */
    private $oNext;

    /**
     * @var L5
     */
    private $l5;

    /**
     * Block constructor.
     * @param $iAddr int
     * @param $l5 L5
     * @throws Exception
     */
    public function __construct($iAddr, $l5) {
        $iAddr = $iAddr & $l5->getBlockAddrMask();
        if (!$l5->addrInBlockArea($iAddr)) {
            throw new Exception('block addr must in ['.$l5->getBlockStartAt().','.$l5->getShmMaxAddr().'] got '.$iAddr);
        }
        $this->iAddr = $iAddr;
        $this->l5 = $l5;
    }

    /**
     * @return static
     */
    public function load() {
        try {
            $bData = $this->l5->getShmAccessor()->read($this->iAddr, static::$iSize);
            $aData = unpack('NiIp/niPort/CiWeight/CiUint8/NiNextAddr', $bData);
            foreach (static::$aStructMembers as $k) {
                $this->$k = $aData[$k];
            }
        } catch (Exception $e) {
            $this->lasterr = $e->getMessage();
            $this->l5->log($this->lasterr);
        }

        return $this;
    }

    /**
     * @return static
     */
    public function save() {
        $this->checkPropertyInit();
        $bData = pack(
            'NnCCN',
            $this->iIp,
            $this->iPort,
            $this->iWeight,
            $this->iUint8,
            $this->iNextAddr
        );
        if (static::$iSize != $this->l5->getShmAccessor()->write($bData, $this->iAddr)) {
            $this->lasterr = sprintf(
                'block save err %d:[%d, %d, %d, %d, %d]'
                ,$this->iAddr
                ,$this->iIp
                ,$this->iPort
                ,$this->iWeight
                ,$this->iUint8
                ,$this->iNextAddr
            );
            $this->l5->log($this->lasterr);
        }
        return $this;
    }

    /**
     * @return static|null
     */
    public function next() {
        $this->checkPropertyInit();
        if ($this->oNext === null && $this->iNextAddr > $this->l5->getBlockStartAt()) {
            $this->oNext = (new static($this->iNextAddr, $this->l5))->load();
        }
        return $this->oNext;
    }

    public function __get($name) {
        if (!in_array($name, static::$aStructMembers)) {
            throw new Exception('property [' . __CLASS__ . '::' . $name . '] access denied');
        }
        $this->checkPropertyInit();
        return $this->$name;
    }

    public function __set($name, $value) {
        if (!in_array($name, static::$aStructMembers)) {
            throw new Exception('property [' . __CLASS__ . '::' . $name . '] access denied');
        }
        $this->l5->checkPropertyRange($name, $value);
        $this->$name = $value;
    }

    private function checkPropertyInit() {
        foreach (static::$aStructMembers as $k) {
            if ($this->$k === null) {
                throw new Exception('property ['.__CLASS__.'::'.$k.'] must be init, you may need call load() before');
            }
        }
    }

    public function getAddr() {
        return $this->iAddr;
    }

    /**
     * @return int
     */
    public static function getSize() {
        return static::$iSize;
    }
}