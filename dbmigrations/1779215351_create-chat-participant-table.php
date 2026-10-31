<?php

namespace Messaging\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class CreateChatParticipantTable extends Migration
{
  public function apply()
  {
    $this->Table("MSG_CHAT_PARTICIPANT")
      ->id("id_msg_chat_participant")
      ->string('ds_key', 255)
      ->fk('id_msg_chat')
      ->string('ds_name', 255)

      ->Index('KEY', DbVocab::IDX_UNIQUE)->setColumns(['ds_key', 'id_msg_chat'])

      ->Foreign('id_msg_chat')
      ->references('id_msg_chat')
      ->atTable("MSG_CHAT")
      ->onUpdate(DbVocab::FKACTION_CASCADE)
      ->onDelete(DbVocab::FKACTION_CASCADE)
    ;

    $this->Table("MSG_CHAT")
      ->text("tx_participants")->drop()
    ;
  }
}
