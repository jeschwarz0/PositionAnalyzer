<?php namespace JobApis\Utilities;

class PositionAnalyzer
{
    protected $_config;

    public function __construct($configPath)
    {
        $this->_config = FALSE;
        if (is_string($configPath) && file_exists($configPath))
            $fileContents = file_get_contents($configPath);
            if (isset($fileContents) && $fileContents !== FALSE){
                $this->_config = simplexml_load_string($fileContents);
                $this->buildPercentTables();
            }
    }

    public function __destruct()
    {
        unset($this->_config);
    }

    public function isValid()
    {
        return $this->_config !== FALSE;
    }

    public function analyzePositionToArray(&$position)
    {
        if (!$this->isValid()) return FALSE;
        $config_version = intval($this->_config->ConfigVersion);
        if ($config_version > 2) return FALSE;
        $result = array();
        foreach ($this->_config->SearchCategory as $categoryArr) {
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
            $sum = array_sum(array_column($result[(string) $categoryArr->Name]['entries'], 'score'));
            $result[(string) $categoryArr->Name]['sum'] = $sum;
            $result[(string) $categoryArr->Name]['pct'] = PositionAnalyzer::calculatePercentage($sum, $categoryArr['min'], $categoryArr['max']);
            $result[(string) $categoryArr->Name]['any_match'] = in_array(true, array_column($result[(string) $categoryArr->Name]['entries'], 'is_match'));
            if ($config_version >= 2) {
                $title_match = false;
                if (isset($categoryArr->CategoryTitle)) {
                    for ($titleIdx = 0;!$title_match && $titleIdx < $categoryArr->CategoryTitle->Term->count(); $titleIdx++) {
                        if (stripos($position->title, (string) $categoryArr->CategoryTitle->Term[$titleIdx]) !== false) {
                            $title_match = true;
                        }

                    }
                }
                $result[(string) $categoryArr->Name]['title_match'] = $title_match;
                $result[(string) $categoryArr->Name]['is_global'] = $categoryArr['isglobal'];
            }
        }
        return $result;
    }

    private function buildPercentTables()
    {
        if (!$this->isValid() || intval($this->_config->ConfigVersion) <= 0) return false;
        foreach ($this->_config->SearchCategory as $categoryArr) {
            $catmin = 0;
            $catmax = 0;
            foreach ($categoryArr->CategoryValue as $categoryVal) {
                $catmin += min(intval($categoryVal->MatchValue), intval($categoryVal->NonMatchValue));
                $catmax += max(intval($categoryVal->MatchValue), intval($categoryVal->NonMatchValue));
            }
            $categoryArr->addAttribute('min', $catmin);
            $categoryArr->addAttribute('max', $catmax);
        }
    }

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
     * 
     * @param array $input The analyzer summary to use
     * @param int $tabCount The number of tabs to prepend to html output
     * @return string The html markup of the analysis summary.
     */
    public static function BuildSummaryList1(&$input, $tabCount = 0)
    { 
        $html = $tabs = '';
        // Build tab prefix of $tabCount
        if (is_int($tabCount))
            for ($tabidx = 0; $tabidx < $tabCount; $tabidx++)
                $tabs .= "\t";
        else $tabs = '';
        if (is_array($input)){
            $html .= "$tabs<ul>" . PHP_EOL;
            foreach ($input as $categoryKey => $categoryValue){
                if ($categoryValue['any_match'] === true){
                    $verbose_summary = 'title="' . $categoryValue['sum'] . ': ';
                    foreach($categoryValue['entries'] as $entryKey => $entryValue){
                        if ($entryValue['score'] !== 0)
                            $verbose_summary .= htmlspecialchars($entryKey) . "(" . $entryValue['score'] . ($entryValue['is_match'] ? 'm' : 'n') . ") ";
                    }
                    $verbose_summary .= '"';
                    $html.= "$tabs\t<li $verbose_summary" . ' class="' . ($categoryValue['pct'] >= 0 ? 'pa-catmatch' : 'pa-catmismatch') . ($categoryValue['title_match'] ? ' pa-titlematch' : '') . ($categoryValue['is_global'] ? ' pa-globalcat' : '') . '">' . htmlspecialchars($categoryKey) . " : " . $categoryValue['pct'] . "%</li>" . PHP_EOL;
                }
            }
            $html .= "$tabs</ul>" . PHP_EOL;
        }
        return $html;
    }
}
?>