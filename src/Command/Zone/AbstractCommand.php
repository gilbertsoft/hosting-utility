<?php
declare(strict_types=1);

/*
 * This file is part of the gilbertsoft/hosting-utility.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Gilbertsoft\HostingUtility\Command\Zone;

use Gilbertsoft\HostingUtility\Command\AbstractCommand as AbstractBaseCommand;
use Gilbertsoft\HostingUtility\Utility\DnsUtility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class AbstractCommand extends AbstractBaseCommand
{
    protected const KEYS_IGNORED = [
        //'host',
        'class',
        'ttl',
        'entries',
    ];

    private function init(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->io = new SymfonyStyle($input, $output);
    }

    private function getZoneNames(): array
    {
        return explode(',', $this->input->getArgument('zones'));
    }

    private function checkRecords(
        string $name,
        array $recordsExpected,
        int $type,
        string $zone
    ): bool {
        if (is_int(array_key_first($recordsExpected))) {
            $recordsDiff = DnsUtility::checkRecords($recordsExpected, static::KEYS_IGNORED, $type, $zone);
        } else {
            $recordsDiff = [];
            foreach ($recordsExpected as $host => $expected) {
                $recordsDiff = array_merge_recursive(
                    $recordsDiff,
                    DnsUtility::checkRecords($expected, static::KEYS_IGNORED, $type, $zone, $host)
                );
            }
        }

        if (!empty($recordsDiff)) {
            $this->io->text('x ' . $name);

            if (\array_key_exists('missing', $recordsDiff)) {
                $this->io->section('Missing:');
                $this->io->text(var_export($recordsDiff['missing']));
                $this->io->newLine();
            }

            if (\array_key_exists('unknown', $recordsDiff)) {
                $this->io->section('Unknown:');
                $this->io->text(var_export($recordsDiff['unknown']));
                $this->io->newLine();
            }

            $this->io->newLine();

            return false;
        } else {
            $this->io->text('√ ' . $name);

            return true;
        }
    }

    protected function checkZone($zone): bool
    {
        $error = false;

        /*
        $hosts = DnsUtility::getMXHosts($zone);

        $mxHostsDiff = array_diff([
            10 => 'mail.gilbertsoft.email',
            20 => 'mxbackup1.junkemailfilter.com',
            30 => 'mxbackup2.junkemailfilter.com',
        ], $hosts);

        if (!empty($mxHostsDiff)) {
            $error = true;
            $this->io->error('MX: ' . implode(',', $mxHostsDiff));
        } else {
            $this->io->success('MX');
        }
        */

        foreach (self::EXPECTED_RECORDS as $test => $value) {
            $error = !static::checkRecords(
                $test,
                $value['hosts'],
                $value['type'],
                $zone
            ) || $error;
        }

        return !$error;
    }

    /**
     * @param string[] $zones
     */
    protected function processZones($zones): void
    {
        $passed = [];
        $failed = [];

        foreach ($zones as $zone) {
            $this->io->title('Checking "' . $zone . '"');

            if ($this->checkZone($zone)) {
                $passed[] = $zone;
            } else {
                $failed[] = $zone;
            }
        }

        sort($passed);
        sort($failed);

        $this->io->success(implode(',', $passed));
        $this->io->warning(implode(',', $failed));
    }

    /**
     * @throws \RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init($input, $output);

        $zones = $this->getZoneNames();

        $this->processZones($zones);
    }

    /**
     * @param string[] $zones
     */
    public function processZones($zones): void
    {
        $passed = [];
        $failed = [];

        foreach ($zones as $zone) {
            $this->io->title('Checking "' . $zone . '"');

            if ($this->checkZone($zone)) {
                $passed[] = $zone;
            } else {
                $failed[] = $zone;
            }
        }

        sort($passed);
        sort($failed);

        $this->io->success(implode(',', $passed));
        $this->io->warning(implode(',', $failed));
    }
}
