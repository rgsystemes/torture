<?php

namespace RG\Torture\Command;

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
