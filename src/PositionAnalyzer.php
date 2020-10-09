<?php namespace JobApis\Utilities;

class PositionAnalyzer
{
    protected $_config;

    /**
     * 
     * Constructs the class.
     * Instantiates configuration object.
     */
    public function __construct($configPath)
    {
        $this->_config = FALSE;
        if (is_string($configPath) && file_exists($configPath)){
            $fileContents = file_get_contents($configPath);
            if (isset($fileContents) && $fileContents !== FALSE){
                $this->_config = simplexml_load_string($fileContents);
                $this->buildPercentTables();
            }
        }
    }

    /**
     * 
     * Destructs the class.
     */
    public function __destruct()
    {
        unset($this->_config);
    }

    /**
     * 
     * Checks if the base configuration is valid.
     * @return bool True if configuration is loaded and valid.
     */
    public function isValid()
    {
        return $this->_config !== FALSE;
    }

    /**
     * 
     * Processes a JobApis Job object and generates analysis information based off it.
     * Format: 
     *   [CategoryName] => Array
     *       (
     *           [entries] => Array
     *               (
     *                   [EntryName] => Array
     *                       (
     *                           [score] => 
     *                           [is_match] => 
     *                       )
     *               )
     *           [sum] => 
     *           [pct] => 
     *           [any_match] => 
     *           [title_match] => 
     *           [is_global] => SimpleXMLElement Object
     *               (
     *                   [0] => 
     *               )
     * 
     * @param \JobApis\Jobs\Client\Job $position The position to be analyzed.
     * @return array|FALSE Returns an array with summary results or FALSE on failure.
     */
    public function analyzePositionToArray(&$position)
    {
        if (!$this->isValid()) {return FALSE;}
        $config_version = intval($this->_config->ConfigVersion);
        if ($config_version > 2) {return FALSE;}// Do not continue if version is > 2
        $result = array();// Initialize result
        foreach ($this->_config->SearchCategory as $categoryArr) {
            //Initialize entries
            $result[(string) $categoryArr->Name] = array();
            $result[(string) $categoryArr->Name]['entries'] = array();
            foreach ($categoryArr->CategoryValue as $categoryVal) {
                $matchIdx = -1;
                for ($entryIdx = 0; $matchIdx === -1 && $entryIdx < $this->_config->SearchEntry->count(); $entryIdx++) {
                    if (strcmp($this->_config->SearchEntry[$entryIdx]->Name, $categoryVal->EntryName) === 0) {
                        $matchIdx = $entryIdx;
                    }
                }
                if ($matchIdx !== -1) {
                    $found = false;
                    for ($tidx = 0;!$found && $tidx < $this->_config->SearchEntry[$matchIdx]->SearchTerms->Term->count(); $tidx++) {
                        if (stripos($position->description, (string) $this->_config->SearchEntry[$matchIdx]->SearchTerms->Term[$tidx][0]) !== false) {
                            $found = true;
                        }

                    }
                    $entryRec = array();
                    $entryRec['score'] = intval($found ? $categoryVal->MatchValue : $categoryVal->NonMatchValue);
                    $entryRec['is_match'] = $found;
                    $result[(string) $categoryArr->Name]['entries'][(string) $categoryVal->EntryName] = $entryRec;
                }
            }
            //Generate sum of values
            $sum = array_sum(array_column($result[(string) $categoryArr->Name]['entries'], 'score'));
            $result[(string) $categoryArr->Name]['sum'] = $sum;
            $result[(string) $categoryArr->Name]['pct'] = PositionAnalyzer::calculatePercentage($sum, $categoryArr['min'], $categoryArr['max']);
            $result[(string) $categoryArr->Name]['any_match'] = in_array(true, array_column($result[(string) $categoryArr->Name]['entries'], 'is_match'));
            if ($config_version >= 2) {
                $title_match = false;
                if (isset($categoryArr->CategoryTitle)) {
                    for ($titleIdx = 0;!$title_match && $titleIdx < $categoryArr->CategoryTitle->Term->count(); $titleIdx++) {
                        if (stripos($position->title, (string) $categoryArr->CategoryTitle->Term[$titleIdx]) !== false) {
                            $title_match = true;//Set $title_match if there is a match
                        }
                    }
                }
                $result[(string) $categoryArr->Name]['title_match'] = $title_match;
                $result[(string) $categoryArr->Name]['is_global'] = $categoryArr['isglobal'];
            }
        }
        return $result;
    }

