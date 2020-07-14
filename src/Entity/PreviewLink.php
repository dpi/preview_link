<?php

declare(strict_types = 1);

namespace Drupal\preview_link\Entity;

use Drupal\Component\Assertion\Inspector;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Url;

/**
 * Defines the node entity class.
 *
 * @ContentEntityType(
 *   id = "preview_link",
 *   label = @Translation("Preview Link"),
 *   base_table = "preview_link",
 *   handlers = {
 *     "storage" = "Drupal\preview_link\PreviewLinkStorage",
 *     "form" = {
 *       "preview_link" = "Drupal\preview_link\Form\PreviewLinkForm"
 *     }
 *   },
 *   entity_keys = {
 *     "id" = "id"
 *   }
 * )
 */
class PreviewLink extends ContentEntityBase implements PreviewLinkInterface {

  /**
   * Keep track on whether we need a new token upon save.
   *
   * @var bool
   */
  protected $needsNewToken = FALSE;

  /**
   * {@inheritdoc}
   */
  public function getUrl(EntityInterface $entity): Url {
    return Url::fromRoute(sprintf('entity.%s.preview_link', $entity->getEntityTypeId()), [
      $entity->getEntityTypeId() => $entity->id(),
      'preview_token' => $this->getToken(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getToken(): string {
    return $this->get('token')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setToken($token) {
    $this->set('token', $token);
    // Add a second so our testing always works.
    $this->set('generated_timestamp', time() + 1);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function regenerateToken($needs_new_token = FALSE): bool {
    $current_value = $this->needsNewToken;
    $this->needsNewToken = $needs_new_token;
    return $current_value;
  }

  /**
   * {@inheritdoc}
   */
  public function getGeneratedTimestamp(): int {
    return (int) $this->get('generated_timestamp')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntities(): array {
    $entities = $this->entities->referencedEntities();
    assert(Inspector::assertAllObjects($entities, EntityInterface::class));
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function setEntities(array $entities) {
    assert(Inspector::assertAllObjects($entities, EntityInterface::class));
    $this->entities = $entities;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addEntity(EntityInterface $entity) {
    $this->entities[] = $entity;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['token'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Preview Token'))
      ->setDescription(t('A token that allows any user to view a preview of this entity.'))
      ->setRequired(TRUE);

    $fields['entities'] = BaseFieldDefinition::create('dynamic_entity_reference')
      ->setLabel(t('Entities'))
      ->setDescription(t('The associated entities this preview link unlocks.'))
      ->setRequired(TRUE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->addConstraint('PreviewLinkEntitiesUniqueConstraint', [])
      ->setSettings(static::entitiesDefaultFieldSettings())
      ->setDisplayOptions('form', [
        'type' => 'preview_link_entities_widget',
        'weight' => 10,
      ]);

    $fields['generated_timestamp'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Generated Timestamp'))
      ->setDescription(t('The time the link was generated'))
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * Rewrites settings for 'entities' dynamic_entity_reference field.
   *
   * DynamicEntityReferenceItem::defaultFieldSettings doesnt receive any context
   * so we need to change the default handlers manually.
   */
  public static function entitiesDefaultFieldSettings(): array {
    $labels = \Drupal::service('entity_type.repository')->getEntityTypeLabels(TRUE);
    $options = $labels[(string) t('Content', [], ['context' => 'Entity type group'])];
    $settings = [
      'exclude_entity_types' => TRUE,
      'entity_type_ids' => [],
    ];
    $settings += array_fill_keys(array_keys($options), [
      'handler' => 'preview_link',
      'handler_settings' => [],
    ]);
    return $settings;
  }

}
