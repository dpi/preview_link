<?php

/**
 * @file
 * Post update functions for Preview Link.
 */

use Drupal\preview_link\Entity\PreviewLink;
use Drupal\preview_link\PreviewLinkStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Add the 'entities' field to 'preview_link' entities.
 */
function preview_link_post_update_0001_migrate_entity_references(): void {
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
}

/**
 * Migrates entity relationship data to new field.
 *
 * @param array $sandbox
 *   Sandbox for maintaining position.
 */
function preview_link_post_update_0002_migrate_entity_references(array &$sandbox) {
  $limit = 10;

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
    $sandbox['id_high_water_mark'] = $previewLink->id();

    $entityTypeId = $previewLink->entity_type_id->value ?? NULL;
    try {
      $hostStorage = $entityTypeManager->getStorage($entityTypeId);
    }
    catch (\Throwable $t) {
      // Entity type no longer exists or is invalid.
      continue;
    }

    $entityId = $previewLink->entity_id->value ?? NULL;
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
