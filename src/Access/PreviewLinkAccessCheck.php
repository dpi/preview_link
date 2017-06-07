<?php

namespace Drupal\preview_link\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;

/**
 * Preview link access check.
 */
class PreviewLinkAccessCheck implements AccessInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * PreviewLinkAccessCheck constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Checks access to the node add page for the node type.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $preview_token
   *   The preview token.
   *
   * @return string
   *   A \Drupal\Core\Access\AccessInterface constant value.
   */
  public function access(EntityInterface $entity = NULL, $preview_token = NULL) {
    if (!$preview_token || !$entity) {
      return AccessResult::forbidden();
    }

    /** @var \Drupal\preview_link\Entity\PreviewLinkInterface $preview_link */
    $preview_link = $this->entityTypeManager->getStorage('preview_link')->getPreviewLink($entity);

    // If we can't find a valid preview link then don't allow.
    if (!$preview_link) {
      return AccessResult::forbidden();
    }

    if ($preview_token !== $preview_link->getToken()) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

}