    /**
     * 
     * Appends percentage summary information to configuration object.
     * Calculates the min and max scores of every CategoryValue.
     */
    private function buildPercentTables()
    {
        if (!$this->isValid() || intval($this->_config->ConfigVersion) <= 0) {return false;}
        foreach ($this->_config->SearchCategory as $categoryArr) {
            $catmin = 0;
            $catmax = 0;// For each CategoryValue add the min and max attributes
            foreach ($categoryArr->CategoryValue as $categoryVal) {
                $catmin += min(intval($categoryVal->MatchValue), intval($categoryVal->NonMatchValue));
                $catmax += max(intval($categoryVal->MatchValue), intval($categoryVal->NonMatchValue));
            }
            $categoryArr->addAttribute('min', $catmin);
            $categoryArr->addAttribute('max', $catmax);
        }
    }

    /**
     * 
     * Calculates a percentage ranging from 0 to max or min.
     * Negative values are possible.
     * @param int $sum The value to calculate
     * @param int $min The minimum possible value
     * @param int $max The maximum possible value
     * @return int The percentage (positive or negative) rounded.
     */
    private static function calculatePercentage(&$sum, $min, $max)
    {
        $divisor = intval($sum > 0 ? $max : $min);
        $pct = 0;
        if ($sum !== 0 && $divisor !== 0) {
            $pct = ($sum / $divisor) * 100;
        }
        if ($sum < 0 || $divisor < 0) {
            $pct *= -1;
        }
        return round($pct);
    }
    
    /**
     * 
     * Generates an HTML list from analyzer output array with tabs.
     * Output uses the following css classes:
     * pa-catmatch: The category matches (>= 0%)
     * pa-catmismatch: The category does not match (< 0%)
     * pa-titlematch: The title matches a value for category
     * pa-globalcat: The category is global
     * 
     * @param array $input The analyzer summary to use
     * @param int $tabCount The number of tabs to prepend to html output
     * @return string The html markup of the analysis summary.
     */
    public static function BuildSummaryList1(&$input, $tabCount = 0)
    { 
        $html = $tabs = '';
        // Build tab prefix of $tabCount
        if (is_int($tabCount)){
            for ($tabidx = 0; $tabidx < $tabCount; $tabidx++){
                $tabs .= "\t";
            }
        }else {$tabs = '';}
        if (is_array($input)){
            $html .= "$tabs<ul>" . PHP_EOL;
            foreach ($input as $categoryKey => $categoryValue){
                if ($categoryValue['any_match'] === true){
                    $verbose_summary = 'title="' . $categoryValue['sum'] . ': ';
                    foreach($categoryValue['entries'] as $entryKey => $entryValue){
                        if ($entryValue['score'] !== 0){
                            $verbose_summary .= htmlspecialchars($entryKey) . "(" . 
                                    $entryValue['score'] . ($entryValue['is_match'] ? 'm' : 'n') . ") ";
                        }    
                        }
                    $verbose_summary .= '"';
                    $html.= "$tabs\t<li $verbose_summary" . ' class="' . 
                            ($categoryValue['pct'] >= 0 ? 'pa-catmatch' : 'pa-catmismatch') . 
                            ($categoryValue['title_match'] ? ' pa-titlematch' : '') . 
                            ($categoryValue['is_global'] ? ' pa-globalcat' : '') . '">' . 
                            htmlspecialchars($categoryKey) . " : " . 
                            $categoryValue['pct'] . "%</li>" . PHP_EOL;
                }
            }
            $html .= "$tabs</ul>" . PHP_EOL;
        }
        return $html;
    }
}
?>