<?php
/**---------------------------------------------------------------------------
 * Custom Extraction Code from BLE Advertissement
 * 
 * This code is dynamically included inside methode ArubaWssDevice::setTelemetryFromAdvert($p_value)
 * It must not include any 'return' or 'exit' commands.
 * 
 * The BLE Advertissement raw value is in variable $p_value. It is string containing 
 * each hex value in the following format : '02-01-A4-D7......'
 * The extracted telemetry values (if any) must be in $v_telemetry_values array.
 * If something is wrong, or no value is extracted, the array must be empty.
 * ---------------------------------------------------------------------------
 */

  // ----- Extracted telemetry values are to be set inside the $v_telemetry_values array
  // $v_telemetry_values[]['name'] : telemetry name
  // $v_telemetry_values[]['value'] : telemetry value
  // $v_telemetry_values[]['type'] : telemetry type (optional) 'numeric', 'boolean', 'string', ... 
  // An empty array means, no telemetry value to return.
  $v_telemetry_values = array();

  // ----- Double check that we are with the right device model
  if (($this->vendor_id == 'ATC') && ($this->model_id == 'LYWSD03MMC')) {
  
  /*
    [2021-11-30 17:37:45] [debug]:Data is '10-16-1A-18-A4-C1-38-07-FC-EE-00-ED-24-29-0A-3F-08'.
    
    The custom firmware sends every minute an update of advertising data on the UUID 0x181A with the Tempereature, Humidity and Battery data.
    
    The format of the advertising data is as follow:
    
    Byte 5-10 MAC in correct order
    Byte 11-12 Temperature in int16
    Byte 13 Humidity in percent
    Byte 14 Battery in percent
    Byte 15-16 Battery in mV uint16_t
    Byte 17 frame packet counter
    
    Example: 0x0e, 0x16, 0x1a, 0x18, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0xaa, 0xaa, 0xbb, 0xcc, 0xdd, 0xdd, 0x00
          
  */
        
    $v_item = explode('-', $p_value);
    if (sizeof($v_item) != 17) {
      ArubaWssTool::log('debug', "Adv_Ind value from ATC should be 17 bytes. Ignore.");
    }
    else {
    
      $v_val_temp = hexdec($v_item[10].$v_item[11]);
      $v_val_humi = hexdec($v_item[12]);
      $v_val_battery = hexdec($v_item[13]);
      
      //ArubaWssTool::log('debug', "v_val_temp : ".$v_val_temp);
      //ArubaWssTool::log('debug', "v_val_humi : ".$v_val_humi);
      //ArubaWssTool::log('debug', "v_val_battery : ".$v_val_battery);

      //$v_temp = ($v_val_sign==1?-1:1) * ( $v_val_temp_int + $v_val_temp_dec/100 );
      $v_temp = $v_val_temp/10;
      $v_humi = $v_val_humi;
  
      ArubaWssTool::log('debug', "ATC Temperature from advert is : ".$v_temp." C");
      ArubaWssTool::log('debug', "ATC Humidity from advert is : ".$v_humi." %");
      ArubaWssTool::log('debug', "ATC Battery from advert is : ".$v_val_battery." %");
      
      $i=0;
      $v_telemetry_values[$i]['name'] = 'temperatureC';
      $v_telemetry_values[$i]['value'] = $v_temp;
      $v_telemetry_values[$i]['type'] = '';
                  
      $i++;
      $v_telemetry_values[$i]['name'] = 'humidity';
      $v_telemetry_values[$i]['value'] = $v_humi;
      $v_telemetry_values[$i]['type'] = '';

      $i++;
      $v_telemetry_values[$i]['name'] = 'battery';
      $v_telemetry_values[$i]['value'] = $v_val_battery;
      $v_telemetry_values[$i]['type'] = '';

    }
    
  }
?>
