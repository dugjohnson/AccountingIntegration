<?php
class AfterUninstall
{
  public function run($conatiner)
  {
    $config = $conatiner->get('config');

    $tabList = $config->get('tabList');
    if (in_array('AccountIntegration', $tabList)) {
      $config->remove('tabList');
    }

    $config->save();
  }
}