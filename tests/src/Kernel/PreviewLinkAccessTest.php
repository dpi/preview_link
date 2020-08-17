<?php

declare(strict_types = 1);

namespace Drupal\Tests\preview_link\Kernel;

use Drupal\preview_link\Entity\PreviewLink;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;

/**
 * Test preview link access.
 *
 * @group preview_link
 */
class PreviewLinkAccessTest extends PreviewLinkBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = ['node', 'filter'];

  /**
   * Node for testing.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * Preview link for node 1.
   *
   * @var \Drupal\preview_link\Entity\PreviewLinkInterface
   */
  protected $previewLink;

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
    $this->previewLink = PreviewLink::create()->addEntity($this->node);
    $this->previewLink->save();
  }

  /**
   * Test the preview access service.
   *
   * @dataProvider previewAccessDeniedDataProvider
   */
  public function testPreviewAccessDenied($entity_type_id, $entity_id, $token, $expected_result): void {
    $entity = $this->container->get('entity_type.manager')->getStorage($entity_type_id)->load($entity_id);
    $access = $this->container->get('access_check.preview_link')->access($entity, $token);
    $this->assertEquals($expected_result, $access->isAllowed());
  }

  /**
   * Data provider for testPreviewAccess().
   */
  public function previewAccessDeniedDataProvider(): array {
    return [
      'empty token' => ['node', 1, '', FALSE],
      'invalid token' => ['node', 1, 'invalid 123', FALSE],
      'invalid entity id' => ['node', 99, 'correct-token', FALSE],
    ];
  }

  /**
   * Ensure access is allowed with a valid token.
   */
  public function testPreviewAccessAllowed(): void {
    $access = $this->container->get('access_check.preview_link')->access($this->node, $this->previewLink->getToken());
    $this->assertEquals(TRUE, $access->isAllowed());
  }

}
