<?php

namespace RG\Torture\Command;

use Symfony\Component\Console\Output\OutputInterface;

class MessageManager
{
    /**
     * @var OutputInterface
     */
    static private $stream = null;

    static public function initialize(OutputInterface $output)
    {
        static::$stream = $output;
    }

    static public function debug($message)
    {
        if (is_null(static::$stream))
            throw new \Exception("No stream found. MessageManager is not initialized?");

        static::$stream->writeln($message);
    }

    static public function warning($message)
    {
        static::debug("Warning: $message");
    }
}
