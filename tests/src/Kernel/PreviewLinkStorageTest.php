<?php

namespace Drupal\Tests\preview_link\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\preview_link\Entity\PreviewLink;
use Drupal\preview_link\Entity\PreviewLinkInterface;
use Drupal\simpletest\ContentTypeCreationTrait;
use Drupal\simpletest\NodeCreationTrait;

/**
 * Preview link form test.
 *
 * @group preview_link
 */
class PreviewLinkStorageTest extends EntityKernelTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'filter', 'preview_link'];

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
    $this->installEntitySchema('preview_link');
    $this->installConfig(['node', 'filter']);
    $this->createContentType(['type' => 'page']);
    $this->node = $this->createNode();
    $this->storage = $this->container->get('entity_type.manager')->getStorage('preview_link');
  }

  /**
   * Ensure preview link creation works.
   */
  public function testCreatePreviewLink() {
    $preview_link = PreviewLink::create([
      'entity_type_id' => 'node',
      'entity_id' => $this->node->id(),
    ]);
    $this->assertTrue($preview_link->getToken());

    $preview_link = $this->storage->createPreviewLinkForEntity($this->node);
    $this->assertTrue($preview_link->getToken());

    $preview_link = $this->storage->createPreviewLink('node', $this->node->id());
    $this->assertTrue($preview_link->getToken());
  }

  /**
   * Test retrieving a preview link.
   */
  public function testGetPreviewLink() {
    $preview_link = $this->storage->createPreviewLinkForEntity($this->node);

    $retrieved_preview_link = $this->storage->getPreviewLinkForEntity($this->node);
    $this->assertPreviewLinkEqual($preview_link, $retrieved_preview_link);
  }

  /**
   * Ensure we can re-generate a token.
   */
  public function testRegenerateToken() {
    $preview_link = $this->storage->createPreviewLinkForEntity($this->node);
    $current_token = $preview_link->getToken();

    // Regenerate and ensure it changed.
    $preview_link->regenerateToken(TRUE);
    $preview_link->save();

    $this->assertNotEquals($current_token, $preview_link->getToken());
  }

  /**
   * Ensure two preview links are the same.
   *
   * @param \Drupal\preview_link\Entity\PreviewLinkInterface $preview_link1
   *   The first preview link.
   * @param \Drupal\preview_link\Entity\PreviewLinkInterface $preview_link2
   *   The second preview link.
   */
  protected function assertPreviewLinkEqual(PreviewLinkInterface $preview_link1, PreviewLinkInterface $preview_link2) {
    $this->assertEquals($preview_link1->getToken(), $preview_link2->getToken());
    $this->assertEquals($preview_link1->getUrl(), $preview_link2->getUrl());
  }

}
