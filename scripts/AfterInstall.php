<?php

class AfterInstall
{
    public function run($conatiner)
    {	

        $config = $conatiner->get('config');

        $tabList = $config->get('tabList');
        if (!in_array('Transaction', $tabList)) {
            $tabList[] = 'Transaction';
            $config->set('tabList', $tabList);
        }
	    copy(__DIR__.'/uploadQB.php',__DIR__.'/../../../../../uploadQB.php');
        $config->save();
    }
}
