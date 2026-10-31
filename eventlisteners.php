<?php

namespace Messaging\EventListeners;

use SplitPHP\EventListener;

class Listeners extends EventListener
{
  private array $evtIds = [];

  public function init(): void
  {
    $this->evtIds['messaging.chat.message'] = $this->addEventListener('messaging.chat.message', function ($event) {
      $evtInfo = $event->info();
      $action  = $evtInfo->action;
      $msg     = $evtInfo->target;

      $srvc    = $this->getService('messaging/chatmessage');
      $srvc->signalChat($msg, $action);
    });
  }
}
