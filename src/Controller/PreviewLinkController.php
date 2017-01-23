<?php

namespace Drupal\preview_link\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Preview link controller to view any entity.
 */
class PreviewLinkController extends ControllerBase {

  /**
   * Preview any entity with the default view mode.
   *
   * @param string $entity_type_id
   *   The entity type Id.
   * @param string $entity_id
   *   The entity Id.
   *
   * @return array
   *   A render array for previewing the entity.
   */
  public function preview($entity_type_id, $entity_id) {
    $entity = $this->entityTypeManager()->getStorage($entity_type_id)->load($entity_id);
    return $this->entityTypeManager()->getViewBuilder($entity_type_id)->view($entity);
  }

}
