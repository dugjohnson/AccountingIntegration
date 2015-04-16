<?php

class AfterUninstall
{
    public function run($conatiner)
    {
        $config = $conatiner->get('config');

        $tabList = $config->get('tabList');
        if (in_array('Transaction', $tabList)) {
            foreach ($tabList as $key => $value) {
                if ($value == "Transaction") {
                    unset($tabList[$key]);
                }
            }
            $tabList = array_values($tabList);
            $config->set('tabList', $tabList);
        }

        $config->save();
    }
}