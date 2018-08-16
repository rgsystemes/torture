#!/usr/bin/php -dphar.readonly=0
<?php

$buildRoot = realpath(__DIR__);

$phar = new Phar($buildRoot.'/torture.phar', 0, 'torture.phar');
$phar->buildFromIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($buildRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY), $buildRoot);
$phar->setStub($phar->createDefaultStub("torture.php"));
