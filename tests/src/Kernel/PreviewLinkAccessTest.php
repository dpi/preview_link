<?php

namespace Drupal\Tests\preview_link\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\preview_link\Entity\PreviewLink;
use Drupal\simpletest\ContentTypeCreationTrait;
use Drupal\simpletest\NodeCreationTrait;

/**
 * Test preview link access.
 *
 * @group preview_link
 */
class PreviewLinkAccessTest extends EntityKernelTestBase {

  use ContentTypeCreationTrait;
  use NodeCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['node', 'filter', 'preview_link'];

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
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installEntitySchema('preview_link');
    $this->installConfig(['node', 'filter']);
    $this->createContentType(['type' => 'page']);
    $this->node = $this->createNode();
    $this->previewLink = PreviewLink::create([
      'entity_type_id' => 'node',
      'entity_id' => $this->node->id(),
    ]);
    $this->previewLink->save();
  }

  /**
   * Test the preview access service.
   *
   * @dataProvider previewAccessDeniedDataProvider
   */
  public function testPreviewAccessDenied($entity_type_id, $entity_id, $token, $expected_result) {
    $access = $this->container->get('access_check.preview_link')->access($entity_type_id, $entity_id, $token);
    $this->assertEquals($expected_result, $access->isAllowed());
  }

  /**
   * Data provider for testPreviewAccess();
   */
  public function previewAccessDeniedDataProvider() {
    return [
      'empty token' => ['node', 1, '', FALSE],
      'invalid token' => ['node', 1, 'invalid 123', FALSE],
      'invalid entity id' => ['node', 99, 'correct-token', FALSE],
      'invalid entity type id' => ['blah', 1, 'correct-token', FALSE],
    ];
  }

  /**
   * Ensure access is allowed with a valid token.
   */
  public function testPreviewAccessAllowed() {
    $access = $this->container->get('access_check.preview_link')->access('node', $this->node->id(), $this->previewLink->getToken());
    $this->assertEquals(TRUE, $access->isAllowed());
  }

}
