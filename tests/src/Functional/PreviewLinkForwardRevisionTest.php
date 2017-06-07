<?php

namespace Drupal\Tests\preview_link\Functional;

use Drupal\preview_link\Entity\PreviewLink;
use Drupal\Tests\BrowserTestBase;
use Drupal\workflows\Entity\Workflow;

/**
 * Test forward revisions are loaded.
 *
 * @group preview_link
 */
class PreviewLinkForwardRevisionTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'preview_link',
    'node',
    'filter',
    'content_moderation',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->createContentType(['type' => 'page']);

    $workflow = Workflow::load('editorial');
    $workflow
      ->getTypePlugin()
      ->addEntityTypeAndBundle('node', 'page');
    $workflow->save();
  }

  /**
   * Test the latest forward revision is loaded.
   */
  public function testForwardRevision() {
    $original_random_text = 'Original Title';
    $latest_random_text = 'Latest Title';

    // Create a node with some random text.
    $node = $this->createNode(['title' => $original_random_text, 'moderation_state' => 'published']);

    // Create a forward revision with new text.
    $node->setTitle($latest_random_text);
    $node->moderation_state = 'draft';
    $node->save();

    // Create the preview link.
    $previewLink = PreviewLink::create([
      'entity_type_id' => 'node',
      'entity_id' => $node->id(),
    ]);
    $previewLink->save();

    // Visit the node and assert the original text.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->pageTextNotContains($latest_random_text);
    $this->assertSession()->pageTextContains($original_random_text);

    // Visit the preview link and assert the forward revision text.
    $this->drupalGet($previewLink->getUrl());
    $this->assertSession()->pageTextContains($latest_random_text);
    $this->assertSession()->pageTextNotContains($original_random_text);
  }

}
