<?php
declare(strict_types=1);

/*
 * This file is part of the gilbertsoft/hosting-utility.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Gilbertsoft\HostingUtility\Console;

use Gilbertsoft\HostingUtility\Command;
use Symfony\Component\Console\Application as BaseApplication;

/**
 * Application
 */
class Application extends BaseApplication
{
    const VERSION = '1.1.0-DEV';

    public function __construct()
    {
        parent::__construct('Zone Checker', self::VERSION);
        $this->add(new Command\TestCommand());
        $this->add(new Command\Zone\CheckCommand());
        $this->add(new Command\Zone\Mail\CheckCommand());
    }
}
