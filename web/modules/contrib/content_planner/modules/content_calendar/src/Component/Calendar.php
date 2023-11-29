<?php

namespace Drupal\content_calendar\Component;

use Drupal\content_calendar\ContentTypeConfigService;
use Drupal\content_calendar\ContentCalendarService;
use Drupal\content_calendar\DateTimeHelper;
use Drupal\content_planner\ContentModerationService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Implements Calendar class.
 */
class Calendar {

  /**
   * The theme manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The content moderatiom service.
   *
   * @var \Drupal\content_planner\ContentModerationService
   */
  protected $contentModerationService;

  /**
   * The content type config service.
   *
   * @var \Drupal\content_calendar\ContentTypeConfigService
   */
  protected $contentTypeConfigService;

  /**
   * The content calendar service.
   *
   * @var \Drupal\content_calendar\ContentCalendarService
   */
  protected $contentCalendarService;

  /**
   * Defines the Content Type Config entity.
   *
   * @var \Drupal\content_calendar\Entity\ContentTypeConfig[]
   */
  protected $contentTypeConfigEntities;

  /**
   * Desired months to be rendered.
   *
   * @var int
   */
  protected $month;

  /**
   * Desired year to be rendered.
   *
   * @var int
   */
  protected $year;

  /**
   * Calendar constructor.
   *
   * @param \Drupal\Core\Theme\ThemeManagerInterface $themeManager
   *   The theme manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\content_planner\ContentModerationService $contentModerationService
   *   The content moderation service.
   * @param \Drupal\content_calendar\ContentTypeConfigService $contentTypeConfigService
   *   The content type config service.
   * @param \Drupal\content_calendar\ContentCalendarService $contentCalendarService
   *   The content calendar service.
   */
  public function __construct(
    ThemeManagerInterface $themeManager,
    AccountProxyInterface $currentUser,
    ConfigFactoryInterface $configFactory,
    ContentModerationService $contentModerationService,
    ContentTypeConfigService $contentTypeConfigService,
    ContentCalendarService $contentCalendarService
  ) {
    $this->themeManager = $themeManager;
    $this->currentUser = $currentUser;
    $this->configFactory = $configFactory;
    $this->contentModerationService = $contentModerationService;
    $this->contentTypeConfigService = $contentTypeConfigService;
    $this->contentCalendarService = $contentCalendarService;
    $this->contentTypeConfigEntities = $this->contentTypeConfigService->loadAllEntities();
  }

  /**
   * @return static
   */
  public function setMonth(int $month): self {
    $this->month = $month;
    return $this;
  }

  /**
   * @return static
   */
  public function setYear(int $year): self {
    $this->year = $year;
    return $this;
  }

  /**
   * Generates a calendar id.
   *
   * @return string
   *   The calendar id.
   */
  protected function generateCalendarId() {
    return $this->year . '-' . $this->month;
  }

  /**
   * Creates the render array for the calendar.
   *
   * @return array
   *   The render array of the calendar.
   */
  public function build() {

    // Build data structure first.
    $calendar = $this->buildCalendarDataStructure();

    // Get nodes per node type.
    $node_basic_data = [];

    foreach ($this->contentTypeConfigEntities as $node_type => $config_entity) {
      $node_basic_data[$node_type] = $this->contentCalendarService->getNodesByType(
        $node_type,
        [
          'month' => $this->month,
          'year' => $this->year,
        ]
      );
    }

    // Place nodes in Calendars.
    $this->placeNodesInCalendars($calendar, $node_basic_data);

    // Get the weekdays based on the Drupal first day of week setting.
    $weekdays = DateTimeHelper::getWeekdays();

    $build = [
      '#theme' => 'content_calendar',
      '#calendar' => $calendar,
      '#weekdays' => $weekdays,
      '#node_type_creation_permissions' => $this->getPermittedNodeTypeCreationActions(),
      '#add_content_set_schedule_date' => $this->configFactory
        ->get('content_calendar.settings')
        ->get('add_content_set_schedule_date'),
      '#attached' => [
        'library' => ['content_calendar/calendar'],
      ],
    ];

    if ($this->isGinThemeActive()) {
      $build['#attached']['library'][] = 'content_calendar/gin';
    }

    return $build;
  }

