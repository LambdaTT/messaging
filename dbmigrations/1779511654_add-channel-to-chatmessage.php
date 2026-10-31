<?php

namespace Messaging\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class AddChannelToChatmessage extends Migration
{
  public function apply()
  {
    $this->Table('MSG_CHAT_MESSAGE')
      ->string('ds_channel', 100)->nullable()->setDefaultValue(null)
    ;
  }
}
