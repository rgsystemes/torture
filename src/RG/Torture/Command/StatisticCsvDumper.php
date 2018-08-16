<?php

namespace RG\Torture\Command;

class StatisticCsvDumper
{
    private $fileName;

    private $fp = null;

    private function getHeaders()
    {
        return [
            TortureCommand::OPERATION_DISK_BW => 0,
            TortureCommand::OPERATION_DISK_PING => 0,
            TortureCommand::OPERATION_CPU => 0,
            TortureCommand::OPERATION_NETWORK_CLIENT_BW => 0,
            TortureCommand::OPERATION_NETWORK_CLIENT_PING => 0
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
            TortureCommand::OPERATION_DISK_BW => $value
        ]);
    }

    public function pushDiskPing($value)
    {
        $this->pushData([
            TortureCommand::OPERATION_DISK_PING => $value
        ]);
    }

    public function pushCpu($value)
    {
        $this->pushData([
            TortureCommand::OPERATION_CPU => $value
        ]);
    }

    public function pushNetworkClientBandwidth($value)
    {
        $this->pushData([
            TortureCommand::OPERATION_NETWORK_CLIENT_BW => $value
        ]);
    }

    public function pushNetworkClientPing($value)
    {
        $this->pushData([
            TortureCommand::OPERATION_NETWORK_CLIENT_PING => $value
        ]);
    }
}