  /**
   * Get all permitted Node Type Creation actions.
   *
   * @return array
   *   Returns an array with permitted node types.
   */
  protected function getPermittedNodeTypeCreationActions() {

    $permitted_node_types = [];

    foreach ($this->contentTypeConfigEntities as $node_type => $config_entity) {

      if ($this->currentUser->hasPermission("create $node_type content")) {
        $permitted_node_types[$node_type] = $config_entity;
      }

    }

    return $permitted_node_types;
  }

  /**
   * Build data structure for Calendar.
   *
   * @return array
   *   The data for the calendar.
   *
   * @throws \Exception
   */
  protected function buildCalendarDataStructure() {

    $today_datetime = new \DateTime();
    $today_datetime->setTime(0, 0, 0);

    $one_day_interval = new \DateInterval('P1D');

    // Get the first date of a given month.
    $datetime = DateTimeHelper::getFirstDayOfMonth($this->month, $this->year);

    $scaffold_data = [
      'calendar_id' => $this->generateCalendarId(),
      'month' => $this->month,
      'year' => $this->year,
      'label' => DateTimeHelper::getMonthLabelByNumber($this->month) . ' ' . $this->year,
      'first_date_weekday' => DateTimeHelper::getDayOfWeekByDate($datetime),
      'days' => [],
    ];

    // Calculate the days in a month.
    $days_in_month = DateTimeHelper::getDayCountInMonth($this->month, $this->year);

    // Build all dates in a month.
    $i = 1;
    while ($i <= $days_in_month) {

      $scaffold_data['days'][] = [
        'date' => $datetime->format('Y-m-d'),
        'day' => $datetime->format('j'),
        'weekday' => DateTimeHelper::getDayOfWeekByDate($datetime),
        'nodes' => [],
        'is_today' => ($today_datetime == $datetime) ? TRUE : FALSE,
      ];

      $i++;
      $datetime->add($one_day_interval);
    }

    return $scaffold_data;

  }

  /**
   * Place Nodes in Calendar.
   *
   * @param array $calendar
   *   Calendar array.
   * @param array $node_basic_data
   *   Array with node basic data.
   */
  protected function placeNodesInCalendars(array &$calendar, array $node_basic_data) {

    foreach ($node_basic_data as $node_type => $node_rows) {

      foreach ($node_rows as $node_row) {

        $calendar_entry = new CalendarEntry(
          $this->month,
          $this->year,
          $this->contentModerationService->getCurrentStateLabel('node', $node_type, $node_row->moderation_state),
          $this->getNodeTypeConfig($node_type),
          $node_row
        );

        foreach ($calendar['days'] as &$day) {

          // If date of entry is the current date of the calendar day.
          if ($day['date'] == $calendar_entry->formatSchedulingDateAsMySqlDateOnly()) {

            // Generate a unique key within the day for the entry.
            $key = $calendar_entry->getRelevantDate() . '_' . $calendar_entry->getNodeId();

            $day['nodes'][$key] = $calendar_entry->build();

            // Sort by keys.
            ksort($day['nodes']);
          }

        }
      }

    }

  }

  /**
   * Get Content Type config entity by Node Type.
   *
   * @param string $node_type
   *   The node type id to get the config.
   *
   * @return bool|\Drupal\content_calendar\Entity\ContentTypeConfig
   *   The content type config.
   */
  protected function getNodeTypeConfig($node_type) {

    if (array_key_exists($node_type, $this->contentTypeConfigEntities)) {
      return $this->contentTypeConfigEntities[$node_type];
    }

    return FALSE;
  }

  /**
   * Determines whether the current theme is Gin or a subtheme of Gin.
   *
   * @return bool
   *   TRUE if the current theme is Gin or a subtheme of Gin
   */
  protected function isGinThemeActive() {
    $theme = $this->themeManager->getActiveTheme();

    return $theme->getName() === 'gin' || isset($theme->getBaseThemeExtensions()['gin']);
  }

}
