<?php

namespace Messaging\Migrations;

use SplitPHP\DbManager\Migration;
use SplitPHP\Database\DbVocab;

class AdaptPushTablesToIamDevice extends Migration
{
  public function apply()
  {
    $this->Table('MSG_PUSH_DEVICE_SUBSCRIPTION', 'Inscrição de Dispositivo Push')

      // Fields
      ->id('id_msg_push_device_subscription') // int primary key auto increment
      ->string('ds_key', 17)
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->datetime('dt_updated')->nullable()->setDefaultValue(null)
      ->fk('id_iam_device')
      ->int('nr_expiration_time')->nullable()->setDefaultValue(null)
      ->text('tx_token')

      // Indexes
      ->Index('KEY', DbVocab::IDX_UNIQUE)->onColumn('ds_key')

      // Foreign Keys
      ->Foreign('id_iam_device')->references('id_iam_device')->atTable('IAM_DEVICE')->onUpdate(DbVocab::FKACTION_CASCADE)->onDelete(DbVocab::FKACTION_CASCADE);

    $this->Table('MSG_PUSH_QUEUE', 'Fila de Notificações Push')

      // Fields
      ->id('id_msg_push_queue') // int primary key auto increment
      ->datetime('dt_created')->setDefaultValue(DbVocab::SQL_CURTIMESTAMP())
      ->text('tx_token')
      ->string('ds_title', 60)
      ->string('ds_body', 128)
      ->string('ds_icon_192', 255)->nullable()->setDefaultValue(null)
      ->string('ds_icon_128', 255)->nullable()->setDefaultValue(null)
      ->string('ds_image', 255)->nullable()->setDefaultValue(null)
      ->string('ds_link', 255)->nullable()->setDefaultValue(null);
  }
}
