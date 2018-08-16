<?php

namespace RG\Torture\Command;

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
