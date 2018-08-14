<?php

require_once('vendor/autoload.php');

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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

class Utils
{
    /**
     * Format a number with the correct dividing style, and the given suffix
     *
     * Supports a wide range from 10^-24 to 10^24
     *
     *   $this->compare(Utils::formatNumber(1000000000000000000000000, 'L', true), '1.00 YL');
     *   $this->compare(Utils::formatNumber(1000000000000000000000, 'L', true), '1.00 ZL');
     *   $this->compare(Utils::formatNumber(1000000000000000000, 'L', true), '1.00 EL');
     *   $this->compare(Utils::formatNumber(1000000000000000, 'L', true), '1.00 PL');
     *   $this->compare(Utils::formatNumber(1000000000000, 'L', true), '1.00 TL');
     *   $this->compare(Utils::formatNumber(1000000000, 'L', true), '1.00 GL');
     *   $this->compare(Utils::formatNumber(1000000, 'L', true), '1.00 ML');
     *   $this->compare(Utils::formatNumber(1000, 'L', true), '1.00 kL');
     *   $this->compare(Utils::formatNumber(1, 'L', true), '1.00 L');
     *   $this->compare(Utils::formatNumber(0.001, 'L', true), '1.00 mL');
     *   $this->compare(Utils::formatNumber(0.000001, 'L', true), '1.00 µL');
     *   $this->compare(Utils::formatNumber(0.000000001, 'L', true), '1.00 nL');
     *   $this->compare(Utils::formatNumber(0.000000000001, 'L', true), '1.00 pL');
     *   $this->compare(Utils::formatNumber(0.000000000000001, 'L', true), '1.00 fL');
     *   $this->compare(Utils::formatNumber(0.000000000000000001, 'L', true), '1.00 aL');
     *   $this->compare(Utils::formatNumber(0.000000000000000000001, 'L', true), '1.00 zL');
     *   $this->compare(Utils::formatNumber(0.000000000000000000000001, 'L', true), '1.00 yL');
     *
     * @param $value
     * @param string $suffix
     * @param bool $metric
     * @param int $decimals
     * @return string
     */
    public static function formatNumber($value, $suffix = 'B', $metric = false, $decimals = 2)
    {
        $unit = $metric ? 1000 : 1024;
        if ($value < $unit) {
            if ($unit == 1024 || $value == 0) // there's no milli bits/s :P
                return "$value $suffix";

            $prefixes = [
                '', 'm', 'µ', 'n', 'p', 'f', 'a', 'z', 'y'
            ];

            $exp = 0;
            while ($value < 1 && $exp < count($prefixes) - 1) {
                $value = $value * $unit;
                $exp ++;
            }
        } else {
            $prefixes = $metric ? [
                '', 'k', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y'
            ] : [
                '', 'Ki', 'Mi', 'Gi', 'Ti', 'Pi', 'Ei', 'Zi', 'Yi'
            ];

            $exp = (int)max(1, min(count($prefixes) - 1, log($value) / log($unit)));
            $value = $value / pow($unit, $exp);
        }

        $result = number_format($value, $decimals) . ' ' . $prefixes[$exp];
        return "$result$suffix";
    }
}

abstract class AbstractBenchmarkStatistics
{
    /**
     * Define the frequency we should aggregate statistics in seconds
     *
     * @var int
     */
    private $frequency;

    private $currentCycles;
    private $totalCycles;
    private $currentAmount;
    private $totalAmount;
    private $timeConsumed;

    private $lastCycle;
    private $lastReport;

    public function __construct($frequency = 1)
    {
        $this->frequency = $frequency;

        $this->currentCycles = 0;
        $this->totalCycles = 0;
        $this->currentAmount = 0;
        $this->totalAmount = 0;
        $this->timeConsumed = 0;

        $this->lastCycle = null;
        $this->lastReport = null;
    }

    public function ignoreUntilNow()
    {
        $this->lastCycle = microtime(true);
    }

