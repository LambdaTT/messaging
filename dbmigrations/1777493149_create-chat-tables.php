<?php

namespace Messaging\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class CreateChatTables extends Migration
{
  public function apply()
  {
    $this->Table("MSG_CHAT")

      // Fields:
      ->id("id_msg_chat")
      ->string("ds_key", 17)
      ->datetime("dt_created")->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->datetime("dt_updated")->nullable()->setDefaultValue(null)
      ->int("id_iam_user_created")->nullable()->setDefaultValue(null)
      ->int("id_iam_user_updated")->nullable()->setDefaultValue(null)
      ->datetime("dt_initiated")->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->datetime("dt_ended")->nullable()->setDefaultValue(null)

      // Indexes:
      ->Index("ds_key", DbVocab::IDX_UNIQUE)->onColumn("ds_key")
    ;

    $this->Table("MSG_CHAT_MESSAGE")

      // Fields:
      ->id("id_msg_chat_message")
      ->string("ds_key", 17)
      ->datetime("dt_created")->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->fk("id_msg_chat")
      ->text("tx_content")
      ->string("ds_sender", 255)->nullable()->setDefaultValue(null)

      // Indexes:
      ->Index("ds_key", DbVocab::IDX_UNIQUE)->onColumn("ds_key")

      // Foreign Keys:
      ->Foreign("id_msg_chat")
      ->references("id_msg_chat")
      ->atTable("MSG_CHAT")
      ->onUpdate(DbVocab::FKACTION_CASCADE)
      ->onDelete(DbVocab::FKACTION_CASCADE)
    ;
  }
}
