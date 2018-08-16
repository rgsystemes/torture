# torture
Torture is designed to test on a long period of time a machine

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
