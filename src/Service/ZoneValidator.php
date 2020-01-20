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
     * @param string $zone
     * @param string $hostname
     * @return array
     */
    public function getRecords(int $type, string $hostname): array
    {
        return dns_get_record($hostname, $type);
    }

    private function filterRecords(array $ignoreKeys, array $records): array
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

    private static function sortRecords(array &$records): bool
    {
        return usort($records, array(self::class, "sortRecordsCmp"));
    }

    private static function arrayDiffRecursive($array1, $array2)
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
    public static function checkRecords(
        array $recordsExpected,
        array $ignoreKeys,
        int $type,
        string $zone,
        string $host = ''
    ): array {
        $hostname = !empty($host) ? $host . '.' . $zone : $zone;

        $records = static::getRecords($type, $hostname);

        static::sortRecords($records);
        $filtered = static::filterRecords($ignoreKeys, $records);

        if (!is_int(array_key_first($recordsExpected))) {
            $expected[] = $recordsExpected;
        } else {
            $expected = $recordsExpected;
        }


        foreach ($expected as $eKey => $eValue) {
            $expected[$eKey]['host'] = $hostname;
            \ksort($expected[$eKey]);

            foreach ($filtered as $key => $value) {
                if ($type == DNS_TXT) {
                    if (array_key_exists(static::OPTIONS, $eValue)) {
                        foreach ($eValue[static::OPTIONS] as $option => $oValue) {
                            switch ($option) {
                                case static::OPT_EXPLICIT_PREFIX:
                                    if (substr($value['txt'], 0, strlen($oValue)) !== $oValue) {
                                        unset($filtered[$key]);
                                    }
                                    break;
                                case static::OPT_SHORTEN_TO_LENGTH:
                                    $filtered[$key]['txt'] = substr($value['txt'], 0, strlen($eValue['txt']));
                                    break;
                                default:
                                    throw new Exception('Error option ' . $option . ' not implemented!');
                            }
                        }

                        unset($expected[$eKey][static::OPTIONS]);
                    }
                }

                if (array_key_exists($key, $filtered) && \is_array($filtered[$key])) {
                    \ksort($filtered[$key]);
                }
            }
        }

        $filtered = array_values($filtered);

        static::sortRecords($expected);

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
