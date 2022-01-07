# Generating PHP Classes from .proto files for Aruba Telemetry API	


## Introduction

The PHP classes used to manage the protobuf messages of the API are already generated and available with the code of Aruba Websocket Server.

However for the sake of understanding, and future maintenance, this section is describing how to generate the PHP source code
from the .proto files.

Be aware that version 3.0.0 of Protobuf Compiler is needed. This version is available by default on Debian 9 (stretch), 
however on Debian 10 (buster) version 3.6.1 is the default and not the right version for the PHP Protobuf library used for AWSS.

## Tasks

In order to generate the PHP classes for Aruba Telemetry messages and data, you will need to:
-	Install protobuf compiler (debian package)
-	Install Protobuf-plugin PHP package (from Github / Composer)
-	Get the protobuf description files from Aruba Service Portal (ASP)
-	Run the commands to generate the classes.

Create a dedicated directory for this task:

```
root@bagheera:/home/www/websocket# mkdir class-generator
root@bagheera:/home/www/websocket# chmod a+rw class-generator/
root@bagheera:/home/www/websocket# cd class-generator/
```

Download the protobuf description file from Aruba Service Portal (ASP).

```
root@bagheera:/home/www/websocket/class-generator# ls -al
total 12
drwxrwxrwx 3 root root 4096 Nov 20 18:48 .
drwxrwxrwx 5 root root 4096 Nov 20 18:46 ..
drwxr-xr-x 2 root root 4096 Nov 20 18:48 aruba-iot-protobuf-spec-AP
```

```
root@bagheera:/home/www/websocket/class-generator# ls -al aruba-iot-protobuf-spec-AP/
total 64
drwxr-xr-x 2 root root 4096 Nov 20 18:48 .
drwxrwxrwx 3 root root 4096 Nov 20 18:48 ..
-rw-r--r-- 1 root root 1385 Nov 20 18:48 aruba-iot-nb-action-results.proto
-rw-r--r-- 1 root root 1307 Nov 20 18:48 aruba-iot-nb-ble-data.proto
-rw-r--r-- 1 root root 1179 Nov 20 18:48 aruba-iot-nb-characteristic.proto
-rw-r--r-- 1 root root 1094 Nov 20 18:48 aruba-iot-nb-device-count.proto
-rw-r--r-- 1 root root 2616 Nov 20 18:48 aruba-iot-nb.proto
-rw-r--r-- 1 root root  467 Nov 20 18:48 aruba-iot-nb-status.proto
-rw-r--r-- 1 root root 6613 Nov 20 18:48 aruba-iot-nb-telemetry.proto
-rw-r--r-- 1 root root  530 Nov 20 18:48 aruba-iot-nb-wifi-data.proto
-rw-r--r-- 1 root root 1234 Nov 20 18:48 aruba-iot-sb-action.proto
-rw-r--r-- 1 root root  420 Nov 20 18:48 aruba-iot-sb-config.proto
-rw-r--r-- 1 root root 1496 Nov 20 18:48 aruba-iot-sb.proto
-rw-r--r-- 1 root root  510 Nov 20 18:48 aruba-iot-sb-status.proto
-rw-r--r-- 1 root root 3601 Nov 20 18:48 aruba-iot-types.proto
root@bagheera:/home/www/websocket/class-generator#
```

Install the protobuf compiler :

```
apt-get install protobuf-compiler
```

You can check the installation and version by doing : 

```
root@bagheera:/home/www/websocket# protoc --version
libprotoc 3.0.0
```

Install the PHP plugin to generate the classes

To do this, we will reuse the composer capabilities. Just create a local composer.json file by doing:

```
composer require "protobuf-php/protobuf-plugin"
```

This will install all the required packages:

```
root@bagheera:/home/www/websocket/class-generator# ls -al
total 44
drwxrwxrwx 4 root root  4096 Nov 20 18:52 .
drwxrwxrwx 5 root root  4096 Nov 20 18:46 ..
drwxr-xr-x 2 root root  4096 Nov 20 18:48 aruba-iot-protobuf-spec-AP
-rw-r--r-- 1 root root    76 Nov 20 18:51 composer.json
-rw-r--r-- 1 root root 23454 Nov 20 18:52 composer.lock
drwxr-xr-x 9 root root  4096 Nov 20 18:52 vendor
root@bagheera:/home/www/websocket/class-generator#
```

Generate the PHP Classes:

```
root@bagheera:/home/www/websocket/class-generator# mkdir src
root@bagheera:/home/www/websocket/class-generator# php ./vendor/bin/protobuf --include-descriptors -i ./aruba-iot-protobuf-spec-AP/ -o ./src/ ./aruba-iot-protobuf-spec-AP/*.proto
PHP classes successfully generate.
```

