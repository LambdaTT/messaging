<?php

namespace Messaging\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class CreatePushRelatedTables extends Migration
{
  public function apply()
  {
    $this->Table('MSG_PUSH_SUBSCRIPTION', 'Push Subscription')
      ->id('id_msg_push_subscription') // int primary key auto increment
      ->string('ds_key', 17)
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->datetime('dt_updated')->nullable()->setDefaultValue(null)
      ->int('id_iam_user_created')->nullable()->setDefaultValue(null)
      ->int('id_iam_user_updated')->nullable()->setDefaultValue(null)
      ->int('id_iam_user')
      ->float('vl_expiration_time')->nullable()->setDefaultValue(null)
      ->text('tx_token')
      ->Index('KEY', DbVocab::IDX_UNIQUE)->onColumn('ds_key')
      ->Foreign('id_iam_user')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_CASCADE)
      ->Foreign('id_iam_user_created')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_SETNULL)
      ->Foreign('id_iam_user_updated')->references('id_iam_user')->atTable('IAM_USER')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_SETNULL);

    $this->Table('MSG_PUSH_QUEUE', 'Push Queue')
      ->id('id_msg_push_queue') // int primary key auto increment
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->text('tx_token')
      ->string('ds_title', 60)
      ->string('ds_body', 128)
      ->text('tx_image')->nullable()->setDefaultValue(null)
      ->string('ds_link')->nullable()->setDefaultValue(null);
  }
}
