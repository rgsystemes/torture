<?php

namespace RG\Torture\Command;

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
