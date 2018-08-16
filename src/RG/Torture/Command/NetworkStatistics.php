<?php

namespace RG\Torture\Command;

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
