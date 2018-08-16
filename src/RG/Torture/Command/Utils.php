<?php

namespace RG\Torture\Command;

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
