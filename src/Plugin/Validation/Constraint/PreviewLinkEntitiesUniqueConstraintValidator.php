<?php

declare(strict_types = 1);

namespace Drupal\preview_link\Plugin\Validation\Constraint;

use Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceFieldItemList;
use Drupal\dynamic_entity_reference\Plugin\Field\FieldType\DynamicEntityReferenceItem;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the PreviewLinkEntitiesUniqueConstraint constraint.
 */
class PreviewLinkEntitiesUniqueConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    assert($constraint instanceof PreviewLinkEntitiesUniqueConstraint);
    assert($value instanceof DynamicEntityReferenceFieldItemList);

    $entities = [];
    foreach ($value as $delta => $item) {
      assert($item instanceof DynamicEntityReferenceItem);
      $entity = $item->entity;
      $hash = $item->target_type . '|' . $item->target_id;
      if (($duplicateDelta = array_search($hash, $entities, TRUE)) !== FALSE) {
        $this->context
          ->buildViolation($constraint->multipleReferences)
          ->setParameter('%entity_type', $entity ? $entity->getEntityType()->getSingularLabel() : $item->target_type)
          ->setParameter('%other_delta', $duplicateDelta + 1)
          ->atPath($delta)
          ->addViolation();
      }
      else {
        $entities[$delta] = $hash;
      }
    }
  }

}
