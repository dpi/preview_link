<?php

declare(strict_types = 1);

namespace Drupal\preview_link\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\dynamic_entity_reference\Plugin\Field\FieldWidget\DynamicEntityReferenceWidget;
use Drupal\preview_link\Form\PreviewLinkForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a PreviewLinkEntitiesWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, RouteMatchInterface $routeMatch) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('current_route_match')
    );
  }

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
