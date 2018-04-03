<?php
/**
 * Created by PhpStorm.
 * User: yangqiu
 * Date: 2018/1/11
 * Time: 15:05
 */

namespace app\backend\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

class Resolution extends Command
{
    protected function configure()
    {
        $this->setName('res')->setDescription('拆分');
    }

    protected function execute(Input $input, Output $output)
    {
        echo "111111";
    }
}