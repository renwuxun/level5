<?php

/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 2017/2/8 0008
 * Time: 16:59
 */
class Level5_Mutex {

    private $iKey;
    private $iSemid;

    private $iCounter = 0;

    public function __construct($iKey) {
        $this->iKey = $iKey;
    }

    private function getSemid() {
        if (!is_resource($this->iSemid)) {
            $this->iSemid = sem_get($this->iKey, 1);
        }
        return $this->iSemid;
    }

    public function lock() {
        $this->iCounter++;
        return sem_acquire($this->getSemid());
    }

    /**
     * @since 5.6.1
     * @return bool
     */
    public function trylock() {
        $r = sem_acquire($this->getSemid(), true);
        if ($r) {
            $this->iCounter++;
        }
        return $r;
    }

    public function unlock() {
        $this->iCounter--;
        return sem_release($this->iSemid);
    }

    public function __destruct() {
        while ($this->iCounter > 0) {
            $this->unlock();
        }
        // http://php.net/manual/zh/function.sem-remove.php
        sem_remove($this->iSemid);
    }


    private static $instances = array();

    /**
     * @param $iKey
     * @return self
     */
    public static function getInstance($iKey) {
        if (!isset(self::$instances[$iKey])) {
            self::$instances[$iKey] = new self($iKey);
        }
        return self::$instances[$iKey];
    }

}