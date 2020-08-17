<?php

declare(strict_types = 1);

namespace Drupal\preview_link\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Preview link controller to view any entity.
 */
class PreviewLinkController extends ControllerBase {

  /**
   * Private temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $privateTempStoreFactory;

  /**
   * PreviewLinkController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $privateTempStoreFactory
   *   The tempstore factory.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, PrivateTempStoreFactory $privateTempStoreFactory) {
    $this->entityTypeManager = $entityTypeManager;
    $this->privateTempStoreFactory = $privateTempStoreFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
    );
  }

  /**
   * Preview any entity with the default view mode.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   * @param string $preview_token
   *   A validated Preview Link token.
   *
   * @return array
   *   A render array for previewing the entity.
   */
  public function preview(RouteMatchInterface $routeMatch, string $preview_token): array {
    // Accessing the controller will bind the Preview Link token to the session.
    $this->claimToken($preview_token);
    $entity = $this->resolveEntity($routeMatch);
    $view = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())->view($entity);
    // Subsequent [cached] requests to the page need to be able to activate
    // links.
    (new CacheableMetadata())
      ->addCacheContexts(['session'])
      ->applyTo($view);
    return $view;
  }

  /**
   * Preview page title.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   *
   * @return string|null
   *   The title of the entity.
   */
  public function title(RouteMatchInterface $routeMatch): ?string {
    return $this->resolveEntity($routeMatch)->label();
  }

  /**
   * Resolve the entity being previewed.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  protected function resolveEntity(RouteMatchInterface $routeMatch): EntityInterface {
    $entityParameterName = $routeMatch->getRouteObject()->getOption('preview_link.entity_type_id');
    return $routeMatch->getParameter($entityParameterName);
  }

  /**
   * Claim a Preview Link token to the session.
   *
   * @param string $preview_token
   *   A validated Preview Link token.
   */
  protected function claimToken(string $preview_token): void {
    $collection = $this->privateTempStoreFactory->get('preview_link');
    $currentKeys = $collection->get('keys') ?? [];
    if (!in_array($preview_token, $currentKeys, TRUE)) {
      $currentKeys[] = $preview_token;
      // Writing the value will start a session if one doesnt exist.
      $collection->set('keys', $currentKeys);
    }
  }

}
