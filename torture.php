#!/usr/bin/env php
<?php

require __DIR__.'/vendor/autoload.php';

use RG\Torture\Command\TortureCommand;
use Symfony\Component\Console\Application;

$application = new Application('torture', '1.0.0');
$command = new TortureCommand();

$application->add($command);

$application->setDefaultCommand($command->getName(), true);
