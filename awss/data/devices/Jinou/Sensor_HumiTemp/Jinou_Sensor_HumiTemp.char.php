<?php
/**---------------------------------------------------------------------------
 * Custom Extraction Code from BLE GATT Read
 * 
 * This code is dynamically included inside methode ArubaWssDevice::setTelemetryFromCharacteristic($p_service_uuid, $p_char_uuid, $p_value)
 * It must not include any 'return' or 'exit' commands.
 * 
 * The BLE Characteristic raw value is in variable $p_value. It is string containing 
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
  if (($this->vendor_id == 'Jinou') && ($this->model_id == 'Sensor_HumiTemp')) {
    if (($p_service_uuid == "AA-20") && ($p_char_uuid == "AA-21") && ($p_value != '')) {
    /*
      Service 0XAA20. Temp & humid data. There are 6 bytes.
      1. Temperature positive/negative: 0 means positive (+) and 1 means negative (-)
      2. Integer part of temperature. Show in Hexadecimal.
      3. Decimal part of temperature. Show in Hexadecimal.
      4. Reserved byte. Ignore it.
      5. Integer part of humidity. Show in Hexadecimal.
      6. Decimal part of humidity. Show in Hexadecimal.
      For example: 00 14 05 22 32 08 means +20.5C 50.8%
      01 08 09 00 14 05 means -8.9C 20.5%        
    */
    
      $v_item = explode('-', $p_value);
      if (sizeof($v_item) != 6) {
        ArubaWssTool::log('debug', "Characteristic value from Jinou should be 6 bytes. Ignore.");
        return;
      }
      
      $v_val_sign = hexdec($v_item[0]);
      $v_val_temp_int = hexdec($v_item[1]);
      $v_val_temp_dec = hexdec($v_item[2]);
      $v_val_humi_int = hexdec($v_item[4]);
      $v_val_humi_dec = hexdec($v_item[5]);
      
      $v_temp = ($v_val_sign==1?-1:1) * ( $v_val_temp_int + $v_val_temp_dec/100 );
      $v_humi = $v_val_humi_int + $v_val_humi_dec/100;
  
      ArubaWssTool::log('debug', "Temperature is : ".$v_temp);
      ArubaWssTool::log('debug', "Humidity is : ".$v_humi);
      
      $i=0;
      $v_telemetry_values[$i]['name'] = 'temperatureC';
      $v_telemetry_values[$i]['value'] = $v_temp;
      $v_telemetry_values[$i]['type'] = '';
            
      $i++;
      $v_telemetry_values[$i]['name'] = 'humidity';
      $v_telemetry_values[$i]['value'] = $v_humi;
      $v_telemetry_values[$i]['type'] = '';

    }
  }
  
?>
