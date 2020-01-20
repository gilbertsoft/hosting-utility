<?php
declare(strict_types=1);

/*
 * This file is part of the gilbertsoft/hosting-utility.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Gilbertsoft\HostingUtility\Command\Zone;

use Gilbertsoft\HostingUtility\Utility\DnsUtility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CheckCommand extends AbstractCommand
{
    protected static $defaultName = 'zone:check';

    protected function configure()
    {
        $this->setName(self::$defaultName);
        $this->setDescription('Run a test');
        $this->setDefinition(
            new InputDefinition([
                new InputArgument('zone', InputArgument::REQUIRED)
            ])
        );
    }

    /**
     * @throws \RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $zone = $input->getArgument('zone');
        if (!$zone) {
            $io->error('No valid zone provided!');
            exit(1);
        }

        $host = DnsUtility::getPrimaryMXHost($zone);
        if (!$host) {
            $io->error('No host found!');
            exit(1);
        }

        $io->comment('Host "' . $host . '" found for zone "' . $zone . '"');
        $io->success('Host "' . $host . '" found for zone "' . $zone . '"');

        $hosts = DnsUtility::getMXHosts($zone);

        foreach ($hosts as $pri => $host) {
            $io->comment($host . ' (' . $pri . ')');
        }
    }
}