    public function reportCycle($amount)
    {
        // Init cycle info
        $now = microtime(true);

        // Initialize the first timestamp
        if (is_null($this->lastReport)) {
            $this->lastReport = $now;
            $this->lastCycle = $now;
        }

        // Handle data
        $this->currentCycles ++;
        $this->totalCycles ++;
        $this->currentAmount += $amount;
        $this->totalAmount += $amount;
        $this->timeConsumed += abs($this->lastCycle - $now);

        // Ignore the first call because lastReport = now
        $realDuration = abs($this->lastReport - $now);
        if ($realDuration > $this->frequency) {
            $this->handleAggregatedStats($this->currentCycles, $this->totalCycles, $this->currentAmount, $this->totalAmount, $this->timeConsumed, $realDuration);

            $this->currentCycles = 0;
            $this->currentAmount = 0;

            // Move the timestamp marker
            $this->lastReport = $now;
            $this->timeConsumed = 0;
        }

        // Make sure to ignore the virtual call to handleAggregatedStats duration
        $this->ignoreUntilNow();
    }

    abstract function handleAggregatedStats($currentCycles, $totalCycles, $currentAmount, $totalAmount, $timeConsumed, $realDuration);
}

class StatisticCsvDumper
{
    private $fileName;

    private $fp = null;

    private function getHeaders()
    {
        return [
            BenchmarkCommand::OPERATION_DISK_BW => 0,
            BenchmarkCommand::OPERATION_DISK_PING => 0,
            BenchmarkCommand::OPERATION_CPU => 0,
            BenchmarkCommand::OPERATION_NETWORK_CLIENT_BW => 0,
            BenchmarkCommand::OPERATION_NETWORK_CLIENT_PING => 0
        ];
    }

    public function __construct($fileName)
    {
        if (file_exists($fileName))
            throw new \Exception("Cannot overwrite existing file: $fileName");

        $this->fileName = $fileName;
        MessageManager::debug("Dumping statistics at: " . $this->fileName);
    }

    public function __destruct()
    {
        if (!is_null($this->fp)) {
            MessageManager::debug("Statistics dumped at: " . $this->fileName);
            fclose($this->fp);
        }
    }

    private function pushData($fields)
    {
        if (is_null($this->fp)) {
            $this->fp = fopen($this->fileName, 'w');

            // Create the first line with headers
            $this->pushData(array_combine(
                array_keys($this->getHeaders()),
                array_keys($this->getHeaders())
            ));
        }

        fputcsv($this->fp, array_merge($this->getHeaders(), $fields));
    }

    public function pushDiskBandwidth($value)
    {
        $this->pushData([
            BenchmarkCommand::OPERATION_DISK_BW => $value
        ]);
    }

    public function pushDiskPing($value)
    {
        $this->pushData([
            BenchmarkCommand::OPERATION_DISK_PING => $value
        ]);
    }

    public function pushCpu($value)
    {
        $this->pushData([
            BenchmarkCommand::OPERATION_CPU => $value
        ]);
    }

    public function pushNetworkClientBandwidth($value)
    {
        $this->pushData([
            BenchmarkCommand::OPERATION_NETWORK_CLIENT_BW => $value
        ]);
    }

    public function pushNetworkClientPing($value)
    {
        $this->pushData([
            BenchmarkCommand::OPERATION_NETWORK_CLIENT_PING => $value
        ]);
    }
}

class DiskIoStatistics extends AbstractBenchmarkStatistics
{
    private $ping;

    /**
     * @var StatisticCsvDumper
     */
    private $dumper;

    public function __construct($ping, $fileName)
    {
        parent::__construct(1);

        $this->ping = $ping;

        $this->dumper = new StatisticCsvDumper($fileName);
    }

    // Disk bandwidth: 9.00 MiB/s (32 op/s) latency: 23 µs
    public function handleAggregatedStats($currentCycles, $totalCycles, $currentAmount, $totalAmount, $timeConsumed, $realDuration)
    {
        // The current amount represent how much Bytes have been consumed in 1 second (frequency is 1s)
        $diskBandwidth = Utils::formatNumber($currentAmount, 'B/s');

        // How many cycles have been consumed to be able to send this aggregated value
        $operations = Utils::formatNumber($currentCycles, 'op/s', true);

        // The latency is defined by the amount of time it took to perform X cycles
        $latencyRaw = $timeConsumed / $currentCycles;
        $latency = Utils::formatNumber($latencyRaw, 's', true);

        if ($this->ping) {
            MessageManager::debug("Disk latency: $latency ($operations)");
            $this->dumper->pushDiskPing($latencyRaw);
        } else {
            MessageManager::debug("Disk bandwidth: $diskBandwidth ($operations)");
            $this->dumper->pushDiskPing($currentAmount);
        }
    }
}

