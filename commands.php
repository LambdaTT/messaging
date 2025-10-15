<?php

namespace Messaging\Commands;

use SplitPHP\Cli;
use SplitPHP\Utils;

class Pushnotification extends Cli
{
  public function init()
  {
    $this->addCommand('send', function () {
      $ids = [];

      $this->getDao('MSG_PUSH_QUEUE')
        ->fetch(function ($row) use (&$ids) {
          $ids[] = $row->id_msg_push_queue;
          $row->ds_token = Utils::dataDecrypt($row->ds_token, PUBLIC_KEY);
          $response = $this->getService('messaging/push')->sendPushNotification($row);
          Utils::printLn($response);
        }, "SELECT * FROM `MSG_PUSH_QUEUE` LIMIT 500");

      $this->getDao('MSG_PUSH_QUEUE')
        ->filter('id_msg_push_queue')->in($ids)
        ->delete();
    });
  }
}
