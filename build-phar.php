#!/usr/bin/php -dphar.readonly=0
<?php

$vendorRoot = realpath(__DIR__."");
$buildRoot = realpath(__DIR__);

echo "Build Symfony Console phar\n";
$phar = new Phar($buildRoot.'/torture.phar', 0, 'torture.phar');
$phar->buildFromIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vendorRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY), $vendorRoot);

$phar->addFile("torture.php");

$phar->addFile("src/RG/Torture/Command/StatisticCsvDumper.php");
$phar->addFile("src/RG/Torture/Command/NetworkStatistics.php");
$phar->addFile("src/RG/Torture/Command/MessageManager.php");
$phar->addFile("src/RG/Torture/Command/CpuStatistics.php");
$phar->addFile("src/RG/Torture/Command/AbstractBenchmarkStatistics.php");
$phar->addFile("src/RG/Torture/Command/DiskIoStatistics.php");
$phar->addFile("src/RG/Torture/Command/Utils.php");
$phar->addFile("src/RG/Torture/Command/TortureCommand.php");

$phar->setStub($phar->createDefaultStub("torture.php"));

exit("Build complete\n");
