<?php

declare(strict_types = 1);

namespace Drupal\Tests\preview_link\Functional;

use Drupal\entity_test\Entity\EntityTestMulRevPub;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\preview_link\Entity\PreviewLink;

/**
 * Tests upgrade path adding Preview Link session functionality.
 *
 * @group preview_link
 */
class PreviewLinkSessionTokenUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['entity_test', 'preview_link_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installModulesFromClassProperty($this->container);
  }

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../../../../../../core/modules/system/tests/fixtures/update/drupal-8.8.0.bare.standard.php.gz',
      __DIR__ . '/../../../../tests/fixtures/update/preview-link-multi-3155009.php',
    ];
  }

  /**
   * Tests upgrade path to enable DER and migrate data over.
   *
   * @see \preview_link_update_8201()
   * @see \preview_link_update_8202()
   * @see \preview_link_update_8203()
   * @see \preview_link_post_update_0001_entities_field()
   * @see \preview_link_post_update_0002_migrate_entity_references()
   * @see \preview_link_post_update_0003_delete_fields()
   */
  public function testMultipleEntitiesFieldMigration(): void {
    EntityTestMulRevPub::create([
      'id' => 2,
    ])->save();

    $dbSchema = \Drupal::database()->schema();
    $configFactory = \Drupal::configFactory();

    // Check that the sms tables exist but the others don't.
    $this->assertTrue($dbSchema->tableExists('preview_link'));
    $this->assertTrue($dbSchema->fieldExists('preview_link', 'entity_type_id'));
    $this->assertTrue($dbSchema->fieldExists('preview_link', 'entity_id'));

    $result = \Drupal::database()->query('SELECT * FROM {preview_link};')->fetchAll();
    $this->assertEquals('de3a19ee-1edc-4b2e-9af8-f512dddcddcc', $result[0]->token);

    $this->assertNull($configFactory->get('preview_link.settings')->get('multiple_entities'));
    $definition = \Drupal::entityDefinitionUpdateManager()->getEntityType('preview_link');
    $this->assertTrue($definition->hasKey('entity_id'));
    $this->assertTrue($definition->hasKey('entity_type_id'));
    $this->assertTrue($definition->hasKey('token'));

    $this->runUpdates();

    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('dynamic_entity_reference'));

    $this->assertTrue($dbSchema->tableExists('preview_link'));
    $this->assertTrue($dbSchema->tableExists('preview_link__entities'));
    $this->assertFalse($dbSchema->fieldExists('preview_link', 'entity_type_id'));
    $this->assertFalse($dbSchema->fieldExists('preview_link', 'entity_id'));

    $this->assertFalse($configFactory->get('preview_link.settings')->get('multiple_entities'));
    $definition = \Drupal::entityTypeManager()->getDefinition('preview_link');
    $this->assertFalse($definition->hasKey('entity_id'));
    $this->assertFalse($definition->hasKey('entity_type_id'));
    $this->assertFalse($definition->hasKey('token'));

    /** @var \Drupal\preview_link\Entity\PreviewLinkInterface[] $previewLinks */
    $previewLinks = PreviewLink::loadMultiple();
    $this->assertCount(1, $previewLinks);
    $this->assertEquals('de3a19ee-1edc-4b2e-9af8-f512dddcddcc', $previewLinks[1]->getToken());
    $hostEntities = $previewLinks[1]->getEntities();
    $previewLinkHostEntity = reset($hostEntities);
    $this->assertEquals('entity_test_mulrevpub', $previewLinkHostEntity->getEntityTypeId());
    $this->assertEquals('2', $previewLinks[1]->getEntities()[0]->id());
  }

}
