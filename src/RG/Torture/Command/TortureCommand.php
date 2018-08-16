<?php

namespace RG\Torture\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TortureCommand extends Command
{
    const OPERATION_DISK_BW = 'disk-bw';
    const OPERATION_DISK_PING = 'disk-ping';
    const OPERATION_CPU = 'cpu';
    const OPERATION_NETWORK_CLIENT_BW = 'net-client-bw';
    const OPERATION_NETWORK_CLIENT_PING = 'net-client-ping';
    const OPERATION_NETWORK_SERVER = 'net-server';
    const OPERATION_MERGE_RESULTS = 'merge';

    protected function configure()
    {
        $this
            ->setName('rg:benchmark')
            ->setDescription('Benchmark the machine you\'re running on')
            ->addArgument('operation', InputArgument::REQUIRED, 'Define the aimed operation: available are merge, disk-bw, disk-ping, cpu, net-client-bw, net-client-ping or net-server')
            ->addOption('peer', null, InputOption::VALUE_OPTIONAL, 'Define the ip:port to connect at or listen from', '*:10000')
            ->addOption('buff-size', null, InputOption::VALUE_OPTIONAL, 'The buffer size to use: for network (default: 50MB) or disk (default: 1MB)', null)
            ->addOption('file', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'The result files to merge', null)
            ->addOption('deadline', null, InputOption::VALUE_OPTIONAL, 'Define the deadline the operation should run until', null)
        ;
    }

    private $abort = false;

    private $runDeadLine = null;

    private function shouldAbort()
    {
        if (!is_null($this->runDeadLine) && new \DateTime('now') > $this->runDeadLine)
            return true;

        pcntl_signal_dispatch();
        if ($this->abort)
            return true;

        return false;
    }

    public function abort($signalNumber)
    {
        $this->abort = true;
    }

    private function doCpu(AbstractBenchmarkStatistics $stats)
    {
        while (!$this->shouldAbort()) {
            $operations = 0;
            while ($operations < 100000)
                $operations ++;

            $stats->reportCycle($operations);
        }
    }

    private function createError($reason)
    {
        return new \Exception($reason);
    }

    /**
     * @param AbstractBenchmarkStatistics $stats
     * @param $peer
     * @throws \Exception
     */
    private function doNetworkServer(AbstractBenchmarkStatistics $stats, $peer)
    {
        if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false)
            throw $this->createError('socket_create error: ' . socket_strerror(socket_last_error()));

        list($address, $port) = explode(':', $peer);
        if ($address == "*")
            $address = 0;

        if (socket_bind($sock, $address, $port) === false)
            throw $this->createError('socket_bind error: ' . socket_strerror(socket_last_error($sock)));

        if (socket_listen($sock, 5) === false)
            throw $this->createError('socket_listen error: ' . socket_strerror(socket_last_error($sock)));

        do {
            if (($msgsock = socket_accept($sock)) === false)
                throw $this->createError('socket_accept error: ' . socket_strerror(socket_last_error($sock)));

            if (false === socket_recv($msgsock, $buf, 4, MSG_WAITALL))
                throw $this->createError('socket_recv error: ' . socket_strerror(socket_last_error($msgsock)));

            $size = unpack('l', $buf);
            $size = $size[1];
            $data = random_bytes($size);
            MessageManager::debug("Buffer size: $size");

            try {
                do {
                    socket_write($msgsock, $data, $size);
                    $buf = '';
                    if (false === socket_recv($msgsock, $buf, $size, MSG_WAITALL))
                        throw $this->createError('socket_recv error: ' . socket_strerror(socket_last_error($msgsock)));

                    // x2 because write + read
                    // x8 because we compute bits (not bytes)
                    $stats->reportCycle($size * 2 * 8);
                } while (!$this->shouldAbort());
            } catch(\Exception $e) {
                MessageManager::warning("Ending session because: " . $e->getMessage());
            }

            socket_close($msgsock);
        } while (!$this->shouldAbort());

