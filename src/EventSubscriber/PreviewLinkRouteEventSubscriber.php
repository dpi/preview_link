<?php

declare(strict_types = 1);

namespace Drupal\preview_link\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\preview_link\Exception\PreviewLinkRerouteException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Modifies canonical entity routing to redirect to preview link.
 */
class PreviewLinkRouteEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

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
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * PreviewLinkRouteEventSubscriber constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirectDestination
   *   Provides helpers for redirect destinations.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The string translation service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(MessengerInterface $messenger, RedirectDestinationInterface $redirectDestination, TranslationInterface $stringTranslation, AccountInterface $currentUser) {
    $this->messenger = $messenger;
    $this->redirectDestination = $redirectDestination;
    $this->stringTranslation = $stringTranslation;
    $this->currentUser = $currentUser;
  }

  /**
   * Redirects from canonical routes to preview link route.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The exception event.
   */
  public function onException(ExceptionEvent $event) {
    $exception = $event->getThrowable();
    if ($exception instanceof PreviewLinkRerouteException) {
      $entity = $exception->getEntity();

      $token = $exception->getPreviewLink()->getToken();
      $previewLinkUrl = Url::fromRoute('entity.' . $entity->getEntityTypeId() . '.preview_link', [
        $entity->getEntityTypeId() => $entity->id(),
        'preview_token' => $token,
      ]);

      // Push the user back, but only if they have permission to view the
      // canonical route.
      // Redirect destination actually has the canonical route since thats
      // where we are right now.
      $removeUrl = Url::fromRoute('preview_link.session_tokens.remove');
      $destination = $this->redirectDestination->get();
      try {
        $canonicalUrl = Url::fromUserInput($destination);
        if ($canonicalUrl->access($this->currentUser)) {
          $removeUrl->setOption('query', $this->redirectDestination->getAsArray());
        }
      }
      catch (\InvalidArgumentException $e) {
      }

      // Message is designed to only be visible on canonical -> preview link
      // redirects, not on preview link routes accessed directly.
      $this->messenger->addMessage($this->t('You are viewing this page because a preview link granted you access. Click <a href="@remove_session_url">here</a> to remove token.', [
        '@remove_session_url' => $removeUrl->toString(),
      ]));

      // 307: temporary.
      $response = (new TrustedRedirectResponse($previewLinkUrl->toString(), Response::HTTP_TEMPORARY_REDIRECT))
        ->addCacheableDependency($exception);
      $event->setResponse($response);
      $event->stopPropagation();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::EXCEPTION] = 'onException';
    return $events;
  }

}
