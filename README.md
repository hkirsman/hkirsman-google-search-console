# hkirsman/google-search-console

Wrapper for google/apiclient to access and retrieve data from Google Search Console using PHP. You can find this project in both https://packagist.org/packages/hkirsman/google-search-console and https://github.com/hkirsman/hkirsman-google-search-console

## Installation (using Composer)

Add hkirsman/google-search-console to your project:

```
composer require hkirsman/google-search-console:dev-master
```

Get key from https://console.developers.google.com. There you have to create a project if you don't have it already, enable the 'Google Search Console API' and create 'Service account key' (json format). Add this keyfile to your project root or define custom path like this:
```
$searchConsole = SearchConsoleApi('/foo/bar/service-account.json');
```
instead of just
```
$searchConsole = new SearchConsoleApi();
```

You'll also have to get Service account ID (XXXX@developer.gserviceaccount.com) and add it as user in the https://www.google.com/webmasters/tools/.

## Example

Try this example file. Replace `http://www.example.com/` with your url and also `'expression' => '/SUBPATH/',`

```php
<?php

require_once 'vendor/autoload.php';

use HannesKirsman\GoogleSearchConsole\SearchConsoleApi;

$searchConsole = new SearchConsoleApi();
$options = SearchConsoleApi::getDefaultOptions();
$options['site_url'] = 'http://www.example.com/';
$options['start_date'] = date('Y-m-d', strtotime("-3 days"));;
$options['end_date'] = date('Y-m-d', strtotime("-3 days"));;
$options['setDimensionFilterGroups'] = array(
  'filters' => array (
    'dimension' => 'page',
    'operator' => 'contains',
    'expression' => '/SUBPATH/',
  ),
);

$rows = $searchConsole->getRows($options);
print_r($rows);
```
