<?php

namespace Messaging\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class AddReadFlagToChatMsg extends Migration
{
  public function apply()
  {
    $this->Table('MSG_CHAT')
      ->text('tx_participants')->setDefaultValue('[]') // JSON array of participants slugs
    ;


    $this->Table('MSG_CHAT_MESSAGE')
      ->text('tx_readers')->setDefaultValue('[]') // JSON array of readers slugs
    ;
  }
}
