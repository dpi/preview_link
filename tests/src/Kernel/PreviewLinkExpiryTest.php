<?php

declare(strict_types = 1);

namespace Drupal\Tests\preview_link\Kernel;

use Drupal\preview_link\Entity\PreviewLink;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Preview link expiry test.
 *
 * @group preview_link
 */
class PreviewLinkExpiryTest extends PreviewLinkBase {

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
    $this->storage = $this->container->get('entity_type.manager')->getStorage('preview_link');
    $this->node = $this->createNode();
  }

  /**
   * Test preview links are automatically expired on cron.
   */
  public function testPreviewLinkExpires(): void {
    $days = \Drupal::state()->get('preview_link_expiry_days', 7);
    // Add an extra day to make it expired.
    $days = $days + 1;
    $days_in_seconds = $days * 86400;
    $expired_preview_link = PreviewLink::create()->addEntity($this->node);
    // Set a timestamp that will definitely be expired.
    $expired_preview_link->generated_timestamp = $days_in_seconds;
    $expired_preview_link->save();
    $id = $expired_preview_link->id();

    // Run cron and then ensure the entity is gone.
    preview_link_cron();
    $this->assertNull($this->storage->load($id));
  }

}
