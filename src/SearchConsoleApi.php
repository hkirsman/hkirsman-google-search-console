<?php

namespace SearchConsoleApi;

/**
 * @file
 * SearchConsoleApi class file.
 */

use Google_Service_Webmasters;
use Google_Client;
use Google_Service_Webmasters_SearchAnalyticsQueryRequest;
use Google_Service_Webmasters_ApiDimensionFilter;
use Google_Service_Webmasters_ApiDimensionFilterGroup;

/**
 * Class SearchConsoleApi.
 *
 * SportScheck specific wrapper Google Search Console.
 *
 * @package Drupal\spo_google_search_console
 */
class SearchConsoleApi extends Google_Service_Webmasters {

  /**
   * @var Google_Service_Webmasters_SearchAnalyticsQueryRequest
   */
  public $query;
  private $queryOptions;
  private $client;
  private $authJson;
  private $applicationName;
  private $scopes;
  private $connectionInitTime = 0;

  const WEBMASTERS_ROW_LIMIT = 5000;

  /**
   * SportScheckWebmasters constructor.
   */
  public function __construct() {
    $this->applicationName = "SportScheckGoogleConsole";
    $this->scopes = ['https://www.googleapis.com/auth/webmasters.readonly'];
  }

  /**
   * Get default options for queries.
   *
   * @return array
   */
  public static function getDefaultOptions() {
    return [
      'site_url' => 'http://www.sportscheck.com/',
      'dimensions' => ['date', 'device', 'page', 'query', 'country'],
    ];
  }

  /**
   * Set up connection to Google.
   */
  public function initNewConnection() {
    if ($this->connectionInitTime === 0 || time() - $this->connectionInitTime > 3500) {
      $this->authJson = \Drupal::config('spo_google_search_console.auth')->get();
      $this->connectionInitTime = time();
      $this->client = new Google_Client();
      // Note that using json for "Service accounts" login is the prefered way
      // according to docs at vendor/google/apiclient/UPGRADING.md.
      $this->client->setAuthConfig($this->authJson);
      $this->client->setApplicationName($this->applicationName);
      $this->client->setScopes($this->scopes);
      parent::__construct($this->client);
    }
  }

  /**
   * Set query options.
   *
   * @param object $query_options
   *   Google_Service_Webmasters_SearchAnalyticsQueryRequest() object - creates
   *   the query.
   */
  public function setQueryOptions($query_options) {
    $this->query = new Google_Service_Webmasters_SearchAnalyticsQueryRequest();
    $this->query->setStartDate($query_options['start_date']);
    $this->query->setEndDate($query_options['end_date']);
    $this->query->setDimensions($query_options['dimensions']);
    $this->query->setRowLimit(self::WEBMASTERS_ROW_LIMIT);
    $this->query->setStartRow(0);
    $this->queryOptions = $query_options;
    if (isset($query_options['setDimensionFilterGroups'])) {
      $filter = new Google_Service_Webmasters_ApiDimensionFilter();
      $filter->setDimension($query_options['setDimensionFilterGroups']['filters']['dimension']);
      $filter->setOperator($query_options['setDimensionFilterGroups']['filters']['operator']);
      $filter->setExpression($query_options['setDimensionFilterGroups']['filters']['expression']);

      $filter_group = new Google_Service_Webmasters_ApiDimensionFilterGroup();
      $filter_group->setFilters([$filter]);

      $this->query->setDimensionFilterGroups([$filter_group]);
    }
  }

  /**
   * Get data from the Search Console API.
   *
   * @param array $options
   * @return array
   */
  public function getRows($options) {
    $data = [];
    $items = [];
    $row_count = 0;
    $request_count = 0;

    $this->initNewConnection();
    $this->setQueryOptions($options);

    // Start a timer.
    $start = microtime(TRUE);

    do {
      $request_count++;

      // Ask Google for data.
      try {
        $result = $this->searchanalytics->query($options['site_url'], $this->query);
      } catch (\Google_Service_Exception $e) {
        break;
      }

      $rows = $result->getRows();
      $row_count += count($rows);

      // Iterate all rows and fill items array.
      foreach ($rows as $row) {
        // Extract dimensions (see $query_options_base above) values from rows.
        $date = $row->keys[0];
        $device = $row->keys[1];
        $url = $row->keys[2];
        $keyword = $row->keys[3];
        $country = $row->keys[4];

        if (!isset($items[$url])) {
          $items[$url] = [
            'url' => $url,
            'search_console' => [],
          ];
        }

        // Prepare data for Elasticsearch record.
        $items[$url]['search_console'][] = [
          'date' => $date,
          'query' => $keyword,
          'clicks' => $row->clicks,
          'impressions' => $row->impressions,
          'avg_position' => round($row->position, 2),
          'device' => $device,
          'country' => $country,
        ];
      }

      // Calculating the offset for the next run.
      $new_offset = $this->query->getStartRow() + count($rows);

      // Set the new start row for the next query.
      $this->query->setStartRow($new_offset);
    } while (count($rows));

    // Stop the timer and round the result.
    $elapsed = round(microtime(TRUE) - $start, 3);

    // Make items in search_console unique.
    $items_unique = array_map(function ($item) {
      $item['search_console'] = array_values(array_unique($item['search_console'], SORT_REGULAR));

      return $item;
    }, $items);

    $data += [
      'date' => $options['start_date'],
      'time' => $elapsed,
      'items' => $items_unique,
      'row_count' => $row_count,
      'request_count' => $request_count,
    ];

    return $data;
  }

}
