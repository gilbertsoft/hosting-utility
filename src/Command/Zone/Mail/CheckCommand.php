<?php
declare(strict_types=1);

/*
 * This file is part of the gilbertsoft/hosting-utility.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Gilbertsoft\HostingUtility\Command\Zone\Mail;

use Gilbertsoft\HostingUtility\Command\Zone\BaseCommand;
use Gilbertsoft\HostingUtility\Utility\DnsUtility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CheckCommand extends BaseCommand
{
    protected static $defaultName = 'zone:mail:check';

    protected const KEYS_IGNORED = [
        //'host',
        'class',
        'ttl',
        'entries',
    ];

    private const EXPECTED_RECORDS = [
        'MX' => [
            'type' => DNS_MX,
            'hosts' => [
                0 => [
                    'pri' => '10',
                    'target' => 'mail.gilbertsoft.email',
                ],
                1 => [
                    'pri' => '20',
                    'target' => 'mxbackup1.junkemailfilter.com',
                ],
                2 => [
                    'pri' => '30',
                    'target' => 'mxbackup2.junkemailfilter.com',
                ],
            ],
        ],
        'CNAME' => [
            'type' => DNS_CNAME,
            'hosts' => [
                'autoconfig' => [
                    'target' => 'mail.gilbertsoft.email',
                ],
                'autodiscover' => [
                    'target' => 'mail.gilbertsoft.email',
                ],
            ],
        ],
        'SRV' => [
            'type' => DNS_SRV,
            'hosts' => [
                '_imap._tcp' => [
                    'pri' => 0,
                    'weight' => 1,
                    'port' => 143,
                    'target' => 'mail.gilbertsoft.email',
                ],
                '_imaps._tcp' => [
                    'pri' => 0,
                    'weight' => 1,
                    'port' => 993,
                    'target' => 'mail.gilbertsoft.email',
                ],
                '_submission._tcp' => [
                    'pri' => 0,
                    'weight' => 1,
                    'port' => 587,
                    'target' => 'mail.gilbertsoft.email',
                ],
                '_smtps._tcp' => [
                    'pri' => 0,
                    'weight' => 1,
                    'port' => 465,
                    'target' => 'mail.gilbertsoft.email',
                ],
                '_autodiscover._tcp' => [
                    'pri' => 0,
                    'weight' => 1,
                    'port' => 443,
                    'target' => 'mail.gilbertsoft.email',
                ],
                '_carddavs._tcp' => [
                    'pri' => 0,
                    'weight' => 1,
                    'port' => 443,
                    'target' => 'mail.gilbertsoft.email',
                ],
                '_caldavs._tcp' => [
                    'pri' => 0,
                    'weight' => 1,
                    'port' => 443,
                    'target' => 'mail.gilbertsoft.email',
                ],
            ],
        ],
        'TXT' => [
            'type' => DNS_TXT,
            'hosts' => [
                '' => [
                    DnsUtility::OPTIONS => [
                        DnsUtility::OPT_EXPLICIT_PREFIX => 'v=spf',
                    ],
                    'txt' => 'v=spf1 redirect=gilbertsoft.net',
                ],
                '_carddavs._tcp' => [
                    'txt' => 'path=/SOGo/dav/',
                ],
                '_caldavs._tcp' => [
                    'txt' => 'path=/SOGo/dav/',
                ],
                '_dmarc' => [
                    'txt' => 'v=DMARC1; p=reject',
                ],
                'dkim._domainkey' => [
                    DnsUtility::OPTIONS => [
                        DnsUtility::OPT_SHORTEN_TO_LENGTH => true,
                    ],
                    'txt' => 'v=DKIM1;k=rsa;t=s;s=email;p=',
                ],
            ],
        ],
    ];

    protected function configure()
    {
        $this
            ->setDescription('Checks the mail services DNS configuration.')
            ->setHelp('This command allows you to check the mail services DNS configuration for the provided zones.')
        ;
    }

    protected function getExpectedRecords(): array
    {
        return self::EXPECTED_RECORDS;
    }

    protected function checkRecords(
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
     * @throws \RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->init($input, $output);

        //$messages = \is_array($message) ? array_values($message) : [$message];
        $passed = [];
        $failed = [];

        $zones = $this->getZoneNames();

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
