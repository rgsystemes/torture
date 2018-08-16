# torture
Torture is designed to test on a long period of time a machine

# Build

```
$ curl -O http://getcomposer.org/composer.phar
$ chmod +x composer.phar
$ php composer.phar install
$ chmod +x build-phar.php
$ ./build-phar.php
$ php torture.phar cpu
The script will run until you hit CTRL+C
Dumping statistics at: results-cpu.csv
Cpu speed: 32.10 Mop/s
Cpu speed: 31.50 Mop/s
^CStatistics dumped at: results-cpu.csv
```

# Usage

```
$ php torture.phar --help
Usage:
  rg:torture [options] [--] <operation>

Arguments:
  operation                    Define the aimed operation: available are disk-speed, disk-ping, cpu, net-client-speed, net-client-ping or net-server

Options:
      --peer[=PEER]            Define the ip:port to connect at or listen from [default: "*:10000"]
      --buff-size[=BUFF-SIZE]  The buffer size to use: for network (default: 50MB) or disk (default: 1MB)
      --file[=FILE]            The result files to merge (multiple values allowed)
  -h, --help                   Display this help message
  -q, --quiet                  Do not output any message
  -V, --version                Display this application version
      --ansi                   Force ANSI output
      --no-ansi                Disable ANSI output
  -n, --no-interaction         Do not ask any interactive question
  -s, --shell                  Launch the shell.
      --process-isolation      Launch commands from shell as a separate process.
  -e, --env=ENV                The Environment name. [default: "dev"]
      --no-debug               Switches off debug mode.
  -v|vv|vvv, --verbose         Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
  Benchmark the machine you're running on

$ php torture.phar net-server &

$ php torture.phar cpu
[12:55:35.79201800] Dumping statistics at: results_cpu.csv
[12:55:36.79802300] Cpu speed: 32.60 Mop/s
[12:55:37.79894700] Cpu speed: 32.50 Mop/s
^C

$ php torture.phar disk-speed
[12:55:48.38491700] Dumping statistics at: results_disk-speed.csv
[12:55:49.58591400] Disk speed: 10.00 MiB/s (10.00 op/s)
[12:55:50.63670900] Disk speed: 9.00 MiB/s (9.00 op/s)
[12:55:51.68616900] Disk speed: 9.00 MiB/s (9.00 op/s)
^C

$ php torture.phar disk-ping
[12:55:57.18408600] Dumping statistics at: results_disk-ping.csv
[12:55:58.18553300] Disk latency: 9.16 µs (7.20 kop/s)
[12:55:59.18500000] Disk latency: 9.24 µs (5.04 kop/s)
[12:56:00.18510800] Disk latency: 9.15 µs (4.36 kop/s)
[12:56:01.18517000] Disk latency: 9.01 µs (5.84 kop/s)
^C

$ php torture.phar net-client-speed
[12:56:19.80130900] Dumping statistics at: results_net-client-speed.csv
[12:56:26.90241300] Network speed: 10.16 Gib/s (13.00 op/s)
[12:56:27.98416700] Network speed: 7.03 Gib/s (9.00 op/s)
[12:56:29.00999100] Network speed: 8.59 Gib/s (11.00 op/s)
[12:56:30.04972400] Network speed: 5.47 Gib/s (7.00 op/s)
[12:56:31.09059000] Network speed: 10.16 Gib/s (13.00 op/s)
^C

$ php torture.phar net-client-ping
[12:56:37.54410100] Dumping statistics at: results_net-client-ping.csv
[12:56:38.54611000] Network latency: 23.35 µs (18.41 kop/s)
[12:56:39.54550600] Network latency: 23.35 µs (18.38 kop/s)
[12:56:40.54554900] Network latency: 23.52 µs (18.25 kop/s)
[12:56:41.54553500] Network latency: 23.25 µs (18.48 kop/s)
[12:56:42.54557800] Network latency: 23.25 µs (18.48 kop/s)
[12:56:43.54560100] Network latency: 23.17 µs (18.55 kop/s)
^C

$ php torture.phar merge-results --file results_cpu.csv --file results_disk-ping.csv --file results_disk-speed.csv --file results_net-client-ping.csv --file results_net-client-speed.csv
[12:57:50.29641300] Dumping merged results at: results_merge-results.csv

$ cat results_merge-results.csv
disk-speed,disk-ping,cpu,net-client-speed,net-client-ping
0,10485760,32600000,10905190400,2.3352978755616E-5
0,9437184,32500000,7549747200,2.3352187417676E-5
0,9437184,0,9227468800,2.3523332831095E-5
0,9.0058666397207E-6,0,5872025600,2.3247630269936E-5
0,0,0,10905190400,2.3248766371458E-5
0,0,0,0,2.3168613662732E-5
```
