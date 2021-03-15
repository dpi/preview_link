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
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
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
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

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
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel.
   */
  public function __construct(MessengerInterface $messenger, RedirectDestinationInterface $redirectDestination, TranslationInterface $stringTranslation, AccountInterface $currentUser, LoggerInterface $logger) {
    $this->messenger = $messenger;
    $this->redirectDestination = $redirectDestination;
    $this->stringTranslation = $stringTranslation;
    $this->currentUser = $currentUser;
    $this->logger = $logger;
  }

  /**
   * Redirects from canonical routes to preview link route.
   *
   * Need to use GetResponseForExceptionEvent and getException method instead of
   * ExceptionEvent::getThrowable() since these are in Symfony 4.4, and
   * Drupal 8.9 supports Symfony 3.4.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent $event
   *   The exception event.
   */
  public function onException(GetResponseForExceptionEvent $event): void {
    $exception = $event->getException();
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

      $this->logger->debug('Redirecting to preview link of @entity', [
        '@entity' => $entity->label(),
      ]);

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
    // Needs to be higher than ExceptionLoggingSubscriber::onError (priority 50)
    // so exception is not logged. Larger numbers are earlier:
    $events[KernelEvents::EXCEPTION][] = ['onException', 51];
    return $events;
  }

}
