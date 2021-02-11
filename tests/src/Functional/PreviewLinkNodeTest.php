<?php

declare(strict_types = 1);

namespace Drupal\Tests\preview_link\Functional;

use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests nodes are referencable.
 *
 * When using the 'default' reference group only published nodes were
 * referencable.
 *
 * @group preview_link
 */
class PreviewLinkNodeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'dynamic_entity_reference',
    'preview_link',
    'node',
  ];

  /**
   * Tests referencing an unpublished node.
   */
  public function testReferenceUnpublishedNode(): void {
    $this->createContentType(['type' => 'page']);

    $this->drupalLogin($this->createUser([
      'generate preview links',
      'access content',
    ]));
    $node1 = $this->createNode([
      'title' => 'node1',
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);
    $node2 = $this->createNode([
      'title' => 'node2',
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);

    $generateUrl = $node1->toUrl('preview-link-generate');
    $this->drupalGet($generateUrl);

    $edit = [
      'entities[1][target_id]' => 'node2 (' . $node2->id() . ')',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save');
    // This would fail if unpublished wasnt referencable:
    $this->assertSession()->pageTextContains('Preview Link saved.');
  }

}
