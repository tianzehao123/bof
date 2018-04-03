<?php

namespace app\home\controller;

use Service\Stock;
use think\Controller;

class Timing extends Controller
{
    public function testnew()
    {
        $stock = new Stock();
        $ceshi = $stock->loadAccount();
        echo $ceshi;
        die;
    }
}