        socket_close($sock);
    }

    /**
     * @param AbstractBenchmarkStatistics $stats
     * @param $peer
     * @param $size
     * @throws \Exception
     */
    private function doNetworkClient(AbstractBenchmarkStatistics $stats, $peer, $size)
    {
        if (($socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false)
            throw $this->createError('socket_create error: ' . socket_strerror(socket_last_error()));

        list($address, $port) = explode(':', $peer);
        if ($address == "*")
            $address = 'localhost';

        if (socket_connect($socket, $address, $port) === false)
            throw $this->createError('socket_connect error: ' . socket_strerror(socket_last_error($socket)));

        socket_write($socket, pack('l', $size), 4);

        do {
            $buf = '';
            if (false === socket_recv($socket, $buf, $size, MSG_WAITALL))
                throw $this->createError('socket_recv error: ' . socket_strerror(socket_last_error($socket)));

            // x2 because write + read
            // x8 because we compute bits (not bytes)
            $stats->reportCycle($size * 2 * 8);
            socket_write($socket, $buf, $size);
        } while (!$this->shouldAbort());

        socket_close($socket);
    }

    /**
     * @param AbstractBenchmarkStatistics $stats
     * @param $size
     */
    private function doIoStats(AbstractBenchmarkStatistics $stats, $size)
    {
        // Ensure the file exists
        $fileName = 'test-file';
        file_put_contents($fileName, $fileName);
        do {
            // Dependances : sudo pecl install dio-beta
            // vim /etc/php5/mods-available/dio.ini
            // Add extension=dio.so
            // php5enmod dio

            // Open file read + write, and reset the file to 0 at beginning
            $fd = dio_open($fileName, O_RDWR | O_TRUNC);

            // Start from 0
            dio_seek($fd, 0);

            // Build the data
            $data = random_bytes($size);

            // Write random stuff
            $stats->ignoreUntilNow();
            dio_write($fd, $data);
            $stats->reportCycle($size);

            // Close the opened file
            dio_close($fd);
        } while (!$this->shouldAbort());
    }

    private function doMergeFiles($resultFile, $files)
    {
        MessageManager::debug("Dumping merged results at: $resultFile");
        $mergedFd = fopen($resultFile, 'w');

        // Open all files
        $fds = array_map(function($file) {
            return fopen($file, 'r');
        }, $files);

        // Define the method used to consume a line in each files, and close when the end of file is reached
        $consume = function() use (& $fds) {
            // Map each file descriptor to the next line in the csv
            return array_map(function (& $fd) {
                // Handle the case when the fd has been converted into null, indicating we reached the end, thus close the file
                if (!is_resource($fd))
                    return null;

                // Read the line
                $data = fgetcsv($fd);
                if ($data === false) {
                    // If it's the end, close the file, and return null
                    fclose($fd);
                    $fd = null;
                    return null;
                }

                // Return the data
                return $data;
            }, $fds);
        };

        // Check when we reached the end of ALL files
        $isArrayEmpty = function($array) {
            foreach ($array as $element)
                if ($element)
                    return true;

            return false;
        };

        // Define how we should merge lines
        $merger = function($carry, $element) {
            // If the carry (the line we'd like to merge) is empty, use the first element as base
            if (!$carry)
                return $element;

            // Simply conserve elements if they are not 0
            foreach ($element as $key => $value)
                if ($value)
                    $carry[$key] = $value;

            return $carry;
        };

        // While we have at least one file returning a line
        while ($isArrayEmpty($lines = $consume())) {
            $mergedLine = [];
            // Iterate over all lines, and if there's data, merge it with the current line
            foreach ($lines as $line)
                if ($line)
                    $mergedLine = $merger($mergedLine, $line);

            // Put the merged line
            fputcsv($mergedFd, $mergedLine);
        }

        // Close the result file
        fclose($mergedFd);
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        // Disable memory limitation
        ini_set('memory_limit', '-1');

        // Initialize the message manager
        MessageManager::initialize($output);

        if (!pcntl_signal(SIGINT, array($this, 'abort')))
            throw new \Exception('Cannot listen to INT signal, stopping');

        parent::initialize($input, $output);
    }

    private function createFileName($operation)
    {
        $count = 0;
        $operation = 'results-' . $operation;
        $operationName = $operation . '.csv';
        while (true) {
            if (!file_exists($operationName))
                return $operationName;

            MessageManager::warning("Unable to use $operationName: file already exists");
            $operationName = $operation . '_' . ++$count . '.csv';
        }

        // Useless, but php storm need this
        return null;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($deadLine = $input->getOption('deadline')) {
            $parseRelative = date_parse("+$deadLine");
            if ($parseRelative['error_count'] || $parseRelative['warning_count'])
                throw new \Exception('Invalid deadline: ' . join(',', $parseRelative['errors'] + $parseRelative['warnings']));

            $this->runDeadLine = new \DateTime("+$deadLine", new \DateTimeZone('UTC'));
            MessageManager::debug("The deadline indicate the script will end at " . $this->runDeadLine->format(\DateTime::ISO8601) . " and run for " . (new \DateTime)->diff($this->runDeadLine)->format('%ad %hh %im %ss'));
        } else {
            MessageManager::debug("The script will run until you hit CTRL+C");
        }

        $size = $input->getOption('buff-size');
        $operation = $input->getArgument('operation');
        switch ($operation) {
            case self::OPERATION_CPU:
                $this->doCpu(new CpuStatistics($this->createFileName($operation)));
                break;

            case self::OPERATION_NETWORK_CLIENT_PING:
                $this->doNetworkClient(
                    new NetworkStatistics(
                        NetworkStatistics::PING,
                        $this->createFileName($operation)
                    ),
                    $input->getOption('peer'),
                    4
                );

                break;

            case self::OPERATION_NETWORK_CLIENT_BW:
                if (!$size)
                    $size = 52428800;

                $this->doNetworkClient(
                    new NetworkStatistics(
                        NetworkStatistics::BANDWIDTH,
                        $this->createFileName($operation)
                    ),
                    $input->getOption('peer'),
                    $size
                );

                break;

            case self::OPERATION_NETWORK_SERVER:
                $this->runDeadLine = null; // Ensure there's no deadline for the tcp server
                $this->doNetworkServer(
                    new NetworkStatistics(
                        NetworkStatistics::SERVER,
                        $this->createFileName($operation)
                    ),
                    $input->getOption('peer')
                );

                break;

            case self::OPERATION_DISK_PING:
                $this->doIoStats(new DiskIoStatistics(true, $this->createFileName($operation)), 4);
                break;

            case self::OPERATION_DISK_BW:
                if (!$size)
                    $size = 1048576;

                $this->doIoStats(new DiskIoStatistics(false, $this->createFileName($operation)), $size);
                break;

            case self::OPERATION_MERGE_RESULTS:
                $this->doMergeFiles($this->createFileName($operation), $input->getOption('file'));
                break;

            default:
                throw new \Exception('Unknown operation');
        }
    }
}