class CpuStatistics extends AbstractBenchmarkStatistics
{
    /**
     * @var StatisticCsvDumper
     */
    private $dumper;

    public function __construct($fileName)
    {
        parent::__construct(1);

        $this->dumper = new StatisticCsvDumper($fileName);
    }

    // Cpu speed: 32.80 Mop/s
    public function handleAggregatedStats($currentCycles, $totalCycles, $currentAmount, $totalAmount, $timeConsumed, $realDuration)
    {
        // The current amount represent how much 100000 batched operations have been consumed in 1 second (frequency is 1s)
        $cpuSpeed = Utils::formatNumber($currentAmount, 'op/s', true);

        MessageManager::debug("Cpu speed: $cpuSpeed");

        $this->dumper->pushCpu($currentAmount);
    }
}

class NetworkStatistics extends AbstractBenchmarkStatistics
{
    const BANDWIDTH = 1;
    const PING = 2;
    const SERVER = 3;

    private $type;

    /**
     * @var StatisticCsvDumper
     */
    private $dumper;

    public function __construct($type, $fileName)
    {
        parent::__construct(1);

        $this->type = $type;

        if ($type != self::SERVER)
            $this->dumper = new StatisticCsvDumper($fileName);
    }

    // Network bandwidth: 32.80 MiB/s (7832op/s)
    // Network latency: 14 ms (7832op/s)
    public function handleAggregatedStats($currentCycles, $totalCycles, $currentAmount, $totalAmount, $timeConsumed, $realDuration)
    {
        // The current amount represent how much bits have been consumed in 1 second (frequency is 1s)
        $networkBandwidth = Utils::formatNumber($currentAmount, 'b/s');

        // How many cycles have been consumed to be able to send this aggregated value
        $operations = Utils::formatNumber($currentCycles, 'op/s', true);

        // The latency is defined by the amount of time it took to perform 2 * X cycles
        // x2 because we only compute the amount of time a packet take to reach the peer, not the time it takes to also get back at us
        $latencyRaw = $timeConsumed / (2 * $currentCycles);
        $latency = Utils::formatNumber($latencyRaw, 's', true);

        switch($this->type) {
            case self::BANDWIDTH:
                MessageManager::debug("Network bandwidth: $networkBandwidth ($operations)");
                $this->dumper->pushNetworkClientBandwidth($currentAmount);
                break;

            case self::PING:
                MessageManager::debug("Network latency: $latency ($operations)");
                $this->dumper->pushNetworkClientPing($latencyRaw);
                break;

            case self::SERVER:
                MessageManager::debug("Network server: $networkBandwidth / $operations / $latency");
                break;

            default:
                break;
        }
    }
}

class BenchmarkCommand extends Command
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

class MyApplication extends Application
{
    /**
     * Gets the name of the command based on input.
     *
     * @param InputInterface $input The input interface
     *
     * @return string The command name
     */
    protected function getCommandName(InputInterface $input)
    {
        // This should return the name of your command.
        return 'rg:benchmark';
    }

    /**
     * Gets the default commands that should always be available.
     *
     * @return array An array of default Command instances
     */
    protected function getDefaultCommands()
    {
        // Keeps the core default commands to have the HelpCommand
        // which is used when using the --help option
        $defaultCommands = parent::getDefaultCommands();

        $defaultCommands[] = new BenchmarkCommand();

        return $defaultCommands;
    }

    /**
     * Overridden so that the application doesn't expect the command
     * name to be the first argument.
     */
    public function getDefinition()
    {
        $inputDefinition = parent::getDefinition();
        // clears out the normal first argument, which is the command name
        $inputDefinition->setArguments();

        return $inputDefinition;
    }
}

$application = new MyApplication();
$application->run();
