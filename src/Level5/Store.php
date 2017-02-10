<?php

/**
 * Created by PhpStorm.
 * User: renwuxun
 * Date: 2017/2/9 0009
 * Time: 11:58
 */
interface Level5_Store {

    /**
     * @return bool
     */
    public function open();

    /**
     * @return void
     */
    public function close();

    /**
     * @param $iOffset
     * @param $iLength
     * @return string
     */
    public function read($iOffset, $iLength);

    /**
     * @param $sData
     * @param $iOffset
     * @return int
     */
    public function write($sData, $iOffset);


}