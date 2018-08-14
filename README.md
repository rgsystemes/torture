# torture
Torture is designed to test on a long period of time a machine

```
$ curl -O http://getcomposer.org/composer.phar
$ chmod +x composer.phar
$ php composer.phar install
Loading composer repositories with package information
Updating dependencies (including require-dev)
Package operations: 4 installs, 0 updates, 0 removals
As there is no 'unzip' command installed zip files are being unpacked using the PHP zip extension.
This may cause invalid reports of corrupted archives. Installing 'unzip' may remediate them.
  - Installing psr/log (1.0.2): Loading from cache
  - Installing symfony/debug (v3.0.9): Loading from cache
  - Installing symfony/polyfill-mbstring (v1.9.0): Loading from cache
  - Installing symfony/console (v2.8.44): Loading from cache
symfony/console suggests installing psr/log-implementation (For using the console logger)
symfony/console suggests installing symfony/event-dispatcher
symfony/console suggests installing symfony/process
Writing lock file
Generating autoload files
$ chmod +x build
$ ./build
Build Symfony Console phar
Build complete
$ php benchmark.phar cpu
The script will run until you hit CTRL+C
Dumping statistics at: results-cpu.csv
Cpu speed: 32.10 Mop/s
Cpu speed: 31.50 Mop/s
^CStatistics dumped at: results-cpu.csv
```
