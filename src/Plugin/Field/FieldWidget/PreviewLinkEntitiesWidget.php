<?php

declare(strict_types = 1);

namespace Drupal\preview_link\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dynamic_entity_reference\Plugin\Field\FieldWidget\DynamicEntityReferenceWidget;
use Drupal\preview_link\Form\PreviewLinkForm;

/**
 * Form widget for entities field on Preview Link.
 *
 * Prevents mixing referenced entity types, unless they were created
 * programmatically.
 *
 * @FieldWidget(
 *   id = "preview_link_entities_widget",
 *   label = @Translation("Preview Link Entities Widget"),
 *   description = @Translation("Widget for selecting entities related to a Preview Link"),
 *   field_types = {
 *     "dynamic_entity_reference"
 *   }
 * )
 *
 * @internal
 */
final class PreviewLinkEntitiesWidget extends DynamicEntityReferenceWidget {

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    $storageDefinition = $field_definition->getFieldStorageDefinition();
    return $storageDefinition->getTargetEntityTypeId() === 'preview_link' && $storageDefinition->getName() === 'entities';
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $element = parent::formElement($items, $delta, $element, $form, $form_state);
    $formObject = $form_state->getFormObject();
    if (!$formObject instanceof PreviewLinkForm) {
      throw new \LogicException('Can only be used with PreviewLinkForm');
    }

    $targetType = $items->get($delta)->target_type;
    $targetId = $items->get($delta)->target_id;
    $host = $formObject->getHostEntity(\Drupal::routeMatch());
    $hostEntityTypeId = $host->getEntityTypeId();

    // Swap select field to value.
    if ($element['target_type']['#type'] !== 'value') {
      $element['target_type'] = [
        '#type' => 'value',
        '#value' => $targetType,
      ];
    }

    // If target type not set yet (e.g for new items). Set the value.
    if (empty($targetType)) {
      // Force new items to be the same as host entity type.
      $element['target_type']['#value'] = $hostEntityTypeId;

      // Set otherwise autocomplete will use the wrong route.
      $settings = $this->getFieldSettings();
      $element['target_id']['#target_type'] = $hostEntityTypeId;
      $element['target_id']['#selection_handler'] = $settings[$hostEntityTypeId]['handler'];
      $element['target_id']['#selection_settings'] = $settings[$hostEntityTypeId]['handler_settings'];
    }

    // Protect host entity from modification.
    if ($targetType == $hostEntityTypeId && $targetId == $host->id()) {
      $element['target_id']['#disabled'] = TRUE;
    }

    return $element;
  }

}
