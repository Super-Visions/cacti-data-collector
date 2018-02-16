Cacti Data Collector
====================

This standalone PHP application collects data from [Cacti](https://www.cacti.net).
It synchronizes device and interface information with an [iTop](https://www.combodo.com/itop-193) instance using Synchronization Data Sources.

## Features

* Collects NetworkDevice information along with Model, IOSVersion and PhysicalInterface.
* If enabled, also collects IPv4Address for the device it's management IP.
* Connects to the iTop REST interface

## Requirements

* Cacti > 0.8.8
* iTop > 2.3
* [TeemIp standalone](https://www.combodo.com/teemip-194) or as [iTop extension](https://store.itophub.io/en_US/products/teemip-core-ip-mgmt).

## Installation

This application needs to be installed on the same server where Cacti is installed.
This is needed as it will run a Cacti cli script and will retrieve some SNMP data from the network devices.

Create an empty configuration file at `conf/params.local.xml` and adapt the settings to connect to your iTop instance and Cacti DB.
To get the default configuration, run the following command:
```
php exec.php --dump_config_only
```

You can find information about the several configuration items in the files `conf/params.distrib.xml` and `collectors/params.distrib.xml`.

## Usage

The first time the collector is run, the following command is recommended:
```
php exec.php --configure_only
```
This will create the Synchronization Data Sources if they don't already exist.

To collect the data without synchronizing with iTop, run:
```
php exec.php --collect_only
```
This will store the collected data in CSV files in the data/ subdirectory of the collector - this is useful for checking the data before it is passed over to iTop.
Mapped values can be checked and mapping tables updated - however, note that collection should be run again after such changes.

Finally, to perform iTop synchronization with the data collected:
```
php exec.php --synchro_only
```
Data collection and synchronization (and data source update/creation if necessary) can be performed in a single step if desired:
```
php exec.php
```
While this is simpler, it affords less control over the synchronization process.

More information on running the collector may be found on the [itop-data-collector-base](https://www.itophub.io/wiki/page?id=extensions%3Aitop-data-collector-base) page.