<?php

namespace Drupal\content_calendar\Form;

use Drupal\content_calendar\ContentTypeConfigService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form that configures forms module settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Drupal\content_calendar\ContentTypeConfigService definition.
   *
   * @var \Drupal\content_calendar\ContentTypeConfigService
   */
  protected $contentTypeConfigService;

  /**
   * Module configuration object.
   *
   * @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Provides an interface for entity type managers.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The content moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $contentModerationInformation;

  /**
   * Config name.
   */
  const CONFIG_NAME = 'content_calendar.settings';

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->contentTypeConfigService = $container->get('content_calendar.content_type_config_service');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->contentModerationInformation = $container->get('content_moderation.moderation_information');
    $instance->config = $instance->config(self::CONFIG_NAME);

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'content_calendar_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'content_calendar.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {

    // Get select options for content types.
    $content_type_options = $this->getConfiguredContentTypes();

    if (!$content_type_options) {
      $message = $this->t('Content Calendar can only be used with Scheduler. At least one Content Type needs to have the scheduling options enabled.');
      $this->messenger()->addMessage($message, 'error');
      return [];
    }

    $this->buildContentTypeConfiguration($form, $form_state);
    $this->buildCalendarOptions($form, $form_state);
    $this->buildSchedulingOptions($form, $form_state);

    return parent::buildForm($form, $form_state);
  }

  /**
   * Build Content Type select options.
   *
   * @return array
   *   Returns an array with display options.
   */
  protected function getConfiguredContentTypes() {

    $display_options = [];

    // Load Node Type configurations.
    $definition = $this->entityTypeManager
      ->getDefinition('node');
    $node_types = $this->entityTypeManager
      ->getStorage('node_type')
      ->loadMultiple();

    foreach ($node_types as $node_type_key => $node_type) {
      // Exclude node types without scheduler.
      if (!$scheduler = $node_type->getThirdPartySettings('scheduler')) {
        continue;
      }
      if (empty($scheduler['publish_enable'])) {
        continue;
      }

      // Exclude node types without content moderation.
      if (!$this->contentModerationInformation->shouldModerateEntitiesOfBundle($definition, $node_type_key)) {
        continue;
      }

      $display_options[$node_type_key] = $node_type->label();
    }

    return $display_options;
  }

  /**
   * Build Content type configuration.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function buildContentTypeConfiguration(array &$form, FormStateInterface $form_state) {

    // Get select options for content types.
    $content_type_options = $this->getConfiguredContentTypes();

    // Get all config entities.
    $entities = $this->contentTypeConfigService->loadAllEntities();

    // Get all config entities keys.
    $entity_keys = array_keys($entities);

    $form['content_type_configuration'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Content Type Configuration'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    ];

    $form['content_type_configuration']['content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Which Content Types need to be displayed?'),
      '#description' => $this->t('Only content types with Scheduler and Content Moderation enabled are listed here.'),
      '#required' => TRUE,
      '#options' => $content_type_options,
      '#default_value' => $entity_keys,
    ];

    if ($entities) {

      $rows = [];

      foreach ($entities as $entity_key => $entity) {

        $options = [
          'query' => [
            'destination' => Url::fromRoute('content_calendar.settings')->toString(),
          ],
        ];

        $edit_link = Link::createFromRoute(
          $this->t('Configure'),
          'entity.content_type_config.edit_form',
          ['content_type_config' => $entity_key],
          $options
        );

        $row = [
          $entity->label(),
          $entity->id(),
          $entity->getColor(),
          $edit_link->toString(),
        ];

        $rows[] = $row;
      }

      $headers = [
        $this->t('Content Type'),
        $this->t('ID'),
        $this->t('Color'),
        $this->t('Actions'),
      ];

      $form['content_type_configuration']['table'] = [
        '#type' => 'table',
        '#header' => $headers,
        '#rows' => $rows,
        '#weight' => 20,
      ];
    }

  }

  /**
   * Build the form elements for the calendar options.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function buildCalendarOptions(array &$form, FormStateInterface $form_state) {

    // Fieldset.
    $form['options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Display Options'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    ];

    // Show user thumb checkbox.
    $user_picture_field_exists = !$this->config('field.field.user.user.user_picture')->isNew();

    $form['options']['show_user_thumb'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show thumbnail image of User image'),
      '#description' => $this->t('This option is only available, if the User account has the "user_picture" field. See Account configuration.'),
      '#disabled' => !$user_picture_field_exists,
      '#default_value' => $this->config->get('show_user_thumb'),
    ];

    $form['options']['bg_color_unpublished_content'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Background color for unpublished content'),
      '#description' => $this->t("Define the background color for unpublished content. Use a css color in word format (e.x. red) or a hexadecimal value (e.x. #ffcc00). When empty the default value will be used."),
      '#default_value' => $this->config->get('bg_color_unpublished_content'),
    ];

  }

  /**
   * Build the form elements for the scheduling options.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function buildSchedulingOptions(array &$form, FormStateInterface $form_state) {

    // Fieldset.
    $form['scheduling'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Scheduling options'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    ];

    // Show user thumb checkbox.
    $form['scheduling']['add_content_set_schedule_date'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically schedule content when creating through the calendar'),
      '#description' => $this->t('If enabled, both the created date and schedule date will be set when creating content through the add button on calendar dates. If disabled, only the created date will be set.'),
      '#default_value' => $this->config->get('add_content_set_schedule_date'),
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Get form values.
    $values = $form_state->getValues();

    // Save show user image thumbnail option.
    $this->config(self::CONFIG_NAME)
      ->set('show_user_thumb', $values['show_user_thumb'])
      ->set('bg_color_unpublished_content', $values['bg_color_unpublished_content'])
      ->set('add_content_set_schedule_date', $values['add_content_set_schedule_date'])
      ->save();

    // Get selected Content Types.
    $selected_content_types = $this->getSelectedContentTypes($form_state);

    // Load config entities.
    $config_entities = $this->contentTypeConfigService->loadAllEntities();

    // Check which config entity needs to be added.
    $this->addNewConfigEntities($selected_content_types, $config_entities);

    // Check which config entity needs to be deleted.
    $this->deleteObsoleteConfigEntities($selected_content_types, $config_entities);

    $this->messenger()->addStatus('Settings successfully updated');
  }

  /**
   * Get selected content types.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Return selected content types.
   */
  protected function getSelectedContentTypes(FormStateInterface &$form_state) {

    // Get values.
    $values = $form_state->getValues();

    // Save Content types to be displayed.
    $selected_content_types = [];

    foreach ($values['content_types'] as $key => $selected) {

      if ($selected) {
        $selected_content_types[] = $key;
      }
    }

    return $selected_content_types;
  }

  /**
   * Check which config entity needs to be deleted.
   *
   * @param array $selected_content_types
   *   An array with selected content types.
   * @param \Drupal\content_calendar\Entity\ContentTypeConfig[] $config_entities
   *   An array with config entities.
   */
  protected function addNewConfigEntities(array $selected_content_types, array &$config_entities) {

    // Get entity keys.
    $entity_keys = array_keys($config_entities);

    foreach ($selected_content_types as $selected_content_type) {

      if (!in_array($selected_content_type, $entity_keys)) {

        if ($node_type = $this->entityTypeManager->getStorage('node_type')->load($selected_content_type)) {
          $this->contentTypeConfigService->createEntity($selected_content_type, $node_type->label());
          $this->messenger()->addMessage($this->t('Content Type @name has been added and can be configured below.', ['@name' => $node_type->label()]));
        }
      }
    }
  }

  /**
   * Check which config entity needs to be deleted.
   *
   * @param array $selected_content_types
   *   Array with a selected content types.
   * @param \Drupal\content_calendar\Entity\ContentTypeConfig[] $config_entities
   *   Array with content calendar.
   */
  protected function deleteObsoleteConfigEntities(array $selected_content_types, array &$config_entities) {

    foreach ($config_entities as $config_entity_id => $config_entity) {

      if (!in_array($config_entity_id, $selected_content_types)) {
        $this->messenger()->addMessage($this->t('Content Type @name has been removed from Content Calendar.', ['@name' => $config_entity->label()]));
        $config_entity->delete();
      }
    }

  }

}
