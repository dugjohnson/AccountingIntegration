<?php
class AfterUninstall
{
  public function run($conatiner)
  {
    $config = $conatiner->get('config');

    $tabList = $config->get('tabList');
    if (in_array('AccountIntegration', $tabList)) {
        foreach ($tabList as $key => $value) {
            if ($value=="AccountIntegration") {
                unset($tabList[$key]);
            }
        }
      $config->set('tabList',$tabList);
    }

    $config->save();
  }
}