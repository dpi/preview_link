<?php

declare(strict_types = 1);

namespace Drupal\preview_link\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\preview_link\Exception\PreviewLinkRerouteException;
use Drupal\preview_link\PreviewLinkMessageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Modifies canonical entity routing to redirect to preview link.
 */
class PreviewLinkRouteEventSubscriber implements EventSubscriberInterface {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Provides helpers for redirect destinations.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Provides common messenger functionality.
   *
   * @var \Drupal\preview_link\PreviewLinkMessageInterface
   */
  protected $previewLinkMessages;

  /**
   * PreviewLinkRouteEventSubscriber constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirectDestination
   *   Provides helpers for redirect destinations.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\preview_link\PreviewLinkMessageInterface $previewLinkMessages
   *   Provides common messenger functionality.
   */
  public function __construct(MessengerInterface $messenger, RedirectDestinationInterface $redirectDestination, ConfigFactoryInterface $configFactory, PreviewLinkMessageInterface $previewLinkMessages) {
    $this->messenger = $messenger;
    $this->redirectDestination = $redirectDestination;
    $this->configFactory = $configFactory;
    $this->previewLinkMessages = $previewLinkMessages;
  }

  /**
   * Redirects from canonical routes to preview link route.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The exception event.
   */
  public function onException(ExceptionEvent $event): void {
    $exception = $event->getThrowable();
    if ($exception instanceof PreviewLinkRerouteException) {
      $entity = $exception->getEntity();

      $token = $exception->getPreviewLink()->getToken();
      $previewLinkUrl = Url::fromRoute('entity.' . $entity->getEntityTypeId() . '.preview_link', [
        $entity->getEntityTypeId() => $entity->id(),
        'preview_token' => $token,
      ]);

      // This message will display for subsequent page loads.
      // Message is designed to only be visible on canonical -> preview link
      // redirects, not on preview link routes accessed directly.
      $config = $this->configFactory->get('preview_link.settings');
      // 'always' includes subsequent.
      if (in_array($config->get('display_message'), ['always', 'subsequent'], TRUE)) {
        // Redirect destination actually has the canonical route since that's
        // where we are right now.
        $this->messenger->addMessage($this->previewLinkMessages->getGrantMessage($entity->toUrl()));
      }

      // 307: temporary.
      $response = (new TrustedRedirectResponse($previewLinkUrl->toString(), TrustedRedirectResponse::HTTP_TEMPORARY_REDIRECT))
        ->addCacheableDependency($exception);
      $event->setResponse($response);
      $event->stopPropagation();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[KernelEvents::EXCEPTION] = 'onException';
    return $events;
  }

}
