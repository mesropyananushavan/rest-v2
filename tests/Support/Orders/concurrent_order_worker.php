#!/usr/bin/env php
<?php

declare(strict_types=1);

use Tests\Support\Orders\ConcurrentOrderWorker;

require dirname(__DIR__, 3).'/vendor/autoload.php';

exit(ConcurrentOrderWorker::main($argv));
