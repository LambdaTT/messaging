<?php

namespace Messaging\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class RemovePushRelatedTables extends Migration
{
  public function apply()
  {
    $this->Table("MSG_PUSH_SUBSCRIPTION")->drop();
    $this->Table("MSG_PUSH_QUEUE")->drop();
  }
}
