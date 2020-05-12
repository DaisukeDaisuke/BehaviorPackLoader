<?php

namespace author\pluginName;

use pocketmine\plugin\PluginBase;

class Main extends PluginBase
{
  public function onLoad()
  {
    require $this->getFile() . "vendor/autoload.php";
  }

  public function onEnable(): void
  {
    $this->logger->info('Hey');
  }
}
