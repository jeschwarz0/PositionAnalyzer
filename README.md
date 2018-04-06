# PositionAnalyzer

Provides analysis functionality for JobApis Job objects using configuration from a provided xml configuration file.

## Getting Started

### Prerequisites

```
php >= 5.5.0
```

### Installing

To install, use composer:

```
composer require jschwarz/position-analyzer
```

### Usage

Create a PositionAnalyzer object with the path to the xml configuration file.
```php
$analyzer = new \JobApis\Utilities\PositionAnalyzer('/path/to/xml');
```
Analyze each Job to array:
```php
$result = $analyzer->analyzePositionToArray($job);
```
(Optional) Use BuildSummaryList1() to generate HTML
```php
$html = \JobApis\Utilities\PositionAnalyzer::BuildSummaryList1($result, 1)
```
### Schemas
See [dtd](dtd/)

## Authors

* **Jesse Schwarz** - *Initial work*


## License

Default

## Acknowledgments

* [JobApis](http://www.jobapis.com) Community