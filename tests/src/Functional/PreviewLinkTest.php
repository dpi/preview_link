<?php

declare(strict_types = 1);

namespace Drupal\Tests\preview_link\Functional;

use Drupal\entity_test\Entity\EntityTestRevPub;
use Drupal\node\NodeInterface;
use Drupal\preview_link\Entity\PreviewLink;
use Drupal\Tests\BrowserTestBase;

/**
 * Integration test for the preview link.
 *
 * @group preview_link
 */
class PreviewLinkTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'classy';

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'preview_link',
    'node',
    'filter',
    'entity_test',
    'preview_link_test',
    'preview_link_test_time',
  ];

  /**
   * Test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $admin;

  /**
   * The test node.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->createContentType(['type' => 'page']);
    $this->admin = $this->createUser([
      'generate preview links',
      'access content',
      'edit any page content',
    ]);
    $this->node = $this->createNode(['status' => NodeInterface::NOT_PUBLISHED]);

    \Drupal::configFactory()
      ->getEditable('preview_link.settings')
      ->set('enabled_entity_types', [
        'node' => ['page'],
        'entity_test_revpub' => ['entity_test_revpub'],
      ])
      ->save();

    /** @var \Drupal\preview_link_test_time\TimeMachine $timeMachine */
    $timeMachine = \Drupal::service('datetime.time');
    $currentTime = new \DateTime('14 May 2012 15:00:00');
    $timeMachine->setTime($currentTime);
  }

  /**
   * Test the preview link page.
   */
  public function testPreviewLinkPage(): void {
    /** @var \Drupal\preview_link_test_time\TimeMachine $timeMachine */
    $timeMachine = \Drupal::service('datetime.time');
    $timeMachine->setTime(new \DateTime('14 May 2014 14:00:00'));

    $assert = $this->assertSession();
    // Can only be visited by users with correct permission.
    $url = $this->node->toUrl('preview-link-generate');
    $this->drupalGet($url);
    $assert->statusCodeEquals(403);

    $this->drupalLogin($this->admin);
    $this->drupalGet($url);
    $assert->statusCodeEquals(200);

    // Grab the link from the page and ensure it works.
    $link = $this->cssSelect('.preview-link__link')[0]->getText();
    $this->assertSession()->pageTextContains('Expiry: 1 week');
    $this->drupalGet($link);
    $assert->statusCodeEquals(200);
    $assert->responseContains($this->node->getTitle());

    // Submitting form re-generates the link.
    $this->drupalPostForm($url, [], 'Save and regenerate preview link');
    $new_link = $this->cssSelect('.preview-link__link')[0]->getText();
    $this->assertNotEquals($link, $new_link);

    // Old link doesn't work.
    $this->drupalGet($link);
    $assert->statusCodeEquals(403);
    $assert->responseNotContains($this->node->getTitle());

    // New link does work.
    $this->drupalGet($new_link);
    $assert->statusCodeEquals(200);
    $assert->responseContains($this->node->getTitle());

    // Logout, new link works for anonymous user.
    $this->drupalLogout();
    $this->drupalGet($new_link);
    $assert->statusCodeEquals(200);
    $assert->responseContains($this->node->getTitle());
  }

  /**
   * Test preview link reset.
   */
  public function testReset(): void {
    /** @var \Drupal\preview_link_test_time\TimeMachine $timeMachine */
    $timeMachine = \Drupal::service('datetime.time');
    $currentTime = new \DateTime('14 May 2014 14:00:00');
    $timeMachine->setTime($currentTime);

    $this->drupalLogin($this->createUser(['generate preview links']));
    $entity = EntityTestRevPub::create();
    $entity->save();

    $previewLink = PreviewLink::create()->addEntity($entity);
    $previewLink->save();
    $token = $previewLink->getToken();
    $previewLink->save();
    $this->assertEquals($currentTime->getTimestamp(), $previewLink->getGeneratedTimestamp());

    $url = $entity->toUrl('preview-link-generate');
    $this->drupalGet($url);
    $this->assertSession()->pageTextContains('Generate a preview link for the entity.');
    $currentTime = new \DateTime('14 May 2014 20:00:00');
    $timeMachine->setTime($currentTime);
    $this->drupalPostForm(NULL, [], 'Reset lifetime');
    $this->assertSession()->pageTextContains('Preview link will now expire at Wed, 05/21/2014 - 20:00.');

    // Reload preview link.
    $previewLink = PreviewLink::load($previewLink->id());
    $this->assertEquals($currentTime->getTimestamp(), $previewLink->getGeneratedTimestamp());
    // Ensure token was not regenerated.
    $this->assertEquals($token, $previewLink->getToken());
  }

  /**
   * Tests managing entities for a Preview Link.
   */
  public function testEntities(): void {
    $this->drupalLogin($this->createUser([
      'generate preview links',
      'view test entity',
    ]));
    $entity1 = EntityTestRevPub::create([
      'name' => 'foo1',
    ]);
    $entity1->save();
    $entity2 = EntityTestRevPub::create([
      'name' => 'foo2',
    ]);
    $entity2->save();

    $generateUrl1 = $entity1->toUrl('preview-link-generate');
    $this->drupalGet($generateUrl1);
    $this->assertSession()->fieldValueEquals('entities[0][target_id]', 'foo1 (1)');
    $this->assertSession()->elementAttributeContains('css', '[name="entities[0][target_id]"]', 'disabled', 'disabled');
    $this->assertSession()->fieldExists('entities[1][target_id]');

    // Adding entity2, entity2 should remain editable. entity1 not editable.
    $edit = [
      'entities[1][target_id]' => 'foo2 (2)',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save');

    $this->assertSession()->pageTextContains('Preview Link saved.');
    $this->assertSession()->fieldValueEquals('entities[0][target_id]', 'foo1 (1)');
    $this->assertSession()->fieldValueEquals('entities[1][target_id]', 'foo2 (2)');
    $this->assertSession()->elementAttributeContains('css', '[name="entities[0][target_id]"]', 'disabled', 'disabled');
    $entity2Element = $this->assertSession()->elementExists('css', '[name="entities[1][target_id]"]');
    $this->assertFalse($entity2Element->hasAttribute('disabled'));
    $this->assertSession()->fieldExists('entities[2][target_id]');

    // Navigating to the other entity, entity1 now editable.
    $generateUrl2 = $entity2->toUrl('preview-link-generate');
    $this->drupalGet($generateUrl2);
    $this->assertSession()->fieldValueEquals('entities[0][target_id]', 'foo1 (1)');
    $this->assertSession()->fieldValueEquals('entities[1][target_id]', 'foo2 (2)');
    $entity1Element = $this->assertSession()->elementExists('css', '[name="entities[0][target_id]"]');
    $this->assertFalse($entity1Element->hasAttribute('disabled'));
    $this->assertSession()->elementAttributeContains('css', '[name="entities[1][target_id]"]', 'disabled', 'disabled');
  }

  /**
   * Tests unique entities for Preview Link.
   */
  public function testEntitiesUniqueConstraint(): void {
    $this->drupalLogin($this->createUser([
      'generate preview links',
      'view test entity',
    ]));
    $entity = EntityTestRevPub::create([
      'name' => 'foo1',
    ]);
    $entity->save();

    $generateUrl = $entity->toUrl('preview-link-generate');
    $this->drupalGet($generateUrl);
    $edit = [
      'entities[0][target_id]' => 'foo1 (1)',
      'entities[1][target_id]' => 'foo1 (1)',
    ];
    $this->drupalPostForm(NULL, $edit, 'Save');
    $this->assertSession()->pageTextContains('test entity - revisions and publishing status is already referenced by item #1.');
  }

  /**
   * Tests managing entities not possible when config is off.
   */
  public function testEntitiesInaccessible(): void {
    \Drupal::configFactory()->getEditable('preview_link.settings')
      ->set('multiple_entities', FALSE)
      ->save(TRUE);

    $this->drupalLogin($this->createUser([
      'generate preview links',
    ]));
    $entity = EntityTestRevPub::create([
      'name' => 'foo1',
    ]);
    $entity->save();

    $generateUrl = $entity->toUrl('preview-link-generate');
    $this->drupalGet($generateUrl);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->buttonExists('op');
    $this->assertSession()->pageTextNotContains('The associated entities this preview link unlocks.');
    $this->assertSession()->fieldNotExists('entities[0][target_id]');
  }

}
