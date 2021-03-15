<?php

declare(strict_types = 1);

namespace Drupal\preview_link;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\preview_link\Access\PreviewLinkAccessCheck;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Responds to entity hooks/events.
 */
class PreviewLinkEntityHooks implements ContainerInjectionInterface {

  /**
   * Preview link access check.
   *
   * @var \Drupal\preview_link\Access\PreviewLinkAccessCheck
   */
  protected $accessCheck;

  /**
   * Provides service tasks for hooks.
   *
   * @var \Drupal\preview_link\PreviewLinkHookHelper
   */
  protected $hookHelper;

  /**
   * Current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new PreviewLinkEntityHooks.
   *
   * @param \Drupal\preview_link\Access\PreviewLinkAccessCheck $accessCheck
   *   Preview link access check.
   * @param \Drupal\preview_link\PreviewLinkHookHelper $hookHelper
   *   Provides service tasks for hooks.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   Current route match.
   */
  public function __construct(PreviewLinkAccessCheck $accessCheck, PreviewLinkHookHelper $hookHelper, RouteMatchInterface $routeMatch) {
    $this->accessCheck = $accessCheck;
    $this->hookHelper = $hookHelper;
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('access_check.preview_link'),
      $container->get('preview_link.hook_helper'),
      $container->get('current_route_match')
    );
  }

  /**
   * Implements \hook_entity_access().
   *
   * @see \preview_link_entity_access()
   */
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    $neutral = AccessResult::neutral()
      ->addCacheableDependency($entity)
      ->addCacheContexts(['preview_link_route']);

    if ($operation !== 'view' || !PreviewLinkUtility::isEntityTypeSupported($entity->getEntityType())) {
      return $neutral;
    }

    if ($this->hookHelper->isPreviewLinkGrantingAccess()) {
      return $this->accessCheck->access($entity, $this->routeMatch->getParameter('preview_token'));
    }

    return $neutral;
  }

}
