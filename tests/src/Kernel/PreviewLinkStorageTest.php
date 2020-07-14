<?php

declare(strict_types = 1);

namespace Drupal\Tests\preview_link\Kernel;

use Drupal\preview_link\Entity\PreviewLink;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Preview link session test.
 *
 * @group preview_link
 * @coversDefaultClass \Drupal\preview_link\PreviewLinkStorage
 */
class PreviewLinkStorageTest extends PreviewLinkBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'filter'];

  /**
   * Testing node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * The preview link storage.
   *
   * @var \Drupal\preview_link\PreviewLinkStorageInterface
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installConfig(['node', 'filter']);
    $this->createContentType(['type' => 'page']);
    $this->node = $this->createNode();
    $this->storage = $this->container->get('entity_type.manager')->getStorage('preview_link');
  }

  /**
   * Ensure preview link creation works.
   */
  public function testCreatePreviewLink(): void {
    $preview_link = PreviewLink::create()->addEntity($this->node);
    $this->assertIsString($preview_link->getToken());

    $preview_link = PreviewLink::create()->addEntity($this->node);
    $preview_link->save();
    $this->assertIsString($preview_link->getToken());
  }

  /**
   * Ensure we can re-generate a token.
   */
  public function testRegenerateToken(): void {
    $preview_link = PreviewLink::create()->addEntity($this->node);
    $preview_link->save();
    $current_token = $preview_link->getToken();
    $current_timestamp = $preview_link->getGeneratedTimestamp();

    // Regenerate and ensure it changed.
    $preview_link->regenerateToken(TRUE);
    $preview_link->save();

    $this->assertNotEquals($current_token, $preview_link->getToken());
    $this->assertNotEquals($current_timestamp, $preview_link->getGeneratedTimestamp());
  }

}