```
root@bagheera:/home/www/websocket/class-generator# ls -al src/aruba_telemetry/
total 628
drwxr-xr-x 2 root root  4096 Nov 20 18:55 .
drwxr-xr-x 3 root root  4096 Nov 20 18:55 ..
-rw-r--r-- 1 root root 11466 Nov 20 18:55 Accelerometer.php
-rw-r--r-- 1 root root  2387 Nov 20 18:55 AccelStatus.php
-rw-r--r-- 1 root root 17110 Nov 20 18:55 Action.php
-rw-r--r-- 1 root root 13333 Nov 20 18:55 ActionResult.php
-rw-r--r-- 1 root root  8794 Nov 20 18:55 ActionStatus.php
-rw-r--r-- 1 root root  3623 Nov 20 18:55 ActionType.php
-rw-r--r-- 1 root root  2723 Nov 20 18:55 Alarm.php
-rw-r--r-- 1 root root  6197 Nov 20 18:55 BeaconEvent.php
-rw-r--r-- 1 root root  8891 Nov 20 18:55 Beacons.php
-rw-r--r-- 1 root root 11326 Nov 20 18:55 BleData.php
-rw-r--r-- 1 root root  3043 Nov 20 18:55 BleFrameType.php
-rw-r--r-- 1 root root  2255 Nov 20 18:55 CellEvent.php
-rw-r--r-- 1 root root  7595 Nov 20 18:55 Cell.php
-rw-r--r-- 1 root root 16489 Nov 20 18:55 Characteristic.php
-rw-r--r-- 1 root root  5073 Nov 20 18:55 CharProperty.php
-rw-r--r-- 1 root root  1446 Nov 20 18:55 ConnectCode.php
-rw-r--r-- 1 root root  8282 Nov 20 18:55 ConnectStatus.php
-rw-r--r-- 1 root root  8319 Nov 20 18:55 Contact.php
-rw-r--r-- 1 root root  1387 Nov 20 18:55 ContactPosition.php
-rw-r--r-- 1 root root 10696 Nov 20 18:55 deviceClassEnum.php
-rw-r--r-- 1 root root 40429 Nov 20 18:55 DeviceCount.php
-rw-r--r-- 1 root root 10215 Nov 20 18:55 Eddystone.php
-rw-r--r-- 1 root root  8412 Nov 20 18:55 EddyUID.php
-rw-r--r-- 1 root root  8507 Nov 20 18:55 EddyURL.php
-rw-r--r-- 1 root root  9218 Nov 20 18:55 Firmware.php
-rw-r--r-- 1 root root 11415 Nov 20 18:55 History.php
-rw-r--r-- 1 root root 13854 Nov 20 18:55 Ibeacon.php
-rw-r--r-- 1 root root 10025 Nov 20 18:55 Inputs.php
-rw-r--r-- 1 root root 16482 Nov 20 18:55 IotSbMessage.php
-rw-r--r-- 1 root root  4443 Nov 20 18:55 MechanicalH.php
-rw-r--r-- 1 root root 11736 Nov 20 18:55 Meta.php
-rw-r--r-- 1 root root  3894 Nov 20 18:55 NbTopic.php
-rw-r--r-- 1 root root  5977 Nov 20 18:55 Occupancy.php
-rw-r--r-- 1 root root  7635 Nov 20 18:55 Receiver.php
-rw-r--r-- 1 root root 46158 Nov 20 18:55 Reported.php
-rw-r--r-- 1 root root 17315 Nov 20 18:55 Reporter.php
-rw-r--r-- 1 root root  8388 Nov 20 18:55 RockerSwitch.php
-rw-r--r-- 1 root root  2014 Nov 20 18:55 rockerSwitchPosition.php
-rw-r--r-- 1 root root 13568 Nov 20 18:55 Rssi.php
-rw-r--r-- 1 root root  1830 Nov 20 18:55 SbTopic.php
-rw-r--r-- 1 root root 39402 Nov 20 18:55 Sensors.php
-rw-r--r-- 1 root root 11213 Nov 20 18:55 Stats.php
-rw-r--r-- 1 root root  9849 Nov 20 18:55 Status.php
-rw-r--r-- 1 root root  1606 Nov 20 18:55 statusValue.php
-rw-r--r-- 1 root root  1783 Nov 20 18:55 switchState.php
-rw-r--r-- 1 root root 28949 Nov 20 18:55 Telemetry.php
-rw-r--r-- 1 root root  7908 Nov 20 18:55 TransportConfig.php
-rw-r--r-- 1 root root  8060 Nov 20 18:55 VendorData.php
-rw-r--r-- 1 root root 12342 Nov 20 18:55 WiFiData.php
root@bagheera:/home/www/websocket/class-generator#
```

You can then copy the file to the production directory, or modify the command to have the file directly produced in the right directory.


---


[Back to Readme](../README.md)
