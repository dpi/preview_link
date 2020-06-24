<?php

namespace Drupal\preview_link\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
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
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->state = $container->get('state');
    return $instance;
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $form['preview_link'] = [
      '#theme' => 'preview_link',
      '#title' => $this->t('Preview link'),
      '#link' => $this->entity
        ->getUrl()
        ->setAbsolute()
        ->toString(),
    ];

    $form['actions']['submit']['#value'] = $this->t('Re-generate preview link');

    $form['actions']['reset'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reset lifetime'),
      '#submit' => ['::resetLifetime', '::save'],
      '#weight' => 100,
    ];

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
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->regenerateToken(TRUE);
    $this->messenger()->addMessage($this->t('The token has been re-generated.'));
  }

  /**
   * Resets the lifetime of the current preview link.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function resetLifetime(array &$form, FormStateInterface $form_state) {
    $time = $this->time->getRequestTime();
    $this->entity->set('generated_timestamp', $time);
    $days = $this->state->get('preview_link_expiry_days', 7);
    $days_in_seconds = $days * 86400;
    $this->messenger()->addMessage($this->t('Preview link will now expire at %time.', [
      '%time' => $this->dateFormatter->format($time + $days_in_seconds),
    ]));
  }

}
