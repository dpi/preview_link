<?php

namespace Drupal\preview_link\Entity;

use Drupal\Core\Entity\ContentEntityInterface;

interface PreviewLinkInterface extends ContentEntityInterface {

  /**
   * The URL for this preview link.
   *
   * @return \Drupal\Core\Url
   *   The url object.
   */
  public function getUrl();

  public function getToken();

  public function setToken($token);

  public function regenerateToken($needs_new_token = FALSE);

}
