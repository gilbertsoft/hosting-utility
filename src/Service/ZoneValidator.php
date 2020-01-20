<?php
declare(strict_types=1);

/*
 * This file is part of the gilbertsoft/hosting-utility.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Gilbertsoft\HostingUtility\Service;

/**
 * ZoneValidator
 */
class ZoneValidator
{
    public const OPTIONS = '_options';
    public const OPT_EXPLICIT_PREFIX = 'explicit_prefix';
    public const OPT_SHORTEN_TO_LENGTH = 'shorten_to_length';

    /**
     * @param integer $type
     * @param string $hostname
     * @return array
     */
    protected function getRecords(int $type, string $hostname): array
    {
        return dns_get_record($hostname, $type);
    }

    protected function filterRecords(array $ignoreKeys, array $records): array
    {
        foreach ($records as $recordNo => $record) {
            foreach ($record as $key => $record) {
                if (in_array($key, $ignoreKeys)) {
                    unset($records[$recordNo][$key]);
                }
            }
        }

        return $records;
    }

    private function sortRecordsCmp($a, $b): int
    {
        if (is_array($a) && is_array($b)) {
            if (array_key_exists('target', $a) && array_key_exists('target', $b)) {
                return strcmp($a["target"], $b["target"]);
            } elseif (array_key_exists('txt', $a) && array_key_exists('txt', $b)) {
                return strcmp($a["txt"], $b["txt"]);
            }
        }

        return 0;
    }

    protected function sortRecords(array &$records): bool
    {
        return usort($records, array(__CLASS__, "sortRecordsCmp"));
    }

    protected function arrayDiffRecursive($array1, $array2)
    {
        $return = [];

        foreach ($array1 as $mKey => $mValue) {
            if (array_key_exists($mKey, $array2)) {
                if (is_array($mValue)) {
                    $aRecursiveDiff = self::arrayDiffRecursive($mValue, $array2[$mKey]);
                    if (count($aRecursiveDiff)) {
                        $return[$mKey] = $aRecursiveDiff;
                    }
                } else {
                    if ($mValue != $array2[$mKey]) {
                        $return[$mKey] = $mValue;
                    }
                }
            } else {
                $return[$mKey] = $mValue;
            }
        }

        return $return;
    }

    /**
     * @param string $zone
     * @param string $type
     * @param string $host
     * @return array
     */
    public function checkRecords(
        array $recordsExpected,
        array $ignoreKeys,
        int $type,
        string $zone,
        string $host = ''
    ): array {
        $hostname = !empty($host) ? $host . '.' . $zone : $zone;

        $records = $this->getRecords($type, $hostname);

        $this->sortRecords($records);
        $filtered = $this->filterRecords($ignoreKeys, $records);

        if (!is_int(array_key_first($recordsExpected))) {
            $expected[] = $recordsExpected;
        } else {
            $expected = $recordsExpected;
        }


        foreach ($expected as $eKey => $eValue) {
            // Handle default keys
            $expected[$eKey]['host'] = $hostname;

            switch ($type) {
                // DNS_A, DNS_CNAME, DNS_HINFO, DNS_CAA, DNS_MX, DNS_NS, DNS_PTR, DNS_SOA, DNS_TXT, DNS_AAAA, DNS_SRV,
                // DNS_NAPTR, DNS_A6
                case DNS_CNAME:
                    $strType = 'CNAME';
                    break;
                case DNS_MX:
                    $strType = 'MX';
                    break;
                case DNS_TXT:
                    $strType = 'TXT';
                    break;
                case DNS_SRV:
                    $strType = 'SRV';
                    break;
                default:
                    throw new Exception('Error type ' . $type . ' not implemented!');
            }

            $expected[$eKey]['host'] = $strType;

            \ksort($expected[$eKey]);

            // Apply special options
            if ($type == DNS_TXT) {
                foreach ($filtered as $key => $value) {
                    if (array_key_exists($this->OPTIONS, $eValue)) {
                        foreach ($eValue[$this->OPTIONS] as $option => $oValue) {
                            switch ($option) {
                                case $this->OPT_EXPLICIT_PREFIX:
                                    if (substr($value['txt'], 0, strlen($oValue)) !== $oValue) {
                                        unset($filtered[$key]);
                                    }
                                    break;
                                case $this->OPT_SHORTEN_TO_LENGTH:
                                    $filtered[$key]['txt'] = substr($value['txt'], 0, strlen($eValue['txt']));
                                    break;
                                default:
                                    throw new Exception('Error option ' . $option . ' not implemented!');
                            }
                        }

                        unset($expected[$eKey][$this->OPTIONS]);
                    }

                    if (array_key_exists($key, $filtered) && \is_array($filtered[$key])) {
                        \ksort($filtered[$key]);
                    }
                }
            }
        }

        $filtered = array_values($filtered);

        $this->sortRecords($expected);

        //var_dump($expected);
        //var_dump($filtered);

        $result['missing'] = self::arrayDiffRecursive($expected, $filtered);
        $result['unknown'] = self::arrayDiffRecursive($filtered, $expected);

        if (empty($result['missing'])) {
            unset($result['missing']);
        }

        if (empty($result['unknown'])) {
            unset($result['unknown']);
        }

        return $result;
    }
}
