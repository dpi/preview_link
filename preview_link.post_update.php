<?php

/**
 * @file
 * Post update functions for Preview Link.
 */

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Site\Settings;
use Drupal\preview_link\Entity\PreviewLink;
use Drupal\preview_link\Entity\PreviewLinkInterface;
use Drupal\preview_link\PreviewLinkStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Add the 'entities' field to 'preview_link' entities.
 */
function preview_link_post_update_0001_entities_field(): void {
  $storageDefinition = BaseFieldDefinition::create('dynamic_entity_reference')
    ->setLabel(t('Entities'))
    ->setDescription(t('The associated entities this preview link unlocks.'))
    ->setRequired(TRUE)
    ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
    ->addConstraint('PreviewLinkEntitiesUniqueConstraint', [])
    ->setSettings(PreviewLink::entitiesDefaultFieldSettings())
    ->setDisplayOptions('form', [
      'type' => 'preview_link_entities_widget',
      'weight' => 10,
    ]);
  \Drupal::entityDefinitionUpdateManager()->installFieldStorageDefinition(
    'entities',
    'preview_link',
    'preview_link',
    $storageDefinition
  );

  // Change the column schema so the update doesnt complain when entities are
  // resaved in the next post.
  $dbSchema = \Drupal::database()->schema();
  $dbSchema->changeField('preview_link', 'entity_type_id', 'entity_type_id', [
    'type' => 'varchar',
    'not null' => FALSE,
    'length' => '255',
  ]);
  $dbSchema->changeField('preview_link', 'entity_id', 'entity_id', [
    'type' => 'varchar',
    'not null' => FALSE,
    'length' => '255',
  ]);
}

/**
 * Migrates entity relationship data to new field.
 */
function preview_link_post_update_0002_migrate_entity_references(array &$sandbox) {
  $limit = Settings::get('entity_update_batch_size', 50);

  $entityTypeManager = \Drupal::entityTypeManager();
  $previewLinkStorage = $entityTypeManager->getStorage('preview_link');
  assert($previewLinkStorage instanceof PreviewLinkStorageInterface);

  $query = $previewLinkStorage->getQuery()
    ->sort('id', 'ASC')
    ->range(0, $limit);

  if (isset($sandbox['id_high_water_mark'])) {
    $query->condition('id', $sandbox['id_high_water_mark'], '>');
  }

  $nextEntitiesIds = $query->execute();
  $previewLinks = $previewLinkStorage->loadMultiple($nextEntitiesIds);

  $sandbox['#finished'] = count($previewLinks) === 0 ? 1 : 0;

  foreach ($previewLinks as $previewLink) {
    assert($previewLink instanceof PreviewLinkInterface);
    $sandbox['id_high_water_mark'] = $previewLink->id();

    // Cant access the field normally, field definition is no longer present.
    $entityTypeId = $previewLink->entity_type_id[LanguageInterface::LANGCODE_DEFAULT] ?? NULL;
    try {
      $hostStorage = $entityTypeManager->getStorage($entityTypeId);
    }
    catch (\Throwable $t) {
      // Entity type no longer exists or is invalid.
      continue;
    }

    $entityId = $previewLink->entity_id[LanguageInterface::LANGCODE_DEFAULT] ?? NULL;
    $entity = $hostStorage->load($entityId);
    if (!$entity) {
      // Entity no longer exists.
      continue;
    }

    $previewLink->addEntity($entity);
    $previewLink->save();
  }
}

/**
 * Removes the 'entity_id' and 'entity_type_id' fields from 'preview_link'.
 */
function preview_link_post_update_0003_delete_fields() {
  $entityDefinitionUpdateManager = \Drupal::entityDefinitionUpdateManager();
  $entityIdField = $entityDefinitionUpdateManager->getFieldStorageDefinition('entity_id', 'preview_link');
  $entityDefinitionUpdateManager->uninstallFieldStorageDefinition($entityIdField);
  $entityTypeIdField = $entityDefinitionUpdateManager->getFieldStorageDefinition('entity_type_id', 'preview_link');
  $entityDefinitionUpdateManager->uninstallFieldStorageDefinition($entityTypeIdField);
}
