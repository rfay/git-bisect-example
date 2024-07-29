<?php

namespace Drupal\file;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\File\FileExists;

/**
 * Form handler for the file edit forms.
 *
 * @internal
 */
class FileForm extends ContentEntityForm {

  /**
   * The Current User object.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a FileForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, AccountInterface $current_user, DateFormatterInterface $date_formatter) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->currentUser = $current_user;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('current_user'),
      $container->get('date.formatter'),
      $container->get('entity_type.manager')->getStorage('file'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\file\FileInterface $file */
    $file = $this->entity;

    if ($this->operation == 'edit') {
      $form['#title'] = $this->t('<em>Edit</em> @title', [
        '@title' => $file->label(),
      ]);
    }

    // Changed must be sent to the client, for later overwrite error checking.
    $form['changed'] = [
      '#type' => 'hidden',
      '#default_value' => $file->getChangedTime(),
    ];

    $form['new_file'] = [
      '#type' => 'file',
      '#title' => $this->t('Replace file'),
      '#required' => TRUE,
    ];

    $form = parent::form($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\file\FileInterface $entity */
    $entity = parent::validateForm($form, $form_state);
    $new_file = file_save_upload('new_file');
    if (is_array($new_file) && count($new_file) > 0) {
      $new_file = reset($new_file);
      if ($entity->getFileName() !== $new_file->getFileName()) {
        $form_state->setErrorByName('new_file', $this->t('The uploaded file @newname name does not match the existing file @oldname', [
          '@oldname' => $entity->getFileName(),
          '@newname' => $new_file->getFileName(),
        ]));
      }
      else {
        $form_state->set('new_file', $new_file);
      }
    }
    else {
      $form_state->set('new_file', NULL);
    }
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    assert($this->entity instanceof FileInterface, '\Drupal\file\FileInterface instance expected.');

    $new_file = $form_state->get('new_file');

    \Drupal::service('file_system')->copy($new_file->getFileUri(), $this->entity->getFileUri(), FileExists::Replace);
    $this->entity->setMimeType($new_file->getMimeType());
    $this->entity->setSize($new_file->getSize());

    $new_file->delete();

    if (\Drupal::moduleHandler()->moduleExists('image')) {
      $image = \Drupal::service('image.factory')->get($this->entity->getFileUri());
      if ($image->isValid()) {
        image_path_flush($this->entity->getFileUri());
      }
    }
    return parent::save($form, $form_state);
  }

}
