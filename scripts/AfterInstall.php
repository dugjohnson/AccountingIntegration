<?php

class AfterInstall
{
    public function run($conatiner)
    {
        $config = $conatiner->get('config');

        $tabList = $config->get('tabList');
        if (!in_array('AccountingIntegration', $tabList)) {
            $tabList[] = 'AccountingIntegration';
            $config->set('tabList', $tabList);
        }

        $config->save();
    }
}