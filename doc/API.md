# Aruba Websocket Server (AWSS) JSON API


The Websocket Server can be accessed by JSON API, in order to monitor or configure some of the properties. The prefered access method should be through a websocket connection rather than regular HTTP connection.
Most of the actions triggered by the server are asymetric actions, by using a websocket connection, actions results can be send to client when they are ready.

The JSON API is accessible using URL : http://\<server_ip\>:\<port\>/api. If not customized, default value for \<port\> is 8081.

You can use tools like Postman (https://www.postman.com/) to try and test the APIs.

![Postman Overview](images/postman_overview.png)


## General Format

General request format is :
```json
{
  "api_version":"1.0",        // mandatory : future use
  "api_key":"<api_key>",      // mandatory
  "event":{
    "name":"<event_name>",    // mandatory
    "event_id":"<event_id>",  // optional : uniq id to identifiy the request, used for async response like in websocket.
    "data": {                 // optional : event data depending on event
      "data_1":"value_1",
      "data_2":"value_2"
    }
  }
}
```

General response or notification format is :
```json
{
    "status": "success|fail",
    "status_msg": "<text_mesage>",
    "from_event": "<event_name>",
    "event_id": "<event_id>",
    "data": {
      "data_1":"value_1",
      "data_2":"value_2"
    }
}
```



## API : Websocket Info

Get information regarding websocket status

Request :

```json
{
	"api_version":"1.0",
	"api_key":"<api_key>",
    "event": {
        "name":"websocket_info",
        "event_id":"<event_id>"
    }
}
```

Response (sample) :

```json
{
    "status": "success",
    "status_msg": "",
    "from_event": "websocket_info",
    "event_id": "<event_id>",
    "data": {
        "websocket": {
            "aruba_ws_version": "1.0-dev",
            "ip_address": "0.0.0.0",
            "tcp_port": "8081",
            "up_time": 1631885139,
            "presence_timeout": 90,
            "presence_min_rssi": -90,
            "presence_rssi_hysteresis": 5,
            "nearest_ap_hysteresis": 5,
            "nearest_ap_timeout": 90,
            "nearest_ap_min_rssi": -90,
            "reporters_allow_list": "",
            "access_token": "",
            "reporters_nb": 0,
            "devices_nb": 0,
            "include_mode": 0,
            "include_device_count": 0,
            "device_type_allow_list": "",
            "include_generic_with_local": 0,
            "include_generic_with_mac": 0,
            "include_generic_mac_prefix": "",
            "include_generic_max_devices": 3,
            "stats": {
                "payload_data": 0,
                "raw_data": 413
            },
            "gatt_queue_nb": 0
        }
    }
}
```

## API : Device List

Request :

```json
{
	"api_version":"1.0",
	"api_key":"123",
    "event":{
        "name":"device_list",
        "event_id":"<event_id>",
        "data": {
          "extended":1
        }
    }
}
```

Response (sample) :

```json
{
    "status": "success",
    "status_msg": "",
    "from_event": "device_list",
    "event_id": "<event_id>",
    "data": {
        "devices": [
            {
                "mac": "E6:FE:37:0D:A4:D7",
                "name": "Jinou_Sensor_HumiTemp",
                "classname": "generic",
                "vendor_id": "Jinou",
                "model_id": "Sensor_HumiTemp",
                "nearest_ap_mac": "9C:8C:D8:C9:39:9E",
                "rssi": -49,
                "vendor_name": "",
                "local_name": "Jinou_Sensor_HumiTemp",
                "model": "",
                "presence": 1,
                "connect_status": "disconnected",
                "is_connectable": "unknown",
                "is_discoverable": "unknown",
                "services": [],
                "telemetry_values": []
            },
            {
                "mac": "A4:C1:38:07:FC:EE",
                "name": "LYWSD03MMC",
                "classname": "generic",
                "vendor_id": "",
                "model_id": "",
                "nearest_ap_mac": "9C:8C:D8:C9:39:9E",
                "rssi": -39,
                "vendor_name": "",
                "local_name": "LYWSD03MMC",
                "model": "",
                "presence": 1,
                "connect_status": "disconnected",
                "is_connectable": "unknown",
                "is_discoverable": "unknown",
                "services": [],
                "telemetry_values": []
            },
            {
                "mac": "E5:00:00:00:03:F7",
                "name": "enoceanSensor E5:00:00:00:03:F7",
                "classname": "enoceanSensor",
                "vendor_id": "Enocean",
                "model_id": "Sensor",
                "nearest_ap_mac": "9C:8C:D8:C9:39:9E",
                "rssi": -35,
                "vendor_name": "",
                "local_name": "",
                "model": "",
                "presence": 1,
                "connect_status": "disconnected",
                "is_connectable": "unknown",
                "is_discoverable": "unknown",
                "services": [],
                "telemetry_values": {
                    "illumination": {
                        "name": "illumination",
                        "type": "",
                        "value": 136,
                        "timestamp": 1631885797
                    },
                    "occupancy": {
                        "name": "occupancy",
                        "type": "",
                        "value": 50,
                        "timestamp": 1631885797
                    }
                }
            }
        ]
    }
}
```

## API : Device Info


Request :

```json
{
	"api_version":"1.0",
	"api_key":"123",
    "event":{
        "name":"device_info",
        "event_id":"<event_id>",
        "data": {
          "device_mac":"E5:00:00:00:03:F7"
        }
    }
}
```

Response (sample) :

```json
{
    "status": "success",
    "status_msg": "",
    "from_event": "device_info",
    "event_id": "<event_id>",
    "data": {
        "device_mac": "E5:00:00:00:03:F7",
        "device": {
            "mac": "E5:00:00:00:03:F7",
            "name": "enoceanSensor E5:00:00:00:03:F7",
            "classname": "enoceanSensor",
            "vendor_id": "Enocean",
            "model_id": "Sensor",
            "nearest_ap_mac": "9C:8C:D8:C9:39:9E",
            "rssi": -35,
            "vendor_name": "",
            "local_name": "",
            "model": "",
            "presence": 1,
            "connect_status": "disconnected",
            "is_connectable": "unknown",
            "is_discoverable": "unknown",
            "services": [],
            "telemetry_values": {
                "illumination": {
                    "name": "illumination",
                    "type": "",
                    "value": 121,
                    "timestamp": 1631886158
                },
                "occupancy": {
                    "name": "occupancy",
                    "type": "",
                    "value": 50,
                    "timestamp": 1631886158
                }
            }
        }
    }
}
```


## API : Reporters List



Request :

```json
{
	"api_version":"1.0",
	"api_key":"123",
    "event":{
        "name":"reporter_list",
        "event_id":"<event_id>"
    }
}
```

Response (sample) :

```json
{
    "status": "success",
    "status_msg": "",
    "from_event": "reporter_list",
    "event_id": "<event_id>",
    "data": {
        "websocket": {
            [... see above ...]
        },
        "reporters": [
            {
                "mac": "9C:8C:D8:C9:39:9E",
                "name": "AP-515-Lab",
                "local_ip": "192.168.102.100",
                "remote_ip": "192.168.22.156",
                "model": "AP-515",
                "version": "8.9.0.0-8.9.0.0",
                "telemetry": 1,
                "rtls": 0,
                "lastseen": 1631885947
            }
        ]
    }
}
```



## API : Reporter Infos



Request :

```json


```

Response (sample) :

```json


```


## API : Device Include Mode



Request :

```json


```

Response (sample) :

```json


```


## API : Device Include Count



Request :

```json


```

Response (sample) :

```json


```

---


[Back to Readme](../README.md)
