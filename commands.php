<?php

namespace Messaging\Commands;

use SplitPHP\Cli;
use SplitPHP\Utils;

class Pushnotification extends Cli
{
  public function init()
  {
    $this->addCommand('send', function () {
      $processedIds = [];

      $this->getDao('MSG_PUSH_QUEUE')
        ->fetch(function ($row) use (&$processedIds) {
          try {
            $response = $this->getService('messaging/push')->send($row->tx_token, [
              'title' => $row->ds_title,
              'body'  => $row->ds_body,
              'link'  => $row->ds_link,
              'image' => $row->ds_image,
            ]);
            Utils::printLn($response);
            $processedIds[] = $row->id_msg_push_queue;
          } catch (\Throwable $e) {
            // Mantém a linha na fila para nova tentativa; não aborta o lote por causa de uma falha.
            Utils::printLn("Failed to send push #{$row->id_msg_push_queue}: " . $e->getMessage());
          }
        }, "SELECT * FROM `MSG_PUSH_QUEUE` LIMIT 500");

      if (!empty($processedIds)) {
        $this->getDao('MSG_PUSH_QUEUE')
          ->filter('id_msg_push_queue')->in($processedIds)
          ->delete();
      }
    });
  }
}
