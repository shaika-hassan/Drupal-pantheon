<?php

namespace Drupal\content_kanban;

use Drupal\content_planner\ContentModerationService;
use Drupal\Core\Database\Connection;
use Drupal\workflows\Entity\Workflow;

/**
 * Implements KanbanStatisticService class.
 */
class KanbanStatisticService {

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The kanban workflow service.
   *
   * @var \Drupal\content_kanban\KanbanWorkflowService
   */
  protected $kanbanWorkflowService;

  /**
   * The Content Moderation service.
   *
   * @var \Drupal\content_planner\ContentModerationService
   */
  protected $contentModerationService;

  /**
   * Constructs a new NewsService object.
   */
  public function __construct(
    Connection $database,
    KanbanWorkflowService $kanban_workflow_service,
    ContentModerationService $content_moderation_service
  ) {
    $this->database = $database;
    $this->kanbanWorkflowService = $kanban_workflow_service;
    $this->contentModerationService = $content_moderation_service;
  }

  /**
   * Get content counts from a given Workflow.
   *
   * @param \Drupal\workflows\Entity\Workflow $workflow
   *   The workflow object.
   *
   * @return array
   *   Returns an array with the workflow state content counts.
   */
  public function getWorkflowStateContentCounts(Workflow $workflow) {

    // Get all workflow states form a given workflow.
    $workflow_states = $this->contentModerationService->getWorkflowStates($workflow);

    $data = [];

    foreach ($workflow_states as $state_id => $state_label) {

      $count = $this->getWorkflowStateContentCount($workflow->id(), $state_id);

      $data[$state_id] = [
        'id' => $state_id,
        'label' => $state_label,
        'count' => $count,
      ];
    }

    return $data;
  }

  /**
   * Get the content count of a given workflow state.
   *
   * @param string $workflow_id
   *   The workflow ID.
   * @param string $state_id
   *   The state ID.
   *
   * @return mixed
   *   Returns the workflow state content count.
   */
  public function getWorkflowStateContentCount($workflow_id, $state_id) {

    $query = $this->database->select('content_moderation_state_field_data', 'c');

    $query->addField('c', 'id');

    $query->condition('c.workflow', $workflow_id);
    $query->condition('c.moderation_state', $state_id);

    $count_query = $query->countQuery();

    $result = $count_query->execute()->fetchObject();

    return $result->expression;
  }

}
