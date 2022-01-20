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
  if (($this->vendor_id == 'Jinou') && ($this->model_id == 'Sensor_HumiTemp')) {
  
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
    
    [2021-09-16 10:59:26] [debug]:Device MAC is 'E6:FE:37:0D:A4:D7'.
    [2021-09-16 10:59:26] [debug]:Data is '02-01-06-03-02-20-AA-0E-FF-00-19-00-00-3E-00-64-E6-FE-37-0D-A4-D7'.

    La valeur se retrouve là : 00-19-00-00-3E-00    
  */
  
    $v_item = explode('-', $p_value);
    if (sizeof($v_item) < 15) {
      ArubaWssTool::log('debug', "Adv_Ind value from Jinou should be 15 bytes or more. Ignore.");
    }
    else {
    
      $v_val_sign = hexdec($v_item[9]);
      $v_val_temp_int = hexdec($v_item[10]);
      $v_val_temp_dec = hexdec($v_item[11]);
      $v_val_humi_int = hexdec($v_item[13]);
      $v_val_humi_dec = hexdec($v_item[14]);
      
      $v_temp = ($v_val_sign==1?-1:1) * ( $v_val_temp_int + $v_val_temp_dec/100 );
      $v_humi = $v_val_humi_int + $v_val_humi_dec/100;
  
      ArubaWssTool::log('debug', "Jinou Temperature from advert is : ".$v_temp);
      ArubaWssTool::log('debug', "Jinou Humidity from advert is : ".$v_humi);
      
      $v_telemetry_values[0]['name'] = 'temperatureC';
      $v_telemetry_values[0]['value'] = $v_temp;
      $v_telemetry_values[0]['type'] = '';
                  
      $v_telemetry_values[1]['name'] = 'humidity';
      $v_telemetry_values[1]['value'] = $v_humi;
      $v_telemetry_values[1]['type'] = '';
      
    }
                
  }
?>
