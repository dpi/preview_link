<?php

namespace Drupal\preview_link\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\preview_link\LinkExpiry;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Preview link form.
 */
class PreviewLinkForm extends ContentEntityForm {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The link expiry service.
   *
   * @var \Drupal\preview_link\LinkExpiry
   */
  protected $linkExpiry;

  /**
   * PreviewLinkForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, DateFormatterInterface $date_formatter, LinkExpiry $link_expiry) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->dateFormatter = $date_formatter;
    $this->linkExpiry = $link_expiry;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('date.formatter'),
      $container->get('preview_link.link_expiry')
    );
  }

  /**
   * @inheritDoc
   */
  public function getFormId() {
    return 'preview_link_entity_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityFromRouteMatch(RouteMatchInterface $route_match, $entity_type_id) {
    /** @var \Drupal\preview_link\PreviewLinkStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('preview_link');
    $related_entity = $this->getRelatedEntity();
    if (!$preview_link = $storage->getPreviewLink($related_entity)) {
      $preview_link = $storage->createPreviewLinkForEntity($related_entity);
    }
    return $preview_link;
  }

  /**
   * @inheritDoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $expiration = $this->entity->getGeneratedTimestamp() + $this->linkExpiry->getSeconds() - $this->time->getRequestTime();
    $remaining_expiry = $this->dateFormatter->formatInterval($expiration);

    $form['preview_link'] = [
      '#theme' => 'preview_link',
      '#title' => $this->t('Preview link'),
      '#related_entity' => $this->getRelatedEntity(),
      '#total_expiry_days' => $this->linkExpiry->getDays(),
      '#remaining_expiry' => $remaining_expiry,
      '#link' => $this->entity
        ->getUrl()
        ->setAbsolute()
        ->toString(),
    ];

    $form['actions']['submit']['#value'] = $this->t('Regenerate preview link');

    return $form;
  }

  /**
   * Attempts to load the entity this preview link will be related to.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The content entity interface.
   *
   * @throws \InvalidArgumentException
   *   Only thrown if we cannot detect the related entity.
   */
  protected function getRelatedEntity() {
    $entity = NULL;
    $entity_type_ids = array_keys($this->entityTypeManager->getDefinitions());

    foreach ($entity_type_ids as $entity_type_id) {
      if ($entity = \Drupal::request()->attributes->get($entity_type_id)) {
        break;
      }
    }

    if (!$entity) {
      throw new \InvalidArgumentException('Something went very wrong');
    }

    return $entity;
  }

  /**
   * @inheritDoc
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->regenerateToken(TRUE);
    $this->messenger()->addMessage($this->t('The token has been re-generated.'));

  }
}
