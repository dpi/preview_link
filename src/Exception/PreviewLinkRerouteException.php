<?php

declare(strict_types = 1);

namespace Drupal\preview_link\Exception;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\RefinableCacheableDependencyTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\preview_link\Entity\PreviewLinkInterface;

/**
 * Exception thrown when an entity needs to redirect to a preview link.
 */
class PreviewLinkRerouteException extends \Exception implements CacheableDependencyInterface {

  use RefinableCacheableDependencyTrait;

  /**
   * The entity redirecting to preview link.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The preview link redirecting to.
   *
   * @var \Drupal\preview_link\Entity\PreviewLinkInterface
   */
  protected $previewLink;

  /**
   * PreviewLinkRerouteException constructor.
   *
   * @param string $message
   *   The Exception message to throw.
   * @param int $code
   *   The Exception code.
   * @param \Throwable|null $previous
   *   The previous throwable used for the exception chaining.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity redirecting to preview link.
   * @param \Drupal\preview_link\Entity\PreviewLinkInterface $previewLink
   *   The preview link redirecting to.
   */
  public function __construct($message, $code, ?\Throwable $previous, EntityInterface $entity, PreviewLinkInterface $previewLink) {
    parent::__construct($message, $code, $previous);
    $this->entity = $entity;
    $this->previewLink = $previewLink;
  }

  /**
   * Get the entity redirecting to preview link.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity redirecting to preview link.
   */
  public function getEntity(): EntityInterface {
    return clone $this->entity;
  }

  /**
   * Get the preview link redirecting to.
   *
   * @return \Drupal\preview_link\Entity\PreviewLinkInterface
   *   The preview link redirecting to.
   */
  public function getPreviewLink(): PreviewLinkInterface {
    return clone $this->previewLink;
  }

}
