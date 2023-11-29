<?php

namespace Drupal\content_planner\Plugin\DashboardBlock;

use Drupal\content_planner\DashboardBlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * The base class for block base.
 *
 * @todo Plugin derivates should be used instead of a class hierarchy here.
 */
abstract class CustomHTMLBlockBase extends DashboardBlockBase {
  use StringTranslationTrait;

  /**
   * Builds the render array for the block.
   *
   * @return array
   *   The render array for the block.
   */
  public function build() {
    if (!$this->currentUserHasRole()) {
      return [];
    }

    $build = [];

    // Get config.
    $config = $this->getConfiguration();

    if (isset($config['plugin_specific_config']['content'])) {

      $build = [
        '#markup' => check_markup($config['plugin_specific_config']['content']['value'], $config['plugin_specific_config']['content']['format']),
      ];

    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigSpecificFormFields(FormStateInterface &$form_state, Request &$request, array $block_configuration) {

    $form = [];

    if (!empty($block_configuration['plugin_specific_config']['content']['value'])) {
      $default_value = $block_configuration['plugin_specific_config']['content']['value'];
    }
    else {
      $default_value = '';
    }

    $form['content'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Content'),
      '#format' => 'full_html',
      '#default_value' => $default_value,
    ];

    $form['allowed_roles'] = $this->buildAllowedRolesSelectBox($block_configuration);

    return $form;
  }

}
