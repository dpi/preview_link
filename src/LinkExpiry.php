<?php

namespace Drupal\preview_link;

use Drupal\Core\State\StateInterface;

/**
 * Calculates link expiry time.
 */
class LinkExpiry {

  /**
   * Default expiry time in days.
   *
   * @var int
   */
  const DEFAULT_EXPIRY_DAYS = 7;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * LinkExpiry constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * Gets expiry time in seconds.
   *
   * @return int
   *   Link expiry time in seconds.
   */
  public function getSeconds() {
    $days = $this->state->get('preview_link_expiry_days', static::DEFAULT_EXPIRY_DAYS);
    return $days * 86400;
  }

  /**
   * Gets expiry time in days.
   *
   * @return int
   *   Link expiry time in days.
   */
  public function getDays() {
    return $this->state->get('preview_link_expiry_days', static::DEFAULT_EXPIRY_DAYS);
  }

}
