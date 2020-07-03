<?php

declare(strict_types = 1);

namespace Drupal\preview_link\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\preview_link\Exception\PreviewLinkRerouteException;
use Drupal\preview_link\PreviewLinkHostInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Reroutes users from a canonical route to preview link route.
 *
 * Does not actually grant access, access checkers are in the right place
 * to interrupt routing and send the user agent elsewhere.
 */
class PreviewLinkCanonicalRerouteAccessCheck implements AccessInterface {

  /**
   * Private temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $privateTempStoreFactory;

  /**
   * Preview link host service.
   *
   * @var \Drupal\preview_link\PreviewLinkHostInterface
   */
  protected $previewLinkHost;

  /**
   * PreviewLinkCanonicalRerouteAccessCheck constructor.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $privateTempStoreFactory
   *   Private temp store factory.
   * @param \Drupal\preview_link\PreviewLinkHostInterface $previewLinkHost
   *   Preview link host service.
   */
  public function __construct(PrivateTempStoreFactory $privateTempStoreFactory, PreviewLinkHostInterface $previewLinkHost) {
    $this->privateTempStoreFactory = $privateTempStoreFactory;
    $this->previewLinkHost = $previewLinkHost;
  }

  /**
   * Checks if an activated preview link token is associated with this entity.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match.
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The request.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   A \Drupal\Core\Access\AccessInterface value.
   *
   * @throws \Drupal\preview_link\Exception\PreviewLinkRerouteException
   *   When a claimed token grants access to entity for this route match.
   */
  public function access(Route $route, RouteMatchInterface $routeMatch, Request $request = NULL) {
    $entityParameterName = $route->getRequirement('_access_preview_link_canonical_rerouter');

    $cacheability = (new CacheableMetadata())
      ->addCacheContexts(['session', 'route']);

    if (!$request) {
      return AccessResult::allowed()->addCacheableDependency($cacheability);
    }

    $entity = $routeMatch->getParameter($entityParameterName);
    if (!$entity instanceof EntityInterface) {
      // Entity was not upcast for preview link reroute access check.
      return AccessResult::allowed()->addCacheableDependency($cacheability);
    }

    $collection = $this->privateTempStoreFactory->get('preview_link');
    $claimedTokens = $collection->get('keys') ?? [];
    if (!$claimedTokens) {
      // Session has no claimed tokens.
      return AccessResult::allowed()->addCacheableDependency($cacheability);
    }

    if (!$this->previewLinkHost->isToken($entity, $claimedTokens)) {
      return AccessResult::neutral('This session does has activated preview link tokens that match this entity.')->addCacheableDependency($cacheability);
    }

    // Check if any keys in this session unlock this entity.
    $previewLinks = $this->previewLinkHost->getPreviewLinks($entity);
    // Get the first token that matches this entity.
    foreach ($previewLinks as $previewLink) {
      if (in_array($previewLink->getToken(), $claimedTokens, TRUE)) {
        throw new PreviewLinkRerouteException('', 0, NULL, $entity, $previewLink);
      }
    }

    throw new \LogicException('Shouldnt get here unless there are implementation differences between isToken and getPreviewLinks.');
  }

}
