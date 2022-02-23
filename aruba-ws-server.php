<?php
/**
 * Aruba Websocket Server for Testing ...
 *
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * 
 */

  ini_set('display_errors', '1');
  define('ARUBA_WSS_VERSION', '1.4-beta');

  /**
   * Look for specific arguments to manage extensions and console log
   */
  $g_awss_extension = '';
  $g_awss_console_log = false;
  $v_count = 0;
  foreach ($argv as $arg)     {
    if ($arg == '-extension') {
      $g_awss_extension = (isset($argv[$v_count+1]) ? $argv[$v_count+1] : '');
    }
    if ($arg == '-no_extension') {
      $g_awss_extension = 'no';
    }
    if ($arg == '-console_log') {
      $g_awss_console_log = true;
    }
    if ($arg == '-debug_level') {
      $g_awss_debug_level = (isset($argv[$v_count+1]) ? $argv[$v_count+1] : '');
    }
    $v_count++;
  }      
      
  // ----- Extension include
  if (($g_awss_extension != '') && ($g_awss_extension != 'no')) {
    $v_filemane = __DIR__.'/ArubaWss'.$g_awss_extension.'.class.php';
    if (!file_exists($v_filemane)) {
      $v_filemane = __DIR__.'/'.$g_awss_extension;
      if (!file_exists($v_filemane)) {
        echo "Missing extension file '".$g_awss_extension."'\n";
        exit(0);
      }
    }
    require_once $v_filemane;
  }

  // ----- Look for use af an external extension (like Jeedom)
  // This value can be overrided in the above $v_filemane included file
  if (!defined('ARUBA_WSS_DEVICE_CLASS')) {
    define('ARUBA_WSS_DEVICE_CLASS', 'ArubaWssDevice');
  }

  // ----- Look customized path to third party source
  // This value can be overrided in the above $v_filemane included file
  // Changing this value makes sense if the vendor/ directory was moved somewhere else
  if (!defined('ARUBA_WSS_THIRDPARTY_LOAD')) {
    define('ARUBA_WSS_THIRDPARTY_LOAD', __DIR__.'/vendor/autoload.php');
  }

  // ----- Other constants
  define('AWSS_STATUS_CONNECTED', 'connected');
  define('AWSS_STATUS_DISCONNECTED', 'disconnected');
  
  // ----- 3rd Part libraries includes
  $loader = require ARUBA_WSS_THIRDPARTY_LOAD;
  $loader->addPsr4('aruba_telemetry\\', __DIR__);

  // ----- Namespaces
  use GuzzleHttp\Psr7\Response;
  use Ratchet\RFC6455\Handshake\PermessageDeflateOptions;
  use Ratchet\RFC6455\Messaging\MessageBuffer;
  use Ratchet\RFC6455\Messaging\MessageInterface;
  use Ratchet\RFC6455\Messaging\FrameInterface;
  use Ratchet\RFC6455\Messaging\Frame;
  use React\Socket\ConnectionInterface;

  /**---------------------------------------------------------------------------
   * Class : ArubaWssTool
   * Description :
   * A placeholder to group tool functions.
   * ---------------------------------------------------------------------------
   */
  class ArubaWssTool {

    /**---------------------------------------------------------------------------
     * Method : log()
     * Description :
     *   A placeholder to encapsulate log message, and be able do some
     *   troubleshooting locally.
     * ---------------------------------------------------------------------------
     */
    static function log($p_type, $p_message) {
      global $aruba_iot_websocket;
      $aruba_iot_websocket->log($p_type, $p_message);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Function : getConfig()
     * Description :
     * ---------------------------------------------------------------------------
     */
    static function getConfig($p_name) {
      global $aruba_iot_websocket;
      return($aruba_iot_websocket->getConfig($p_name));
    }
    /* -------------------------------------------------------------------------*/
 
    /**---------------------------------------------------------------------------
     * Function : getReporterByMac()
     * Description :
     * ---------------------------------------------------------------------------
     */
    static function getReporterByMac($p_mac) {
      global $aruba_iot_websocket;
      return($aruba_iot_websocket->getReporterByMac($p_mac));
    }
    /* -------------------------------------------------------------------------*/
 
    /**---------------------------------------------------------------------------
     * Function : getDeviceByMac()
     * Description :
     * ---------------------------------------------------------------------------
     */
    static function getDeviceByMac($p_mac) {
      global $aruba_iot_websocket;
      return($aruba_iot_websocket->getDeviceByMac($p_mac));
    }
    /* -------------------------------------------------------------------------*/
 
    /**---------------------------------------------------------------------------
     * Function : notification()
     * Description :
     * ---------------------------------------------------------------------------
     */
    static function notification($p_notification, $p_args) {
      global $aruba_iot_websocket;
      return($aruba_iot_websocket->notification($p_notification, $p_args));
    }
    /* -------------------------------------------------------------------------*/
 
    /**---------------------------------------------------------------------------
     * Method : macToString()
     * Description :
     *   utility to format MAC@ from ArubaTelemetry protobuf format to string
     * ---------------------------------------------------------------------------
     */
    static function macToString($p_mac) {

      $v_size = $p_mac->getSize();
      if ($v_size != 6) {
        return "";
      }

      $v_data = $p_mac->getContents();
      $_val = unpack('C6parts', $v_data);

      $v_mac = sprintf("%02x:%02x:%02x:%02x:%02x:%02x",$_val['parts1'],$_val['parts2'],$_val['parts3'],$_val['parts4'],$_val['parts5'],$_val['parts6']);

      return filter_var(trim(strtoupper($v_mac)), FILTER_VALIDATE_MAC);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : stringToMac()
     * Description :
     *   utility to format string to MAC@ for ArubaTelemetry protobuf format
     * ---------------------------------------------------------------------------
     */
    static function stringToMac($p_mac_str) {

      $v_item = explode(':', $p_mac_str);
      if (sizeof($v_item) != 6) {
        return(null);
      }
      
      $v_mac = pack('CCCCCC', hexdec($v_item[0]), hexdec($v_item[1]), hexdec($v_item[2]), 
                          hexdec($v_item[3]), hexdec($v_item[4]), hexdec($v_item[5]));

      $stream = fopen('php://temp', 'r+');
      $v_mac_obj = new Protobuf\Stream($stream);
      $v_mac_obj->write($v_mac, 6);
      
      return($v_mac_obj);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : bytesToString()
     * Description :
     * ---------------------------------------------------------------------------
     */
    static function bytesToString($p_bytes) {

      $v_size = $p_bytes->getSize();
      if ($v_size == 0) {
        return('');
      }

      $v_data = $p_bytes->getContents();
      $v_val = unpack('C'.$v_size.'parts', $v_data);
      
      $v_size = sizeof($v_val);

      $v_result = '';
      $v_result_list = array();
      for ($i=1; $i<=$v_size; $i++) {
        $v_result_list[] = sprintf("%02x", $v_val['parts'.$i]);
      }
      $v_result = implode('-', $v_result_list);
      
      return(strtoupper($v_result));
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : stringToBytes()
     * Description :
     *   utility to format string to MAC@ for ArubaTelemetry protobuf format
     * ---------------------------------------------------------------------------
     */
    static function stringToBytes($p_bytes_str) {

      $v_item = explode('-', $p_bytes_str);
      
      $v_len = sizeof($v_item);
      
      $stream = fopen('php://temp', 'r+');
      $v_obj = new Protobuf\Stream($stream);

      for ($i=0; $i<$v_len; $i++) {
        //ArubaWssTool::log('info', "Pack :'".$v_item[$i]."'");
        $v_byte = pack('C', hexdec($v_item[$i]));
        $v_obj->write($v_byte, 1);
      }
                  
      return($v_obj);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : stringbytesToText()
     * Description :
     *   utility to get an ascii string based on bytes presented in "hex string"
     *   like "41-6C-6C-6F" -> "Allo"
     * ---------------------------------------------------------------------------
     */
    static function stringbytesToText($p_bytes_str, $p_strict=false) {

      $v_item = explode('-', $p_bytes_str);
      
      $v_len = sizeof($v_item);
      
      $v_string = '';
      $v_all_ascii = true;

      for ($i=0; $i<$v_len; $i++) {
        //ArubaWssTool::log('info', "Pack :'".$v_item[$i]."'");
        //echo "byte:".print_r(hexdec($v_item[$i]), true)."\n";
        //echo "chr:".chr($v_byte)."/n";
        $v_val = hexdec($v_item[$i]);
        if (($v_val > 0x1F) && ($v_val < 0x7F)) {
          $v_byte = pack('C', hexdec($v_item[$i]));
          $v_char = print_r($v_byte, true);
        }
        else {
          $v_char ='.';
          $v_all_ascii = false;
          // ----- If strict no need to go through all the bytes
          if ($p_strict)
            break;
        }
          
        $v_string .= $v_char;
        //$v_string .= print_r(hexdec($v_item[$i]), true);
      }
                  
      if ($p_strict && !$v_all_ascii) {
        return($p_bytes_str);
      }
      
      return($v_string);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : sendProtoMessage()
     * Description :
     * ---------------------------------------------------------------------------
     */
    static function sendProtoMessage($p_cnx, $p_proto_msg) {
    
      if (($p_cnx === null) || ($p_proto_msg === null)) {
        return(false);
      }

      $v_closeFrameChecker = new \Ratchet\RFC6455\Messaging\CloseFrameChecker;
      $v_deflateOptions = null;      
      $v_msg_interface = new \Ratchet\RFC6455\Messaging\Message();      
      $v_frame = new \Ratchet\RFC6455\Messaging\Frame($p_proto_msg->toStream(), true, \Ratchet\RFC6455\Messaging\Frame::OP_BINARY);      
      $v_msg_interface->addFrame($v_frame);      

      $v_msg_buffer = new \Ratchet\RFC6455\Messaging\MessageBuffer($v_closeFrameChecker,
                                           function (MessageInterface $v_message, MessageBuffer $v_messageBuffer) {}, 
                                           null, true, null, null, null,
                                           [$p_cnx, 'write'], $v_deflateOptions);
         
      $v_msg_buffer->sendMessage($v_msg_interface->getPayload(), true, $v_msg_interface->isBinary());

      return(true);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : sendWebsocketMessage()
     * Description :
     * ---------------------------------------------------------------------------
     */
    static function sendWebsocketMessage($p_cnx, $p_msg) {
    
      if (($p_cnx === null) || ($p_msg == '')) {
        return(false);
      }

      $v_closeFrameChecker = new \Ratchet\RFC6455\Messaging\CloseFrameChecker;
      $v_deflateOptions = null;      

      $v_msg_buffer = new \Ratchet\RFC6455\Messaging\MessageBuffer($v_closeFrameChecker,
                                           function (MessageInterface $v_message, MessageBuffer $v_messageBuffer) {}, 
                                           null, true, null, null, null,
                                           [$p_cnx, 'write'], $v_deflateOptions);
         
      $v_msg_buffer->sendMessage($p_msg, true);

      return(true);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : getDeviceClassList()
     * Description :
     * ---------------------------------------------------------------------------
     */
    static function getDeviceClassList() {
      global $aruba_iot_websocket;
      return($aruba_iot_websocket->getDeviceClassList());
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : arubaClassToVendor()
     * Description :
     * ---------------------------------------------------------------------------
     */
    static function arubaClassToVendor($p_classname) {
      $v_result = array();
      
      // ----- Get known device class list
      $v_list = ArubaWssTool::getDeviceClassList();
      
      // ----- Extract vendor_id:device_id from Aruba classname
      foreach ($v_list as $v_key => $v_vendor) {
        foreach ($v_vendor['devices'] as $v_device) {
          ArubaWssTool::log('debug', "Look for : ".$v_vendor['name'].':'.$v_device['name']);
          if ($v_device['aruba_class'] == $p_classname) {
            $v_result['vendor_id'] = $v_vendor['name'];
            $v_result['model_id'] = $v_device['name'];
            return($v_result);
          }
        }
      }

      // ----- Not found      
      $v_result['vendor_id'] = 'generic';
      $v_result['model_id'] = 'generic';

      return($v_result);
    }
    /* -------------------------------------------------------------------------*/
    
    /**---------------------------------------------------------------------------
     * Method : arubaClassToVendor()
     * Description :
     * ---------------------------------------------------------------------------
     */
    static function arubaClassToVendor_BACK($p_classname) {
      $v_result = array();
      $v_result['vendor_id'] = 'generic';
      $v_result['model_id'] = 'generic';
      
      $v_list = array(
        "unclassified"=>"generic:generic",
        "arubaBeacon"=>"Aruba:Beacon",
        "arubaTag"=>"Aruba:Tag",
        "zfTag"=>"ZF:Tag",
        "stanleyTag"=>"Stanley:Tag",
        "virginBeacon"=>"Virgin:Beacon",
        "enoceanSensor"=>"Enocean:Sensor",
        "enoceanSwitch"=>"Enocean:Switch",
        "iBeacon"=>"generic:generic",
        "allBleData"=>"generic:generic",
        "RawBleData"=>"generic:generic",
        "eddystone"=>"generic:generic",
        "assaAbloy"=>"AssaAbloy:generic",
        "arubaSensor"=>"Aruba:Sensor",
        "abbSensor"=>"ABB:Sensor",
        //"wifiTag"=>"generic:generic",
        //"wifiAssocSta"=>"generic:generic",
        //"wifiUnassocSta"=>"generic:generic",
        "mysphera"=>"MySphera:generic",
        "sBeacon"=>"generic:generic",
        "wiliot"=>"Wiliot:generic",
        "ZSD"=>"ZSD:generic",
        //"serialdata"=>"generic:generic",
        //"exposureNotification"=>"generic:generic",
        "onity"=>"Onity:generic",
        "minew"=>"Minew:generic",
        "google"=>"Google:generic",
        "polestar"=>"Polestar:generic",
        "blyott"=>"Blyott:generic",
        "diract"=>"Diract:generic",
        "gwahygiene"=>"Gwahygiene:generic"
        );
      
      if (isset($v_list[$p_classname])) {
        $v_val = explode(":", $v_list[$p_classname]);
        $v_result['vendor_id'] = $v_val[0];
        $v_result['model_id'] = $v_val[1];
      }
      
      return($v_result);
    }
    /* -------------------------------------------------------------------------*/
    
  }
  /* -------------------------------------------------------------------------*/



  /**---------------------------------------------------------------------------
   * Class : ArubaWebsocket
   * Description :
   * ---------------------------------------------------------------------------
   */
  class ArubaWebsocket {
    // ----- Attributes from configuration
    protected $ip_address = '0.0.0.0';
    protected $tcp_port = '8081';
    protected $api_key = '';
    protected $presence_timeout = 90;
    protected $presence_min_rssi = -90;
    protected $presence_rssi_hysteresis = 5; 
    protected $nearest_ap_hysteresis = 5;
    protected $nearest_ap_timeout = 90;
    protected $nearest_ap_min_rssi = -90; 
    protected $reporters_allow_list = array();
    protected $access_token = '';
    
    // ----- telemetry_max_timestamp (default 60 secondes)
    // When a telemetry value received is same as previous one, the change flag is not updated, the value is not notified to websocket clients or thirdparty plugins.
    // But when the aging time is greater than "telemetry_max_timestamp" second then the value is updated, even is the value is the same.
    protected $telemetry_max_timestamp = 60;
    
    protected $console_log = false;
    protected $log_fct_name = 'ArubaWebsocket::log_fct_empty';
    protected $debug_level = 5;

    // ----- Attributes to manage dynamic datas
    protected $up_time = 0;
    protected $connections_list;
    protected $reporters_list = array();
    protected $cached_devices = array();

    // ----- Attributes to manage include mode mechanism
    protected $include_mode = false;
    protected $include_device_count = 0;
    protected $device_type_allow_list = array();
    protected $include_generic_with_local = 0;
    protected $include_generic_with_mac = 0;
    protected $include_generic_mac_prefix = '';
    protected $include_generic_max_devices = 3;

    // ----- Statistics data
    protected $payload_data = 0;
    protected $raw_data = 0;
    
    // ----- Cron-like mechanism
    protected $cron_action_list = array();
    protected $cron20_tik = 2;  
    protected $cron30_tik = 3;
    protected $cron60_tik = 6;
    protected $cron5m_tik = 30;
    protected $cron10m_tik = 60;
    protected $cron30m_tik = 180;
    protected $cron60m_tik = 360;   
    
    // ----- Data to manage GATT message queueing
    protected $gatt_queue = array();
    // ----- Use to store last log msg for gatt request/response 
    protected $gatt_log_msg = '';
    
    // ----- Data to manage notification queueing
    protected $notification_queue = array();


    /**---------------------------------------------------------------------------
     * Method : __construct()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function __construct() {
      $this->connections_list = new \SplObjectStorage;
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Function : log_fct_empty()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function log_fct_empty($p_type, $p_sub_type, $p_level, $p_message) {
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Function : log()
     * Description :
     *   $p_type : '<main>' or '<main>:<level>' or '<main>-<subtype>:<level>'
     *     <main> : must be 'debug', 'info', 'error', 'warning'
     *     <subtype> : future use (filtering by block)
     *     <level> : debug level
     *   $p_message : log message.
     * ---------------------------------------------------------------------------
     */
    public function log($p_type, $p_message) {
    
      // ----- Extract level from type
      $v_list = explode(':', $p_type);
      $v_type = $v_list[0];
      $v_level = 1;
      $v_n = sizeof($v_list);
      if ($v_n >= 2) {
        $v_level = $v_list[$v_n-1];
      }
      
      // ----- Extract subtype from type
      $v_list = explode('-', $v_type);
      $v_type = $v_list[0];
      $v_subtype = '';
      $v_n = sizeof($v_list);
      if ($v_n >= 2) {
        $v_subtype = $v_list[$v_n-1];
      }
      
      // ----- Filter by debug level
      if ($v_level > $this->debug_level) {
        // ----- Ignore this level of debug
        return;
      }
      
      // ----- Display on console if needed
      if ($this->console_log) {
        echo '['.date("Y-m-d H:i:s").'] ['.$p_type.']:'.$p_message."\n";
      }

      // ----- The function MUST EXISTS !!
      // Normally defined in init, so no worry. And for plugins, 
      // it is checked before being set, so no worry again.
      // Avoidinig testing is to speed up the process.
      // if not can add : function_exists()      
      $v_fct = $this->log_fct_name;
      $v_fct($v_type, $v_subtype, $v_level, $p_message);
  
      return;
    }
    /* -------------------------------------------------------------------------*/
 
    public function setIpAddress($p_ip_addess) {
      $p_ip_addess = filter_var(trim($p_ip_addess), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
      if ($p_ip_addess == '') {
        $p_ip_addess = "0.0.0.0";
      }
      $this->ip_address = $p_ip_addess;
    }

    public function getIpAddress() {
      return($this->ip_address);
    }

    public function setTcpPort($p_tcp_port) {
      $this->tcp_port = $p_tcp_port;
    }

    public function getTcpPort() {
      return($this->tcp_port);
    }

    public function getInterruptTimeout() {
      $v_timeout = 10;

      /*
      // Seems that an interrupt every 10 sec should be ok to validate the absence of devices ...
      $v_timeout = ArubaIotConfig::byKey('presence_timeout', 'ArubaIot');
      // ----- Min interrupt is 10 seconds
      if ($v_timeout < 10)
        $v_timeout = 10;

        */
      return($v_timeout);
    }

    public function getReporterByMac($p_mac) {
      if (isset($this->reporters_list[$p_mac])) {
          return($this->reporters_list[$p_mac]);
      }
    }

    public function getReporterByDeviceMac($p_device_mac) {
      if (($v_device = $this->getDeviceByMac($p_device_mac)) === null) {      
        return(null);
      }
      return($this->getReporterByMac($v_device->getConnectApMac()));
    }

    public function getDeviceByMac($p_mac) {
      if (isset($this->cached_devices[$p_mac])) {
          return($this->cached_devices[$p_mac]);
      }
      return(null);
    }

    public function getReporterByConnectionId($p_id) {
      foreach ($this->reporters_list as $v_reporter) {
        if ($v_reporter->connection_id == $p_id) {
          return($v_reporter);
        }
      }
      return(null);
    }

    public function getConnectionBleByReporterMac($p_mac) {
      $v_reporter = $this->getReporterByMac($p_mac);
      if ($v_reporter != null) {
        return($this->getConnectionById($v_reporter->getConnectionIdBle()));
      }
      return(null);
    }

    public function getConnectionById($p_id) {
      foreach ($this->connections_list as $v_connection) {
        if ($v_connection->my_id == $p_id) {
          return($v_connection);
        }
      }
      return(null);
    }

    /**---------------------------------------------------------------------------
     * Method : getConfig()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function getConfig($p_name) {
      $v_value = '';

      switch ($p_name) {
        case 'presence_timeout':
          $v_value = $this->presence_timeout;
        break;
        case 'ws_ip_address':
          $v_value = $this->ip_address;
        break;
        case 'ws_port':
          $v_value = $this->tcp_port;
        break;
        case 'access_token':
          $v_value = $this->access_token;
        break;
        case 'reporters_allow_list':
          $v_value = '';
        break;
        case 'presence_min_rssi':
          $v_value = $this->presence_min_rssi;
        break;
        case 'presence_rssi_hysteresis':
          $v_value = $this->presence_rssi_hysteresis;
        break;
        case 'nearest_ap_hysteresis':
          $v_value = $this->nearest_ap_hysteresis;
        break;
        case 'nearest_ap_timeout':
          $v_value = $this->nearest_ap_timeout;
        break;
        case 'nearest_ap_min_rssi':
          $v_value = $this->nearest_ap_min_rssi;
        break;
        case 'api_key':
          $v_value = $this->api_key;
        break;
        case 'console_log':
          $v_value = $this->console_log;
        break;
        case 'log_fct_name':
          $v_value = $this->log_fct_name;
        break;
        case 'telemetry_max_timestamp':
          $v_value = $this->telemetry_max_timestamp;
        break;
        
        default :
          ArubaWssTool::log('error', "Unknown configuration type '".$p_name."'");
          $v_value = '';
      }

      return($v_value);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : getDeviceClassList()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function getDeviceClassList() {
      return($this->device_class_list);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : loadDeviceClassList()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function loadDeviceClassList() {
    
      // ----- Look Aruba Class JSON file
      $v_filename = __DIR__."/awss/data/devices/aruba_class.json";
      if (!file_exists($v_filename)) {
        ArubaWssTool::log('error', "Missing Aruba Class JSON file '".$v_filename."'");
        return;
      }
      
      // ----- Read file
      if (($v_handle = @fopen($v_filename, "r")) === null) {
        ArubaWssTool::log('error', "Fail to open Aruba Class JSON file '".$v_filename."'");
        return;
      }
      $v_list_json = @fread($v_handle, filesize($v_filename));
      @fclose($v_handle);
      
      if (($this->device_class_list = json_decode($v_list_json, true)) === null) {
        ArubaWssTool::log('error', "Badly formatted JSON content in file '".$v_filename."'");
        return;
      }
      
      // ----- Add a type field to identify 'known' BLE vendor Aruba class
      // will be used later to add 'unclassified' BLE vendors.
      foreach ($this->device_class_list as $v_key => $v_vendor) {
        $this->device_class_list[$v_key]['type'] = 'classified';  
      }
      
      //ArubaWssTool::log('debug', "device_class_list : ".print_r($this->device_class_list, true));
    
      return($this->device_class_list);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : loadDeviceClassList()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function loadDeviceClassList_BACK() {

      $v_class_json = <<<JSON_EOT
{
  "Aruba": {
    "name": "Aruba",
    "description": "",
    "devices": {
      "Beacon": {
        "aruba_class": "arubaBeacon", 
        "name": "Beacon",
        "description": ""
      },
      "Tag": {
        "aruba_class": "arubaTag", 
        "name": "Tag",
        "description": ""
      },
      "Sensor": {
        "aruba_class": "arubaSensor", 
        "name": "Sensor",
        "description": ""
      }
    }
  },
  
  "ZF": {
    "name": "ZF",
    "description": "",
    "devices": {
      "Tag": {
        "aruba_class": "zfTag", 
        "name": "Tag",
        "description": ""
      }
    }
  },
  
  "Stanley": {
    "name": "Stanley",
    "description": "",
    "devices": {
      "Tag": {
        "aruba_class": "stanleyTag", 
        "name": "Tag",
        "description": ""
      }
    }
  },
  
  "Virgin": {
    "name": "Virgin",
    "description": "",
    "devices": {
      "Beacon": {
        "aruba_class": "virginBeacon", 
        "name": "Beacon",
        "description": ""
      }
    }
  },
  
  "Enocean": {
    "name": "EnOcean",
    "description": "",
    "devices": {
      "Sensor": {
        "aruba_class": "enoceanSensor", 
        "name": "Sensor",
        "description": ""
      },
      "Switch": {
        "aruba_class": "enoceanSwitch", 
        "name": "Switch",
        "description": ""
      }
    }
  },
  
  "ABB": {
    "name": "ABB",
    "description": "",
    "devices": {
      "Sensor": {
        "aruba_class": "abbSensor", 
        "name": "Sensor",
        "description": ""
      }
    }
  },
  
  "MySphera": {
    "name": "MySphera",
    "description": "",
    "devices": {
      "generic": {
        "aruba_class": "mysphera", 
        "name": "generic",
        "description": ""
      }
    }
  },
  
  "Wiliot": {
    "name": "Wiliot",
    "description": "",
    "devices": {
      "generic": {
        "aruba_class": "wiliot", 
        "name": "generic",
        "description": ""
      }
    }
  },
  
  "Onity": {
    "name": "Onity",
    "description": "",
    "devices": {
      "generic": {
        "aruba_class": "onity", 
        "name": "generic",
        "description": ""
      }
    }
  },
  
  "Minew": {
    "name": "Minew",
    "description": "",
    "devices": {
      "generic": {
        "aruba_class": "minew", 
        "name": "generic",
        "description": ""
      }
    }
  },
  
  "Google": {
    "name": "Google",
    "description": "",
    "devices": {
      "generic": {
        "aruba_class": "google", 
        "name": "generic",
        "description": ""
      }
    }
  },
  
  "Polestar": {
    "name": "Polestar",
    "description": "",
    "devices": {
      "generic": {
        "aruba_class": "polestar", 
        "name": "generic",
        "description": ""
      }
    }
  },
  
  "Blyott": {
    "name": "Blyott",
    "description": "",
    "devices": {
      "generic": {
        "aruba_class": "blyott", 
        "name": "generic",
        "description": ""
      }
    }
  },
  
  "Diract": {
    "name": "Diract",
    "description": "",
    "devices": {
      "generic": {
        "aruba_class": "diract", 
        "name": "generic",
        "description": ""
      }
    }
  },
  
  "Gwahygiene": {
    "name": "Gwahygiene",
    "description": "",
    "devices": {
      "generic": {
        "aruba_class": "gwahygiene", 
        "name": "generic",
        "description": ""
      }
    }
  }
  
  
}
JSON_EOT;

      $this->device_class_list = json_decode($v_class_json, true);

      return($this->device_class_list);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : toArray()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function toArray($p_mode='') {
      $v_item = array();
      
      $v_item['aruba_ws_version'] = ARUBA_WSS_VERSION;
      $v_item['ip_address'] = $this->getIpAddress();
      $v_item['tcp_port'] = $this->getTcpPort();
      $v_item['up_time'] = $this->up_time;
      
      $v_item['presence_timeout'] = $this->presence_timeout;
      $v_item['presence_min_rssi'] = $this->presence_min_rssi;
      $v_item['presence_rssi_hysteresis'] = $this->presence_rssi_hysteresis; 
      $v_item['nearest_ap_hysteresis'] = $this->nearest_ap_hysteresis;
      $v_item['nearest_ap_timeout'] = $this->nearest_ap_timeout;
      $v_item['nearest_ap_min_rssi'] = $this->nearest_ap_min_rssi; 
      $v_item['reporters_allow_list'] = implode(",", $this->reporters_allow_list);
      $v_item['access_token'] = $this->access_token;
    
      $v_item['reporters_nb'] = sizeof($this->reporters_list);
      $v_item['devices_nb'] = sizeof($this->cached_devices);

      $v_item['include_mode'] = ($this->include_mode?1:0);
      $v_item['include_device_count'] = $this->include_device_count;
      $v_item['device_type_allow_list'] = implode(",", $this->device_type_allow_list);
      $v_item['include_generic_with_local'] = $this->include_generic_with_local;
      $v_item['include_generic_with_mac'] = $this->include_generic_with_mac;
      $v_item['include_generic_mac_prefix'] = $this->include_generic_mac_prefix;
      $v_item['include_generic_max_devices'] = $this->include_generic_max_devices;

      $v_item['stats']['payload_data'] = $this->payload_data;
      $v_item['stats']['raw_data'] = $this->raw_data;
    
      $v_item['gatt_queue_nb'] = sizeof($this->gatt_queue); 
      
      if ($p_mode == 'extended') {
      }
      
      return($v_item);
    }
    /* -------------------------------------------------------------------------*/
    
    /**---------------------------------------------------------------------------
     * Method : parseArgV()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function parseArgV($p_argv) {
      $v_args = array();
      
      $v_count = 0;
      foreach ($p_argv as $arg)     {
    
        if ($arg == '-server_ip') {
          $v_args['server_ip'] = (isset($p_argv[$v_count+1]) ? $p_argv[$v_count+1] : '');
        }
    
        if ($arg == '-server_port') {
          $v_args['server_port'] = (isset($p_argv[$v_count+1]) ? $p_argv[$v_count+1] : '');
        }
    
        if ($arg == '-reporters_key') {
          $v_args['reporters_key'] = (isset($p_argv[$v_count+1]) ? $p_argv[$v_count+1] : '');
        }
        if ($arg == '-reporters_list') {
          $v_args['reporters_list'] = (isset($p_argv[$v_count+1]) ? $p_argv[$v_count+1] : '');
        }
    
        if ($arg == '-devices_list') {
          $v_args['devices_list'] = (isset($p_argv[$v_count+1]) ? $p_argv[$v_count+1] : '');
        }
        
        if ($arg == '-api_key') {
          $v_args['api_key'] = (isset($p_argv[$v_count+1]) ? $p_argv[$v_count+1] : '');
        }
        
        if ($arg == '-console_log') {
          $v_args['console_log'] = true;
        }
    
        if ($arg == '-debug_level') {
          $v_args['debug_level'] = (isset($p_argv[$v_count+1]) ? $p_argv[$v_count+1] : '');
        }

        if ($arg == '-display_ping') {
          $v_args['display_ping'] = true;
        }
    
        if ($arg == '-display_raw_data') {
          $v_args['display_raw_data'] = true;
        }
    
        if ($arg == '-file') {
          $v_args['use_testing_file'] = (isset($p_argv[$v_count+1]) ? $p_argv[$v_count+1] : '');
        }
    
        if (($arg == '-help') || ($arg == '--help')) {
          echo "----- \n";
          echo $p_argv[0]." [-help] [-version] [-console_log] [-debug_level X] ";
          echo "[-server_ip X.X.X.X] [-server_port XXX] [-api_key XXX] ";
          echo "[-reporters_key XXX] [-reporters_list X1,X2,X3...] ";
          echo "[-devices_list X1,X2,X3...] [-display_ping] [-display_raw_data] ";
          echo "[-no_extension] [-extension <extension_name>] ";
          echo "[-file <debug_message_filename>]\n";
          echo "----- \n";
          exit();
        }
    
        if (($arg == '-version') || ($arg == '--version')) {
          echo "----- \n";
          echo $p_argv[0]." ".ARUBA_WSS_VERSION."\n";
          echo "----- \n";
          exit();
        }
    
        $v_count++;
      }      
      
      return($v_args);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : init()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function init($p_args_v) {

      // ----- Store start time
      $this->up_time = time();

      // ----- Parse arguments
      $p_args = $this->parseArgV($p_args_v);
      
      // ----- Look for console log
      // Must be just after arg parse
      if (isset($p_args['console_log'])) {
        $this->console_log = true;
      } 
      if (isset($p_args['debug_level'])) {
        $this->debug_level = $p_args['debug_level'];
      } 
      
      // ----- Change for extension log function
      $v_obj_class = ARUBA_WSS_DEVICE_CLASS;
      if (($v_obj_class != 'ArubaWssDevice') && (class_exists($v_obj_class))) {
        if (method_exists($v_obj_class,'extension_log')) {
          $this->log_fct_name = $v_obj_class.'::extension_log';
        }
      }

      ArubaWssTool::log('info', "----- Starting ArubaIot Websocket Server Daemon v.".ARUBA_WSS_VERSION." (".date("Y-m-d H:i:s", $this->up_time).")'");

      // ----- Look for no list, create empty one
      if ($p_args === null) {
        $p_args = array();
      }

      // ----- Get init function for extension (if any)
      // In this inti the extension may change arg values received in argv
      $v_obj_class = ARUBA_WSS_DEVICE_CLASS;
      if (($v_obj_class != 'ArubaWssDevice') && (class_exists($v_obj_class))) {
        $v_obj_class .= '::extension_init';
        $v_obj_class($p_args);
      }

      // ----- Initialize ip/port/etc ... from plugin configuration
      if (isset($p_args['server_ip'])) {
        $this->setIpAddress($p_args['server_ip']);
      }
      if (isset($p_args['server_port'])) {
        $this->setTcpPort($p_args['server_port']);
      }
      if (isset($p_args['reporters_key'])) {
        $this->access_token = trim($p_args['reporters_key']);
      }
      if (isset($p_args['api_key'])) {
        $this->api_key = trim($p_args['api_key']);
      }
      if (isset($p_args['reporters_list'])) {
        $v_val = trim($p_args['reporters_list']);

        //$v_val = trim(ArubaWssTool::getConfig('reporters_allow_list'));
        ArubaWssTool::log('info', "Learning reporters allowed list : '".$v_val."'");
        $v_list = explode(',', $v_val);
        $this->reporters_allow_list = array();
        foreach ($v_list as $v_item) {
          $v_mac = filter_var(trim(strtoupper($v_item)), FILTER_VALIDATE_MAC);
          if ($v_mac !== false) {
            ArubaWssTool::log('debug', "Allowed reporter : '".$v_mac."'");
            $this->reporters_allow_list[] = $v_mac;
          }
        }
      }
      
      // ----- Load unknown device class list
      $this->loadDeviceClassList();
      
      // ----- Pre-load devices
      if (isset($p_args['devices_list'])) {
        $v_list = explode(',', $p_args['devices_list']);
        foreach ($v_list as $v_item) {
          $this->loadDevice(trim($v_item));
        }
      }

      return;
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : createDevice()
     * Description :
     *   Create a new local device in the wss cache.
     *   If the device already exists in the cache will send it back or not 
     *   depending on $p_strict.
     *   If the device is to be created, then the third  party hook (createMe()) 
     *   will be called.
     *   If telemetry values are present will use info if possible for name and 
     *   vendor.
     *   Different from loadDevice() which will create the object in the cache, 
     *   but knowing that the device is known by the third party plugin.
     * Parameters :
     *   $v_telemetry : Protobuf decoded Telemetry message.
     *   $p_strict : true : will create a new device only if no exisiting one with this mac. else return null.
     *               false : will return the existing device or create a new one.
     * ---------------------------------------------------------------------------
     */
    public function createDevice($v_device_mac, $v_class_name, $v_telemetry=null, $p_strict=true) {

      // TBC : Check MAC@ format ...
/*    
            $v_mac = filter_var(trim(strtoupper($v_item)), FILTER_VALIDATE_MAC);
        if ($v_mac !== false) {
*/

      // ----- Look if aready in cache
      $v_device = $this->getDeviceByMac($v_device_mac);
      if ($v_device !== null) {
        if ($p_strict) {
          ArubaWssTool::log('debug',  "Trying to create a device '".$v_device_mac."' which is already in device cached list. return null.");
          return(null);
        }
        else {
          ArubaWssTool::log('debug',  "Trying to create a device '".$v_device_mac."' which is already in device cached list. return it.");
          return($v_device);
        }
      }
      
      // ----- Get the configured object class
      $v_obj_class = ARUBA_WSS_DEVICE_CLASS;
      if (!class_exists($v_obj_class)) {
        $v_obj_class = 'ArubaWssDevice';
      }
      
      // ----- Create the object
      // The class must inherit from ArubaWssDevice
      $v_device = new $v_obj_class($v_device_mac, $v_class_name);
      
      // ----- Look for infos in telemetry data
      if ($v_telemetry !== null) {
        $v_device->updateObjectClass($v_telemetry, $v_class_name);
      }
      
      // ----- Change name if possible
      $v_name = '';
      if (($v_name = $v_device->getLocalName()) != '') {
        $v_device->setName($v_name);
      }
      if (($v_name = $v_device->getVendorName()) != '') {
        if (($v_name2 = $v_device->getModel()) != '') {
          $v_name .= ' '.$v_name2;
        }
        $v_device->setName($v_name);
      }
      
      // ----- Call the creation method, used mainly to hook to extensions
      $v_device->createMe();
            
      // ----- Add object in the device list
      $this->cached_devices[$v_device_mac] = $v_device;
    
      // ----- Return created object
      return($v_device);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : loadDevice()
     * Description :
     *   The function create a new device in the wss cache list, but knowing 
     *   that the device exists elsewhere suing a thirdparty plugin.
     *   So after the create the device will be loaded from the plugin.
     *   It is different from createDevice, which create a new device in the 
     *   cache and warm the thirdparty plugin that there is a new device 
     *   in the wss.
     * ---------------------------------------------------------------------------
     */
    public function loadDevice($v_device_mac) {
      
      // ----- Look if aready in cache
      $v_device = $this->getDeviceByMac($v_device_mac);
      if ($v_device !== null) {
      //if (isset($this->cached_devices[$v_device_mac])) {
        ArubaWssTool::log('debug',  "Trying to load a device '".$v_device_mac."' which is already in device cached list. retrun null.");
        return(null);
      }
      
      // ----- Get the configured object class
      $v_obj_class = ARUBA_WSS_DEVICE_CLASS;
      if (!class_exists($v_obj_class)) {
        $v_obj_class = 'ArubaWssDevice';
      }
      
      // ----- Create the object
      // The class must inherit from ArubaWssDevice
      $v_device = new $v_obj_class($v_device_mac);
      
      // ----- Call the creation method, used mainly to hook to extensions
      if ($v_device->loadMe()) {
        // ----- Add object in the device list
        $this->cached_devices[$v_device_mac] = $v_device;
      }
      else {
        unset($v_device);
        $v_device = null;
      }
    
      // ----- Return created object
      return($v_device);
    }
    /* -------------------------------------------------------------------------*/


    /**---------------------------------------------------------------------------
     * Method : onOpen()
     * Description :
     *   'telemetry', 'rtls', 'serial', 'zigbee' connexion are websocket/protobuf connexions.
     *   'ws_api' are websocket API connexion
     *   'api' are http like API connexion (no persistence)
     *   'http' are http/html like connexion
     * Parameters :
     *  $p_type : 'telemetry', 'rtls', 'serial', 'zigbee', 'ws_api', 'api', 'http'
     * ---------------------------------------------------------------------------
     */
    public function onOpen(ConnectionInterface &$p_connection, $p_type) {

      // ----- Get connection IP and TCP values to create an Id
      // Trick : I'm adding a internal ID for the connection, to optimize search later
      // I'm also adding an internal status
      // And a link to the reporter
      $v_address = $p_connection->getRemoteAddress();
      $v_ip = trim(parse_url($v_address, PHP_URL_HOST), '[]');
      $v_port = trim(parse_url($v_address, PHP_URL_PORT), '[]');
      $v_id = $v_ip.':'.$v_port;

      // ------ Look for an existing connection with this ID
      // TBC : should check that an already active connection is not here, or reactivate an old connection ??

      // ----- Add my own attributes to the connection object ....
      $p_connection->my_id = $v_id;
      
      // ----- Type of the connexion from the requested URI
      // 'telemetry', 'rtls', 'serial', 'zigbee', api', 'ws_api', 'http'
      $p_connection->my_type = $p_type;
      
      // ----- Store remote IP of the connexion (mayb useful later)
      $p_connection->my_remote_ip = $v_ip;
      
      // ----- Attach connection in the list
      $this->connections_list->attach($p_connection);

      ArubaWssTool::log('info', "New '".$p_type."' connection from ".$p_connection->my_id."");
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onClose()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function onClose(ConnectionInterface &$connection) {

      $v_flag_cleaning = false;

      // ----- Sometimes I have connexion that are not identified ... to be troubleshooted
      if (!isset($connection->my_id)) {
        $v_address = $connection->getRemoteAddress();
        $v_ip = trim(parse_url($v_address, PHP_URL_HOST), '[]');
        $v_port = trim(parse_url($v_address, PHP_URL_PORT), '[]');
        $v_id = $v_ip.':'.$v_port;
        ArubaWssTool::log('info', "Closing unknown connection '".$v_id."' (".date("Y-m-d H:i:s").")");
      }
      
      else {
        ArubaWssTool::log('info', "Closing Connection '".$connection->my_id."' (".date("Y-m-d H:i:s").")");
        
        $v_cnx_id = $connection->my_id;
        
        if (isset($connection->my_type) && ($connection->my_type == 'ws_api')) {
          $v_flag_cleaning = true;
        }
  
        // ----- Remove cross-links between connection and reporter
        foreach ($this->reporters_list as $v_reporter) {
          // ----- Get reporter
          if ($v_reporter->isConnectedWith($v_cnx_id)) {
            $v_reporter->disconnect($connection);
            foreach ($this->cached_devices as $v_device) {
              $v_device->reporterDisconnectNotification($v_reporter->getMac());
            }
          }
        }
      }
      
      // ----- Remove from connection list
      $this->connections_list->detach($connection);
      
      // ----- Do some cleaning before closing this websocket cnx (remote notification, ...)
      if ($v_flag_cleaning) {
        $this->notificationRemoveByCb('ws_api', $v_cnx_id);
      }
    }
    /* -------------------------------------------------------------------------*/


    /**---------------------------------------------------------------------------
     * Method : onApiCall()
     * Description :
     * Waiting  for jason data :
     * {
     *   "api_version":"1.0",
     *   "api_key":"xxx",
     *   "event":{
     *     "name":"event_name",    // mandatory
     *     "event_id":"123456",    // optional : uniq id to identifiy the request, used for async response like in websocket.
     *     "data": {               // event data depending on event
     *       "data_1":"value_1",
     *       "data_2":"value_2"
     *     }
     *   }
     * }
     * Response format :
     * {
     *   "status":"success",
     *   "status_msg":"Success !!",
     *   "from_event":"event_name",
     *   "event_id":"12345",
     *   "data":{
     *     "data_1":"value_1",
     *     "data_2":"value_2"
     *   }
     * } 
     * ---------------------------------------------------------------------------
     */
    public function onApiCall(ConnectionInterface &$p_connection, $p_msg) {

      ArubaWssTool::log('debug', "New API Connection from ".$p_connection->my_id."");

      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['data'] = array();

      ArubaWssTool::log('debug', 'JSON Data :'.$p_msg);

      // ----- Decode & check JSON format
      if (($v_data = json_decode($p_msg, true)) === null) {
        $v_response['status_msg'] = "Missing or bad json data in API call";
        ArubaWssTool::log('debug', $v_response['status_msg']);
        return(json_encode($v_response));
      }

      //ArubaWssTool::log('trace', $v_data);

      // ----- Look for API version (for future use)
      if (!isset($v_data['api_version']) || ($v_data['api_version'] != '1.0')) {     
        $v_response['status_msg'] = "Bad or missing API version. Only version 1.0 supported today. Cnx refused.";
        ArubaWssTool::log('debug', $v_response['status_msg']);
        return(json_encode($v_response));
      }

      // ----- Look for existing API Key
      if ($this->api_key == '') {      
        $v_response['status_msg'] = "API key is not set. Must be configured on websocket server. Cnx refused.";
        ArubaWssTool::log('debug', $v_response['status_msg']);
        return(json_encode($v_response));
      }

      // ----- Look for valid API Key
      if (!isset($v_data['api_key']) || ($v_data['api_key'] != $this->api_key)) {     
        $v_response['status_msg'] = "Bad or missing API key. Cnx refused.";
        ArubaWssTool::log('debug', $v_response['status_msg']);
        return(json_encode($v_response));
      }

      ArubaWssTool::log('debug', "Valid API key received");

      // ----- Look for missing event
      if (  !isset($v_data['event']) || !is_array($v_data['event'])
          || !isset($v_data['event']['name']) ) {
        $v_response['status_msg'] = "Missing event name in payload.";
        ArubaWssTool::log('debug', $v_response['status_msg']);
        return(json_encode($v_response));
      }

      $v_event_name = $v_data['event']['name'];
      ArubaWssTool::log('debug', "Receive event '".$v_event_name."'");

      // ----- Look for optional event_id
      $v_event_id = (isset($v_data['event']['event_id'])?$v_data['event']['event_id']:'');

      ArubaWssTool::log('debug', "Receive event id '".$v_event_id."'");

      // ----- Call method associated to event
      // By doing this generic call, adding a new event, just need to add the method formatted  apiEvent_<name_of_the_event>()
      $v_method = 'apiEvent_'.$v_event_name;
      if (method_exists($this, $v_method)) {
        // ----- Call the method associated to event name
        $v_response = $this->$v_method((isset($v_data['event']['data'])?$v_data['event']['data']:array()), $p_connection->my_id, $v_event_id);
        
        // ----- Add from_event info
        $v_response['from_event'] = $v_event_name;
        
        // ----- Look for event id to add
        if ($v_event_id != '') {
          $v_response['event_id'] = $v_event_id;
        }
        
        // ----- Return response
        return(json_encode($v_response));
      }
      else {
        // ----- Do nothing !
        $v_response['status_msg'] = "Unknown event '".$v_event_name."'";
        ArubaWssTool::log('debug', $v_response['status_msg']);
        return(json_encode($v_response));
      }
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onWebsocketClientCall()
     * Description :
     * 
     * ---------------------------------------------------------------------------
     */
    public function onWebsocketClientCall(ConnectionInterface &$p_connection, $p_msg) {

      ArubaWssTool::log('debug', "New Websocket Client message from connection ".$p_connection->my_id."");

       //ArubaWssTool::log('debug', 'Received JSON Data :'.$p_msg);

      $v_response = $this->onApiCall($p_connection, $p_msg);      
      
      ArubaWssTool::sendWebsocketMessage($p_connection, $v_response);      

      return(true);

    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onInternalApiCall()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function onInternalApiCall($p_msg) {

      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['data'] = array();
      
      // ----- Reformat to be like external API (should reuse the external ?)
      $p_msg = '{ "event" : '.$p_msg.'}';

      ArubaWssTool::log('debug', 'JSON Data :'.$p_msg);

      if (($v_data = json_decode($p_msg, true)) === null) {
        $v_response['data'] = "Missing or bad json data in API call";
        ArubaWssTool::log('debug', $v_response['data']);
        return(json_encode($v_response));
      }

      // ----- Look for missing event
      if (  !isset($v_data['event']) || !is_array($v_data['event'])
          || !isset($v_data['event']['name']) ) {
        $v_response['data'] = "Missing event info";
        ArubaWssTool::log('debug', $v_response['data']);
        return(json_encode($v_response));
      }

      $v_event_name = $v_data['event']['name'];
      ArubaWssTool::log('debug', "Receive event '".$v_event_name."'");

      // ----- Call method associated to event
      // By doing this generic call, adding a new event, just need to add the method formatted  apiEvent_<name_of_the_event>()
      $v_method = 'apiEvent_'.$v_event_name;
      if (method_exists($this, $v_method)) {
        // ----- Call the method associated to event name
        $v_response = $this->$v_method((isset($v_data['event']['data'])?$v_data['event']['data']:array()));
        
        // ----- Add from_event info
        $v_response['from_event'] = $v_event_name;
        
        // ----- Return response
        return(json_encode($v_response));
      }
      else {
        // ----- Do nothing !
        $v_response['status_msg'] = 'Unknown event.';
        ArubaWssTool::log('debug', $v_response['status_msg']);
        return(json_encode($v_response));
      }

    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiCheckMandatoryData()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function apiCheckMandatoryData(&$p_response, $p_data, $p_fields) {
      
      $v_list = array();
      foreach ($p_fields as $p_att) {
        if (!isset($p_data[$p_att])) {
          $v_list[] = $p_att;
        }
      }
      
      if (sizeof($v_list) == 0) {
        // ----- All good
        return(true);
      }
      
      // ----- Prepare fail response
      $v_list2 = implode(",", $v_list);
      $p_response['status'] = 'fail';
      $p_response['status_msg'] = "Missing mandatory fields in API event : ".$v_list2;
      $p_response['data'] = array();
      
      ArubaWssTool::log('debug', $p_response['status_msg']);

      return(false);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_exeeemple()
     * Description :
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_exeeemple($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();

      // ----- Check mandatory fields are present
      if (!$this->apiCheckMandatoryData($v_response, $p_data, array('state'))) {
        return($v_response);
      }

      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['data'] = array();

      if (!isset($p_data['state'])) {
        $v_response['data'] = "Missing event data";
        ArubaWssTool::log('debug', $v_response['data']);
        return(json_encode($v_response));
      }

      $v_response['status'] = 'success';
      $v_response['data'] = array();

      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_ble_connect()
     * Description :
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_ble_connect($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'ble_connect';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();
      
      if (!$this->apiCheckMandatoryData($v_response, $p_data, array('device_mac'))) {
        return($v_response);
      }
      
      $v_device_mac = "";
      if (isset($p_data['device_mac'])) {        
        $v_device_mac = filter_var(trim(strtoupper($p_data['device_mac'])), FILTER_VALIDATE_MAC);
      }
      $v_response['data']['device_mac'] = $v_device_mac;
      
      ArubaWssTool::log('debug', 'Send Gatt bleConnect');
      if (($v_value = $this->gattDeviceConnect($v_device_mac, $p_cnx_id, $p_external_id)) > 0) {                    
        $v_response['status'] = 'initiated';
        $v_response['status_msg'] = 'bleConnect initiated.';
      }
      else {
        $v_response['status'] = 'fail';
        $v_response['status_msg'] = $this->gatt_log_msg;
      }

      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiResponse_ble_connect()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function apiResponse_ble_connect($p_cnx_id, $p_status, $p_device_mac, $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'ble_connect';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      $v_response['status'] = $p_status;      
      $v_response['data']['device_mac'] = $p_device_mac;

      // ----- Search cnx
      if (($v_cnx = $this->getConnectionById($p_cnx_id)) === null) {
        ArubaWssTool::log('debug', "Fail to find a connexion with this ID from gatt queue.");
        return;
      }
      
      // ----- Look for cnx type
      if ($v_cnx->my_type == 'ws_api') {
        ArubaWssTool::sendWebsocketMessage($v_cnx, json_encode($v_response));
      }

      return;
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_ble_disconnect()
     * Description :
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_ble_disconnect($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'ble_disconnect';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      // ----- Check mandatory fields are present
      if (!$this->apiCheckMandatoryData($v_response, $p_data, array('device_mac'))) {
        return($v_response);
      }

      $v_device_mac = "";
      //$v_device_mac = "54:6C:0E:08:0A:41";
      if (isset($p_data['device_mac'])) {        
        $v_device_mac = filter_var(trim(strtoupper($p_data['device_mac'])), FILTER_VALIDATE_MAC);
      }
      $v_response['data']['device_mac'] = $v_device_mac;

      ArubaWssTool::log('debug', 'Send Gatt bleDisconnect');
      //$v_value = $this->gattDeviceDisconnect($v_device_mac, $p_cnx_id, $p_external_id);
      if (($v_value = $this->gattDeviceDisconnect($v_device_mac, $p_cnx_id, $p_external_id)) > 0) {                    
        $v_response['status'] = 'initiated';
        $v_response['status_msg'] = 'bleDisconnect initiated.';
      }
      else {
        $v_response['status'] = 'fail';
        $v_response['status_msg'] = $this->gatt_log_msg;
      }

      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiResponse_ble_disconnect()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function apiResponse_ble_disconnect($p_cnx_id, $p_status, $p_device_mac, $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'ble_disconnect';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      $v_response['status'] = $p_status;
      $v_response['data']['device_mac'] = $p_device_mac;

      // ----- Search cnx
      if (($v_cnx = $this->getConnectionById($p_cnx_id)) === null) {
        ArubaWssTool::log('debug', "Fail to find a connexion with this ID from gatt queue.");
        return;
      }
      
      // ----- Look for cnx type
      if ($v_cnx->my_type == 'ws_api') {
        ArubaWssTool::sendWebsocketMessage($v_cnx, json_encode($v_response));
      }

      return;
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_ble_discover()
     * Description :
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_ble_discover($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'ble_discover';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();
      
      if (!$this->apiCheckMandatoryData($v_response, $p_data, array('device_mac'))) {
        return($v_response);
      }
      
      $v_device_mac = "";
      if (isset($p_data['device_mac'])) {        
        $v_device_mac = filter_var(trim(strtoupper($p_data['device_mac'])), FILTER_VALIDATE_MAC);
      }
      $v_response['data']['device_mac'] = $v_device_mac;
      
      ArubaWssTool::log('debug', 'Send Gatt bleConnect for characteristics discovery');
      if (($v_value = $this->gattDeviceDiscover($v_device_mac, $p_cnx_id, $p_external_id)) > 0) {                    
        $v_response['status'] = 'initiated';
        $v_response['status_msg'] = 'bleDiscover initiated.';
      }
      else {
        $v_response['status'] = 'fail';
        $v_response['status_msg'] = $this->gatt_log_msg;
      }

      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiResponse_ble_discover()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function apiResponse_ble_discover($p_cnx_id, $p_status, $p_device_mac, $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'ble_discover';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      $v_response['status'] = $p_status;      
      $v_response['data']['device_mac'] = $p_device_mac;

      // ----- Search cnx
      if (($v_cnx = $this->getConnectionById($p_cnx_id)) === null) {
        ArubaWssTool::log('debug', "Fail to find a connexion with this ID from gatt queue.");
        return;
      }
      
      // ----- Look for cnx type
      if ($v_cnx->my_type == 'ws_api') {
        ArubaWssTool::sendWebsocketMessage($v_cnx, json_encode($v_response));
      }

      return;
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_ble_read_multiple()
     * Description :
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_ble_read_multiple($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'ble_read_multiple';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      // ----- Check mandatory fields are present
      if (!$this->apiCheckMandatoryData($v_response, $p_data, array('device_mac','char_list'))) {
        return($v_response);
      }

      $v_device_mac = "";
      if (isset($p_data['device_mac'])) {        
        $v_device_mac = filter_var(trim(strtoupper($p_data['device_mac'])), FILTER_VALIDATE_MAC);
      }
      $v_response['data']['device_mac'] = $p_device_mac;      
      
      $v_list = array();
      $i=0;
      foreach ($p_data['char_list'] as $v_item) {
        if (isset($v_item['service_uuid']) && isset($v_item['char_uuid'])) {
          $v_list[$i] = $v_item;
          $i++;
        }
      }

      ArubaWssTool::log('debug', 'Send Gatt read multiple data');

      if (($v_value = $this->gattDeviceReadMultiple($v_device_mac, $v_list, $p_cnx_id, $p_external_id)) > 0) {                    
        $v_response['status'] = 'initiated';
        $v_response['status_msg'] = 'gattRead initiated.';
      }
      else {
        $v_response['status'] = 'fail';
        $v_response['status_msg'] = $this->gatt_log_msg;
      }
                    
      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_ble_read()
     * Description :
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_ble_read($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'ble_read';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      // ----- Check mandatory fields are present
      if (!$this->apiCheckMandatoryData($v_response, $p_data, array('device_mac','service_uuid','char_uuid'))) {
        return($v_response);
      }

      $v_device_mac = "";
      if (isset($p_data['device_mac'])) {        
        $v_device_mac = filter_var(trim(strtoupper($p_data['device_mac'])), FILTER_VALIDATE_MAC);
      }
      $v_response['data']['device_mac'] = $p_device_mac;
      
      $v_service_uuid = "";
      if (isset($p_data['service_uuid'])) {        
        $v_service_uuid = trim(strtoupper($p_data['service_uuid']));
      }
      
      $v_char_uuid = "";
      if (isset($p_data['char_uuid'])) {        
        $v_char_uuid = trim(strtoupper($p_data['char_uuid']));
      }
      
      ArubaWssTool::log('debug', 'Send Gatt read');
      if (($v_value = $this->gattDeviceRead($v_device_mac, $v_service_uuid, $v_char_uuid, $p_cnx_id, $p_external_id)) > 0) {                    
        $v_response['status'] = 'initiated';
        $v_response['status_msg'] = 'gattRead initiated.';
      }
      else {
        $v_response['status'] = 'fail';
        $v_response['status_msg'] = $this->gatt_log_msg;
      }
                    
      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiResponse_ble_read()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function apiResponse_ble_read($p_cnx_id, $p_status, $p_device_mac, $p_service_uuid, $p_char_uuid, $p_value, $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'ble_read';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();


      $v_response['status'] = $p_status;
      $v_response['data']['device_mac'] = $p_device_mac;
      $v_response['data']['service_uuid'] = $p_service_uuid;
      $v_response['data']['char_uuid'] = $p_char_uuid;
      $v_response['data']['value'] = $p_value;
      
      if ($p_value != null) {
        $v_response['data']['value_string'] = ArubaWssTool::stringbytesToText($p_value, true);
      }

      // ----- Search cnx
      if (($v_cnx = $this->getConnectionById($p_cnx_id)) === null) {
        ArubaWssTool::log('debug', "Fail to find a connexion with this ID from gatt queue.");
        return;
      }
      
      // ----- Look for cnx type
      if ($v_cnx->my_type == 'ws_api') {
        ArubaWssTool::sendWebsocketMessage($v_cnx, json_encode($v_response));
      }

      return;
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_ble_read_repeat()
     * Description :
     * 
     * {
     * 	"api_version":"1.0",
     * 	"api_key":"123",
     *     "event":{
     *         "name":"ble_read_repeat",
     *         "data": {
     *           "repeat_time":20,
     *           "repeat_count":2,
     *           "service_uuid":"AA-20",
     *           "char_uuid":"AA-21",
     *           "device_mac":"E6:FE:37:0D:A4:D7"
     *         }
     *     }
     * }
     * 
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_ble_read_repeat($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'ble_read_repeat';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      // ----- Check mandatory fields are present
      if (!$this->apiCheckMandatoryData($v_response, $p_data, array('device_mac','service_uuid','char_uuid','repeat_time','repeat_count'))) {
        return($v_response);
      }

      $v_device_mac = "";
      if (isset($p_data['device_mac'])) {        
        $v_device_mac = filter_var(trim(strtoupper($p_data['device_mac'])), FILTER_VALIDATE_MAC);
      }
      $v_response['data']['device_mac'] = $p_device_mac;
      
      $v_service_uuid = "";
      if (isset($p_data['service_uuid'])) {        
        $v_service_uuid = trim(strtoupper($p_data['service_uuid']));
      }
      
      $v_char_uuid = "";
      if (isset($p_data['char_uuid'])) {        
        $v_char_uuid = trim(strtoupper($p_data['char_uuid']));
      }
      
      $v_repeat_time = 0;
      if (isset($p_data['repeat_time'])) {        
        $v_repeat_time = $p_data['repeat_time'];
      }
      
      $v_repeat_count = 0;
      if (isset($p_data['repeat_count'])) {        
        $v_repeat_count = $p_data['repeat_count'];
      }
      
      ArubaWssTool::log('debug', 'Send Gatt read repeat');
      if (($v_value = $this->gattDeviceReadRepeat($v_device_mac, $v_service_uuid, $v_char_uuid, $v_repeat_time, $v_repeat_count)) > 0) {                    
        $v_response['status'] = 'initiated';
        $v_response['status_msg'] = 'Repeated gattRead initiated.';
      }
      else {
        $v_response['status'] = 'fail';
        $v_response['status_msg'] = $this->gatt_log_msg;
      }
                    
      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_ble_notify()
     * Description :
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_ble_notify($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'ble_notify';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();
      
      // ----- ***** DISCONNECT THIS API
      /**
       * BLE GATT Notify is opening a permanent connect status with a device
       * For now some situation shows that it can't be disconnected
       * So avoid this since a full troubleshooting is performed to understand.
       * */
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = 'This API is disconnected for now. Please to not use since corrected.';
      return($v_response);

      // ----- Check mandatory fields are present
      if (!$this->apiCheckMandatoryData($v_response, $p_data, array('device_mac','service_uuid','char_uuid','mode'))) {
        return($v_response);
      }

      $v_device_mac = "";
      if (isset($p_data['device_mac'])) {        
        $v_device_mac = filter_var(trim(strtoupper($p_data['device_mac'])), FILTER_VALIDATE_MAC);
      }
      $v_response['data']['device_mac'] = $p_device_mac;
      
      $v_service_uuid = "";
      if (isset($p_data['service_uuid'])) {        
        $v_service_uuid = trim(strtoupper($p_data['service_uuid']));
      }
      
      $v_char_uuid = "";
      if (isset($p_data['char_uuid'])) {        
        $v_char_uuid = trim(strtoupper($p_data['char_uuid']));
      }
      
      $v_mode = "00";
      if (isset($p_data['mode'])) {        
        $v_mode = ($p_data['mode']===1?"01":"00");
      }
      
      // ----- 0 means no timeout
      // TBC : not the timeout in each msg, but the timeout for auto-stopping the notify
      $v_timeout = 0;
      if (isset($p_data['timeout'])) {        
        $v_timeout = $p_data['timeout'];
      }
      
      ArubaWssTool::log('debug', 'Send Gatt notification');
      if (($v_value = $this->gattDeviceNotify($v_device_mac, $v_service_uuid, $v_char_uuid, $v_mode, $p_cnx_id, $p_external_id, false, $v_timeout)) > 0) {                    
        $v_response['status'] = 'initiated';
        $v_response['status_msg'] = 'gattNotification initiated.';
      }
      else {
        $v_response['status'] = 'fail';
        $v_response['status_msg'] = $this->gatt_log_msg;
      }
                    
      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiResponse_ble_notify()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function apiResponse_ble_notify($p_cnx_id, $p_status, $p_status_msg, $p_device_mac, $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'ble_notify';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      $v_response['status'] = $p_status;
      $v_response['status_msg'] = $p_status_msg;      
      $v_response['data']['device_mac'] = $p_device_mac;

      // ----- Search cnx
      if (($v_cnx = $this->getConnectionById($p_cnx_id)) === null) {
        ArubaWssTool::log('debug', "Fail to find a connexion with this ID from gatt queue.");
        return;
      }
      
      // ----- Look for cnx type
      if ($v_cnx->my_type == 'ws_api') {
        ArubaWssTool::sendWebsocketMessage($v_cnx, json_encode($v_response));
      }

      return;
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiNotify_ble_notify()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function apiNotify_ble_notify($p_cnx_id, $p_status, $p_device_mac, $p_service_uuid, $p_char_uuid, $p_value, $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'ble_notify';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      $v_response['status'] = $p_status;
      $v_response['status_msg'] = '';
      $v_response['data']['device_mac'] = $p_device_mac;
      $v_response['data']['service_uuid'] = $p_service_uuid;
      $v_response['data']['char_uuid'] = $p_char_uuid;
      $v_response['data']['value'] = $p_value;
      
      if ($p_value != null) {
        $v_response['data']['value_string'] = ArubaWssTool::stringbytesToText($p_value, true);
      }

      // ----- Search cnx
      if (($v_cnx = $this->getConnectionById($p_cnx_id)) === null) {
        ArubaWssTool::log('debug', "Fail to find a connexion with this ID from gatt queue.");
        return;
      }
      
      // ----- Look for cnx type
      if ($v_cnx->my_type == 'ws_api') {
        ArubaWssTool::sendWebsocketMessage($v_cnx, json_encode($v_response));
      }

      return;
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_get_stats()
     * Description :
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_get_stats($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'ble_stats';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();


      $v_response['status'] = 'success';
      $v_response['data'] = $this->getStats();

      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_device_remove()
     * Description :
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_device_remove($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'device_remove';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      // ----- Check mandatory fields are present
      if (!$this->apiCheckMandatoryData($v_response, $p_data, array('mac_address'))) {
        return($v_response);
      }

      if (!isset($p_data['mac_address']) || ($p_data['mac_address'] == '') ) {
        $v_response['status_msg'] = "Missing valid 'mac_address' in JSON event data";
        ArubaWssTool::log('debug', $v_response['status_msg']);
        return($v_response);
      }
      $v_response['data']['device_mac'] = $p_data['mac_address'];

      ArubaWssTool::log('debug', "Remove device ".$p_data['mac_address']."");

      if (isset($this->cached_devices[$p_data['mac_address']])) {
        unset($this->cached_devices[$p_data['mac_address']]);
        ArubaWssTool::log('info', "Device '".$p_data['mac_address']."' was removed from cache.");
      }
      else {

      }

      $v_response['status'] = 'success';

      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_device_update()
     * Description :
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_device_update($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'device_update';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      // ----- Check mandatory fields are present
      if (!$this->apiCheckMandatoryData($v_response, $p_data, array('mac_address'))) {
        return($v_response);
      }

      if (!isset($p_data['mac_address']) || ($p_data['mac_address'] == '') ) {
        $v_response['status_msg'] = "Missing valid 'mac_address' in JSON event data";
        ArubaWssTool::log('debug', $v_response['status_msg']);
        return($v_response);
      }
      $v_response['data']['device_mac'] = $p_data['mac_address'];
      $v_response['status'] = 'success';

      ArubaWssTool::log('debug', "Update device '".$p_data['mac_address']."'");

      if ($p_data['mac_address'] == '00:00:00:00:00:00') {
        ArubaWssTool::log('debug', "Not yet a valid MAC@, waiting ...");
        $v_response['status'] = 'fail';
        $v_response['status_msg'] = "Not yet a valid MAC@";
        return($v_response);
      }

      // ----- Look if a device already exist with this mac
      $v_device = $this->getDeviceByMac($p_data['mac_address']);
      if ($v_device !== null) { 
        ArubaWssTool::log('info', "Device '".$p_data['mac_address']."' is already in the cache. Update data.");
        
        // ----- reload the device properties from third party (if needed)
        if (!$v_device->loadMe()) {
          // TBC : should not occur ... or device disappear from jeedom
          $v_response['status'] = 'fail';
          $v_response['status_msg'] = "Device with this mac : ".$p_data['mac_address']." fail to load().";
          ArubaWssTool::log('debug', $v_response['status_msg']);
        }
      }
      else {
        // ----- Load device from thirdparty plugin function
        $v_device = $this->loadDevice($p_data['mac_address']);
        if ($v_device === null) {
          $v_response['status'] = 'fail';
          $v_response['status_msg'] = "Failed to load device with this mac : ".$p_data['mac_address'].".";
          ArubaWssTool::log('debug', $v_response['status_msg']);
        }
      }
      
      // ----- Update properties
      // TBC
      ArubaWssTool::log('debug', "Still need to be implemented ...");

      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_websocket_info()
     * Description :
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_websocket_info($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'websocket_info';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      // ----- Check mandatory fields are present
      //if (!$this->apiCheckMandatoryData($v_response, $p_data, array('mac_address'))) {
      //  return($v_response);
      //}

      $v_response['status'] = 'success';
      $v_response['data']['websocket'] = $this->toArray();

      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_reporter_list()
     * Description :
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_reporter_list($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'reporter_list';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      // ----- Check mandatory fields are present
      //if (!$this->apiCheckMandatoryData($v_response, $p_data, array('mac_address'))) {
      //  return($v_response);
      //}

      ArubaWssTool::log('debug', "Send reporter list");

     $v_response['data']['websocket'] = $this->toArray();

      $v_response['data']['reporters'] = array();
      $i = 0;
      foreach ($this->reporters_list as $v_reporter) {
        $v_item = array();
        $v_item['mac'] = $v_reporter->getMac();
        $v_item['name'] = $v_reporter->getName();
        $v_item['local_ip'] = $v_reporter->getLocalIp();
        $v_item['remote_ip'] = $v_reporter->getRemoteIp();
        $v_item['model'] = $v_reporter->getHardwareType();
        $v_item['version'] = $v_reporter->getSoftwareVersion();
        $v_item['telemetry'] = $v_reporter->hasTelemetryCnx();
        $v_item['rtls'] = $v_reporter->hasRtlsCnx();
        $v_item['serial'] = $v_reporter->hasSerialCnx();
        $v_item['zigbee'] = $v_reporter->hasZigbeeCnx();
        $v_item['lastseen'] = $v_reporter->getLastSeen();
        $v_response['data']['reporters'][] = $v_item;
      }

      $v_response['status'] = 'success';

      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_reporter_info()
     * Description :
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_reporter_info($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'reporter_info';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      // ----- Check mandatory fields are present
      if (!$this->apiCheckMandatoryData($v_response, $p_data, array('reporter_mac'))) {
        return($v_response);
      }

      if (!isset($p_data['reporter_mac'])) {
        $v_response['status_msg'] = "Missing mac address";
        ArubaWssTool::log('debug', $v_response['status_msg']);
        return($v_response);
      }
      $v_response['data']['reporter_mac'] = $p_data['reporter_mac'];
                    
      $v_reporter = $this->getReporterByMac($p_data['reporter_mac']);
      if ($v_reporter == null) {
        $v_response['status_msg'] = "Bad mac address, unable to find reporter.";
        ArubaWssTool::log('debug', $v_response['status_msg']);
        return($v_response);
      }
      
      $v_response['data']['reporter'] = $v_reporter->toArray();    
      $v_response['status'] = 'success';
              
      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_device_list()
     * Description :
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_device_list($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'device_list';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      // ----- Check mandatory fields are present
      //if (!$this->apiCheckMandatoryData($v_response, $p_data, array('mac_address'))) {
      //  return($v_response);
      //}

      $v_extended = false;
      if (isset($p_data['extended'])) {
        $v_extended = ($p_data['extended'] == 1);
      }
             
      $v_response['data']['devices'] = array();
      $i = 0;
      foreach ($this->cached_devices as $v_device) {
        $v_response['data']['devices'][] = $v_device->toArray(($v_extended?'extended':''));        
      }
      
      $v_response['status'] = 'success';
              
      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_device_info()
     * Description :
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_device_info($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'device_info';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      // ----- Check mandatory fields are present
      if (!$this->apiCheckMandatoryData($v_response, $p_data, array('device_mac'))) {
        return($v_response);
      }

      if (!isset($p_data['device_mac'])) {
        $v_response['status_msg'] = "Missing mac address";
        ArubaWssTool::log('debug', $v_response['status_msg']);
        return($v_response);
      }
      $v_response['data']['device_mac'] = $p_data['device_mac'];
                    
      $v_device = $this->getDeviceByMac($p_data['device_mac']);
      if ($v_device == null) {
        $v_response['status_msg'] = "Bad mac address, unable to find device.";
        ArubaWssTool::log('debug', $v_response['status_msg']);
        return($v_response);
      }
      
      $v_response['data']['device'] = $v_device->toArray('extended');    
      
      $v_response['status'] = 'success';
              
      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_include_mode()
     * Description :
     * {
	"api_version":"1.0",
	"api_key":"123",
    "event":{
        "name":"include_mode",
        "data": {
          "state":1,
          "type":"generic",
          "generic_with_local":"",
          "generic_with_mac":0,
          "generic_mac_prefix":"",
          "generic_max_devices":10
        }
    }
}
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_include_mode($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'include_mode';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      // ----- Check mandatory fields are present
      if (!$this->apiCheckMandatoryData($v_response, $p_data, array('state'))) {
        return($v_response);
      }

      if (!isset($p_data['state'])) {
        $v_response['status_msg'] = "Missing include_mode event data";
        ArubaWssTool::log('debug', $v_response['status_msg']);
        return($v_response);
      }

      $this->include_mode = ($p_data['state'] == 1?true:false);

      // ----- Reset allow list
      $this->device_type_allow_list = array();

      ArubaWssTool::log('info', "Changing include mode to ".($this->include_mode ? 'true' : 'false'));
      if ($this->include_mode) {
        if (isset($p_data['type'])) {
          ArubaWssTool::log('debug', "Classes to include : ".$p_data['type']);
          $this->device_type_allow_list = explode(',', $p_data['type']);
        }
        else {
          ArubaWssTool::log('debug', "Missing classes to include ! ");
        }

        if (in_array('generic', $this->device_type_allow_list)) {
          $this->include_generic_with_local = (isset($p_data['generic_with_local']) ? $p_data['generic_with_local'] : 0);
          $this->include_generic_with_mac = (isset($p_data['generic_with_mac']) ? $p_data['generic_with_mac'] : 0);
          $this->include_generic_mac_prefix = strtoupper((isset($p_data['generic_mac_prefix']) ? $p_data['generic_mac_prefix'] : ''));
          $this->include_generic_max_devices = (isset($p_data['generic_max_devices']) ? $p_data['generic_max_devices'] : 3);
        }
      }

      // ----- Stop include mode
      else {
        $this->include_generic_with_local = 0;
        $this->include_generic_with_mac = 0;
        $this->include_generic_mac_prefix = '';
        $this->include_generic_max_devices = 3;
      }

      // ----- Reset new device count
      $this->include_device_count = 0;

      $v_response['status'] = 'success';
      $v_response['data']['state'] = ($this->include_mode?1:0);

      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_include_device_count()
     * Description :
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_include_device_count($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'include_device_count';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      // ----- Check mandatory fields are present
      //if (!$this->apiCheckMandatoryData($v_response, $p_data, array('state'))) {
      //  return($v_response);
      //}

      $v_response['status'] = 'success';
      $v_response['data']['count'] = $this->include_device_count;

      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_notification_add()
     * Description :
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_notification_add($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'notification_add';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      // ----- Check mandatory fields are present
      if (!$this->apiCheckMandatoryData($v_response, $p_data, array('type'))) {
        return($v_response);
      }

      // ----- Get type
      $v_type = $p_data['type'];
      
      // ----- Prepare response
      $v_response['status'] = 'success';
      $v_response['data']['type'] = $v_type;
      
      switch ($v_type) {
        case 'include_mode_status' :
        break;
        case 'device_status' :
          if (!isset($p_data['device_mac'])) {
            $v_response['status'] = 'fail';
            $v_response['status_msg'] = "Missing 'device_mac' in event data";
            ArubaWssTool::log('debug', $v_response['status_msg']);
            //return($v_response);
          }          
          else if (($v_value = $this->notificationAddDeviceStatus($p_data['device_mac'], 'ws_api', $p_cnx_id)) < 1) {
            $v_response['status'] = 'fail';
            if ($v_value == -1) {
              $v_response['status_msg'] = "Duplicate registration of notification device_status for this device.";
              ArubaWssTool::log('debug', $v_response['status_msg']);
            }
          }
        break;
        case 'reporter_status' :
          if (($v_value = $this->notificationAdd('reporter_status', 'ws_api', $p_cnx_id, [] )) < 1) {
            $v_response['status'] = 'fail';
            if ($v_value == -1) {
              $v_response['status_msg'] = "Duplicate registration of notification reporter_status.";
              ArubaWssTool::log('debug', $v_response['status_msg']);
            }
          }
        break;
        case 'new_reporter' :
        break;
        case 'new_device' :
        break;
        default :
          $v_response['status'] = 'fail';
      }
      
      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_notification_remove()
     * Description :
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_notification_remove($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'notification_remove';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      // ----- Check mandatory fields are present
      if (!$this->apiCheckMandatoryData($v_response, $p_data, array('type'))) {
        return($v_response);
      }

      // ----- Get type
      $v_type = $p_data['type'];
      
      // ----- Prepare response
      $v_response['status'] = 'success';
      $v_response['data']['type'] = $v_type;
      
      switch ($v_type) {
        case 'all' :
          if (($v_value = $this->notificationRemoveByCb('ws_api', $p_cnx_id)) < 1) {
            $v_response['status'] = 'fail';
            $v_response['status_msg'] = "Fail to remove the notification(s).";
            ArubaWssTool::log('debug', $v_response['status_msg']);
          }
        break;
        case 'device_status' :
          $v_device_mac = '';
          if (isset($p_data['device_mac'])) {
            $v_device_mac = $p_data['device_mac'];
            $v_response['data']['type'] = $v_device_mac;
          }          
          
          if (($v_value = $this->notificationRemoveDeviceStatus($v_device_mac, 'ws_api', $p_cnx_id)) < 1) {
            $v_response['status'] = 'fail';
            $v_response['status_msg'] = "Fail to remove the notification(s).";
            ArubaWssTool::log('debug', $v_response['status_msg']);
          }
        break;
        case 'new_reporter' :
        break;
        case 'new_device' :
        break;
        default :
          $v_response['status'] = 'fail';
      }
      
      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiNotify_notification()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function apiNotify_notification($p_cnx_id, $p_data, $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'success';
      $v_response['status_msg'] = '';
      $v_response['data'] = $p_data;
      $v_response['from_event'] = 'notification';
      $v_response['event_id'] = $p_external_id;

      // ----- Search cnx
      if (($v_cnx = $this->getConnectionById($p_cnx_id)) === null) {
        ArubaWssTool::log('debug', "Fail to find a connexion with this ID from gatt queue.");
        return;
      }
      
      // ----- Look for cnx type
      if ($v_cnx->my_type == 'ws_api') {
        ArubaWssTool::sendWebsocketMessage($v_cnx, json_encode($v_response));
      }

      return;
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : apiEvent_debug()
     * Description :
     * ---------------------------------------------------------------------------
     */
    protected function apiEvent_debug($p_data, $p_cnx_id='', $p_external_id='') {
      $v_response = array();
      $v_response['status'] = 'fail';
      $v_response['status_msg'] = '';
      $v_response['from_event'] = 'debug';
      $v_response['event_id'] = $p_external_id;
      $v_response['data'] = array();

      // ----- Check mandatory fields are present
      if (!$this->apiCheckMandatoryData($v_response, $p_data, array('target'))) {
        return($v_response);
      }

      $v_target = $p_data['target'];
      $v_response['data']['target'] = $v_target;
      
      if ($v_target == 'reporter_list') {
        ArubaWssTool::log('debug', 'Reporters list : '.print_r($this->reporters_list, true));
      }
      else if ($v_target == 'cron_table') {
        ArubaWssTool::log('debug', 'CRON Table : '.print_r($this->cron_action_list, true));
      }
      else if ($v_target == 'gatt_queue') {
        ArubaWssTool::log('debug', 'GATT Queue : '.print_r($this->gatt_queue, true));
      }
      else if ($v_target == 'notification_queue') {
        ArubaWssTool::log('debug', 'Notification Queue : '.print_r($this->notification_queue, true));
      }
      else if ($v_target == 'cnx_list') {
        $v_list = array();
        foreach ($this->connections_list as $v_connection) {
          $v_item = array();
          $v_item['id'] = $v_connection->my_id;
          $v_item['type'] = $v_connection->my_type;
          $v_item['remote_ip'] = $v_connection->my_remote_ip;

          $v_list[] = $v_item;
        }
      
        ArubaWssTool::log('debug', 'GATT Queue : '.print_r($v_list, true));
      }
      else if ($v_target == 'websocket') {
        ArubaWssTool::log('debug', 'Websocket dump : '.print_r($this, true));
      }

      $v_response['status'] = 'success';

      return($v_response);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : deviceIncludeValidation()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function deviceIncludeValidation($p_device_mac, $p_class_name, $p_telemetry) {

      ArubaWssTool::log('debug',  "Check include mode for device ".$p_device_mac);

      $v_result = true;

      if (!$this->include_mode) {
        return(false);
      }
      if (!in_array($p_class_name, $this->device_type_allow_list)) {
        return(false);
      }

      /*
          protected $include_generic_with_local;
    protected $include_generic_with_mac;
    protected $include_generic_mac_prefix;
    protected $include_generic_max_devices;
        */

      if ($p_class_name == 'generic') {

        // ----- Look for device count
        if ($this->include_generic_max_devices < 1) {
          ArubaWssTool::log('debug',  "Max generic device inclusion reached. Do not include.");
          return(false);
        }

      ArubaWssTool::log('debug',  "Check include with mac@ mode : '".$this->include_generic_with_mac."'");
      ArubaWssTool::log('debug',  "Check include mac@ with prefix : '".$this->include_generic_mac_prefix."'");

        // ----- Look for mac prefix
        if (($this->include_generic_with_mac) && ($this->include_generic_mac_prefix != '')) {
          ArubaWssTool::log('debug',  "Check MAC prefix '".$this->include_generic_mac_prefix."' for MAC '".$p_device_mac."'");
          if (strpos($p_device_mac, $this->include_generic_mac_prefix) !== 0) {
            ArubaWssTool::log('debug',  "No valid prefix mac for device. Do not include.");
            return(false);
          }
        }

      ArubaWssTool::log('debug',  "Check include with local name : '".$this->include_generic_with_local."'");

        // ----- Look for local name
        if ($this->include_generic_with_local) {

          if (!$p_telemetry->hasVendorName() && !$p_telemetry->hasLocalName() && !$p_telemetry->hasModel()) {
            ArubaWssTool::log('debug',  "No local value for device. Do not include.");
            return(false);
          }

        }

      }


      return(true);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : changeDeviceConnectStatus()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function changeDeviceConnectStatus($p_device, $p_status, $p_status_string='') {

      // ----- Change BLE connect status of the reporter
      $v_reporter = $p_device->getConnectAp();
      if ($v_reporter != null) {
        $v_reporter->changeConnectStatus($p_status, $p_device->getMac());
      }
      
      // ----- Change BLE connect status of the device
      // Will return false if the status is already the same
      $v_result = $p_device->changeConnectStatus($p_status, ($p_status_string==''?$p_status:$p_status_string));
      //if ($v_result == 1) {
      //  $this->notificationTriggerDeviceStatus($p_device);
      //}
      
      return($v_result);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onMsgTelemetry()
     * Description :
     * 
     * Message Sample :

      meta {
        version: 1
        access_token: "12346"
        nbTopic: telemetry
      }
      reporter {
        name: "AP-515-Lab"
        mac:
        ipv4: "192.168.102.100"
        hwType: "AP-515"
        swVersion: "8.9.0.1-8.9.0.1"
        swBuild: "82154"
        time: 1638630017
      }
      reported {
        unknownFieldSet:
        mac:
        deviceClass: enoceanSwitch
        lastSeen: 1638629973
        bevent {
          event: update
        }
        stats {
          frame_cnt: 0
        }
        inputs {
          rocker {
            id: "switch bank 1: idle"
            state: idle
          }
        }
      }
      reported {
        unknownFieldSet:
        mac:
        deviceClass: unclassified
        lastSeen: 1638630017
        bevent {
          event: update
        }
        rssi {
          avg: -61
        }
        stats {
          frame_cnt: 24
        }
        localName: "Jinou_Sensor_HumiTemp"
      }
      reported {
        unknownFieldSet:
        mac:
        deviceClass: enoceanSensor
        lastSeen: 1638629976
        bevent {
          event: update
        }
        sensors {
          battery: 98
          illumination: 6
          occupancy {
            level: 50
          }
        }
        stats {
          seq_nr: 498739
          frame_cnt: 0
        }
      }

     * 
     * 
     * 
     * 
     * ---------------------------------------------------------------------------
     */
    public function onMsgTelemetry(&$p_reporter, $v_at_telemetry_msg) {

      ArubaWssTool::log('debug',  "Received telemetry message from ".$p_reporter->getName()."");

      ArubaWssTool::log('debug:4', $v_at_telemetry_msg);

      // ----- Look if there is a list of reported device
      if ($v_at_telemetry_msg->hasReportedList()) {
        $v_col = $v_at_telemetry_msg->getReportedList();

        ArubaWssTool::log('debug', "+-----------------------------------------------------------------+");
        ArubaWssTool::log('debug', "| MAC@ Address      | Class List          | Model      | RSSI     |");

        // ----- Look at each reported device
        foreach ($v_col as $v_object) {

          // ----- Extract the class of the object
          // The object can have several class, so concatene in a single string
          $v_class_list = array();
          if ($v_object->hasDeviceClassList()) {
            foreach ($v_object->getDeviceClassList() as $v_class) {
              $v_class_list[] = $v_class->name();
            }
          }
          sort($v_class_list);
          $v_class_name = trim(implode(' ', $v_class_list));
          // ----- Remove double names
          if ($v_class_name == 'arubaBeacon iBeacon')
            $v_class_name = 'arubaBeacon';
          // ----- Change name to 'generic'
          if ($v_class_name == 'unclassified')
            $v_class_name = 'generic';

          // ----- Debug display
          ArubaWssTool::log('debug', "+-------------------------------------------------------------------------------+");
          $v_msglog = "|";
          $v_msglog .= sprintf(" %17s ", ($v_object->hasMac() ? ArubaWssTool::macToString($v_object->getMac()) : ' '));
          $v_msglog .= "|";
          $v_msglog .= sprintf("%20s ", $v_class_name);
          $v_msglog .= "|";
          $v_msglog .= sprintf(" %10s ", ($v_object->hasModel() ? $v_object->getModel() : ' '));
          $v_msglog .= "|";
          $v_msglog .= sprintf(" %8s ", ($v_object->hasRSSI() ? trim($v_object->getRSSI()) : ' '));

          // ----- Get device mac @
          $v_device_mac = ($v_object->hasMac() ? ArubaWssTool::macToString($v_object->getMac()) : '');

          if ($v_device_mac == '') {
            ArubaWssTool::log('debug', $v_msglog."|");
            ArubaWssTool::log('debug',"Received a device with malformed MAC@, skip telemetry data.");
            continue;
          }

          // ----- Look for an allowed device in the cache with this MAC@
          $v_device = $this->getDeviceByMac($v_device_mac);

          // ----- Create new device if allowed class and inclusion mode on
          if (($v_device == null) && $this->deviceIncludeValidation($v_device_mac, $v_class_name, $v_object)) {

            ArubaWssTool::log('info', "Inclusion of a new device '".$v_device_mac."'.");
            ArubaWssTool::log('debug', "Create a new device.");
            $v_msglog .= "|         new ";

            // ----- Create the local device cache image
            //$v_device = new ArubaWssDevice($v_device_mac);
            $v_device = $this->createDevice($v_device_mac, $v_class_name, $v_object);

            $this->include_device_count++;
            if ($v_class_name == 'generic') {
              $this->include_generic_max_devices--;
            }

          }

          // ----- Look for existing device and enabled
          if ($v_device != null) {
            $v_device->resetChangedFlag();

             // ----- Update object class and BLE vendor infos
            if ($v_device->updateObjectClass($v_object, $v_class_name) != 1) {
              ArubaWssTool::log('debug', "Device '".$v_device->getMac()."' has invalid classname. Skip telemetry data.");
              continue;
            }

            // ----- Look for enabled device  : no telemetry data to update
            if (!$v_device->isEnabled()) {
              ArubaWssTool::log('debug', "Device '".$v_device->getMac()."' is disabled. Skip telemetry data.");
              continue;
            }

            // ----- If same nearest AP or better one, update telemetry data.
            if ($v_device->updateNearestAP($p_reporter, $v_object)) {
              $v_device->updateTelemetryData($p_reporter, $v_object, $v_class_name);
            }

            // ----- If object supporting triangulation, update triangulation
            $v_device->updateTriangulation($p_reporter, $v_object);

            // ----- Debuf msg
            $v_msglog .= "|      active ";
          
            // ----- Call post actions on telemetry update
            $v_device->doActionIfModified();
 
         }

          else {
            $v_msglog .= "| ignored     ";
          }

          // TBC : should be looked at beginning and skip if too old ?
          if ($v_object->hasLastSeen()) {
            $v_val = $v_object->getLastSeen();
            $v_msglog .= "| ".date("Y-m-d H:i:s", $v_val);
          }
          ArubaWssTool::log('debug', $v_msglog."|");

        } // end of foreach

        ArubaWssTool::log('debug', "+-------------------------------------------------------------------------------+");
      }
      else {
        ArubaWssTool::log('debug', "Message with no reported devices.");
      }

      return(true);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onMsgWifiData()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function onMsgWifiData(ConnectionInterface &$p_connection, $v_at_telemetry_msg) {

      ArubaWssTool::log('debug',  "Received RTLS message from ".$p_connection->my_id."");

      ArubaWssTool::log('debug:4', $v_at_telemetry_msg);

      // ----- Look if there is a list of reported device
      if ($v_at_telemetry_msg->hasWifiDataList()) {
        $v_list = $v_at_telemetry_msg->getWifiDataList();

        ArubaWssTool::log('debug', "+-----------------------------------------------------------------+");
        ArubaWssTool::log('debug', "| MAC@ Address      | RSSI     |");

        // ----- Look at each reported device
        foreach ($v_list as $v_object) {

          // ----- Extract the class of the object
          // The object can have several class, so concatene in a single string
          $v_class_list = array();
          if ($v_object->hasDeviceClassList()) {
            foreach ($v_object->getDeviceClassList() as $v_class) {
              $v_class_list[] = $v_class->name();
            }
          }
          sort($v_class_list);
          $v_class_name = trim(implode(' ', $v_class_list));
          // ----- Remove double names
          if ($v_class_name == 'arubaBeacon iBeacon')
            $v_class_name = 'arubaBeacon';
          // ----- Change name to 'generic'
          if ($v_class_name == 'unclassified')
            $v_class_name = 'generic';

          // ----- Get device mac @
          $v_device_mac = ($v_object->hasMac() ? ArubaWssTool::macToString($v_object->getMac()) : '');

          // ----- Debug display
          ArubaWssTool::log('debug', "+-------------------------------------------------------------------------------+");
          $v_msglog = "|";
          $v_msglog .= sprintf(" %17s ", $v_device_mac);
          $v_msglog .= "|";
          $v_msglog .= sprintf("%20s ", $v_class_name);
          $v_msglog .= "|";
          $v_msglog .= sprintf(" %8s ", ($v_object->hasRSSI() ? trim($v_object->getRSSI()) : ' '));

          if ($v_device_mac == '') {
            ArubaWssTool::log('debug', $v_msglog."|");
            ArubaWssTool::log('debug',"Received a device with malformed MAC@, skip telemetry data.");
            continue;
          }


          // TBC : to be continued !


          ArubaWssTool::log('debug', $v_msglog."|");

        }


        ArubaWssTool::log('debug', "+-------------------------------------------------------------------------------+");
      }

      return(true);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onMsgApHealthUpdate()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function onMsgApHealthUpdate(&$p_reporter, $v_at_telemetry_msg) {

      ArubaWssTool::log('debug',  "Received apHealthUpdate message from ".$p_reporter->getName()."");
      
      ArubaWssTool::log('debug:4', $v_at_telemetry_msg);

      
      // TBC

      return(true);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onMsgActionResultsList()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function onMsgActionResultsList(&$p_reporter, $v_at_telemetry_msg) {

      ArubaWssTool::log('debug',  "Received actionResults message from ".$p_reporter->getName()."");

      ArubaWssTool::log('debug:4', $v_at_telemetry_msg);
      
      if (!$v_at_telemetry_msg->hasResultsList()) {
        ArubaWssTool::log('debug', "Message with no actionResults information (strange !).");
        return(true);    
      }

      // ----- Parse each characteristics
      $v_list = $v_at_telemetry_msg->getResultsList();
      
      // ----- Look for multiple response
      // Not yet supported used in Aruba messages, even if protobuf def can allow that
      // So just take into account the first one
      if (sizeof($v_list) > 1) {
        ArubaWssTool::log('debug', "Unexpected multiple fields 'results' in protobuf message. (strange !). Take only first one.");      
      }

      // ----- Look at each reported device
      foreach ($v_list as $v_aresult) {
        $this->onMsgActionResults($p_reporter, $v_aresult);
        break;
      }
        
      return(true);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onMsgActionResults()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function onMsgActionResults(&$p_reporter, $p_aresult) {

      // ----- Get actionID
      $v_action_id = '';
      if ($p_aresult->hasActionId()) {
        $v_action_id = $p_aresult->getActionId();
      } 
      ArubaWssTool::log('debug', "ActionId is '".$v_action_id."'.");
      
      // ----- Get action type
      $v_action_type = '';
      if ($p_aresult->hasType()) {
        $v_action_type = $p_aresult->getType();
      } 
      ArubaWssTool::log('debug', "Action type is '".$v_action_type."'.");
      
      // ----- Get action status
      $v_status = '';
      if ($p_aresult->hasStatus()) {
        $v_status = $p_aresult->getStatus();
      } 
      ArubaWssTool::log('debug', "Action status is '".$v_status."'.");
      
      // ----- Get action status
      $v_status_string = '';
      if ($p_aresult->hasStatusString()) {
        $v_status_string = $p_aresult->getStatusString();
      } 
      ArubaWssTool::log('debug', "Action status string is '".$v_status_string."'.");
      
      // ----- Get device
      $v_device = null;
      if ($p_aresult->hasDeviceMac()) {
        $v_at_mac = $p_aresult->getDeviceMac();
        $v_mac = ArubaWssTool::macToString($v_at_mac);
        ArubaWssTool::log('debug', "Device MAC is '".$v_mac."'.");
        
        // ----- Look for valid device in cache
        $v_device = $this->getDeviceByMac($v_mac);
        if ($v_device === null) {
          ArubaWssTool::log('debug', "Fail to find a device in cache with mac '".$v_mac."'.");
        }
      }
      
      // ----- Do something depending on type
      // TBC : some action may not have device
      if ($v_action_type == 'bleConnect') {
        $this->onMsgActionResultsBleConnect($v_device, $v_action_id, $v_status, $p_reporter, $v_status_string);
      }
      
      else if ($v_action_type == 'bleDisconnect') {
        $this->onMsgActionResultsBleDisconnect($v_device, $v_action_id, $v_status, $p_reporter, $v_status_string);
      }
        
      else if ($v_action_type == 'gattRead') {
        // Nothing to do for now
      }
        
      else if ($v_action_type == 'gattWrite') {
        // Nothing to do for now
      }
        
      else if ($v_action_type == 'gattWriteWithResponse') {
        // Nothing to do for now
      }
      
      else if ($v_action_type == 'gattNotification') {
        $this->onMsgActionResultsGattNotification($v_device, $v_action_id, $v_status, $p_reporter, $v_status_string);
      }
        
      else if ($v_action_type == 'gattIndication') {
        ArubaWssTool::log('debug', "Not supported message response for '".$v_action_type."'. Skip.");
      }
      
      else if ($v_action_type == 'bleAuthenticate') {
        ArubaWssTool::log('debug', "Not supported message response for '".$v_action_type."'. Skip.");
      }
      
      else if ($v_action_type == 'bleEncrypt') {
        ArubaWssTool::log('debug', "Not supported message response for '".$v_action_type."'. Skip.");
      }
      
      else if ($v_action_type == '') {
        // ----- Some results don't include action_type nor action_id. 
        // Try in this section to manage this situtation ...        
        /*
        Seen cases :
        Response received when triggering a connect/read on a characteristic
results {
  deviceMac:
  status: invalidRequest
  statusString: "Device does not match configured device class filter in iot transport profile"
}        
        */
        
        if ($v_device !== null) {
          // ----- The device might be in connecting phase ... the errors means a connect fail
          if ($v_device->getConnectStatus() == AWSS_STATUS_CONNECTED) {
            // ----- Change status to disconnected
            $this->changeDeviceConnectStatus($v_device, AWSS_STATUS_DISCONNECTED, ($p_status_string==''?$p_status:$v_status.':'.$v_status_string));      
          }
        }
        ArubaWssTool::log('debug', "Not supported message response for '".$v_action_type."'. Skip.");
      }
      
      else {
        ArubaWssTool::log('debug', "Unexpected action_type '".$v_action_type."' (not supported). Skip.");
      }
      
      return(true);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onMsgActionResultsBleConnect()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function onMsgActionResultsBleConnect($p_device, $p_action_id, $p_status, &$p_reporter, $p_status_string='') {

      $v_result = true;
      
      // ----- A valid mac must be present.
      if ($p_device === null) {
        ArubaWssTool::log('debug', "Missing valid device to confirm bleConnect.skip.");
        return(false);
      }
      
      $v_response_status = 'fail';
      
      // ----- Look for success codes
      if (   ($p_status == 'success')
          || ($p_status == 'alreadyConnected') ) {
        //$v_result = $p_device->changeConnectStatus(AWSS_STATUS_CONNECTED);
        $this->changeDeviceConnectStatus($p_device, AWSS_STATUS_CONNECTED);
        $v_response_status = 'success';
      }
      
      // ----- Look for fail codes
      else if (   ($p_status == 'failureGeneric')
               || ($p_status == 'deviceNotFound')
               || ($p_status == 'apNotFound')
               || ($p_status == 'actionTimeout')
               || ($p_status == 'connectionAborted')
               || ($p_status == 'authenticationFailed')
               || ($p_status == 'notConnected')
               || ($p_status == 'previousActionFailed')
               || ($p_status == 'noMoreConnectionSlots')
               || ($p_status == 'decodingFailed')
               || ($p_status == 'characteristicNotFound')
               || ($p_status == 'invalidRequest')
               || ($p_status == 'gattError')
               || ($p_status == 'encryptionFailed') ) {
        //$v_result = $p_device->changeConnectStatus(AWSS_STATUS_DISCONNECTED, ($p_status_string==''?$p_status:$p_status_string));
        //$p_reporter->changeConnectStatus(AWSS_STATUS_DISCONNECTED, $p_device->getMac());
        $this->changeDeviceConnectStatus($p_device, AWSS_STATUS_DISCONNECTED, ($p_status_string==''?$p_status:$p_status_string));
        $v_response_status = 'fail';
      }
            
      else {
        ArubaWssTool::log('debug', "Unexpected status '".$p_status."' for bleConnect. Force disconnect.");
        //$v_result = $p_device->changeConnectStatus(AWSS_STATUS_DISCONNECTED, ($p_status_string==''?$p_status:$p_status_string));
        //$p_reporter->changeConnectStatus(AWSS_STATUS_DISCONNECTED, $p_device->getMac());
        $this->changeDeviceConnectStatus($p_device, AWSS_STATUS_DISCONNECTED, ($p_status_string==''?$p_status:$p_status_string));        
        $v_response_status = 'fail';
      }
      
      // ----- Get queued action for call back
      $v_action_item = $this->gattQueueGetAction($p_action_id, 'bleConnect', $p_device->getMac());
      if ($v_action_item !== null) {
        ArubaWssTool::log('debug', "Found action in gatt queue.");
        ArubaWssTool::log('debug', "value : ".print_r($v_action_item, true));
        
        // ----- Remove the action from the queue
        $this->gattQueueRemoveAction($p_action_id);
        
        // ----- Do callback
        $this->apiResponse_ble_connect($v_action_item['cnx_id'], 
                                       $v_response_status, 
                                       $p_device->getMac(),
                                       $v_action_item['external_id']);
      }
                
      return($v_result);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onMsgActionResultsBleDisconnect()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function onMsgActionResultsBleDisconnect($p_device, $p_action_id, $p_status, &$p_reporter, $p_status_string='') {

      $v_result = true;
      
      // ----- A valid mac must be present.
      if ($p_device === null) {
        ArubaWssTool::log('debug', "Missing valid device to confirm bleConnect.skip.");
        return(false);
      }
      
      $v_response_status = 'fail';

      // ----- Look for success codes
      if (   ($p_status == 'success') ) {
        //$v_result = $p_device->changeConnectStatus(AWSS_STATUS_DISCONNECTED, ($p_status_string==''?$p_status:$p_status_string));
        //$p_reporter->changeConnectStatus(AWSS_STATUS_DISCONNECTED, $p_device->getMac());
        $this->changeDeviceConnectStatus($p_device, AWSS_STATUS_DISCONNECTED, ($p_status_string==''?$p_status:$p_status_string));                
        $v_response_status = 'success';
      }
      
      // ----- Look for fail codes
      else if (   ($p_status == 'failureGeneric')
               || ($p_status == 'alreadyConnected')
               || ($p_status == 'deviceNotFound')
               || ($p_status == 'apNotFound')
               || ($p_status == 'actionTimeout')
               || ($p_status == 'connectionAborted')
               || ($p_status == 'authenticationFailed')
               || ($p_status == 'notConnected')
               || ($p_status == 'previousActionFailed')
               || ($p_status == 'noMoreConnectionSlots')
               || ($p_status == 'decodingFailed')
               || ($p_status == 'characteristicNotFound')
               || ($p_status == 'invalidRequest')
               || ($p_status == 'gattError')
               || ($p_status == 'encryptionFailed') ) {
        //$v_result = $p_device->changeConnectStatus(AWSS_STATUS_DISCONNECTED, ($p_status_string==''?$p_status:$p_status_string));
        //$p_reporter->changeConnectStatus(AWSS_STATUS_DISCONNECTED, $p_device->getMac());
        $this->changeDeviceConnectStatus($p_device, AWSS_STATUS_DISCONNECTED, ($p_status_string==''?$p_status:$p_status_string));                
        
        // TBC : anyhow, I will set the device to disconnected ....
        //$v_response_status = 'fail';
        $v_response_status = 'success';
      }

      else {
        ArubaWssTool::log('debug', "Unexpected status '".$p_status."' for bleDisonnect. Force disconnect.");
        //$v_result = $p_device->changeConnectStatus(AWSS_STATUS_DISCONNECTED, ($p_status_string==''?$p_status:$p_status_string));
        //$p_reporter->changeConnectStatus(AWSS_STATUS_DISCONNECTED, $p_device->getMac());
        $this->changeDeviceConnectStatus($p_device, AWSS_STATUS_DISCONNECTED, ($p_status_string==''?$p_status:$p_status_string));                
        $v_response_status = 'fail';
      }
      
      // ----- Get queued action for call back
      $v_action_item = $this->gattQueueGetAction($p_action_id, 'bleDisconnect', $p_device->getMac());
      if ($v_action_item !== null) {
        ArubaWssTool::log('debug', "Found action in gatt queue.");
        ArubaWssTool::log('debug', "value : ".print_r($v_action_item, true));
        
        // ----- Remove the action from the queue
        $this->gattQueueRemoveAction($p_action_id);
        
        // ----- Do callback
        $this->apiResponse_ble_disconnect($v_action_item['cnx_id'], 
                                          $v_response_status, 
                                          $p_device->getMac(),
                                          $v_action_item['external_id']);
      }
                
      return($v_result);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onMsgActionResultsGattNotification()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function onMsgActionResultsGattNotification($p_device, $p_action_id, $p_status, &$p_reporter, $p_status_string='') {

      $v_result = true;
      
      // ----- A valid mac must be present.
      if ($p_device === null) {
        ArubaWssTool::log('debug', "Missing valid device to confirm gattNotification.skip.");
        return(false);
      }
      
      $v_response_status = 'fail';
      
      // ----- Look for success codes
      if (   ($p_status == 'success') ) {
        // ----- Here I put 'starting' to express that the command was accepted,
        // and data will come through characteristic messages
        $v_response_status = 'starting';
        
        // ----- Set presence, because the device response with success, which means it is present.
        $p_device->setPresence(1);
      }
      
      // ----- Look for fail codes
      else if (   ($p_status == 'failureGeneric')
               || ($p_status == 'alreadyConnected')
               || ($p_status == 'deviceNotFound')
               || ($p_status == 'apNotFound')
               || ($p_status == 'actionTimeout')
               || ($p_status == 'connectionAborted')
               || ($p_status == 'authenticationFailed')
               || ($p_status == 'notConnected')
               || ($p_status == 'previousActionFailed')
               || ($p_status == 'noMoreConnectionSlots')
               || ($p_status == 'decodingFailed')
               || ($p_status == 'characteristicNotFound')
               || ($p_status == 'invalidRequest')
               || ($p_status == 'gattError')
               || ($p_status == 'encryptionFailed') ) {
        $v_response_status = 'fail';
      }

      else {
        ArubaWssTool::log('debug', "Unexpected status '".$p_status."' for gattNotification. skip.");
        $v_response_status = 'fail';
      }
      
      // ----- Get queued action for call back
      $v_action_item = $this->gattQueueGetAction($p_action_id, 'gattNotification', $p_device->getMac());
      if ($v_action_item !== null) {
        ArubaWssTool::log('debug', "Found action in gatt queue.");
        ArubaWssTool::log('debug', "value : ".print_r($v_action_item, true));
        
        // ----- Do not remove, but update timestamp
        $this->gattQueueUpdateTimestamp($p_action_id);
        
        // ----- Do callback
        $this->apiResponse_ble_notify($v_action_item['cnx_id'], 
                                       $v_response_status, 
                                       $p_status, 
                                       $p_device->getMac(),
                                       $v_action_item['external_id']);
      }

      return($v_result);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onMsgCharacteristicDiscovery()
     * Description :
     *   Method called when the protobuf message don't include an 
     *   "actionResults" (!hasResultsList()) field. This is the sign that 
     *   this is not a response to a request, but a result of a characteristics
     *   discovery process.
     * 
     * Sample of received discovery response :

meta {
  version: 1
  access_token: "12346"
  nbTopic: characteristics
}
reporter {
  name: "AP-515-Lab"
  mac:
  ipv4: "192.168.102.101"
  hwType: "AP-515"
  swVersion: "8.8.0.1-8.8.0.1"
  swBuild: "80393"
  time: 1627454059
}
characteristics {
  deviceMac:
  serviceUuid: "\u0018\u0000"
  characteristicUuid: "*\u0000"
  properties: read
  properties: writeWithResponse
}
characteristics {
  deviceMac:
  serviceUuid: "\u0018\u0000"
  characteristicUuid: "*\u0001"
  properties: read
}
characteristics {
  deviceMac:
  serviceUuid:
  characteristicUuid:
  properties: writeWithResponse
  properties: indicate
}

     * 
     * 
     * ---------------------------------------------------------------------------
     */
    public function onMsgCharacteristicDiscovery(&$p_reporter, $v_at_telemetry_msg) {

      ArubaWssTool::log('debug',  "Received characteristics discovery result message from ".$p_reporter->getName()."");

      ArubaWssTool::log('debug:4', $v_at_telemetry_msg);
      
      // ----- Look for characteristics list
      if (!$v_at_telemetry_msg->hasCharacteristicsList()) {
        ArubaWssTool::log('debug', "Message with no reported characteristics (strange !).");
        return(true);    
      }
      
      // ----- Parse each characteristics
      $v_list = $v_at_telemetry_msg->getCharacteristicsList();

      // ----- Temporary stored seen objects in the msg
      $v_temp_mac_list = array();
      
      // ----- Look at each reported device
      foreach ($v_list as $v_characteristic) {
        // ----- Get device mac
        if (!$v_characteristic->hasDeviceMac()) {
          ArubaWssTool::log('debug', "Characteristic with no device mac@. Skip.");
          continue;
        }
        $v_at_mac = $v_characteristic->getDeviceMac();
        $v_mac = ArubaWssTool::macToString($v_at_mac);
        ArubaWssTool::log('debug', "Device MAC is '".$v_mac."'.");
        
        // ----- Look for valid device in cache
        $v_device = $this->getDeviceByMac($v_mac);
        if ($v_device === null) {
          ArubaWssTool::log('debug', "Fail to find a device in cache with mac '".$v_mac."'. Skip.");
          continue;
        }
        
        // ----- Reset modif flag
        $v_temp_mac_list[$v_mac] = $v_device;
        $v_device->resetChangedFlag();
        
        $v_service_uuid = '';
        if ($v_characteristic->hasServiceUuid()) {
          $v_value = $v_characteristic->getServiceUuid();
          $v_service_uuid = ArubaWssTool::bytesToString($v_value);
          ArubaWssTool::log('debug', "Service UUID is '".$v_service_uuid."'.");
        }

        $v_char_uuid = '';
        if ($v_characteristic->hasCharacteristicUuid()) {
          $v_value = $v_characteristic->getCharacteristicUuid();
          $v_char_uuid = ArubaWssTool::bytesToString($v_value);
          ArubaWssTool::log('debug', "Characteristic UUID is '".$v_char_uuid."'.");
        }

        $v_description = '';
        if ($v_characteristic->hasDescription()) {
          $v_description = $v_characteristic->hasDescription();
          ArubaWssTool::log('debug', "Description is '".$v_description."'.");
        }
        
        // ----- Normally in discovery we should not see a value ....
        /*
        $v_char_value = null;
        if ($v_characteristic->hasValue()) {
          $v_value = $v_characteristic->getValue();
          $v_char_value = ArubaWssTool::bytesToString($v_value);
          ArubaWssTool::log('debug', "Characteristic Value is '".$v_char_value."' (".ArubaWssTool::stringbytesToText($v_char_value, true).").");
        }
        */

        $v_char_types = '';
        $v_char_types_list = array();
        if ($v_characteristic->hasPropertiesList()) {
          $v_prop_list = $v_characteristic->getPropertiesList();
          foreach ($v_prop_list as $v_prop) {
            $v_char_types_list[] = $v_prop->name();
          }
          sort($v_char_types_list);
          $v_char_types = implode(',', $v_char_types_list);
          ArubaWssTool::log('debug', "Characteristics types are : '".$v_char_types."'.");
        }
        
        // ----- Add Characteristics to device
        if (($v_service_uuid != '') && ($v_char_uuid != '')) {
          $v_device->setCharacteristic($v_service_uuid, $v_char_uuid, $v_char_types, $v_description);
        }
      }
      
                
      
      // ----- Look for post action
      // I'm doing this outside the loop, because each characteristic is repeating the device mac@
      foreach ($v_temp_mac_list as $v_device) {

        // ----- Get queued action for call back (if triggered by ble_discover API)
        $v_action_item = $this->gattQueueGetActionByType('bleDiscover', $v_device->getMac());
        if ($v_action_item !== null) {
          ArubaWssTool::log('debug', "Found action in gatt queue.");
          ArubaWssTool::log('debug', "value : ".print_r($v_action_item, true));
          
          // ----- Remove the action from the queue
          $this->gattQueueRemoveAction($v_action_item['action_id']);
          
          // ----- Do API callback
          $this->apiResponse_ble_discover($v_action_item['cnx_id'], 
                                          'success', 
                                          $v_device->getMac(),
                                          $v_action_item['external_id']);
        }

        // ----- Set that the device has discoverable characteeristics
        $v_device->setIsDiscoverable();
        
        // ----- Call post actions, including plugin post actions
        $v_device->doActionIfModified();
      }      
            
      return(true);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onMsgCharacteristicResults()
     * Description :
     *   Used for responses from :
     *     gattRead
     *     gattNotification
     *     gattWrite ??? (TBC)
     * 


Response to a GattRead
trace : meta {
  version: 1
  access_token: "12346"
  nbTopic: characteristics
}
reporter {
  name: "AP-515-Lab"
  mac:
  ipv4: "192.168.102.101"
  hwType: "AP-515"
  swVersion: "8.8.0.1-8.8.0.1"
  swBuild: "80393"
  time: 1626361673
}
results {
  actionId: "60f04f493c187"
  type: gattRead
  deviceMac:
  status: success
  statusString: "gattRead Successful!"
}
characteristics {
  deviceMac:
  serviceUuid:
  characteristicUuid:
  value: "\u0000\u001a\t\u00003\t"
}


     * 
     * ---------------------------------------------------------------------------
     */
    public function onMsgCharacteristicResults(&$p_reporter, $v_at_telemetry_msg) {

      ArubaWssTool::log('debug',  "Received characteristics message from ".$p_reporter->getName()."");

      ArubaWssTool::log('debug:4', $v_at_telemetry_msg);
      
      // ----- Look if telemetry has results before characteristics
      if (!$v_at_telemetry_msg->hasResultsList()) {
        ArubaWssTool::log('debug', "Message with no results (strange !).");
        return(true);    
      }
      
      // ----- Parse each characteristics
      $v_list = $v_at_telemetry_msg->getResultsList();
      
      // ----- Look for multiple response
      // Not yet supported used in Aruba messages, even if protobuf def can allow that
      // So just take into account the first one
      if (sizeof($v_list) != 1) {
        ArubaWssTool::log('debug', "Unexpected multiple fields 'results' in protobuf message. (strange !). Take only first one.");      
      }
      
      // ----- Get the protobuf result data
      $p_aresult = $v_list[0];

      // ----- Get actionID
      $v_action_id = '';
      if ($p_aresult->hasActionId()) {
        $v_action_id = $p_aresult->getActionId();
      } 
      ArubaWssTool::log('debug', "ActionId is '".$v_action_id."'.");
      
      // ----- Get action type
      $v_action_type = '';
      if ($p_aresult->hasType()) {
        $v_action_type = $p_aresult->getType();
      } 
      ArubaWssTool::log('debug', "Action type is '".$v_action_type."'.");
      
      // ----- Get action status
      $v_status = '';
      if ($p_aresult->hasStatus()) {
        $v_status = $p_aresult->getStatus();
      } 
      ArubaWssTool::log('debug', "Action status is '".$v_status."'.");
      
      // ----- Get action status
      $v_status_string = '';
      if ($p_aresult->hasStatusString()) {
        $v_status_string = $p_aresult->getStatusString();
      } 
      ArubaWssTool::log('debug', "Action status string is '".$v_status_string."'.");
      
      // ----- Get device
      $v_device = null;
      if ($p_aresult->hasDeviceMac()) {
        $v_at_mac = $p_aresult->getDeviceMac();
        $v_device_mac = ArubaWssTool::macToString($v_at_mac);
        ArubaWssTool::log('debug', "Device MAC is '".$v_device_mac."'.");
        
        // ----- Look for valid device in cache
        $v_device = $this->getDeviceByMac($v_device_mac);
        if ($v_device === null) {
          ArubaWssTool::log('debug', "Fail to find a device in cache with mac '".$v_device_mac."'.Ignore.");
          // TBC : could be better to send a gattRead error
          return(true);    
        }
      }
      
      // ----- Reset change flag
      $v_device->resetChangedFlag();
      
      // ----- Set presence, if status is success and device valid
      if ($v_status == 'success') {
        // ----- Set presence, because the device response with success, which means it is present.
        $v_device->setPresence(1);
      }
      
      // ----- Look for characteristics list
      if (!$v_at_telemetry_msg->hasCharacteristicsList()) {
        ArubaWssTool::log('debug', "Message with no reported characteristics (strange !).");
        return(true);    
      }
      
      // ----- Parse each characteristics
      $v_list = $v_at_telemetry_msg->getCharacteristicsList();
      
      // ----- Only one characteristic is expected ...
      // When not a discovery mode, only one characteristic is expected
      if (sizeof($v_list) > 1) {
        ArubaWssTool::log('debug', "Unexpected multiple fields 'characteristics' in protobuf message. (strange !). Take only first one.");      
      }
      
      // ----- Take only first one
      $v_characteristic = $v_list[0];

        // ----- Get device mac
        if (!$v_characteristic->hasDeviceMac()) {
          ArubaWssTool::log('debug', "Characteristic with no device mac@. Skip.");
          return(true);
        }
        $v_at_mac = $v_characteristic->getDeviceMac();
        $v_device_mac_2 = ArubaWssTool::macToString($v_at_mac);
        ArubaWssTool::log('debug', "Device MAC in characteristic is '".$v_device_mac_2."'.");
        
        if ($v_device_mac != $v_device_mac_2) {
          ArubaWssTool::log('debug', "Device mac@ in action result and characteristics are different !. Skip.");
          return(true);
        }

        $v_service_uuid = '';
        if ($v_characteristic->hasServiceUuid()) {
          $v_value = $v_characteristic->getServiceUuid();
          $v_service_uuid = ArubaWssTool::bytesToString($v_value);
          ArubaWssTool::log('debug', "Service UUID is '".$v_service_uuid."'.");
        }

        $v_char_uuid = '';
        if ($v_characteristic->hasCharacteristicUuid()) {
          $v_value = $v_characteristic->getCharacteristicUuid();
          $v_char_uuid = ArubaWssTool::bytesToString($v_value);
          ArubaWssTool::log('debug', "Characteristic UUID is '".$v_char_uuid."'.");
        }

        $v_char_value = null;
        if ($v_characteristic->hasValue()) {
          $v_value = $v_characteristic->getValue();
          $v_char_value = ArubaWssTool::bytesToString($v_value);
          ArubaWssTool::log('debug', "Characteristic Value is '".$v_char_value."' (".ArubaWssTool::stringbytesToText($v_char_value, true).").");
        }

        // ----- Add Characteristics to device
        if (($v_service_uuid != '') && ($v_char_uuid != '')) {
          $v_device->setCharacteristicValue($v_service_uuid, $v_char_uuid, $v_char_value);
        }
      
      // ----- Look for post action if some value modified
      $v_device->doActionIfModified();
      
      // ----- Get queued action for call back
      $v_action_item = $this->gattQueueGetAction($v_action_id, $v_action_type, $v_device_mac);
      if ($v_action_item !== null) {
        ArubaWssTool::log('debug', "Found action in gatt queue.");
        ArubaWssTool::log('debug', "value : ".print_r($v_action_item, true));
        
                
        // ----- Do callback
        if ($v_action_type == 'gattRead') {
          // ----- Remove the action from the queue
          $this->gattQueueRemoveAction($v_action_id);
          
          // ----- Manage response call back
          $this->apiResponse_ble_read($v_action_item['cnx_id'], "success", $v_device_mac,
                                      $v_service_uuid, $v_char_uuid, $v_char_value, 
                                      $v_action_item['external_id']);
        }
        else if ($v_action_type == 'gattNotification') {
          // ----- Do not remove, but update timestamp
          $this->gattQueueUpdateTimestamp($v_action_id);

          // ----- Manage response call back
          $this->apiNotify_ble_notify($v_action_item['cnx_id'], "success", $v_device_mac,
                                      $v_service_uuid, $v_char_uuid, $v_char_value, 
                                      $v_action_item['external_id']);
        }
        else {
          // TBC
          ArubaWssTool::log('debug', "Unexpected action_type : '".$v_action_type."'.");
        }
                
      }
                  
      return(true);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onMsgStatus()
     * Description :
     * 
     * 
     *   Exemple of status message :
meta {
  version: 1
  access_token: "12346"
  nbTopic: status
}
reporter {
  name: "AP-515-Lab"
  mac:
  ipv4: "192.168.102.101"
  hwType: "AP-515"
  swVersion: "8.8.0.1-8.8.0.1"
  swBuild: "80393"
  time: 1627452429
}
status {
  deviceMac:
  status: inactivityTimeout
  statusString: "Inactivity timer of 30s reached with no actions! Disconnecting"
}

Or :

meta {
  version: 1
  access_token: "12346"
  nbTopic: status
}
reporter {
  name: "AP-515-Lab"
  mac:
  ipv4: "192.168.102.101"
  hwType: "AP-515"
  swVersion: "8.8.0.1-8.8.0.1"
  swBuild: "80393"
  time: 1626106469
}
status {
  deviceMac:
  status: connectionUpdate
  statusString: "MTU Value Updated"
  connUpdate {
    mtu_value: 23
  }
}

     * 
     * 
     * ---------------------------------------------------------------------------
     */
    public function onMsgStatus(&$p_reporter, $v_at_telemetry_msg) {

      ArubaWssTool::log('debug',  "Received status message from ".$p_reporter->getName()."");
      
      ArubaWssTool::log('debug:4', $v_at_telemetry_msg);

      if (!$v_at_telemetry_msg->hasStatus()) {
        ArubaWssTool::log('debug', "Message with no status information (strange !).");
        return(true);    
      }
      
      $v_status_msg = $v_at_telemetry_msg->getStatus();

      // ----- Get status
      $v_status = '';
      if ($v_status_msg->hasStatus()) {
        $v_status = $v_status_msg->getStatus();
      } 
      ArubaWssTool::log('debug', "Status is '".$v_status."'.");
      
      // ----- Get  status string
      $v_status_string = '';
      if ($v_status_msg->hasStatusString()) {
        $v_status_string = $v_status_msg->getStatusString();
      } 
      ArubaWssTool::log('debug', "Status string is '".$v_status_string."'.");
      
      // ----- Get device
      $v_device = null;
      if ($v_status_msg->hasDeviceMac()) {
        $v_at_mac = $v_status_msg->getDeviceMac();
        $v_mac = ArubaWssTool::macToString($v_at_mac);
        ArubaWssTool::log('debug', "Device MAC is '".$v_mac."'.");
        
        // ----- Look for valid device in cache
        $v_device = $this->getDeviceByMac($v_mac);
        if ($v_device === null) {
          ArubaWssTool::log('debug', "Fail to find a device in cache with mac '".$v_mac."'.");
        }
      }
      
      // ----- Only 3 status can be received as of AOS 8.8
      if (($v_status == 'deviceDisconnected') || ($v_status == 'inactivityTimeout')) {
        if ($v_device !== null) {
          //$v_device->changeConnectStatus('inactivityTimeout', ($v_status_string==''?$v_status:$v_status_string));
          //$p_reporter->changeConnectStatus(AWSS_STATUS_DISCONNECTED, $v_device->getMac());
          // This will also trigger the disconnect of the reporter
          $this->changeDeviceConnectStatus($v_device, AWSS_STATUS_DISCONNECTED, ($v_status_string==''?$v_status:$v_status_string));                
        }
        else {          
          $p_reporter->changeConnectStatus(AWSS_STATUS_DISCONNECTED, '');
        }
      }
      else if ($v_status == 'connectionUpdate') {
        // Nothing to do for now
      }
      
      return(true);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onMsgBleData()
     * Description :
     * 
     * 
     *   Exemple of message :

      meta {
        version: 1
        access_token: "12346"
        nbTopic: bleData
      }
      reporter {
        name: "AP-515-Lab"
        mac:
        ipv4: "192.168.102.100"
        hwType: "AP-515"
        swVersion: "8.9.0.1-8.9.0.1"
        swBuild: "82154"
        time: 1638629576
      }
      bleData {
        mac:
        frameType: scan_rsp
        data: "\u000b\tATC_07FCEE"
        rssi: -48
        addrType: addr_type_public
      }
      
      
      meta {
        version: 1
        access_token: "12346"
        nbTopic: bleData
      }
      reporter {
        name: "AP-515-Lab"
        mac:
        ipv4: "192.168.102.100"
        hwType: "AP-515"
        swVersion: "8.9.0.1-8.9.0.1"
        swBuild: "82154"
        time: 1638629578
      }
      bleData {
        mac:
        frameType: adv_ind
        data:
        rssi: -48
        addrType: addr_type_public
      }

     * 
     * 
     * ---------------------------------------------------------------------------
     */
    public function onMsgBleData(&$p_reporter, $v_at_telemetry_msg) {

      ArubaWssTool::log('debug',  "Received bleData message from ".$p_reporter->getName()."");
      
      ArubaWssTool::log('debug:4', $v_at_telemetry_msg);

      // ----- Look if telemetry has results before characteristics
      if (!$v_at_telemetry_msg->hasBleDataList()) {
        ArubaWssTool::log('debug', "Message with no bleData (strange !).");
        return(true);    
      }
      
      // ----- Parse each characteristics
      $v_list = $v_at_telemetry_msg->getBleDataList();
      
      // ----- Look for multiple response
      // Not yet supported used in Aruba messages, even if protobuf def can allow that
      // So just take into account the first one
      if (sizeof($v_list) != 1) {
        ArubaWssTool::log('debug', "Unexpected multiple fields 'bleData' in protobuf message. (strange !). Take only first one.");      
      }
      
      // ----- Get the protobuf result data
      $v_bleData_msg = $v_list[0];

      // ----- Get frametype
      $v_frame_type = '';
      if ($v_bleData_msg->hasFrameType()) {
        $v_frame_type = $v_bleData_msg->getFrameType();
        ArubaWssTool::log('debug', "Frame Type is '".$v_frame_type."'.");
      }
      else {
        ArubaWssTool::log('debug', "Missing frame type. Skip BLE data."); 
        return(true);
      }

      // ----- Skip 'scan_rsp' frame
      if ($v_frame_type == 'scan_rsp') {
        ArubaWssTool::log('debug', "'".$v_frame_type."' frame type ignored for now. Skip BLE data."); 
        return(true);
      }
      
      // ----- Get device
      $v_device = null;
      if ($v_bleData_msg->hasMac()) {
        $v_at_mac = $v_bleData_msg->getMac();
        $v_mac = ArubaWssTool::macToString($v_at_mac);
        ArubaWssTool::log('debug', "Device MAC is '".$v_mac."'.");
        
        // ----- Look for valid device in cache
        $v_device = $this->getDeviceByMac($v_mac);
        if ($v_device === null) {
          ArubaWssTool::log('debug', "Fail to find a device in cache with mac '".$v_mac."'.");
        }
      }
      
      // ----- Get data in bytes in string format '00-00-00'
      $v_bytes = '';
      if ($v_bleData_msg->hasData()) {
        $v_data = $v_bleData_msg->getData();
        $v_bytes = ArubaWssTool::bytesToString($v_data);
        $v_str = ArubaWssTool::stringbytesToText($v_bytes);
        ArubaWssTool::log('debug', "Data is '".$v_bytes."'.");        
        ArubaWssTool::log('debug', "Data string is '".$v_str."'.");        
      }
      
      // ----- Look for 'adv_ind' frame
      if (($v_device !== null) && ($v_bytes != '') && ($v_frame_type == 'adv_ind')) {
        // ----- Get reporter timestamp
        $v_timestamp = $p_reporter->getLastSeen();
        ArubaWssTool::log('debug', "Reporter lastseen is : ".$v_timestamp." (".date("Y-m-d H:i:s", $v_timestamp).")");
        
        // ----- Get RSSI
        $v_rssi = -999;
        if ($v_bleData_msg->hasRssi()) {
          $v_rssi = $v_bleData_msg->getRssi();
          ArubaWssTool::log('debug', "RSSI is : ".$v_rssi." ");
        }
        
        // ----- Update nearestAP, and if nearest, update telemetry value
        if ($v_device->updateNearestAPNew($p_reporter, $v_timestamp, $v_rssi)) {
          $v_device->setTelemetryFromAdvert($v_bytes);     
          $v_device->doActionIfModified(); 
        }
      }
      
      return(true);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onMsgSerialDataNb()
     * Description :
     * 
     * 
     *   Exemple of message :


     * 
     * 
     * ---------------------------------------------------------------------------
     */
    public function onMsgSerialDataNb(&$p_reporter, $v_at_telemetry_msg) {

      ArubaWssTool::log('debug',  "Received serialDataNb message from ".$p_reporter->getName()."");
      
      ArubaWssTool::log('debug:4', $v_at_telemetry_msg);
/*
      // ----- Look if telemetry has results before characteristics
      if (!$v_at_telemetry_msg->hasBleDataList()) {
        ArubaWssTool::log('debug', "Message with no bleData (strange !).");
        return(true);    
      }
      
      // ----- Parse each characteristics
      $v_list = $v_at_telemetry_msg->getBleDataList();
      
      // ----- Look for multiple response
      // Not yet supported used in Aruba messages, even if protobuf def can allow that
      // So just take into account the first one
      if (sizeof($v_list) != 1) {
        ArubaWssTool::log('debug', "Unexpected multiple fields 'bleData' in protobuf message. (strange !). Take only first one.");      
      }
      
      // ----- Get the protobuf result data
      $v_bleData_msg = $v_list[0];

      // ----- Get device
      $v_device = null;
      if ($v_bleData_msg->hasMac()) {
        $v_at_mac = $v_bleData_msg->getMac();
        $v_mac = ArubaWssTool::macToString($v_at_mac);
        ArubaWssTool::log('debug', "Device MAC is '".$v_mac."'.");
        
        // ----- Look for valid device in cache
        $v_device = $this->getDeviceByMac($v_mac);
        if ($v_device === null) {
          ArubaWssTool::log('debug', "Fail to find a device in cache with mac '".$v_mac."'.");
        }
      }
      */
      
      return(true);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onMsgZigbeeData()
     * Description :
     * 
     * 
     *   Exemple of message :


     * 
     * 
     * ---------------------------------------------------------------------------
     */
    public function onMsgZigbeeData(&$p_reporter, $v_at_telemetry_msg) {

      ArubaWssTool::log('debug',  "Received zbNbData message from ".$p_reporter->getName()."");
      
      ArubaWssTool::log('debug:4', $v_at_telemetry_msg);
      
      return(true);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onMessage()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function onMessage(ConnectionInterface &$p_connection, $p_msg) {

      ArubaWssTool::log('debug',  "Received message from ".$p_connection->my_id."");
      
      // ----- Look for websocket client API connection
      if (isset($p_connection->my_type) && ($p_connection->my_type == 'ws_api')) {
        return($this->onWebsocketClientCall($p_connection, $p_msg));
      }

      // ----- Stats
      $v_stat_data_payload = strlen($p_msg);
      $this->stats('data_payload', $v_stat_data_payload);

      // ----- Parse Aruba protobuf message
      // TBC : I should check that the telemetry object is ok
      $v_at_telemetry_msg = new aruba_telemetry\Telemetry($p_msg);

      //ArubaWssTool::log('trace', $v_at_telemetry_msg);

      // ----- Get Meta part of the message
      $v_at_meta = $v_at_telemetry_msg->getMeta();

      // ----- Get Topic
      $v_topic = '';
      if ($v_at_meta->hasNbTopic()) {
        $v_topic = $v_at_meta->getNbTopic()->name();
      }

      ArubaWssTool::log('debug', "--------- Meta :");
      ArubaWssTool::log('debug', "  Version: ".$v_at_meta->getVersion()."");
      ArubaWssTool::log('debug', "  Access Token: ".$v_at_meta->getAccessToken()."");
      ArubaWssTool::log('debug', "  NbTopic: ".$v_topic."");
      ArubaWssTool::log('debug', "---------");
      ArubaWssTool::log('debug', "");
      
/*
enum NbTopic {
    telemetry               = 0;
    actionResults           = 1;
    characteristics         = 2;
    bleData                 = 3;
    wifiData                = 4;
    deviceCount             = 5;
    status                  = 6;
}
Starting release 8.8 :
enum NbTopic {
    telemetry               = 0;
    actionResults           = 1;
    characteristics         = 2;
    bleData                 = 3;
    wifiData                = 4;
    deviceCount             = 5;
    status                  = 6;
    zbNbData                = 7;
    serialDataNb            = 8;
    apHealthUpdate          = 9;
}
*/      

      // ----- Check Access token
      // TBC : here we are looking for a access_token which is the same for all AP. 
      // Could be improved to have a different access token per AP
      if ( ($this->access_token != '') && ($this->access_token != $v_at_meta->getAccessToken()) ) {
        ArubaWssTool::log('info', "Received message from reporter (".$v_mac.",".$v_ipv4.") with invalid access token. Closing connection.");
        return(false);
      }

      // ----- Switch on topic value
     switch ($v_topic) {
       case 'telemetry':
         $v_reporter = $this->getReporterFromProtoMessage($p_connection, $v_topic, $p_msg, $v_at_telemetry_msg);
         if ($v_reporter === null) {
           return(false);
         }
         return($this->onMsgTelemetry($v_reporter, $v_at_telemetry_msg));
       break;
       case 'actionResults':
         $v_reporter = $this->getReporterFromProtoMessage($p_connection, $v_topic, $p_msg, $v_at_telemetry_msg);
         if ($v_reporter === null) {
           return(false);
         }
         return($this->onMsgActionResultsList($v_reporter, $v_at_telemetry_msg));
       break;
       case 'characteristics':
         $v_reporter = $this->getReporterFromProtoMessage($p_connection, $v_topic, $p_msg, $v_at_telemetry_msg);
         if ($v_reporter === null) {
           return(false);
         }

        // ----- Look if telemetry has results before characteristics
        if ($v_at_telemetry_msg->hasResultsList()) {
          // This is gattRead/Write response ... notify ?    
          return($this->onMsgCharacteristicResults($v_reporter, $v_at_telemetry_msg));
        }
        
        // ----- This is a discovery result
        else {
          return($this->onMsgCharacteristicDiscovery($v_reporter, $v_at_telemetry_msg));
        }         
       break;
       case 'bleData':
         $v_reporter = $this->getReporterFromProtoMessage($p_connection, $v_topic, $p_msg, $v_at_telemetry_msg);
         if ($v_reporter === null) {
           return(false);
         }
         return($this->onMsgBleData($v_reporter, $v_at_telemetry_msg));
       break;
       case 'wifiData':
         $v_reporter = $this->getReporterFromProtoMessage($p_connection, $v_topic, $p_msg, $v_at_telemetry_msg);
         if ($v_reporter === null) {
           return(false);
         }
         return($this->onMsgWifiData($p_connection, $v_at_telemetry_msg));
       break;
       case 'deviceCount':
         ArubaWssTool::log('debug', "deviceCount not yet supported by websocket.");
         return(true);
       break;
       case 'status':
         $v_reporter = $this->getReporterFromProtoMessage($p_connection, $v_topic, $p_msg, $v_at_telemetry_msg);
         if ($v_reporter === null) {
           return(false);
         }
         return($this->onMsgStatus($v_reporter, $v_at_telemetry_msg));
       break;
       case 'zbNbData':
         $v_reporter = $this->getReporterFromProtoMessage($p_connection, $v_topic, $p_msg, $v_at_telemetry_msg);
         if ($v_reporter === null) {
           return(false);
         }
         return($this->onMsgZigbeeData($v_reporter, $v_at_telemetry_msg));
       break;
       case 'serialDataNb':
         $v_reporter = $this->getReporterFromProtoMessage($p_connection, $v_topic, $p_msg, $v_at_telemetry_msg);
         if ($v_reporter === null) {
           return(false);
         }
         return($this->onMsgSerialDataNb($v_reporter, $v_at_telemetry_msg));
       break;
       case 'apHealthUpdate':
         $v_reporter = $this->getReporterFromProtoMessage($p_connection, $v_topic, $p_msg, $v_at_telemetry_msg);
         if ($v_reporter === null) {
           return(false);
         }
         return($this->onMsgApHealthUpdate($v_reporter, $v_at_telemetry_msg));
       break;
       default:
         ArubaWssTool::log('debug', "Missing or unknown NbTopic value ('".$v_topic."').");
     }

      return(false);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : getReporterFromProtoMessage()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function getReporterFromProtoMessage(ConnectionInterface &$p_connection, $p_topic, $p_msg, $p_at_telemetry_msg) {

      // ----- Get telemetry msg
      $v_at_telemetry_msg = $p_at_telemetry_msg;
      
      // ----- Check if reporter info is valid
      if (!$v_at_telemetry_msg->hasReporter()) {
        ArubaWssTool::log('debug', "Missing reporter information in telemetry payload. Message ignored.");
        return(null);
      }
      
      // ----- Get reporter infos
      $v_at_reporter = $v_at_telemetry_msg->getReporter();

      // ----- Check if reporter has a valid MAC@
      if (!$v_at_reporter->hasMac()) {
        ArubaWssTool::log('debug', "Missing MAC@ for reporter in telemetry payload. Message ignored.");
        return(null);
      }
      
      // ----- Get Reporter mac@
      $v_mac = ArubaWssTool::macToString($v_at_reporter->getMac());
      $v_ipv4 = ($v_at_reporter->hasIpv4() ? $v_at_reporter->getIpv4() : '');

      ArubaWssTool::log('debug', "--------- Reporter :");
      ArubaWssTool::log('debug', "  Name: ".($v_at_reporter->hasName() ? $v_at_reporter->getName() : '')."");
      ArubaWssTool::log('debug', "  Mac: ".$v_mac."");
      ArubaWssTool::log('debug', "  IPv4: ".$v_ipv4."");
      ArubaWssTool::log('debug', "  IPv6: ".($v_at_reporter->hasIpv6() ? $v_at_reporter->getIpv6() : '-')."");
      ArubaWssTool::log('debug', "  hwType: ".($v_at_reporter->hasHwType() ? $v_at_reporter->getHwType() : '-')."");
      ArubaWssTool::log('debug', "  swVersion: ".($v_at_reporter->hasSwVersion() ? $v_at_reporter->getSwVersion() : '-')."");
      ArubaWssTool::log('debug', "  swBuild: ".($v_at_reporter->hasSwBuild() ? $v_at_reporter->getSwBuild() : '-')."");
      ArubaWssTool::log('debug', "  Time: ".date("Y-m-d H:i:s", $v_at_reporter->getTime())." (".$v_at_reporter->getTime().")");
      ArubaWssTool::log('debug', "---------");
      ArubaWssTool::log('debug', "");

      // ----- Look for controlled list of reporters by MAC@
      if (sizeof($this->reporters_allow_list) > 0) {
        if (!in_array($v_mac, $this->reporters_allow_list)) {
          ArubaWssTool::log('info', "Received message from not allowed reporter (".$v_mac.",".$v_ipv4."). Closing connection.");
          return(null);
        }
      }

      // ----- Look for existing reporter in the list, or create it.
      $v_reporter = $this->getReporterByMac($v_mac);
      if ($v_reporter === null) {

        ArubaWssTool::log('info', "Creating new reporter with MAC@ : ".$v_mac."");

        // ----- Create a new reporter
        $v_reporter = new ArubaWssReporter($v_mac);

        // ----- Set additional attributes
        $v_reporter->setName($v_at_reporter->getName());
        $v_reporter->setLocalIp(($v_at_reporter->hasIpv4() ? $v_at_reporter->getIpv4() : ''));
        //$v_reporter->setLocalIpv6(($v_at_reporter->hasIpv6() ? $v_at_reporter->getIpv6() : ''));
        $v_reporter->setHardwareType($v_at_reporter->getHwType());
        $v_reporter->setSoftwareVersion($v_at_reporter->getSwVersion());
        $v_reporter->setSoftwareBuild($v_at_reporter->getSwBuild());
        $v_reporter->setLastSeen($v_at_reporter->getTime());

        // ----- Attach to list
        $this->reporters_list[$v_mac] = $v_reporter;
        
      }

      // ----- Get cnx supported type
      $v_cnx_type = '';
      switch ($v_topic) {
        case 'telemetry':
        case 'actionResults':
        case 'characteristics':
        case 'bleData':
        case 'status':
        case 'deviceCount':
          $v_cnx_type = 'ble';
        break;
        case 'wifiData':
          $v_cnx_type = 'rtls';
        break;
        case 'zbNbData':
          $v_cnx_type = 'zigbee';
        break;
        case 'serialDataNb':
          $v_cnx_type = 'serial';
        break;
        case 'apHealthUpdate':
        break;
        default:
         //ArubaWssTool::log('debug', "Missing or unknown NbTopic value ('".$p_topic."').");
      }

      // ----- Connect the reporter with the connection
      $v_reporter->connect($p_connection, $v_cnx_type);

      // ----- Update changed attributes of the reporter
      if ($v_reporter->getName() != $v_at_reporter->getName()) {
        ArubaWssTool::log('info', "Reporter '".$v_reporter->getMac()."' changed name '".$v_reporter->getName()."' for '".$v_at_reporter->getName()."'");
        $v_reporter->setName($v_at_reporter->getName());
      }
      $v_ip = ($v_at_reporter->hasIpv4() ? $v_at_reporter->getIpv4() : '');
      if ($v_reporter->getLocalIp() != $v_ip) {
        ArubaWssTool::log('info', "Reporter '".$v_reporter->getMac()."' changed local IP '".$v_reporter->getLocalIp()."' for '".$v_ip."'");
        $v_reporter->setLocalIp($v_ip);
      }
      $v_hard = $v_at_reporter->getHwType();
      if ($v_reporter->getHardwareType() != $v_hard) {
        ArubaWssTool::log('info', "Reporter '".$v_reporter->getMac()."' changed hardware type '".$v_reporter->getHardwareType()."' for '".$v_hard."'");
        $v_reporter->setHardwareType($v_hard);
      }
      $v_soft = $v_at_reporter->getSwVersion();
      if ($v_reporter->getSoftwareVersion() != $v_soft) {
        ArubaWssTool::log('info', "Reporter '".$v_reporter->getMac()."' changed software version '".$v_reporter->getSoftwareVersion()."' for '".$v_soft."'");
        $v_reporter->setSoftwareVersion($v_soft);
      }
      $v_soft = $v_at_reporter->getSwBuild();
      if ($v_reporter->getSoftwareBuild() != $v_soft) {
        ArubaWssTool::log('info', "Reporter '".$v_reporter->getMac()."' changed software build '".$v_reporter->getSoftwareBuild()."' for '".$v_soft."'");
        $v_reporter->setSoftwareBuild($v_soft);
      }

      // ----- Stats
      $v_stat_data_payload = strlen($p_msg);
      $v_reporter->stats('data_payload', $v_stat_data_payload);

      // ----- Update last seen value
      $v_reporter->setLastSeen($v_at_reporter->getTime());

      return($v_reporter);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onPingMessage()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function onPingMessage(ConnectionInterface &$p_connection) {
        //ArubaWssTool::log('debug', "Received ping from ".$connection->my_name." (".date("Y-m-d H:i:s").")");
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : onInterrupt()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function onInterrupt() {

      //ArubaWssTool::log('debug', 'New interupt call');

      // ----- Add a gracefull restart
      // Need to wait for websocket connexions to be up before change status
      // to missing
      // 60 sec is maybe too much ?
      if (($this->up_time + 60) > time()) {
        return;
      }

      // ----- Scan all devices for presence update
      foreach ($this->cached_devices as $v_device) {
        $v_device->updateAbsence();
      }
      
      // ----- Generic 'cron-like' mechanism
      
      // ----- Every 10 sec actions
      $this->doCronCallback(10);
      
      // ----- Look for cron 20sec
      $this->cron20_tik--;
      if ($this->cron20_tik <= 0) {
        // ----- Launch cron 20 sec actions
        //ArubaWssTool::log('debug', '-- Interupt 20sec');
        $this->doCronCallback(20);
        
        // ----- Reset crontik
        $this->cron20_tik = 2;
      }

      // ----- Look for cron 30sec
      $this->cron30_tik--;
      if ($this->cron30_tik <= 0) {
        // ----- Launch cron 30 sec actions
        //ArubaWssTool::log('debug', '-- Interupt 30sec');
        $this->doCronCallback(30);
        
        // ----- Reset crontik
        $this->cron30_tik = 3;
      }

      // ----- Look for cron 60sec / 1 minute
      $this->cron60_tik--;
      if ($this->cron60_tik <= 0) {
        // ----- Launch cron 60 sec actions
        //ArubaWssTool::log('debug', '-- Interupt 60sec');
        $this->doCronCallback(60);
        
        // ----- Clean GATT queue
        // Every minute look to clean teh GATT queue. The message older than 5 minutes will be cleaned.
        $this->gattQueueCleaning();

        // ----- Reset crontik
        $this->cron60_tik = 6;
      }

      // ----- Look for cron 5minutes
      $this->cron5m_tik--;
      if ($this->cron5m_tik <= 0) {
        // ----- Launch cron 5mi actions
        $this->doCronCallback(300);
        
        // ----- Clean notification queue
        // Every 5 minutes look to clean the notification queue. 
        $this->notificationQueueCleaning();
        
        // ----- Reset crontik
        $this->cron5m_tik = 30;
      }

      // ----- Look for cron 10minutes
      $this->cron10m_tik--;
      if ($this->cron10m_tik <= 0) {
        // ----- Launch cron 10mi actions
        $this->doCronCallback(600);
        
        // ----- Reset crontik
        $this->cron10m_tik = 60;
      }

      // ----- Look for cron 30minutes
      $this->cron30m_tik--;
      if ($this->cron30m_tik <= 0) {
        // ----- Launch cron 30mi actions
        $this->doCronCallback(1800);
        
        // ----- Reset crontik
        $this->cron30m_tik = 180;
      }

      // ----- Look for cron 60minutes
      $this->cron60m_tik--;
      if ($this->cron60m_tik <= 0) {
        // ----- Launch cron 60mi actions
         $this->doCronCallback(3600);
       
        // ----- Reset crontik
        $this->cron60m_tik = 360;
      }


      /*
      ArubaWssTool::log('debug', "------ Stats");
      ArubaWssTool::log('debug', "  - raw_data : ".$this->raw_data." bytes");
      ArubaWssTool::log('debug', "  - payload_data : ".$this->payload_data." bytes");
      ArubaWssTool::log('debug', "------------");

      $v_uptime = time() - $this->up_time;

      ArubaWssTool::log('debug', "  - raw_data throuput : ".($this->payload_data/$v_uptime)." bytes/secondes");
      ArubaWssTool::log('debug', "  - payload_data throuput : ".($this->payload_data/$v_uptime)." bytes/secondes");
      ArubaWssTool::log('debug', "------------");


      foreach ($this->reporters_list as $v_reporter)
        $v_reporter->display_stats();
        */

    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : setCronCallback()
     * Description :
     *   $p_time_count=0 : veut dire infini
     * ---------------------------------------------------------------------------
     */
    public function setCronCallback($p_id, $p_action, $p_time, $p_time_count=0) {
      if (($p_time != 10)
          && ($p_time != 20)
          && ($p_time != 30)
          && ($p_time != 60)
          && ($p_time != 300)
          && ($p_time != 600)
          && ($p_time != 1800)
          && ($p_time != 3600)) {
        ArubaWssTool::log('debug', 'Invalid timer '.$p_time.' for cron action.');
        return(null);
      }
      
      // ----- If no Id set one
      if ($p_id == '') {
        $p_id = uniqid();
      }
      
      // ----- Should look if id alraedy exist to replace it
      if (isset($this->cron_action_list[$p_id])) {
        $v_action = $this->cron_action_list[$p_id];
      }
      else {
        // ----- Create new action
        $v_action = array();
      }
      
      // ----- Update action  
      $v_action['id'] = $p_id;
      $v_action['action'] = $p_action;
      $v_action['time'] = $p_time;
      $v_action['count'] = $p_time_count;
      
      // ----- Add in list
      $this->cron_action_list[$p_id] = $v_action;
      
      return($p_id);    
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : doCronCallback()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function doCronCallback($p_time) {
      if (($p_time != 10)
          && ($p_time != 20)
          && ($p_time != 30)
          && ($p_time != 60)
          && ($p_time != 300)
          && ($p_time != 600)
          && ($p_time != 1800)
          && ($p_time != 3600)) {
        ArubaWssTool::log('debug', 'Invalid timer '.$p_time.' for do cron action.');
        return(null);
      }
      
      foreach ($this->cron_action_list as $v_action) {
        if ($v_action['time'] == $p_time) {
          // do the action
         // ArubaWssTool::log('debug', 'Simulate doCronCallback : '.$v_action['action']);
          $v_resultcb = $this->onInternalApiCall($v_action['action']);
          ArubaWssTool::log('debug', ' doCronCallback result : '.print_r($v_resultcb, true));
          
          // look if count to decrement and element to remove
          if ($v_action['count'] > 0) {
            $v_action['count']--;
            if ($v_action['count'] == 0) {
              unset($this->cron_action_list[$v_action['id']]);
            }
            else {
              // ----- update
              $this->cron_action_list[$v_action['id']] = $v_action;
            }
          }
        }
      }
            
      return;    
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattCreateMessage()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function gattCreateMessage(&$p_gatt_msg, $p_device, $p_cnx_id='', $p_external_id='', $p_close_cnx=false) {

      if ($p_device === null) {
        ArubaWssTool::log('debug', 'Missing valid p_device.');
        return(0);
      }
      
      // ----- Find nearest reporter        
      $v_ap_mac = $p_device->getConnectApMac();    
      $v_reporter = $this->getReporterByMac($v_ap_mac);
      if ($v_reporter === null) {
        ArubaWssTool::log('debug', 'Fail to find nearest reporter for this device '.$v_ap_mac);
        return(0);
      }
      
      // ----- Look if reporter is available to connect
      // ok if reporter not alreay connected, or already connected with same device
      if (!$v_reporter->isAvailableToConnectWith($p_device->getMac())) {
        ArubaWssTool::log('debug', 'Reporter is already connected with another device');
        return(0);
      }
      
      // ----- Create gatt_msg array to store infos
      $p_gatt_msg = array();
      
      // ----- Create Protobuf message (Southbound message)
      $v_msg = new aruba_telemetry\IotSbMessage();
      
      // ----- Set meta header
      $v_meta = new aruba_telemetry\Meta();
      $v_meta->setVersion(1);
      $v_meta->setSbTopic(aruba_telemetry\SbTopic::actions());
      $v_msg->setMeta($v_meta);
      
      // ----- Set receiver (reporter)
      $v_receiver = new aruba_telemetry\Receiver();
      $v_mac = ArubaWssTool::stringToMac($v_reporter->getMac());
      $v_receiver->setApMac($v_mac);               
      $v_msg->setReceiver($v_receiver);
      
      // ----- Internally store data to create/fill/send the protobuf message
      $p_gatt_msg['proto_msg'] = $v_msg;
      $p_gatt_msg['actions'] = array();
      $p_gatt_msg['reporter_mac'] = $v_ap_mac;
      $p_gatt_msg['device_mac'] = $p_device->getMac();
      $p_gatt_msg['cnx_id'] = $p_cnx_id;
      $p_gatt_msg['external_id'] = $p_external_id;
      $p_gatt_msg['close_cnx_flag'] = $p_close_cnx;
      
      // ----- Return 
      return(1);            
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattSendMessage()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function gattSendMessage(&$p_gatt_msg) {
    
      // ----- Get websocket cnx associated with reporter AP
      //$v_cnx = $this->getConnectionByReporterMac($p_gatt_msg['reporter_mac']);      
      $v_cnx = $this->getConnectionBleByReporterMac($p_gatt_msg['reporter_mac']);      
      if ($v_cnx === null) {
        ArubaWssTool::log('debug', 'Fail to find connection for the reporter '.$p_gatt_msg['reporter_mac']);
        return(0);
      }
      ArubaWssTool::log('debug', 'Connection to be used is '.$v_cnx->my_id);
      
      // ----- Add the action in protobuf message and Queue the actions
      foreach ($p_gatt_msg['actions'] as $v_item) {
        // ----- Store action in the response queue
        // This queue is used to reconciliate the response
        $this->gattQueueAddAction($v_item['action_id'], 
                                  $v_item['action_type'],
                                  $p_gatt_msg['reporter_mac'],
                                  $p_gatt_msg['device_mac'],
                                  $p_gatt_msg['cnx_id'],
                                  $p_gatt_msg['external_id'],
                                  $p_gatt_msg['close_cnx_flag']);
        
        // ----- Add the action in the current protobuf message
        // In case of discovery, I need to store a action in queue, even if no real gatt message sent
        if ($v_item['action'] != null) {
          $p_gatt_msg['proto_msg']->addActions($v_item['action']);
        }
      }
                  
      // ----- Send the message as a proto message through the connection
      if (!ArubaWssTool::sendProtoMessage($v_cnx, $p_gatt_msg['proto_msg'])) {
        ArubaWssTool::log('debug', 'Fail to send protobuf message to reporter.');
        
        // TBC : could be a good idee to remove all the actions in queue ...
      }
      
      return(1);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattQueueAddAction()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function gattQueueAddAction($p_action_id, $p_action_type, $p_reporter_mac, $p_device_mac, $p_cnx_id, $p_external_id, $p_close_cnx) {
    
      // ----- Create a new entry
      $v_queue_item = array();
      $v_queue_item['action_id'] = $p_action_id;
      $v_queue_item['action_type'] = $p_action_type;
      $v_queue_item['reporter_mac'] = $p_reporter_mac;
      $v_queue_item['device_mac'] = $p_device_mac;
      $v_queue_item['timestamp'] = time();
      $v_queue_item['cnx_id'] = $p_cnx_id;
      $v_queue_item['external_id'] = $p_external_id;
      $v_queue_item['close_cnx_flag'] = $p_close_cnx;
            
      // ----- Add the entry in the queue
      $this->gatt_queue[$p_action_id] = $v_queue_item;
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattQueueUpdateTimestamp()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function gattQueueUpdateTimestamp($p_action_id) {    
      if (isset($this->gatt_queue[$p_action_id])) {
        $this->gatt_queue[$p_action_id]['timestamp'] = time();
      }
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattQueueRemoveAction()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function gattQueueRemoveAction($p_action_id) {    
      if (isset($this->gatt_queue[$p_action_id])) {
        ArubaWssTool::log('debug', 'Remove action '.$p_action_id.' from gatt queue.');
        unset($this->gatt_queue[$p_action_id]);
      }
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattQueueGetAction()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function gattQueueGetAction($p_action_id, $p_action_type='', $p_device_mac='') {    
      $v_result = null;
      
      // ----- If no valid entry in table
      if (!isset($this->gatt_queue[$p_action_id])) {
        ArubaWssTool::log('debug', "No action in gatt queue with this ID '".$p_action_id."'");
        return(null);
      }
      
      $v_result = $this->gatt_queue[$p_action_id];
      
      // ----- Check the action type
      if ( ($p_action_type != '') && ($v_result['action_type'] != $p_action_type) ) {
        ArubaWssTool::log('debug', "Inconsistant action type between queue and gatt response.");
        return(null);
      }
      
      // ----- Check the device mac
      if ( ($p_device_mac != '') && ($v_result['device_mac'] != $p_device_mac) ) {
        ArubaWssTool::log('debug', "Inconsistant device mac between queue and gatt response.");
        return(null);
      }
      
      return($v_result);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattQueueGetActionByType()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function gattQueueGetActionByType($p_action_type, $p_device_mac) {    
      
      foreach ($this->gatt_queue as $v_item) {
        if (($v_item['action_type'] == $p_action_type) && ($v_item['device_mac'] == $p_device_mac)) {
          return($v_item);
        }
      }
      
      return(null);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattQueueCleaning()
     * Description :
     *   Clean the old GATT message that are in the queue for more than 5 minutes.
     * ---------------------------------------------------------------------------
     */
    private function gattQueueCleaning() {
      $v_list = array();
      foreach ($this->gatt_queue as $v_key => $v_item) {
        if (($v_item['timestamp']+300) < time()) {
          $v_list[] = $v_key;
        }
      }
      foreach ($v_list as $v_item) {
        $this->gattQueueRemoveAction($v_item);
      }
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattAddActionConnect()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function gattAddActionConnect(&$p_gatt_msg, $p_timeout=30) {

      // ----- Get a uniq action_id      
      $v_action_id = uniqid();
      
      // ----- Create a protobuf action
      $v_action = new aruba_telemetry\Action();
      $v_action->setActionId($v_action_id);
      $v_action->setType(aruba_telemetry\ActionType::bleConnect());
      $v_mac = ArubaWssTool::stringToMac($p_gatt_msg['device_mac']);
      $v_action->setDeviceMac($v_mac);
      $v_action->setTimeOut($p_timeout);
      
      // ----- Store the action in a temporary array
      $v_index = sizeof($p_gatt_msg['actions']);
      $p_gatt_msg['actions'][$v_index]['action_id'] = $v_action_id;
      $p_gatt_msg['actions'][$v_index]['action_type'] = 'bleConnect';
      $p_gatt_msg['actions'][$v_index]['action'] = $v_action;
      
      ArubaWssTool::log('debug', 'Add Gatt bleConnect in queue (action_id : '.$v_action_id.')');
      
      return(1);            
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattAddActionDisconnect()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function gattAddActionDisconnect(&$p_gatt_msg, $p_timeout=30) {

      // ----- Get a uniq action_id      
      $v_action_id = uniqid();
      
      // ----- Create a protobuf action
      $v_action = new aruba_telemetry\Action();
      $v_action->setActionId($v_action_id);
      $v_action->setType(aruba_telemetry\ActionType::bleDisconnect());
      $v_mac = ArubaWssTool::stringToMac($p_gatt_msg['device_mac']);
      $v_action->setDeviceMac($v_mac);
      $v_action->setTimeOut($p_timeout);
      
      // ----- Store the action in a temporary array
      $v_index = sizeof($p_gatt_msg['actions']);
      $p_gatt_msg['actions'][$v_index]['action_id'] = $v_action_id;
      $p_gatt_msg['actions'][$v_index]['action_type'] = 'bleDisconnect';
      $p_gatt_msg['actions'][$v_index]['action'] = $v_action;
      
      ArubaWssTool::log('debug', 'Add Gatt bleDisconnect in queue (action_id : '.$v_action_id.')');

      return(1);            
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattAddActionDiscover()
     * Description :
     *   gattAddActionDiscover() is in reality a BLE Connect action, but used
     *   to trigger a Characteristics discovery.
     *   The difference is the fact that the action stack need to have
     *   information that the request was a discovery not a connect.
     *   And unfortunately the characteristic discovery do not have an action_id.
     * ---------------------------------------------------------------------------
     */
    private function gattAddActionDiscover(&$p_gatt_msg, $p_timeout=30) {

      // ----- Get a uniq action_id      
      $v_action_id = uniqid();
      
      // ----- Create a protobuf action
      $v_action = new aruba_telemetry\Action();
      $v_action->setActionId($v_action_id);
      $v_action->setType(aruba_telemetry\ActionType::bleConnect());
      $v_mac = ArubaWssTool::stringToMac($p_gatt_msg['device_mac']);
      $v_action->setDeviceMac($v_mac);
      $v_action->setTimeOut($p_timeout);
      
      // ----- Store the 'bleConnect" action in a temporary array
      $v_index = sizeof($p_gatt_msg['actions']);
      $p_gatt_msg['actions'][$v_index]['action_id'] = $v_action_id;
      $p_gatt_msg['actions'][$v_index]['action_type'] = 'bleConnect';
      $p_gatt_msg['actions'][$v_index]['action'] = $v_action;
      
      ArubaWssTool::log('debug', 'Add Gatt bleConnect (discover) in queue (action_id : '.$v_action_id.')');

      // ----- Store a virtual 'Discover" action in a temporary array
      $v_action_id = uniqid();
      $v_index = sizeof($p_gatt_msg['actions']);
      $p_gatt_msg['actions'][$v_index]['action_id'] = $v_action_id;
      $p_gatt_msg['actions'][$v_index]['action_type'] = 'bleDiscover';
      $p_gatt_msg['actions'][$v_index]['action'] = null;
      
      ArubaWssTool::log('debug', 'Add Gatt bleDiscover in queue (action_id : '.$v_action_id.')');
      
      // ===== Add a disconnect action
      // ----- Get a uniq action_id      
      $v_action_id = uniqid();
      
      // ----- Create a protobuf action
      $v_action = new aruba_telemetry\Action();
      $v_action->setActionId($v_action_id);
      $v_action->setType(aruba_telemetry\ActionType::bleDisconnect());
      $v_mac = ArubaWssTool::stringToMac($p_gatt_msg['device_mac']);
      $v_action->setDeviceMac($v_mac);
      $v_action->setTimeOut($p_timeout);
      
      // ----- Store the action in a temporary array
      $v_index = sizeof($p_gatt_msg['actions']);
      $p_gatt_msg['actions'][$v_index]['action_id'] = $v_action_id;
      $p_gatt_msg['actions'][$v_index]['action_type'] = 'bleDisconnect';
      $p_gatt_msg['actions'][$v_index]['action'] = $v_action;
      
      
      ArubaWssTool::log('debug', 'Add Gatt bleDisconnect (discover) in queue (action_id : '.$v_action_id.')');
      
      return(1);            
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattAddActionRead()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function gattAddActionRead(&$p_gatt_msg, $p_service_uuid, $p_char_uuid, $p_timeout=30) {

      // ----- Get a uniq action_id      
      $v_action_id = uniqid();
      
      // ----- Create a protobuf action
      $v_action = new aruba_telemetry\Action();
      $v_action->setActionId($v_action_id);
      $v_action->setType(aruba_telemetry\ActionType::gattRead());
      $v_mac = ArubaWssTool::stringToMac($p_gatt_msg['device_mac']);
      $v_action->setDeviceMac($v_mac);
      $v_action->setTimeOut($p_timeout);

      $v_uuid = ArubaWssTool::stringToBytes($p_service_uuid);
      $v_action->setServiceUuid($v_uuid);               

      $v_uuid = ArubaWssTool::stringToBytes($p_char_uuid);
      $v_action->setCharacteristicUuid($v_uuid);               
      
      // ----- Store the action in a temporary array
      $v_index = sizeof($p_gatt_msg['actions']);
      $p_gatt_msg['actions'][$v_index]['action_id'] = $v_action_id;
      $p_gatt_msg['actions'][$v_index]['action_type'] = 'gattRead';
      $p_gatt_msg['actions'][$v_index]['action'] = $v_action;
      
      ArubaWssTool::log('debug', 'Add Gatt gattRead in queue (action_id : '.$v_action_id.')');
      
      return(1);            
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattAddActionNotify()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function gattAddActionNotify(&$p_gatt_msg, $p_service_uuid, $p_char_uuid, $p_mode, $p_timeout=30) {

      // ----- Get a uniq action_id      
      $v_action_id = uniqid();
      
      // ----- Create a protobuf action
      $v_action = new aruba_telemetry\Action();
      $v_action->setActionId($v_action_id);
      $v_action->setType(aruba_telemetry\ActionType::gattNotification());
      $v_mac = ArubaWssTool::stringToMac($p_gatt_msg['device_mac']);
      $v_action->setDeviceMac($v_mac);
      //$v_action->setTimeOut($p_timeout);
      // seems that 30 is not enought ...
      $v_action->setTimeOut(60);

      $v_uuid = ArubaWssTool::stringToBytes($p_service_uuid);
      $v_action->setServiceUuid($v_uuid);               

      $v_uuid = ArubaWssTool::stringToBytes($p_char_uuid);
      $v_action->setCharacteristicUuid($v_uuid);               
      
      $v_notification = ArubaWssTool::stringToBytes($p_mode);
      $v_action->setValue($v_notification);               

      // ----- Store the action in a temporary array
      $v_index = sizeof($p_gatt_msg['actions']);
      $p_gatt_msg['actions'][$v_index]['action_id'] = $v_action_id;
      $p_gatt_msg['actions'][$v_index]['action_type'] = 'gattNotification';
      $p_gatt_msg['actions'][$v_index]['action'] = $v_action;
      
      ArubaWssTool::log('debug', 'Add Gatt gattNotification in queue (action_id : '.$v_action_id.')');
      
      return(1);            
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattDeviceConnect()
     * Description :
     * 
     *   Exemple du format du message envoyé : aruba-iot-sb.proto
     *   {
     *   "meta":
     *   {
     *   "version": 1,
     *   "sbTopic": "actions"
     *   },
     *   "receiver": {
     *   "apMac": "aa:bb:cc:dd:ee:ff"
     *   },
     *   "actions": [
     *   {
     *   "actionId": "0001",
     *   "type": "bleConnect",
     *   "deviceMac": "11:22:33:44:55:66",
     *   "timeOut": 30
     *   }
     *   ]
     *   }      
     * ---------------------------------------------------------------------------
     */
    public function gattDeviceConnect($p_device_mac, $p_cnx_id='', $p_external_id='', $p_close_cnx=false) {
    
      // ----- Reset log message
      $this->gatt_log_msg = '';
      
      // ----- Internal structure for gatt message
      $v_gatt_msg = array();
      
      // ----- Get the device (if any)
      $v_device = $this->getDeviceByMac($p_device_mac);
      if ($v_device === null) {
        $this->gatt_log_msg = 'Fail to find device with mac '.$p_device_mac;
        ArubaWssTool::log('debug', $this->gatt_log_msg);
        return(0);
      }

      // ----- Prepare a GATT (protobuf) message
      if ($this->gattCreateMessage($v_gatt_msg, $v_device, $p_cnx_id, $p_external_id, $p_close_cnx) != 1) {
        return(0);
      }
      
      // ----- Add a connect action
      if ($this->gattAddActionConnect($v_gatt_msg) != 1) {
        return(0);
      }

      // ----- Look if already connected
      if ($v_device->getConnectStatus() == AWSS_STATUS_CONNECTED) {
        ArubaWssTool::log('debug', "Device is already connected.");
        return(1);
      }
      
      // ----- Add a connect action
      if ($this->gattSendMessage($v_gatt_msg) != 1) {
        return(0);
      }
      
      return(1);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattDeviceDisconnect()
     * Description :
     * 
      Message à envoyer : aruba-iot-sb.proto
{
"meta":
{
"version": 1,
"sbTopic": "actions"
},
"receiver": {
"apMac": "aa:bb:cc:dd:ee:ff"
},
"actions": [
{
"actionId": "0001",
"type": "bleDisconnect",
"deviceMac": "11:22:33:44:55:66",
"timeOut": 30
}
]
}      

     * 
     * ---------------------------------------------------------------------------
     */
    public function gattDeviceDisconnect($p_device_mac, $p_cnx_id='', $p_external_id='', $p_close_cnx=false) {
    
      // ----- Reset log message
      $this->gatt_log_msg = '';
      
      // ----- Internal structure for gatt message
      $v_gatt_msg = array();
      
      // ----- Get the device (if any)
      $v_device = $this->getDeviceByMac($p_device_mac);
      if ($v_device === null) {
        $this->gatt_log_msg = 'Fail to find device with mac '.$p_device_mac;
        ArubaWssTool::log('debug', $this->gatt_log_msg);
        return(0);
      }

      // ----- Prepare a GATT (protobuf) message
      if ($this->gattCreateMessage($v_gatt_msg, $v_device, $p_cnx_id, $p_external_id, $p_close_cnx) != 1) {
        return(0);
      }
      
      // ----- Add a disconnect action
      if ($this->gattAddActionDisconnect($v_gatt_msg) != 1) {
        return(0);
      }

      // ----- Look if already disconnected
      if ($v_device->getConnectStatus() == AWSS_STATUS_DISCONNECTED) {
        ArubaWssTool::log('debug', "Device is already disconnected.");
        return(1);
      }
      
      
      // ----- Add a connect action
      if ($this->gattSendMessage($v_gatt_msg) != 1) {
        return(0);
      }
      
      return(1);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattDeviceDiscover()
     * Description :
     *   Goal of this method is to trigger a characteristic discovery of the
     *   device. The discovery is all the time triggered after a connect.
     *   So the method is just doing a connect, followed immediately by a
     *   disconnect to free up the AP.
     * ---------------------------------------------------------------------------
     */
    public function gattDeviceDiscover($p_device_mac, $p_cnx_id='', $p_external_id='', $p_close_cnx=false) {
    
      // ----- Reset log message
      $this->gatt_log_msg = '';
      
      // ----- Internal structure for gatt message
      $v_gatt_msg = array();
      
      // ----- Get the device (if any)
      $v_device = $this->getDeviceByMac($p_device_mac);
      if ($v_device === null) {
        $this->gatt_log_msg = 'Fail to find device with mac '.$p_device_mac;
        ArubaWssTool::log('debug', $this->gatt_log_msg);
        return(0);
      }

      // ----- Look if already connected
      if ($v_device->getConnectStatus() == AWSS_STATUS_CONNECTED) {
        ArubaWssTool::log('debug', "Device is already connected.");
        return(1);
      }
      
      // ----- Prepare a GATT (protobuf) message
      if ($this->gattCreateMessage($v_gatt_msg, $v_device, $p_cnx_id, $p_external_id, $p_close_cnx) != 1) {
        return(0);
      }
      
      // ----- Add a connect action
      if ($this->gattAddActionDiscover($v_gatt_msg) != 1) {
        return(0);
      }

      // ----- Add a connect action
      if ($this->gattSendMessage($v_gatt_msg) != 1) {
        return(0);
      }
      
      return(1);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattDeviceRead()
     * Description :
     * 
      Message à envoyer : aruba-iot-sb.proto
{
"meta":
{
"version": 1,
"sbTopic": "actions"
},
"receiver": {
"apMac": "aa:bb:cc:dd:ee:ff"
},
"actions": [
{
"actionId": "0003",
"type": "gattRead",
"deviceMac": "11:22:33:44:55:66",
"serviceUuid": "272FE150-6C6C-4718-A3D4-6DE8A3735CFF",
"characteristicUuid": "272FE151-6C6C-4718-A3D4-6DE8A3735CFF",
"timeOut": 30
}
]
}

     * ---------------------------------------------------------------------------
     */
    public function gattDeviceRead($p_device_mac, $p_service_uuid, $p_char_uuid, $p_cnx_id='', $p_external_id='', $p_close_cnx=false) {
    
      // ----- Reset log message
      $this->gatt_log_msg = '';
      
      // ----- Internal structure for gatt message
      $v_gatt_msg = array();
      
      // ----- Get the device (if any)
      $v_device = $this->getDeviceByMac($p_device_mac);
      if ($v_device === null) {
        $this->gatt_log_msg = 'Fail to find device with mac '.$p_device_mac;
        ArubaWssTool::log('debug', $this->gatt_log_msg);
        return(0);
      }

      // ----- Prepare a GATT (protobuf) message
      if ($this->gattCreateMessage($v_gatt_msg, $v_device, $p_cnx_id, $p_external_id, $p_close_cnx) != 1) {
        return(0);
      }
      
      // ----- Look if connect needed
      if ($v_device->getConnectStatus() != AWSS_STATUS_CONNECTED) {     
        ArubaWssTool::log('debug', 'Device not connected, connect before read');   

        // ----- Add a connect action
        if ($this->gattAddActionConnect($v_gatt_msg) != 1) {
          return(0);
        }
      }
      
      // ----- Add read action
      if ($this->gattAddActionRead($v_gatt_msg, $p_service_uuid, $p_char_uuid) != 1) {
        return(0);
      }

      // ----- Add a disconnect action : to free the ble connect and the AP ...
      // Only one connect available per AP, so free the connection after the read
      if ($this->gattAddActionDisconnect($v_gatt_msg) != 1) {
        return(0);
      }

      // ----- Add a connect action
      if ($this->gattSendMessage($v_gatt_msg) != 1) {
        return(0);
      }
      
      return(1);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattDeviceReadMultiple()
     * Description :
     *   Same as gattDeviceRead() but with several char at the same time.
     * ---------------------------------------------------------------------------
     */
    public function gattDeviceReadMultiple($p_device_mac, $p_char_list, $p_cnx_id='', $p_external_id='', $p_close_cnx=false) {
    
      // ----- Reset log message
      $this->gatt_log_msg = '';
      
      // ----- Internal structure for gatt message
      $v_gatt_msg = array();
      
      // ----- Get the device (if any)
      $v_device = $this->getDeviceByMac($p_device_mac);
      if ($v_device === null) {
        $this->gatt_log_msg = 'Fail to find device with mac '.$p_device_mac;
        ArubaWssTool::log('debug', $this->gatt_log_msg);
        return(0);
      }

      // ----- Prepare a GATT (protobuf) message
      if ($this->gattCreateMessage($v_gatt_msg, $v_device, $p_cnx_id, $p_external_id, $p_close_cnx) != 1) {
        return(0);
      }
      
      // ----- Look if connect needed
      if ($v_device->getConnectStatus() != AWSS_STATUS_CONNECTED) {     
        ArubaWssTool::log('debug', 'Device not connected, connect before read');   

        // ----- Add a connect action
        if ($this->gattAddActionConnect($v_gatt_msg) != 1) {
          return(0);
        }
      }
      
      // ----- Add one action per item
      foreach ($p_char_list as $v_char_item) {
        // ----- Add read action
        if ($this->gattAddActionRead($v_gatt_msg, $v_char_item['service_uuid'], $v_char_item['char_uuid']) != 1) {
          return(0);
        }
      }

      // ----- Add a disconnect action : to free the ble connect and the AP ...
      // Only one connect available per AP, so free the connection after the read
      if ($this->gattAddActionDisconnect($v_gatt_msg) != 1) {
        return(0);
      }

      // ----- Add a connect action
      if ($this->gattSendMessage($v_gatt_msg) != 1) {
        return(0);
      }
      
      return(1);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattDeviceReadRepeat()
     * Description :
     *   $p_repeat_count = 0 : infinite
     * ---------------------------------------------------------------------------
     */
    public function gattDeviceReadRepeat($p_device_mac, $p_service_uuid, $p_char_uuid, $p_repeat_time, $p_repeat_count, $p_cnx_id='', $p_external_id='', $p_close_cnx=false) {
    
      // ----- Reset log message
      $this->gatt_log_msg = '';
      
      $v_device = $this->getDeviceByMac($p_device_mac);
      if ($v_device === null) {
        ArubaWssTool::log('debug', 'Fail to find device with mac '.$p_device_mac);
        return(0);
      }
      
      // ----- Set the characteristic (the good way to create it if  not already done)
      // TBC : need to be updated to the right types ... so maybe need to wait for updated info ...
      // Let say that for first run, must be discovered first
      //$v_device->setCharacteristic($p_service_uuid, $p_char_uuid);
      
      // ----- Add API call in cron table 
      // TBC : a améliorer ... :-)
      $v_str = '{"name" : "ble_read", "data": {"service_uuid":"'.$p_service_uuid.'","char_uuid":"'.$p_char_uuid.'","device_mac":"'.$p_device_mac.'"}}';
      $this->setCronCallback(md5('read'.$p_device_mac.$p_service_uuid.$p_char_uuid), $v_str, $p_repeat_time, $p_repeat_count);

      // ----- Run the read for the first time
      $v_result = $this->gattDeviceRead($p_device_mac, $p_service_uuid, $p_char_uuid, $p_cnx_id, $p_external_id, $p_close_cnx);
      
      return($v_result);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : gattDeviceNotify()
     * Description :
     * 
{
"meta":
{
"version": 1,
"sbTopic": "actions"
},
"receiver": {
"apMac": "aa:bb:cc:dd:ee:ff"
},
"actions": [
{
"actionId": "0006",
"type": "gattNotification",
"deviceMac": "11:22:33:44:55:66",
"serviceUuid": "272FE150-6C6C-4718-A3D4-6DE8A3735CFF",
"characteristicUuid": "272FE151-6C6C-4718-A3D4-6DE8A3735CFF",
"value": 1,
"timeOut": 30
}
]
}
     * 
     * ---------------------------------------------------------------------------
     */
    public function gattDeviceNotify($p_device_mac, $p_service_uuid, $p_char_uuid, $p_mode, $p_cnx_id='', $p_external_id='', $p_close_cnx=false, $p_timeout=0) {
    
      // ----- Reset log message
      $this->gatt_log_msg = '';
      
      // ----- Internal structure for gatt message
      $v_gatt_msg = array();
      
      // ----- Get the device (if any)
      $v_device = $this->getDeviceByMac($p_device_mac);
      if ($v_device === null) {
        $this->gatt_log_msg = 'Fail to find device with mac '.$p_device_mac;
        ArubaWssTool::log('debug', $this->gatt_log_msg);
        return(0);
      }

      // ----- Prepare a GATT (protobuf) message
      if ($this->gattCreateMessage($v_gatt_msg, $v_device, $p_cnx_id, $p_external_id, $p_close_cnx) != 1) {
        return(0);
      }
      
      // ----- Look if connect needed
//      if (($v_device->getConnectStatus() != AWSS_STATUS_CONNECT_INITIATED) 
//          && ($v_device->getConnectStatus() != AWSS_STATUS_CONNECTED)) {     
      if ($v_device->getConnectStatus() != AWSS_STATUS_CONNECTED) {     
        ArubaWssTool::log('debug', 'Device not connected, connect before read');   
          
        // ----- Add a connect action
        if ($this->gattAddActionConnect($v_gatt_msg) != 1) {
          return(0);
        }
      }
      
      // ----- Add read action
      if ($this->gattAddActionNotify($v_gatt_msg, $p_service_uuid, $p_char_uuid, $p_mode) != 1) {
        return(0);
      }
      
      // ----- Look for disable to also disconnect
      if ($p_mode == '00') {
        if ($this->gattAddActionDisconnect($v_gatt_msg) != 1) {
          return(0);
        }
      }

      // ----- Add a connect action
      if ($this->gattSendMessage($v_gatt_msg) != 1) {
        return(0);
      }
      
      // ----- Look for timeout
      if ($p_timeout != 0) {
        // TBC : Call a notify stop after $p_timeout inspiration on below

      /*
      // ----- Prepare a callback to disconnect in 10 to 20 sec.
      // This will allow any other request while ble cnx is up, avoiding multiple cnx/dcnx
      // I put the time to 20sec because it will be in reality between 10 and 20 sec depending on where is the clock when
      // the acllback is registered. And because this is a onetime callabck.
      $v_str = '{"name" : "ble_disconnect", "data": {"device_mac":"'.$p_device_mac.'"}}';
      $this->setCronCallback(md5(AWSS_STATUS_DISCONNECTED.$p_device_mac), $v_str, 20, 1);
      */
      

      }
      
      return(1);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : notificationQueueAdd()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function notificationQueueAdd($p_notification_type, $p_cb_type, $p_cb_id, $p_data, $p_notification_id='') {
    
      // ----- Look for id to create
      if ($p_notification_id == '') 
        $p_notification_id = uniqid();
        
      // ----- Create a new entry
      $v_queue_item = array();
      $v_queue_item['notification_id'] = $p_notification_id;
      $v_queue_item['notification_type'] = $p_notification_type;
      $v_queue_item['cb_type'] = $p_cb_type;
      $v_queue_item['cb_id'] = $p_cb_id;
      $v_queue_item['timestamp'] = time();
      $v_queue_item['data'] = $p_data;
            
      // ----- Add the entry in the queue
      $this->notification_queue[$p_notification_id] = $v_queue_item;
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : notificationQueueRemove()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function notificationQueueRemove($p_notification_id) {    
      if (isset($this->notification_queue[$p_notification_id])) {
        ArubaWssTool::log('debug', "Remove notification '".$p_notification_id."' from notification queue.");
        unset($this->notification_queue[$p_notification_id]);
        return(1);
      }
      return(0);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : notificationQueueRemoveByTypeAndCb()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function notificationQueueRemoveByTypeAndCb($p_notification_type, $p_cb_type, $p_cb_id) {    
      $v_list = array();
      foreach ($this->notification_queue as $v_key => $v_item) {
        if (($v_item['notification_type'] == $p_notification_type)
            && ($v_item['cb_type'] == $p_cb_type)
            && ($v_item['cb_id'] == $p_cb_id) ) {
          $v_list[] = $v_key;
        }
      }    
      $v_count = 0;
      foreach ($v_list as $v_item) {
        ArubaWssTool::log('debug', "Remove notification '".$v_item."' from notification queue.");
        unset($this->notification_queue[$v_item]);
        $v_count++;
      }
      return($v_count);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : notificationQueueUpdateTimestamp()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function notificationQueueUpdateTimestamp($p_notification_id) {    
      if (isset($this->notification_queue[$p_notification_id])) {
        $this->notification_queue[$p_notification_id]['timestamp'] = time();
      }
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : notificationQueueGet()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function notificationQueueGet($p_notification_id, $p_notification_type='') {    
      $v_result = null;
      
      // ----- If no valid entry in table
      if (!isset($this->notification_queue[$p_notification_id])) {
        ArubaWssTool::log('debug', "No item in notification queue with this ID '".$p_notification_id."'");
        return(null);
      }
      
      $v_result = $this->notification_queue[$p_notification_id];
      
      // ----- Check the notification type
      if ( ($p_notification_type != '') && ($v_result['notification_type'] != $p_notification_type) ) {
        ArubaWssTool::log('debug', "Inconsistant item type with item in queue.");
        return(null);
      }
      
      return($v_result);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : notificationQueueGetByCb()
     * Description :
     * ---------------------------------------------------------------------------
     */
    private function notificationQueueGetByCb($p_notification_type, $p_cb_type, $p_cb_id) {    
      foreach ($this->notification_queue as $v_item) {
        if (($v_item['notification_type'] == $p_notification_type)
            && ($v_item['cb_type'] == $p_cb_type)
            && ($v_item['cb_id'] == $p_cb_id) ) {
          return($v_item);
        }
      }     
      return(null);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : notificationQueueCleaning()
     * Description :
     *   Clean the old notification that are in the queue.
     *   Example of reason to clean : cnx is not any more active, ...
     * ---------------------------------------------------------------------------
     */
    private function notificationQueueCleaning() {
      $v_list = array();
      foreach ($this->notification_queue as $v_key => $v_item) {
        if (   ($v_item['cb_type'] == 'ws_api')
            && ($this->getConnectionById($v_item['cb_id']) === NULL) ) {
          $v_list[] = $v_key;
        }
      }
      foreach ($v_list as $v_item) {
        $this->notificationQueueRemove($v_item);
      }
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : notificationAdd()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function notificationAdd($p_type, $p_cb_type, $p_cb_id, $p_data='') {

      // ----- Check for duplicate
      foreach ($this->notification_queue as $v_item) {
        if (($v_item['notification_type'] == $p_type)
            && ($v_item['cb_type'] == $p_cb_type)
            && ($v_item['cb_id'] == $p_cb_id) ) {
          // ----- -1 means duplicate
          return(-1);
        }
      }     
      
      // ----- Add in queue
      $this->notificationQueueAdd($p_type, $p_cb_type, $p_cb_id, $p_data);
      
      return(1);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : notificationAddDeviceStatus()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function notificationAddDeviceStatus($p_device_mac, $p_cb_type, $p_cb_id, $p_data='') {

      // ----- Check for duplicate
      foreach ($this->notification_queue as $v_item) {
        if (($v_item['notification_type'] == 'device_status')
            && ($v_item['cb_type'] == $p_cb_type)
            && ($v_item['cb_id'] == $p_cb_id)
            && ($v_item['data']['device_mac'] = $p_device_mac) ) {
          // ----- -1 means duplicate
          return(-1);
        }
      }     

      // ----- Set data to store : add device_mac in ixisting list   
      $v_data['device_mac'] = $p_device_mac;
      if (is_array($p_data))  {
        $v_data = array_merge($v_data, $p_data);
      }
      
      // ----- Add in queue
      $this->notificationQueueAdd('device_status', $p_cb_type, $p_cb_id, $v_data);
      
      return(1);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : notificationRemoveDeviceStatus()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function notificationRemoveDeviceStatus($p_device_mac, $p_cb_type, $p_cb_id, $p_data='') {
      // ----- Look for no device_mac : means all device status for this cnx
      if ($p_device_mac == '') {
        $v_count = $this->notificationQueueRemoveByTypeAndCb('device_status', $p_cb_type, $p_cb_id);
        return($v_count);
      }

      // ----- Look for all to remove
      $v_list = array();
      foreach ($this->notification_queue as $v_key => $v_item) {
        if (($v_item['notification_type'] == 'device_status')
            && ($v_item['cb_type'] == $p_cb_type)
            && ($v_item['cb_id'] == $p_cb_id)
            && ($v_item['data']['device_mac'] = $p_device_mac) ) {
          $v_list[] = $v_key;
        }
      }     

      $v_count = 0;
      foreach ($v_list as $v_item) {
        unset($this->notification_queue[$v_item]);
        $v_count++;
      }
      return($v_count);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : notificationRemoveByCb()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function notificationRemoveByCb($p_cb_type, $p_cb_id) {
      $v_list = array();
      foreach ($this->notification_queue as $v_key => $v_item) {
        if (($v_item['cb_type'] == $p_cb_type)
            && ($v_item['cb_id'] == $p_cb_id) ) {
          $v_list[] = $v_key;
        }
      }    
      $v_count = 0;
      foreach ($v_list as $v_item) {
        ArubaWssTool::log('debug', "Remove notification '".$v_item."' from notification queue.");
        unset($this->notification_queue[$v_item]);
        $v_count++;
      }
      return($v_count);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : notification()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function notification($p_notification, $p_args) {
      ArubaWssTool::log('debug', 'notification('.$p_notification.')');
      
      ArubaWssTool::log('debug', 'args='.print_r($p_args, true));
      
      if ($p_notification == 'device_status') {
      
        $v_device = $this->getDeviceByMac($p_args['mac_address']);
      
        foreach ($this->notification_queue as $v_item) {
          if (   ($v_item['notification_type'] == 'device_status') 
              && ($v_item['data']['device_mac'] == $v_device->getMac()) ) {
            // ----- API Notify response
            if ($v_item['cb_type'] == 'ws_api') {
              $v_data = array();
              $v_data['type'] = 'device_status';
              $v_data['device_mac'] = $v_device->getMac();
              $v_data['status'] = $v_device->getConnectStatus();     
              ArubaWssTool::log('debug', 'Trigger status :'.$v_device->getConnectStatus());       
              $this->apiNotify_notification($v_item['cb_id'], $v_data, $v_item['cb_id']);
            }
          }
        }
      
      }
      
      else if ($p_notification == 'reporter_status') {
      
        $v_reporter = $this->getReporterByMac($p_args['mac_address']);
      
        foreach ($this->notification_queue as $v_item) {
          if (   ($v_item['notification_type'] == 'reporter_status')  ) {
            // ----- API Notify response
            if ($v_item['cb_type'] == 'ws_api') {
              $v_data = array();
              $v_data['type'] = 'reporter_status';
              $v_data['device_mac'] = $v_reporter->getMac();
              $v_data['status'] = $v_reporter->getStatus();     
              ArubaWssTool::log('debug', 'Trigger status :'.$v_reporter->getStatus());       
              $this->apiNotify_notification($v_item['cb_id'], $v_data, $v_item['cb_id']);
            }
          }
        }
      
      }
      
      else if ($p_notification == 'reporter_ble_status') {
      
        $v_reporter = $this->getReporterByMac($p_args['mac_address']);
      
        foreach ($this->notification_queue as $v_item) {
          if (   ($v_item['notification_type'] == 'reporter_ble_status') ) {
            // ----- API Notify response
            if ($v_item['cb_type'] == 'ws_api') {
              $v_data = array();
              $v_data['type'] = 'reporter_ble_status';
              $v_data['device_mac'] = $v_reporter->getMac();
              $v_data['status'] = $v_reporter->getConnectStatus();     
              ArubaWssTool::log('debug', 'Trigger BLE status :'.$v_reporter->getConnectStatus());       
              $this->apiNotify_notification($v_item['cb_id'], $v_data, $v_item['cb_id']);
            }
          }
        }
      
      }
      
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : stats()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function stats($v_type, $v_data) {

      switch ($v_type) {

        case 'raw_data':
      //ArubaWssTool::log('debug', "  ************************ raw_data : ".$v_data." bytes");
          $this->raw_data += $v_data;
        break;
        case 'data_payload':
      //ArubaWssTool::log('debug', "  ************************ data_payload : ".$v_data." bytes");
          $this->payload_data += $v_data;
        break;
        default;
      }


    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : getStats()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function getStats() {
      $v_result = array();

      $v_result['global'] = array();
      $v_result['global']['raw_data_total_size'] = $this->raw_data;
      $v_result['global']['msg_total_size'] = $this->payload_data;
      $v_result['global']['start_time'] = $this->up_time;
      $v_result['global']['uptime'] = ($this->up_time != 0 ? time() - $this->up_time : 0);

      $v_result['reporters'] = array();
      foreach ($this->reporters_list as $v_reporter) {
        $v_name = $v_reporter->getName();
        $v_result['reporters'][$v_name] = array();
        $v_result['reporters'][$v_name]['name'] = $v_reporter->getName();
        $v_result['reporters'][$v_name]['mac_address'] = $v_reporter->getMac();
        $v_result['reporters'][$v_name]['start_time'] = $v_reporter->getUptime();
        $v_result['reporters'][$v_name]['uptime'] = ($v_reporter->getUptime() != 0 ? time() - $v_reporter->getUptime() : 0);
        $v_result['reporters'][$v_name]['stats'] = $v_reporter->getStats();
      }

      return($v_result);
    }
    /* -------------------------------------------------------------------------*/


  }
  /* -------------------------------------------------------------------------*/




  /**---------------------------------------------------------------------------
   * Class : ArubaWssDevice
   * Description :
   *   This object is used to cache essentials informations regarding
   *   BKLE device, and avoid to reload all the data at each
   *   telemetry message.
   * ---------------------------------------------------------------------------
   */
  class ArubaWssDevice {
    protected $mac_address;
    protected $name = '';
    protected $date_created = 0;
    protected $enabled = true;
    
    // ----- Aruba Telemetry Classname
    protected $classname = 'auto';
    
    // ----- Device identification
    // ArubaWss internal specification
    protected $vendor_id = '';
    protected $model_id = '';
    
    // ----- Aruba BLE informations in beacons (received)
    protected $vendor_name = '';
    protected $local_name = '';
    protected $model = '';

    // ----- A flag which indicate the changed values
    // like change_flag['presence'], etc
    protected $change_flag = array();

    // ----- Storing data regarding the nearest AP for the device
    protected $nearest_ap_mac = '';
    protected $nearest_ap_rssi = -999;   // -110 should be the minimum, will be update at the first telemetry message
    protected $nearest_ap_last_seen = 0;
    
    // ----- Storing data regarding presence
    protected $presence = 0;
    protected $presence_last_seen = 0;
    
    // ----- Data to manage BLE connection to device
    protected $is_connectable = 'unknown';   // 'unknown', 'yes' -> when at least one connect success, 'no' -> no when ble connect never succeed.
    protected $is_discoverable = 'unknown';   // 'unknown', 'yes' -> when at least one characteristic full list received, 'no' -> no when no full charateristic received
    protected $connect_status = AWSS_STATUS_DISCONNECTED;  // AWSS_STATUS_CONNECTED, AWSS_STATUS_DISCONNECTED
    protected $connect_status_last_reason = '';
    // mac@ (ie id) of the latest nearest ap or the ble connected AP.
    // AP mac must be keep to maintain ble connect
    protected $connect_ap_mac = '';  

    // TBC : Depretated 
    protected $connect_action_id = '';
    protected $disconnect_action_id = '';
    
    // ----- Telemetry and Gatt informations
    protected $service_list = array();
    protected $telemetry_value_list = array();
    protected $battery_value = 101; // 101 means unknown
    protected $battery_timestamp = 0;
    protected $triangulation = array();


    /**---------------------------------------------------------------------------
     * Method : __construct()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function __construct($p_mac, $p_classname='auto') {
    
      // ----- Check and set device mac@
      $this->mac_address = filter_var(trim(strtoupper($p_mac)), FILTER_VALIDATE_MAC);      
      
      // ----- Set cretaion timestamp
      $this->date_created = time();
      
      // ----- Set classname
      $this->classname = $p_classname;

      // ----- Set default name
      $this->name = $p_classname.' '.$this->mac_address;

    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : createMe()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function createMe() {
      // To be overloaded
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : loadMe()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function loadMe() {
      // To be overloaded
      return(true);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : setPresence()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function setPresence($p_presence, $p_lastseen='') {
    
      // ----- Change and flag only if new value
      if ($this->presence != $p_presence) {
        $this->presence = ($p_presence == 1 ? 1: 0);
        $this->setChangedFlag('presence');
      }

      if ($p_lastseen != '') {
        $this->presence_last_seen = $p_lastseen;
      }
      else {
        $this->presence_last_seen = time();
      }
      
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : getMac()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function getMac() {
      return($this->mac_address);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : getNearestApMac()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function getNearestApMac() {
      return($this->nearest_ap_mac);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : getNearestApRssi()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function getNearestApRssi() {
      return($this->nearest_ap_rssi);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : getConnectApMac()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function getConnectApMac() {
      return($this->connect_ap_mac);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : getConnectAp()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function getConnectAp() {
      return(ArubaWssTool::getReporterByMac($this->connect_ap_mac));
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : reporterDisconnectNotification()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function reporterDisconnectNotification($p_mac) {
      // ----- Look to reset BLE connect status
      if ($p_mac == $this->connect_ap_mac) {
        $this->connect_status = AWSS_STATUS_DISCONNECTED;
        $this->connect_status_last_reason = '';
        $this->connect_ap_mac = '';
      }
      
      // ----- Look to reset nearest AP
      if ($p_mac == $this->nearest_ap_mac) {
        $this->nearest_ap_mac = '';
        $this->nearest_ap_rssi = -999;
        $this->nearest_ap_last_seen = 0;
      }
    }
    /* -------------------------------------------------------------------------*/



    /**---------------------------------------------------------------------------
     * Method : setConnectAp()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function checkAndSetConnectAp($p_ap) {
      if ($p_ap == '') {
        // ----- Nothing to do, ignore empty nearest AP, keep the last best known
      }
      else if ($this->getConnectApMac() == $p_ap) {
        // ----- Nothing to do, already the right AP
      }
      else if ($this->getConnectStatus() == AWSS_STATUS_DISCONNECTED) {
        // ----- Change AP to the nearest one
        $this->connect_ap_mac = $p_ap;
      }
      else {
        // ----- Inable to change AP to the nearest one, because ble_connect active or initiated with device
        ArubaWssTool::log('debug', "Unable to change connect ap while ble_connect is active.");
      }
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : setNearestAp()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function setNearestAp($p_ap, $p_rssi, $p_lastseen='') {
      // ----- Change flag only if new value
      if ($this->nearest_ap_mac != $p_ap) {
        $this->nearest_ap_mac = $p_ap;
        $this->setChangedFlag('nearest_ap');
        ArubaWssTool::log('debug', "Change nearest_ap of ".$this->name." to ".$p_ap." (rssi:".$p_rssi.")");
      }

      // ----- Change flag only if new value
      if ($this->nearest_ap_rssi != $p_rssi) {
        $this->nearest_ap_rssi = $p_rssi;
        $this->setChangedFlag('rssi');
        ArubaWssTool::log('debug', "Change rssi of ".$this->name." to ".$p_ap." (rssi:".$p_rssi.")");
      }

      if ($p_lastseen != '') {
        $this->nearest_ap_last_seen = $p_lastseen;
      }
      else {
        $this->nearest_ap_last_seen = time();
      }
      
      // ----- Look also to change the connect_ap
      $this->checkAndSetConnectAp($p_ap);
    }
    /* -------------------------------------------------------------------------*/

    public function getVendorName() {
      return($this->vendor_name);
    }
    public function getLocalName() {
      return($this->local_name);
    }
    public function getModel() {
      return($this->model);
    }
    public function getConnectStatus() {
      return($this->connect_status);
    }

    /**---------------------------------------------------------------------------
     * Method : getClassname()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function getClassname() {
      return($this->classname);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : setClassname()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function setClassname($p_classname) {
      $this->classname = $p_classname;
      return;
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : getName()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function getName() {
      return($this->name);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : setName()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function setName($p_name) {
      $this->name = $p_name;
      return;
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : setIsDiscoverable()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function setIsDiscoverable($p_value='yes') {
      if (!in_array($p_value, array('unknown','yes','no'))) {
        ArubaWssTool::log('debug', "Invalid value '".$p_value."' for setIsDiscoverable()");
        return;
      }
      $this->is_discoverable = $p_value;
      
      // ----- If discoverable then it is also connectable
      if ($p_value == 'yes') {
        $this->setIsConnectable();
      }
      return;
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : setIsConnectable()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function setIsConnectable($p_value='yes') {
      if (!in_array($p_value, array('unknown','yes','no'))) {
        ArubaWssTool::log('debug', "Invalid value '".$p_value."' for setIsConnectable()");
        return;
      }
      $this->is_connectable = $p_value;
      return;
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : isEnabled()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function isEnabled() {
      $v_result = $this->enabled;      
      return($v_result);
    }
    /* -------------------------------------------------------------------------*/
    
    /**---------------------------------------------------------------------------
     * Method : toArray()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function toArray($p_mode='') {
      $v_item = array();
      
      $v_item['mac'] = $this->getMac();
      $v_item['name'] = $this->getName();
      $v_item['classname'] = $this->getClassname();
      
      $v_item['vendor_id'] = $this->vendor_id;
      $v_item['model_id'] = $this->model_id;
      
      $v_item['nearest_ap_mac'] = $this->getNearestApMac();
      $v_item['rssi'] = $this->getNearestApRssi();
      $v_item['vendor_name'] = $this->getVendorName();
      $v_item['local_name'] = $this->getLocalName();
      $v_item['model'] = $this->getModel();
      $v_item['presence'] = $this->presence;
      
      $v_item['connect_status'] = $this->getConnectStatus();
      $v_item['is_connectable'] = $this->is_connectable;
      $v_item['is_discoverable'] = $this->is_discoverable;
      
      if ($p_mode == 'extended') {
        $v_item['services'] = $this->service_list;
        $v_item['telemetry_values'] = $this->telemetry_value_list;

        if ($this->battery_value != 101) {
          $v_item['battery'] = array();
          $v_item['battery']['value'] = $this->battery_value;
          $v_item['battery']['timestamp'] = $this->battery_timestamp;
        }        
      }
      
      return($v_item);
    }
    /* -------------------------------------------------------------------------*/
    
    /**---------------------------------------------------------------------------
     * Method : setDeviceClassFromRegex()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function setDeviceClassFromRegex() {
      if ($this->vendor_id != '') {
        return;
      }

      // ----- Set default value          
      $this->vendor_id = 'generic';
      $this->model_id = 'generic';
      
      // ----- Look for external regex file
      $v_filename = __DIR__."/awss/data/devices/class_regex.json";
      if (!file_exists($v_filename)) {
        ArubaWssTool::log('debug', "No customized regex file '".$v_filename."'");
        return;
      }
      
      // ----- Read file
      if (($v_handle = @fopen($v_filename, "r")) === null) {
        ArubaWssTool::log('error', "Fail to open regex file '".$v_filename."'");
        return;
      }
      $v_list_json = @fread($v_handle, filesize($v_filename));
      @fclose($v_handle);
      
      if (($v_list = json_decode($v_list_json, true)) === null) {
        ArubaWssTool::log('error', "Badly formatted JSON content in file '".$v_filename."'");
        return;
      }
      
      // ----- Look first for local name
      if (isset($v_list['local_name_regex'])) {
        ArubaWssTool::log('debug:6', 'local_name_regex='.print_r($v_list['local_name_regex'], true));
        
        // ----- Search by regex the right device
        foreach ($v_list['local_name_regex'] as $v_item) {
          if (preg_match($v_item['regex'], $this->local_name) === 1) {
            $this->vendor_id = $v_item['vendor_id'];
            $this->model_id = $v_item['model_id'];
            ArubaWssTool::log('debug', "vendor_id:'".$this->vendor_id."' and model_id:'".$this->model_id."' found by regex '".$v_item['regex']."'");
            return;
          }
        }
      }
      
      return;
    }
    /* -------------------------------------------------------------------------*/
    
    /**---------------------------------------------------------------------------
     * Method : setCharacteristic()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function setCharacteristic($p_service_uuid, $p_char_uuid, $p_char_types='', $p_description='') {
    
      // ----- Look if service exists
      if (!isset($this->service_list[$p_service_uuid])) {
        $this->service_list[$p_service_uuid] = array();
        $this->service_list[$p_service_uuid]['uuid'] = $p_service_uuid;
        $this->service_list[$p_service_uuid]['char_list'] = array();
      }
      
      // ----- Look if char exists
      if (!isset($this->service_list[$p_service_uuid]['char_list'][$p_char_uuid])) {
        $this->service_list[$p_service_uuid]['char_list'][$p_char_uuid] = array();
        $this->service_list[$p_service_uuid]['char_list'][$p_char_uuid]['uuid'] = $p_char_uuid;
        $this->service_list[$p_service_uuid]['char_list'][$p_char_uuid]['types'] = ($p_char_types==''?'read':$p_char_types);
        $this->service_list[$p_service_uuid]['char_list'][$p_char_uuid]['description'] = $p_description;
        $this->service_list[$p_service_uuid]['char_list'][$p_char_uuid]['value'] = NULL;
        $this->service_list[$p_service_uuid]['char_list'][$p_char_uuid]['value_string'] = '';
      }
      
      // ----- Update types and description if needed
      if (($p_char_types != '') && ($p_char_types != $this->service_list[$p_service_uuid]['char_list'][$p_char_uuid]['types'])) {
        $this->service_list[$p_service_uuid]['char_list'][$p_char_uuid]['types'] = $p_char_types;
      }
      if (($p_description != '') && ($p_description != $this->service_list[$p_service_uuid]['char_list'][$p_char_uuid]['description'])) {
        $this->service_list[$p_service_uuid]['char_list'][$p_char_uuid]['description'] = $p_description;
      }      
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : setCharacteristicValue()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function setCharacteristicValue($p_service_uuid, $p_char_uuid, $p_value) {
    
      // ----- Look if service exists and Update value (if any)
      if (   isset($this->service_list[$p_service_uuid]) 
          && isset($this->service_list[$p_service_uuid]['char_list'][$p_char_uuid])
          && ($p_value !== null)) {
        
        $this->service_list[$p_service_uuid]['char_list'][$p_char_uuid]['value'] = $p_value;
        
        // ----- Add a string value when needed
        $v_strtext = ArubaWssTool::stringbytesToText($p_value, true);
        if ($v_strtext != $p_value) {
          $this->service_list[$p_service_uuid]['char_list'][$p_char_uuid]['value_string'] = $v_strtext;
        }
        
        // ----- Set telemetry value from characteristic
        $this->setTelemetryFromCharacteristic($p_service_uuid, $p_char_uuid, $p_value);
      }
      else {
        ArubaWssTool::log('warning', "Service UUID '".$p_service_uuid."' and/or Characteristic UUID '".$p_char_uuid."' don't exist for device '".$this->mac_address."'.");
      }
            
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : setTelemetryFromCharacteristic()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function setTelemetryFromCharacteristic($p_service_uuid, $p_char_uuid, $p_value) {

      // ----- Result for telemety values extracted by custom php
      $v_telemetry_values = array();

      // ----- Include custom PHP code for this device model      
      $v_filename = __DIR__.'/awss/data/devices/'.$this->vendor_id.'/'.$this->model_id.'/'.$this->vendor_id.'_'.$this->model_id.'.char.php';
      if (($this->vendor_id != '') && ($this->vendor_id != '') && @is_file($v_filename)) {
        include($v_filename);
      }
      
      // ----- Set the telemetry values
      foreach ($v_telemetry_values as $v_telemetry) {
        $this->setTelemetryValue($v_telemetry['name'], $v_telemetry['value'], $v_telemetry['type']);
      }
      
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : setTelemetryFromAdvert()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function setTelemetryFromAdvert($p_value) {
    
      // ----- Result for telemety values extracted by custom php
      $v_telemetry_values = array();

      // ----- Include custom PHP code for this device model      
      $v_filename = __DIR__.'/awss/data/devices/'.$this->vendor_id.'/'.$this->model_id.'/'.$this->vendor_id.'_'.$this->model_id.'.adv.php';
      if (($this->vendor_id != '') && ($this->vendor_id != '') && @is_file($v_filename)) {
        include($v_filename);
      }
      
      // ----- Set the telemetry values
      foreach ($v_telemetry_values as $v_telemetry) {
        $this->setTelemetryValue($v_telemetry['name'], $v_telemetry['value'], $v_telemetry['type']);
      }

    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : changeConnectStatus()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function changeConnectStatus($p_status, $p_reason='') {
      ArubaWssTool::log('debug', "Current connect_status of device '".$this->mac_address."' is '".$this->connect_status."'");
      $v_result = true;
      
      if ($p_status == AWSS_STATUS_CONNECTED) {

        if ($this->connect_status == AWSS_STATUS_CONNECTED) {
          ArubaWssTool::log('debug', "Device already connected.");
          $v_result = false;
        }        
        else {
          $this->connect_status = AWSS_STATUS_CONNECTED;
          $v_result = true;
        }
      }

      else if ($p_status == AWSS_STATUS_DISCONNECTED) {
        if ($this->connect_status == AWSS_STATUS_DISCONNECTED) {
          ArubaWssTool::log('debug', "Device already disconnected.");
          $v_result = false;
        }        
        else {
          $this->connect_status = AWSS_STATUS_DISCONNECTED;
          $v_result = true;
        }       
      }
      
      else {
        ArubaWssTool::log('debug', "Unknown status : ".$p_status.".");
        $v_result = false;
      }
      
      // ----- Store last reason
      $this->connect_status_last_reason = $p_reason;
      
      // ----- Trigger notification
      if ($v_result) {
        ArubaWssTool::notification('device_status', ['mac_address'=>$this->mac_address, 
                                                     'status'=>$this->connect_status, 
                                                     'reason'=>$this->connect_status_last_reason]);      
      }

      ArubaWssTool::log('debug', "Change connect_status of device '".$this->mac_address."' to '".$this->connect_status."'");
      return($v_result);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : resetChangedFlag()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function resetChangedFlag() {
      unset($this->change_flag);
      $this->change_flag = array();
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : setChangedFlag()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function setChangedFlag($p_flag_type, $p_flag_name='') {
      // ----- Store the changed flag
      if ($p_flag_type != '') {
        if (!isset($this->change_flag[$p_flag_type])) {
          $this->change_flag[$p_flag_type] = array();
        }
        if ($p_flag_name != '') {
          $this->change_flag[$p_flag_type][$p_flag_name] = true;
        }
      }
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : setTelemetryValue()
     * Description :
     * Return Value :
     * ---------------------------------------------------------------------------
     */
    public function setTelemetryValue($p_name, $p_value, $p_type='') {
    
      // TBC : should check with regexp that name is valid ascii value for array index ?    
      
      // ----- Look for specific case of battery value
      if ($p_name == 'battery') {
        if (($p_value < 0) || ($p_value > 101)) {
          $p_value = 101; // means unknown
        }
        
        // ----- Flag only if new value
        if ($this->battery_value != $p_value) {
          $this->battery_value = $p_value;
          $this->setChangedFlag('battery');
        }
  
        $this->battery_timestamp = time();
        
        return;
      } 
      
      // ----- Look if no existing value for this name. Create one.
      if (!isset($this->telemetry_value_list[$p_name])) {
        $this->telemetry_value_list[$p_name] = array();
        $this->telemetry_value_list[$p_name]['name'] = $p_name;    
        $this->telemetry_value_list[$p_name]['type'] = $p_type;        

        // ----- Update value
        $this->telemetry_value_list[$p_name]['value'] = $p_value;

        // ----- Store last update timer      
        $this->telemetry_value_list[$p_name]['timestamp'] = time();        
      
        $this->setChangedFlag('telemetry_value', $p_name);
      }
      
      // ----- Already an existing telemetry entry with this name
      else {
        // ----- Look for type to update
        // TBC : I don't remember exactly why this is needed ...
        if (($p_type != '') && ($this->telemetry_value_list[$p_name]['type'] == '')) {
          $this->telemetry_value_list[$p_name]['type'] = $p_type;          
        }

        // ----- Look for no new value
        if ($this->telemetry_value_list[$p_name]['value'] == $p_value) {
          $v_telemetry_max_timestamp = ArubaWssTool::getConfig('telemetry_max_timestamp');
          
          // ----- Look if max timestamp reached 
          if (($this->telemetry_value_list[$p_name]['timestamp'] + $v_telemetry_max_timestamp) < time()) {
            // ----- Store last update timer      
            $this->telemetry_value_list[$p_name]['timestamp'] = time();
                    
            // ----- Flag as update to do
            $this->setChangedFlag('telemetry_value', $p_name);
          }
          else {
            ArubaWssTool::log('debug', "Same value for '".$p_name."',no need to store. ");
          }
        }
        // ----- Look for new value
        else {
          // ----- Update value
          $this->telemetry_value_list[$p_name]['value'] = $p_value;
  
          // ----- Store last update timer      
          $this->telemetry_value_list[$p_name]['timestamp'] = time();        
        
          $this->setChangedFlag('telemetry_value', $p_name);
        }

      }
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : setTelemetryBatteryValue()
     * Description :
     * Return Value :
     * ---------------------------------------------------------------------------
     */
    public function setTelemetryBatteryValue_DEPRECATED($p_value) {
    
      if (($p_value < 0) || ($p_value > 101)) {
        $p_value = 101; // means unknown
      }
      
      // ----- Flag only if new value
      if ($this->battery_value != $p_value) {
        $this->battery_value = $p_value;
        $this->setChangedFlag('battery');
      }

      $this->battery_timestamp = time();
    }
    /* -------------------------------------------------------------------------*/


    /**---------------------------------------------------------------------------
     * Method : updateNearestAP()
     * Description :
     * Return Value :
     *   True : if telemetry values are to be updated
     *   False : if telemetry values are to be ignored
     * ---------------------------------------------------------------------------
     */
    public function updateNearestAPNew(&$p_reporter, $p_timestamp, $p_rssi) {

      $v_debug_msg = '';
      
      ArubaWssTool::log('debug', "Look to update nearestAP with ".$p_reporter->getMac());
      
      $v_debug_msg .= $p_reporter->getName().":";

      // ----- Get presence configuration thresholds
      $v_timeout = ArubaWssTool::getConfig('presence_timeout');
      $v_presence_min_rssi = ArubaWssTool::getConfig('presence_min_rssi');
      $v_presence_rssi_hysteresis = ArubaWssTool::getConfig('presence_rssi_hysteresis');

      // ----- Get last seen (if any)
      $v_lastseen = $p_timestamp;
      ArubaWssTool::log('debug', "LastSeen is : ".$v_lastseen." (".date("Y-m-d H:i:s", $v_lastseen).")");

      $v_debug_msg .= "LastSeen:".date("Y-m-d H:i:s", $v_lastseen)."(".$v_lastseen.")".":";

      // ----- Get RSSI (if any)
      $v_rssi = $p_rssi;

      $v_debug_msg .= "RSSI:".$v_rssi."";

      // ----- Look for too old data for updating presence
      // Even if this is a better RSSI the timestamp is too old for the presence flag.
      if ( (($v_lastseen + $v_timeout) < time())) {
        ArubaWssTool::log('debug', "LastSeen value from this AP is already older than presence timeout. Skip presence update.");
      }
      else {
        // ----- Look if last seen timestamp is better than current one
        // if not then this is an old telemetry data compare to others previously 
        // received (by same AP or other AP)
        if ($this->presence_last_seen < $v_lastseen) {
          // ----- Look if RSSI is not too far to be "present"
          if (($v_rssi < $v_presence_min_rssi) && ($this->presence == 0)) {
            ArubaWssTool::log('debug', "RSSI (".$v_rssi.") is not enought to change from absence to presence.");
          }
          else if (($v_rssi < ($v_presence_min_rssi-$v_presence_rssi_hysteresis)) && ($this->presence == 1)) {
            ArubaWssTool::log('debug', "RSSI (".$v_rssi.") is not enought to update presence.");
          }
          else {
            $this->setPresence(1, $v_lastseen);
          }
        }
        else {
          ArubaWssTool::log('debug', "Presence : received lastseen value in telemetry is older than a previous one.");
        }
      }

      $v_debug_msg .= ' ->'.($this->presence?'present':'absent')."";

      // ----- Look if this is the current best reporter (nearest ap)
      if ($this->getNearestApMac() == $p_reporter->getMac()) {
        ArubaWssTool::log('debug', "Reporter '".$p_reporter->getMac()."' is the current nearest reporter. Update last seen value");

        // ----- No change in last seen value => repeated old value ...
        // No : in fact we can receive 2 payloads with the same timestamp (in sec) with different values
        // exemple is the switch up-idle-bottom values
        /*
        if ($this->nearest_ap_last_seen == $v_lastseen) {
          ArubaWssTool::log('debug', "New last seen value is the same : repeated old telemetry data. Skip telemetry data.");
          return(false);
        }
        */

        // ----- Should never occur ...
        if ($this->nearest_ap_last_seen > $v_lastseen) {
          ArubaWssTool::log('error', "New last seen value is older than previous one ! Should never occur. Skip telemetry data.");
          return(false);
        }

        // ----- Update latest RSSI.
        //  if no RSSI, keep the old one ... ?
        //  an object should always send an RSSI or never send an RSSI.
        $this->setNearestAp($this->getNearestApMac(), $v_rssi, $v_lastseen);

        return(true);
      }
      
      // ----- Now look the case when the AP is not the current nearest AP

      $swap_ap_flag = false;

      // ----- Look for no current nearest AP
      if ($this->getNearestApMac() == '') {
        ArubaWssTool::log('debug', "No existing nearest reporter.");
        $swap_ap_flag = true;
      }

      // ----- Look if new reporter has a better RSSI than current nearest
      $v_nearest_ap_hysteresis = ArubaWssTool::getConfig('nearest_ap_hysteresis');
      if (!$swap_ap_flag && ($v_rssi != -110) && ($v_rssi > ($this->nearest_ap_rssi + $v_nearest_ap_hysteresis))) {
        ArubaWssTool::log('debug', "Swap for a new nearest AP with better RSSI, from '".$this->getNearestApMac()."' (RSSI '".$this->nearest_ap_rssi."') to '".$p_reporter->getMac()."' (RSSI '".$v_rssi."')");
        $swap_ap_flag = true;
      }

      /*
      // ----- Look if current reporter has a very long last_seen value
      $v_nearest_ap_timeout = ArubaIotConfig::byKey('nearest_ap_timeout', 'ArubaIot');
      if (!$swap_ap_flag && (($this->nearest_ap_last_seen + $v_nearest_ap_timeout) < time())) {
        ArubaWssTool::log('debug', "Swap for a new nearest AP with better last-seen value, from '".$this->getNearestApMac()."' (".date("Y-m-d H:i:s", $this->nearest_ap_last_seen).") to '".$p_reporter->getMac()."' (".date("Y-m-d H:i:s", $v_lastseen).").");
        $swap_ap_flag = true;
      }
      */

      // ----- Look if rssi lower than the minimum to swap
      if ($swap_ap_flag) {
        $v_nearest_ap_min_rssi = ArubaWssTool::getConfig('nearest_ap_min_rssi');

        if ($v_rssi < $v_nearest_ap_min_rssi) {
          ArubaWssTool::log('debug', "RSSI (".$v_rssi.") is not enought to become a nearestAP.");
          $swap_ap_flag = false;
        }
      }

      // ----- Look if swap to new AP is to be done
      if ($swap_ap_flag) {
        ArubaWssTool::log('debug', "Swap for new nearest reporter '".$p_reporter->getMac()."'");

        // ----- Swap for new nearest AP
        $this->setNearestAp($p_reporter->getMac(), $v_rssi, $v_lastseen);

        return(true);
      }

      // ----- Compare new AP lastseen to nearestAP timestamp
      // Update timer only if already in present state ?

      ArubaWssTool::log('debug', "Reporter '".$p_reporter->getMac()."' not a new nearest reporter compared to current '".$this->getNearestApMac()."'. Skip telemetry data.");

      return(false);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : updateNearestAP()
     * Description :
     * Return Value :
     *   True : if telemetry values are to be updated
     *   False : if telemetry values are to be ignored
     * ---------------------------------------------------------------------------
     */
    public function updateNearestAP(&$p_reporter, $p_telemetry) {

      //ArubaWssTool::log('debug', "Look to update nearestAP with ".$p_reporter->getMac());
      
      // ----- Get last seen (if any)
      $v_lastseen = 0;
      if ($p_telemetry->hasLastSeen()) {
        $v_lastseen = $p_telemetry->getLastSeen();
      }
      else {
        // Should not occur ... I not yet seen an object without this value ...
        ArubaWssTool::log('debug', "LastSeen is missing in telemetry data. Skip presence & nearestAP update.");
        return(false);
      }

      // ----- Get RSSI (if any)
      $v_rssi = -110;
      if ($p_telemetry->hasRSSI()) {
        $v_val = explode(':', $p_telemetry->getRSSI());
        $v_rssi = (isset($v_val[1]) ? intval($v_val[1]) : $v_rssi);
      }
      else {
        // Il semble que lorsque l'IAP ne reçoit plus de beacons, il continu
        // à envoyer un message de télémetry avec comme date la dernière fois
        // qu'il a vu le beacon, mais plus de valeur de RSSI.
        $v_rssi = -110;
      }
      return($this->updateNearestAPNew($p_reporter, $v_lastseen, $v_rssi));
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : updateAbsence()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function updateAbsence() {

      ArubaWssTool::log('debug', "Check Absence for ".$this->mac_address);

      // ----- Look if already absent
      if ($this->presence == 0) {
        ArubaWssTool::log('debug', "-> Already with absence flag.");
        return;
      }

      // ----- Look if device is ble connected (which means must be present)
      if ($this->getConnectStatus() != AWSS_STATUS_DISCONNECTED) {
        ArubaWssTool::log('debug', "-> Device present, because BLE Connected.");
        return;
      }

      $v_timeout = ArubaWssTool::getConfig('presence_timeout');

      $v_absent = true;

      ArubaWssTool::log('debug', "Check Absence for ".$this->mac_address.":Last-seen:".$this->presence_last_seen.",timeout:".$v_timeout."',current time:".time()."");

      if (($this->presence_last_seen+$v_timeout) > time() ) {
          $v_absent = false;
      }

      // ----- Update presence cmd
      if ($v_absent) {
        $this->resetChangedFlag();

        // ----- Reset nearestAP
        $this->setNearestAp('', -110, 0);
        
        // ----- Reset presence
        $this->setPresence(0);

        ArubaWssTool::log('debug', "--> Presence flag is : missing");
        
        // ----- This is a call to a wrapper function for extnsions
        $this->doActionIfModified('presence');
      }
      else {
        ArubaWssTool::log('debug', "--> Presence flag is : presence");
      }

      return;
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : updateTriangulation()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function updateTriangulation(&$p_reporter, $p_telemetry) {

      // ----- Get RSSI value if any
      $v_rssi = -110;
      if ($p_telemetry->hasRSSI()) {
        $v_val = explode(':', $p_telemetry->getRSSI());
        $v_rssi = (isset($v_val[1]) ? intval($v_val[1]) : 0);
      }
      if ($v_rssi != -110) {
        ArubaWssTool::log('debug', "--> Update Triangulation data for ".$p_reporter->getMac()." RSSI : ".$v_rssi);
        
        // ----- Look for existing triangulation value for this reporter
        $v_reporter_mac = $p_reporter->getMac();
        if (!isset($this->triangulation[$v_reporter_mac])) {
          $this->triangulation[$v_reporter_mac] = array();
          $this->triangulation[$v_reporter_mac]['reporter_mac'] = $v_reporter_mac;
        }
        
        // ----- Update triangulation value
        $this->triangulation[$v_reporter_mac]['rssi'] = $v_rssi;
        $this->triangulation[$v_reporter_mac]['timestamp'] = $p_reporter->getLastSeen();
      }

      return;
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : updateInputsTelemetry()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function updateInputsTelemetry($p_telemetry) {

      ArubaWssTool::log('debug', "Update Inputs Telemetry");

      $v_inputs = $p_telemetry->getInputs();

      // ----- Look for rocket list
      if ($v_inputs->hasRockerList()) {
        ArubaWssTool::log('debug', "Update Switch Rocker Telemetry");
        $v_rocker_list = $v_inputs->getRockerList();
        $i = 1;
        foreach ($v_rocker_list as $v_rocker) {
          $v_id = $v_rocker->getId();
          $v_state = $v_rocker->getState();
          ArubaWssTool::log('debug', "Rocker id: '".$v_id."', state: ".$v_state);

          // ----- Look for Enocean case, where state is in the id ...
          // "switch bank 1: idle"
          if ((strstr($v_id, 'switch bank 1:') !== null) || (strstr($v_id, 'switch bank 2:') !== null)) {
            $v_val = explode(':', $v_id);
            ArubaWssTool::log('debug', "Rocker real id: '".$v_val[0]."', real state: ".trim($v_val[1]));
            //$v_cmd_id = str_replace(' ', '_', trim($v_val[0]));
            $v_cmd_id = str_replace('switch bank ', 'button_', trim($v_val[0]));
            $v_cmd_name = str_replace('switch bank', 'Button', trim($v_val[0]));

            $this->setTelemetryValue($v_cmd_id, trim($v_val[1]), 'string');
            //$this->setChangedFlag('telemetry_value');
          }
          else {
            $this->setTelemetryValue($v_id, $v_state, 'string');
            //$this->setChangedFlag('telemetry_value');
          }
          $i++;
        }
      }

      // ----- Look for switch list
      if ($v_inputs->hasSwitchIndexList()) {
        ArubaWssTool::log('debug', "Update Switch Index Telemetry");
        // TBC : strange to be a list, then multiple ids ... or list should all the time be in same order ... ?
        $v_switch_list = $v_inputs->getSwitchIndexList();
        foreach ($v_switch_list as $v_switch) {
          // ----- Its an enum so value should be direct like that ...
          $v_state = $v_switch->value();
          ArubaWssTool::log('debug', "Switch value: ".$v_state);

          $this->setTelemetryValue('switch', $v_state, 'string');
          //$this->setChangedFlag('telemetry_value');
        }
      }

      return;
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : updateSensorTelemetry()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function updateSensorTelemetry($p_telemetry) {

      ArubaWssTool::log('debug', "Update Sensor Telemetry data");

      // ----- Look for sensor telemetry value
      if ($p_telemetry->hasSensors()) {

        $v_item = $p_telemetry->getSensors();

        // ----- Look for illumination values
        if ($v_item->hasIllumination()) {
          $v_val = $v_item->getIllumination();
          ArubaWssTool::log('debug', "Illumination value is : ".$v_val);

          $this->setTelemetryValue('illumination', $v_val);
          //$this->setChangedFlag('telemetry_value');
        }

        // ----- Look for occupancy values
        if ($v_item->hasOccupancy()) {
          $v_level = $v_item->getOccupancy()->getLevel();
          ArubaWssTool::log('debug', "Occupancy value is : ".$v_level);

          $this->setTelemetryValue('occupancy', $v_level);
          //$this->setChangedFlag('telemetry_value');
        }

        if ($v_item->hasTemperatureC()) {
          $v_val = $v_item->getTemperatureC();
          ArubaWssTool::log('debug', "TemperatureC value is : ".$v_val);

          $this->setTelemetryValue('temperatureC', $v_val);
          //$this->setChangedFlag('telemetry_value');
        }

        if ($v_item->hasHumidity()) {
          $v_val = $v_item->getHumidity();
          ArubaWssTool::log('debug', "Humidity value is : ".$v_val);

          $this->setTelemetryValue('humidity', $v_val);
          //$this->setChangedFlag('telemetry_value');
        }

        if ($v_item->hasVoltage()) {
          $v_val = $v_item->getVoltage();
          ArubaWssTool::log('debug', "Voltage value is : ".$v_val);

          $this->setTelemetryValue('voltage', $v_val);
          //$this->setChangedFlag('telemetry_value');
        }

        if ($v_item->hasCO()) {
          $v_val = $v_item->getCO();
          ArubaWssTool::log('debug', "CO value is : ".$v_val);

          $this->setTelemetryValue('CO', $v_val);
          //$this->setChangedFlag('telemetry_value');
        }

        if ($v_item->hasCO2()) {
          $v_val = $v_item->getCO2();
          ArubaWssTool::log('debug', "CO2 value is : ".$v_val);

          $this->setTelemetryValue('CO2', $v_val);
          //$this->setChangedFlag('telemetry_value');
        }

        if ($v_item->hasVOC()) {
          $v_val = $v_item->getVOC();
          ArubaWssTool::log('debug', "VOC value is : ".$v_val);

          $this->setTelemetryValue('VOC', $v_val);
          //$this->setChangedFlag('telemetry_value');
        }

        if ($v_item->hasMotion()) {
          $v_val = $v_item->getMotion();
          ArubaWssTool::log('debug', "Motion value is : ".$v_val);

          $this->setTelemetryValue('motion', $v_val);
          //$this->setChangedFlag('telemetry_value');
        }

        if ($v_item->hasResistance()) {
          $v_val = $v_item->getResistance();
          ArubaWssTool::log('debug', "Resistance value is : ".$v_val);

          $this->setTelemetryValue('resistance', $v_val);
          //$this->setChangedFlag('telemetry_value');
        }

        if ($v_item->hasPressure()) {
          $v_val = $v_item->getPressure();
          ArubaWssTool::log('debug', "Pressure value is : ".$v_val);

          $this->setTelemetryValue('pressure', $v_val);
          //$this->setChangedFlag('telemetry_value');
        }

        // ----- Update battery level
        if ($v_item->hasBattery()) {
          ArubaWssTool::log('debug', "Battery value is : ".$v_item->getBattery());
          //$this->setTelemetryBatteryValue($v_item->getBattery());
          $this->setTelemetryValue('battery', $v_item->getBattery());
        }

        // ----- For future use :
        if ($v_item->hasCurrent()) {
          ArubaWssTool::log('debug', "Field hasCurrent() available. For future use.");
        }
        if ($v_item->hasDistance()) {
          ArubaWssTool::log('debug', "Field hasDistance() available. For future use.");
        }
        if ($v_item->hasMechanicalHandle()) {
          ArubaWssTool::log('debug', "Field hasMechanicalHandle() available. For future use.");
        }
        if ($v_item->hasCapacitance()) {
          ArubaWssTool::log('debug', "Field hasCapacitance() available. For future use.");
        }

      }

      return;
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : updateObjectClass()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function updateObjectClass($p_telemetry, $p_class_name) {
        
      if ($this->classname == 'auto') {
        $this->classname = $p_class_name;
        $this->setChangedFlag('classname');
        ArubaWssTool::log('debug', "Change classname to '".$this->classname."' for device '".$this->getMac()."' ");
      }
      else if ($this->classname != $p_class_name) {
        ArubaWssTool::log('debug', "Device '".$this->getMac()."' is announcing type '".$p_class_name."', when type '".$this->classname."' is expected. Skip telemetry data.");
        return(0);
      }
     
      if (($this->vendor_name == '') && ($p_telemetry->hasVendorName())) {
        $this->vendor_name = $p_telemetry->getVendorName();
        $this->setChangedFlag('vendor_name');
        ArubaWssTool::log('debug', "Change vendor_name to '".$this->vendor_name."' for device '".$this->getMac()."' ");
      }
      if (($this->local_name == '') && ($p_telemetry->hasLocalName())) {
        $this->local_name = $p_telemetry->getLocalName();
        $this->setChangedFlag('local_name');
        ArubaWssTool::log('debug', "Change local_name to '".$this->local_name."' for device '".$this->getMac()."' ");
      }
      if (($this->model == '') && ($p_telemetry->hasModel())) {
        $this->model = $p_telemetry->getModel();
        $this->setChangedFlag('model');
        ArubaWssTool::log('debug', "Change model to '".$this->model."' for device '".$this->getMac()."' ");
      }
      
      if ($this->vendor_id == '') {
        if ($this->classname == 'generic') {
          // TBC
          $this->setDeviceClassFromRegex();
          /*
          if ($this->local_name == 'Jinou_Sensor_HumiTemp') {
            $this->vendor_id = 'Jinou';
            $this->model_id = 'Sensor_HumiTemp';
          }
          else if (preg_match('/ATC_[0-9,A-F]{6}$/', $this->local_name) === 1) {
            $this->vendor_id = 'ATC';
            $this->model_id = 'LYWSD03MMC';
          }
          */
        }
        else {
          // ----- Look to find exact vendor and device model
          // ----- First look from Aruba classname
          $v_value = ArubaWssTool::arubaClassToVendor($p_class_name);
          $this->vendor_id = $v_value['vendor_id'];
          $this->model_id = $v_value['model_id'];
        }
      }

      return(1);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : updateTelemetryData()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function updateTelemetryData(&$p_reporter, $p_telemetry, $p_class_name) {

      $v_changed_flag = false;

      // ----- Update SwitchRocker info
      if ($p_telemetry->hasInputs()) {
        $this->updateInputsTelemetry($p_telemetry);
      }

      // ----- Update sensors telemetry data
      if ($p_telemetry->hasSensors()) {
        $this->updateSensorTelemetry($p_telemetry);
      }


      // ----- Look for hasTxpower() : Nothing to do now, but for future use
      if ($p_telemetry->hasTxpower()) {
        $v_val = $p_telemetry->getTxpower();
        ArubaWssTool::log('debug', "Txpower value is : ".$v_val);

        $this->setTelemetryValue('txpower', $v_val);
        //$this->setChangedFlag('telemetry_value');
      }

      // ----- Look for hasCell() : Nothing to do now, but for future use
      if ($p_telemetry->hasCell()) {
        ArubaWssTool::log('debug', "This device has hasCell info.");
      }

      // ----- Look for hasStats() : Nothing to do now, but for future use
      if ($p_telemetry->hasStats()) {
        ArubaWssTool::log('debug', "This device has hasStats info.");
      }

      // ----- Look for hasIdentity() : Nothing to do now, but for future use
      if ($p_telemetry->hasIdentity()) {
        ArubaWssTool::log('debug', "This device has hasIdentity info.");
      }

      // ----- Look for vendor data : Nothing to do now, but for future use
      if ($p_telemetry->hasVendorData()) {
        ArubaWssTool::log('debug', "This device has vendor data of size ".sizeof($p_telemetry->getVendorData()));
      }

      return(true);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : doActionIfModified()
     * Description :
     * Parameters :
     *   $p_type : comme separated list of telemetry values to take into 
     *   account for modification. Will trigger an action only if these values 
     *   were cahnged.
     * ---------------------------------------------------------------------------
     */
    public function doActionIfModified($p_type='') {
    
      ArubaWssTool::log('debug', "Call doActionIfModified() for device '".$this->mac_address."'.");
      
      // ----- TBC : Manage callbacks to websocket API client or any other needed callbacks
    
      // ----- Trigger external plugin actions (if any)
      $this->doPostActionTelemetry($p_type);
      
      // ----- Reset all changed flags
      $this->resetChangedFlag();
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : doPostActionTelemetry()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function doPostActionTelemetry($p_type='') {
      // looking at modification flags, do third party actions
    }
    /* -------------------------------------------------------------------------*/

  }
  /* -------------------------------------------------------------------------*/

  /**---------------------------------------------------------------------------
   * Class : ArubaWssReporter
   * Description :
   * ---------------------------------------------------------------------------
   */
  class ArubaWssReporter {
    protected $mac_address;
    protected $status;
    protected $name;
    protected $remote_ip;
    protected $local_ip;
    protected $hardware_type;
    protected $software_version;
    protected $software_build;
    
    // ----- Timestamps
    protected $date_created;
    protected $lastseen;

    // ----- Cnx id depending of the types, empty if no cnx    
    protected $connection_id_ble;
    protected $connection_id_serial;
    protected $connection_id_rtls;
    protected $connection_id_zigbee;

    // ----- Store BLE connection status
    // Only one device at a time can be ble_connected
    protected $ble_connect_status = AWSS_STATUS_DISCONNECTED;
    protected $ble_connect_mac = '';

    // ----- Stats data
    protected $stat_telemetry_payload_sum;
    protected $stat_telemetry_msg_count;
    protected $stat_telemetry_payload_max;
    protected $stat_telemetry_payload_min;
    protected $stat_msg_rate_average;
    protected $stat_msg_rate_sum;
    protected $stat_msg_rate_min;
    protected $stat_msg_rate_max;

    public function __construct($p_mac) {
      $this->mac_address = filter_var(trim(strtoupper($p_mac)), FILTER_VALIDATE_MAC);
      $this->connection_id_ble = '';
      $this->connection_id_serial = '';
      $this->connection_id_rtls = '';
      $this->connection_id_zigbee = '';
      
      $this->status = 'inactive';    // active:an active cnx, inactive: no active cnx
      $this->name = '';
      $this->remote_ip = '';
      $this->local_ip = '';
      $this->hardware_type = '';
      $this->software_version = '';
      $this->software_build = '';
      $this->date_created = time();
      $this->lastseen = 0;

      $this->stat_telemetry_payload_sum = 0;
      $this->stat_telemetry_msg_count = 0;
      $this->stat_telemetry_payload_max = 0;
      $this->stat_telemetry_payload_min = 0;

      $this->stat_msg_rate_average = 0;
      $this->stat_msg_rate_sum = 0;
      $this->stat_msg_rate_min = 0;
      $this->stat_msg_rate_max = 0;
    }


    public function setStatus($p_status) {
      if (!in_array($p_status, ['active','inactive'])) {
        ArubaWssTool::log('debug', "Bug : invalid status value '".$p_status."' in ArubaWssReporter::setStatus()");
      }
      
      if ($p_status != $this->status) {
        $this->status = $p_status;

        ArubaWssTool::notification('reporter_status', ['mac_address'=>$this->mac_address, 
                                                       'status'=>$this->status]);      
      }
    }

    public function getStatus() {
      return($this->status);
    }

    public function setName($p_name) {
      $this->name = $p_name;
    }

    public function getName() {
      return($this->name);
    }

    public function setMac($p_mac) {
      $this->mac_address = filter_var(trim(strtoupper($p_mac)), FILTER_VALIDATE_MAC);
    }

    public function getMac() {
      return($this->mac_address);
    }

    public function getConnectStatus() {
      return($this->ble_connect_status);
    }

    public function getConnectionIdBle() {
      return($this->connection_id_ble);
    }

    public function getConnectionIdRtls() {
      return($this->connection_id_rtls);
    }

    public function getConnectionIdSerial() {
      return($this->connection_id_serial);
    }

    public function getConnectionIdZigbee() {
      return($this->connection_id_zigbee);
    }

    public function isConnectedWith($p_cnx_id) {
      if (    ($this->connection_id_ble == $p_cnx_id) 
           || ($this->connection_id_rtls == $p_cnx_id) 
           || ($this->connection_id_serial == $p_cnx_id) 
           || ($this->connection_id_zigbee == $p_cnx_id) ) {
        return(true);
      }
      
      return(false);      
    }

    public function setLocalIp($p_ip) {
      $this->local_ip = filter_var(trim($p_ip), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    public function getLocalIp() {
      return($this->local_ip);
    }

    public function setRemoteIp($p_ip) {
      $this->remote_ip = filter_var(trim($p_ip), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    public function getRemoteIp() {
      return($this->remote_ip);
    }

    public function setHardwareType($p_hard) {
      $this->hardware_type = $p_hard;
    }

    public function getHardwareType() {
      return($this->hardware_type);
    }

    public function setSoftwareVersion($p_soft) {
      $this->software_version = $p_soft;
    }

    public function getSoftwareVersion() {
      return($this->software_version);
    }

    public function setSoftwareBuild($p_soft) {
      $this->software_build = $p_soft;
    }

    public function getSoftwareBuild() {
      return($this->software_build);
    }

    public function setLastSeen($p_time) {
      // ----- stats
      if ($this->lastseen != 0) {
        $v_rate = $p_time - $this->lastseen;
        $this->stats('msg_rate', $v_rate);
      }

      // ----- Update
      $this->lastseen = $p_time;
    }

    public function getLastSeen() {
      return($this->lastseen);
    }

    public function getUptime() {
      return($this->date_created);
    }


    /**---------------------------------------------------------------------------
     * Method : isAvailableToConnectWith()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function isAvailableToConnectWith($p_device_mac='') {
      if ($this->ble_connect_status == AWSS_STATUS_DISCONNECTED) {
        return(true);
      }
      else if (($this->ble_connect_status == AWSS_STATUS_CONNECTED) && ($p_device_mac == $this->ble_connect_mac)) {
        return(true);
      }
      else {
        return(false);
      }
    }
    /* -------------------------------------------------------------------------*/


    /**---------------------------------------------------------------------------
     * Method : changeConnectStatus()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function changeConnectStatus($p_status, $p_device_mac) {
      ArubaWssTool::log('debug', "Current connect_status of reporter '".$this->mac_address."' is '".$this->ble_connect_status."', check for '".$p_status."'");
      $v_result = true;
      
      if (($this->ble_connect_status == AWSS_STATUS_CONNECTED) && ($p_status == AWSS_STATUS_CONNECTED)) {
        if (($p_device_mac == '') || ($this->ble_connect_mac == '')) {
          ArubaWssTool::log('error', "For AWSS_STATUS_CONNECTED device can't be empty.");
          return(false);
        }
        else if ($p_device_mac == $this->ble_connect_mac) {
          ArubaWssTool::log('debug', "Reporter already ble_connected with this device (".$this->ble_connect_mac.").");
          return(false);
        }
        else {
          ArubaWssTool::log('debug', "Reporter already ble_connected with another device (".$this->ble_connect_mac.").");
          // This case should not occur in normal situation ... this is an unknown state
          // ----- Disconnect already connected device
          $v_device = ArubaWssTool::getDeviceByMac($this->ble_connect_mac);
          if ($v_device !== null) {
            // ----- Change BLE connect status of the device
            // Will return false if the status is already the same
            $v_device->changeConnectStatus(AWSS_STATUS_DISCONNECTED, 'Force disconnect. Sticky device ??');
          }          
          ArubaWssTool::log('debug', "Change reporter ble_connected status to ".$p_status." for device '".$p_device_mac."'.");
          $this->ble_connect_status = AWSS_STATUS_CONNECTED;
          $this->ble_connect_mac = $p_device_mac;
          return(true);
        }
      }
      
      
      else if (($this->ble_connect_status == AWSS_STATUS_CONNECTED) && ($p_status == AWSS_STATUS_DISCONNECTED)) {
        if ($p_device_mac == $this->ble_connect_mac) {
          ArubaWssTool::log('debug', "Change reporter ble_connected status to ".$p_status.".");
          $this->ble_connect_status = AWSS_STATUS_DISCONNECTED;
          $this->ble_connect_mac = '';
          return(true);
        }
        else if ($p_device_mac == '') {
          // trigger the dveice disconnect
          if ($this->ble_connect_mac != '') {
            $v_device = ArubaWssTool::getDeviceByMac($this->ble_connect_mac);
            if ($v_device !== null) {
              // ----- Change BLE connect status of the device
              // Will return false if the status is already the same
              $v_device->changeConnectStatus($p_status, '');
            }          
          }
          
          // ----- Specific case : with empty mac it is a force disconnect
          ArubaWssTool::log('debug', "Force reporter ble_connected status to ".$p_status.".");
          $this->ble_connect_status = AWSS_STATUS_DISCONNECTED;
          $this->ble_connect_mac = '';
          return(true);
        }
        else {
          // ----- Device already connected nothing to change
          ArubaWssTool::log('debug', "Reporter ble_connected with another device '".$this->ble_connect_mac."', receive disconnect for '".$p_device_mac."'.");
          return(false);
        }
      }
      
      
      else if (($this->ble_connect_status == AWSS_STATUS_DISCONNECTED) && ($p_status == AWSS_STATUS_DISCONNECTED)) {
        ArubaWssTool::log('debug', "Reporter ble_connected already in '".$p_status."'.");
        $this->ble_connect_mac = '';  // for sanity check, should already by ''
        return(false);
      }
      
      
      else if (($this->ble_connect_status == AWSS_STATUS_DISCONNECTED) && ($p_status == AWSS_STATUS_CONNECTED)) {
        if ($p_device_mac == '') {
          ArubaWssTool::log('debug', "To change reporter ble_connected status to ".$p_status." not empty device mac expected.");
          return(false);
        }
        else {
          ArubaWssTool::log('debug', "Change reporter ble_connected status to ".$p_status." for device '".$p_device_mac."'.");
          $this->ble_connect_status = AWSS_STATUS_CONNECTED;
          $this->ble_connect_mac = $p_device_mac;
          return(true);
        }
      }
      
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : toArray()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function toArray($p_mode='') {
      $v_item = array();
      
      $v_item['mac'] = $this->getMac();
      $v_item['name'] = $this->getName();
      
      $v_item['status'] = $this->status;
      $v_item['remote_ip'] = $this->remote_ip;
      $v_item['local_ip'] = $this->local_ip;
      $v_item['hardware_type'] = $this->hardware_type;
      $v_item['software_version'] = $this->software_version;
      $v_item['software_build'] = $this->software_build;
      $v_item['uptime'] = date("Y-m-d H:i:s", $this->date_created);
                
      if ($p_mode == 'extended') {
        $v_item['stats'] = $this->getStats();
      }
      
      return($v_item);
    }
    /* -------------------------------------------------------------------------*/
    
    /**---------------------------------------------------------------------------
     * Method : hasTelemetryCnx()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function hasTelemetryCnx() {
      return(($this->connection_id_ble != ''?true:false));
    }

    /**---------------------------------------------------------------------------
     * Method : hasRtlsCnx()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function hasRtlsCnx() {
      return(($this->connection_id_rtls != ''?true:false));
    }

    /**---------------------------------------------------------------------------
     * Method : hasSerialCnx()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function hasSerialCnx() {
      return(($this->connection_id_serial != ''?true:false));
    }

    /**---------------------------------------------------------------------------
     * Method : hasZigbeeCnx()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function hasZigbeeCnx() {
      return(($this->connection_id_zigbee != ''?true:false));
    }

    /**---------------------------------------------------------------------------
     * Method : disconnect()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function disconnect(&$p_connection) {
      $v_id = $p_connection->my_id;
      if ($v_id == '') {
        ArubaWssTool::log('debug', "Invalid empty id for connection. Failed to attach connection to reporter.");
        return(false);
      }
            
      ArubaWssTool::log('debug', "Removing connection '".$v_id."' for reporter '".$this->mac_address."'");
      ArubaWssTool::log('warning', "Reporter '".$this->name."' (".$this->mac_address."), lost connection");
      $this->setRemoteIp('');
      
      // ----- Look for BLE Telemetry cnx
      if ($this->connection_id_ble == $v_id) {
        $this->connection_id_ble = '';
        
        // ----- Reset parameters (to clean the situation if needed)
        $this->ble_connect_status = AWSS_STATUS_DISCONNECTED;
        $this->ble_connect_mac = '';
      }

      // ----- Look for WiFi RTLS cnx
      if ($this->connection_id_rtls == $v_id) {
        $this->connection_id_rtls = '';
      }

      // ----- Look for SerialData cnx
      if ($this->connection_id_serial == $v_id) {
        $this->connection_id_serial = '';
      }

      // ----- Look for Zigbee cnx
      if ($this->connection_id_zigbee == $v_id) {
        $this->connection_id_zigbee = '';
      }
      
      else {
        // Unknown type
      }
            
      // ----- Look for last cnx in the list - means reporter becomes inactive
      if (    ($this->connection_id_ble == '')
           && ($this->connection_id_rtls == '')
           && ($this->connection_id_serial == '')
           && ($this->connection_id_zigbee == '') ) {
        $this->setStatus('inactive');
      }
      return(true);

    }
    /* -------------------------------------------------------------------------*/


    /**---------------------------------------------------------------------------
     * Method : connect()
     * Description :
     *   This method is called to link a reporter object to an established websocket cnx.
     *   Must be called only the first time, until decnx of the cnx.
     * ---------------------------------------------------------------------------
     */
    public function connect(&$p_connection, $p_cnx_type) {
      $v_id = $p_connection->my_id;
      if ($v_id == '') {
        ArubaWssTool::log('debug', "Invalid empty id for connection. Failed to attach connection to reporter.");
        return(false);
      }
            
      // ----- Look for BLE Telemetry cnx
      if ($p_cnx_type == 'ble') {
        if ($this->connection_id_ble != $v_id) {
          ArubaWssTool::log('debug', "Adding BLE connection '".$v_id."' to reporter '".$this->mac_address."'");
          $this->connection_id_ble = $v_id;
          
          // ----- Reset parameters (to clean the situation if needed)
          $this->ble_connect_status = AWSS_STATUS_DISCONNECTED;
          $this->ble_connect_mac = '';

          // ----- Set remote IP (public IP) to the reporter
          // TBC : remote IP could be different from cnx ...
          $this->setRemoteIp($p_connection->my_remote_ip);
          ArubaWssTool::log('info', "Reporter '".$this->name."' (".$this->mac_address."), is connected from IP ".$v_id."");
        }
      }

      // ----- Look for WiFi RTLS cnx
      else if ($p_cnx_type == 'rtls') {
        if ($this->connection_id_rtls != $v_id) {
          ArubaWssTool::log('debug', "Adding RTLS connection '".$v_id."' to reporter '".$this->mac_address."'");
          $this->connection_id_rtls = $v_id;
        }
      }

      // ----- Look for SerialData cnx
      else if ($p_cnx_type == 'serial') {
        if ($this->connection_id_serial != $v_id) {
          ArubaWssTool::log('debug', "Adding SerialData connection '".$v_id."' to reporter '".$this->mac_address."'");
          $this->connection_id_serial = $v_id;
        }
      }

      // ----- Look for Zigbee cnx
      else if ($p_cnx_type == 'zigbee') {
        if ($this->connection_id_zigbee != $v_id) {
          ArubaWssTool::log('debug', "Adding Zigbee connection '".$v_id."' to reporter '".$this->mac_address."'");
          $this->connection_id_zigbee = $v_id;
        }
      }
      
      // ----- Change reporter status to 'active'
      $this->setStatus('active');
      
      return(true);
    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : stats()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function stats($v_type, $v_data) {

      if ($v_type == 'data_payload') {

        $this->stat_telemetry_msg_count++;

        $this->stat_telemetry_payload_sum += $v_data;
        if ($v_data > $this->stat_telemetry_payload_max) {
          $this->stat_telemetry_payload_max = $v_data;
        }
        if (($v_data < $this->stat_telemetry_payload_min) || ($this->stat_telemetry_payload_min == 0)) {
          $this->stat_telemetry_payload_min = $v_data;
        }

      }

      else if ($v_type == 'msg_rate') {

        $this->stat_msg_rate_sum += $v_data;

        //$this->stat_msg_rate_average = $this->stat_msg_rate_sum / ($this->stat_telemetry_msg_count-1);
        $this->stat_msg_rate_average = $this->stat_msg_rate_sum / $this->stat_telemetry_msg_count;

        if ($v_data > $this->stat_msg_rate_max) {
          $this->stat_msg_rate_max = $v_data;
        }
        if (($v_data < $this->stat_msg_rate_min) || ($this->stat_msg_rate_min == 0)) {
          $this->stat_msg_rate_min = $v_data;
        }

      }

    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : getStats()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function getStats() {
      $v_result = array();

      $v_result['msg_count'] = $this->stat_telemetry_msg_count;
      $v_result['msg_total_bytes'] = $this->stat_telemetry_payload_sum;
      if ($this->stat_telemetry_msg_count != 0) {
        $v_result['msg_size_average'] = ($this->stat_telemetry_payload_sum/$this->stat_telemetry_msg_count);
      }
      else {
        $v_result['msg_size_average'] = 0;
      }
      $v_result['msg_size_max'] = $this->stat_telemetry_payload_max;
      $v_result['msg_size_min'] = $this->stat_telemetry_payload_min;
      $v_result['msg_rate_average'] = $this->stat_msg_rate_average;
      $v_result['msg_rate_max'] = $this->stat_msg_rate_max;
      $v_result['msg_rate_min'] = $this->stat_msg_rate_min;

      return($v_result);

    }
    /* -------------------------------------------------------------------------*/

    /**---------------------------------------------------------------------------
     * Method : display_stats()
     * Description :
     * ---------------------------------------------------------------------------
     */
    public function display_stats() {

      ArubaWssTool::log('debug', "------ Stats Reporter :".$this->name);
      ArubaWssTool::log('debug', "  - Total received msg : ".$this->stat_telemetry_msg_count." ");
      ArubaWssTool::log('debug', "  - Total received bytes : ".$this->stat_telemetry_payload_sum." bytes");
      ArubaWssTool::log('debug', "  - Average msg size : ".($this->stat_telemetry_payload_sum/$this->stat_telemetry_msg_count)." bytes");
      ArubaWssTool::log('debug', "  - Max msg size : ".$this->stat_telemetry_payload_max." bytes");
      ArubaWssTool::log('debug', "  - Min msg size : ".$this->stat_telemetry_payload_min." bytes");
      ArubaWssTool::log('debug', "------------");
      ArubaWssTool::log('debug', "  - Average interval between msg : ".$this->stat_msg_rate_average." sec");
      ArubaWssTool::log('debug', "  - Max interval between msg : ".$this->stat_msg_rate_max." sec");
      ArubaWssTool::log('debug', "  - Min interval between msg : ".$this->stat_msg_rate_min." sec");
      ArubaWssTool::log('debug', "------------");

    }
    /* -------------------------------------------------------------------------*/

  }
  /* -------------------------------------------------------------------------*/

  // ----- Instanciate object which will manage the "Aruba logic" for the websocket server
  $aruba_iot_websocket = new ArubaWebsocket();

  // ----- Initialize websocket object
  $aruba_iot_websocket->init($argv);

  /**
   * This section is mainly inherited from Ratchet Websocket sample code.
   *
   *
   */

  $loop   = \React\EventLoop\Factory::create();

  $v_timeout = $aruba_iot_websocket->getInterruptTimeout();
  ArubaWssTool::log('debug', "Set interrupt timeout to : ".$v_timeout." secondes");

  $loop->addPeriodicTimer(
    $v_timeout,
    function () use (&$aruba_iot_websocket) {
        $aruba_iot_websocket->onInterrupt();
    }
  );

  // ----- Create socket on IP and port
  try {

    // doc : http://socketo.me/api/class-React.Socket.Server.html
    $socket = new \React\Socket\Server($aruba_iot_websocket->getIpAddress().':'.$aruba_iot_websocket->getTcpPort(), $loop);


  $closeFrameChecker = new \Ratchet\RFC6455\Messaging\CloseFrameChecker;
  $negotiator = new \Ratchet\RFC6455\Handshake\ServerNegotiator(new \Ratchet\RFC6455\Handshake\RequestVerifier, PermessageDeflateOptions::permessageDeflateSupported());

  $uException = new \UnderflowException;

  $socket->on('connection', function (React\Socket\ConnectionInterface $connection) use (&$aruba_iot_websocket, $negotiator, $closeFrameChecker, $uException, $socket) {
      $headerComplete = false;
      $buffer = '';
      $parser = null;
      $connection->on('data', function ($data) use (&$aruba_iot_websocket, &$connection, &$parser, &$headerComplete, &$buffer, $negotiator, $closeFrameChecker, $uException, $socket) {
          // ----- Stats
          $aruba_iot_websocket->stats('raw_data', strlen($data));
          
          if ($headerComplete) {
              $parser->onData($data);
              return;
          }

          // ----- Extract HTTP Header from payload
          $buffer .= $data;
          $parts = explode("\r\n\r\n", $buffer);
          if (count($parts) < 2) {
              return;
          }

          //ArubaWssTool::log('debug', "HTTP Header : ".$parts[0]);
          
          // ----- Parse HTTTP Header
          $headerComplete = true;
          //$psrRequest = \GuzzleHttp\Psr7\parse_request($parts[0] . "\r\n\r\n");
          $psrRequest = \GuzzleHttp\Psr7\Message::parseRequest($parts[0] . "\r\n\r\n");
          
          // ----- Look for websocket connection
          $v_flag_websocket = false;
          $v_upgrade_header = $psrRequest->getHeader('Upgrade');
          if (isset($v_upgrade_header[0]) && (strtolower($v_upgrade_header[0]) == "websocket")) {
            $v_flag_websocket = true;
          }

          // ----- Look for URI commands
          if ($psrRequest->getUri()->getPath() === '/api') {
          
            // ----- Look for websocket client
            if ($v_flag_websocket) {
              ArubaWssTool::log('debug', "Received connection on WS API");
              $aruba_iot_websocket->onOpen($connection, 'ws_api');
            }
            else {          
              $aruba_iot_websocket->onOpen($connection, 'api');
              
              // ----- Call API parser
              $v_json_response = $aruba_iot_websocket->onApiCall($connection, (isset($parts[1])?$parts[1]:''));
  
              // ----- Send response
              //$connection->end(\GuzzleHttp\Psr7\str(new Response(200, ['User-Agent'=>'ArubaWebsocketServer/1.0','Access-Control-Allow-Origin'=>'*','Accept'=>'application/json'], $v_json_response . PHP_EOL)));
              $connection->end(\GuzzleHttp\Psr7\Message::toString(new Response(200, ['User-Agent'=>'ArubaWebsocketServer/1.0','Access-Control-Allow-Origin'=>'*','Accept'=>'application/json'], $v_json_response . PHP_EOL)));
              return;
            }
          }
          else if ($psrRequest->getUri()->getPath() === '/telemetry') {
            ArubaWssTool::log('debug', "Received connection on BLE Telemetry URI");
            $aruba_iot_websocket->onOpen($connection, 'telemetry');
          }
          else if ($psrRequest->getUri()->getPath() === '/rtls') {
            ArubaWssTool::log('debug', "Received connection on WiFi RTLS URI");
            $aruba_iot_websocket->onOpen($connection, 'rtls');
          }
          else if ($psrRequest->getUri()->getPath() === '/serial') {
            ArubaWssTool::log('debug', "Received connection on Serial URI");
            $aruba_iot_websocket->onOpen($connection, 'serial');
          }
          else if ($psrRequest->getUri()->getPath() === '/zigbee') {
            ArubaWssTool::log('debug', "Received connection on Zigbee URI");
            $aruba_iot_websocket->onOpen($connection, 'zigbee');
          }
          /* Need to add authentication for shutdown !!
          else if ($psrRequest->getUri()->getPath() === '/shutdown') {
              //$connection->end(\GuzzleHttp\Psr7\str(new Response(200, [], 'Shutting down echo server.' . PHP_EOL)));
              $connection->end(\GuzzleHttp\Psr7\Message::toString(new Response(200, [], 'Shutting down echo server.' . PHP_EOL)));
              $socket->close();
              return;
          }
          */
          else {
            $aruba_iot_websocket->onOpen($connection, 'http');
            
            if ($psrRequest->getUri()->getPath() === '/favicon.ico') {
              //$connection->end(\GuzzleHttp\Psr7\str(new Response(404, [], '' . PHP_EOL)));
              $connection->end(\GuzzleHttp\Psr7\Message::toString(new Response(404, [], '' . PHP_EOL)));
              return;
            }
            
            if (!$v_flag_websocket) {
              ArubaWssTool::log('debug', "Regular HTTP request on other URI (".$psrRequest->getUri()->getPath().")");
              
              // ----- Read the client file
              $filename = __DIR__."/awss/client/websocket_client.html";
              if (file_exists($filename)) {
                $handle = fopen($filename, "r");
                $contents = fread($handle, filesize($filename));
                fclose($handle);
              
                // ----- Send response
                //$connection->end(\GuzzleHttp\Psr7\str(new Response(200, [], $contents . PHP_EOL)));
                $connection->end(\GuzzleHttp\Psr7\Message::toString(new Response(200, [], $contents . PHP_EOL)));
              }
              else {
                //$connection->end(\GuzzleHttp\Psr7\str(new Response(403, [], 'Missing client file.' . PHP_EOL)));
                $connection->end(\GuzzleHttp\Psr7\Message::toString(new Response(403, [], 'Missing client file.' . PHP_EOL)));
              }
            }
            else {
              ArubaWssTool::log('debug', "HTTP request on bad URI");
              // ----- Send response
              //$connection->end(\GuzzleHttp\Psr7\str(new Response(403, [], '' . PHP_EOL)));
              $connection->end(\GuzzleHttp\Psr7\Message::toString(new Response(403, [], '' . PHP_EOL)));
            }
            return;
          }

          // ----- Perform Websocket handcheck
          $negotiatorResponse = $negotiator->handshake($psrRequest);

          $negotiatorResponse = $negotiatorResponse->withAddedHeader("Content-Length", "0");

          if ($negotiatorResponse->getStatusCode() !== 101 && $psrRequest->getUri()->getPath() === '/shutdown') {
              //$connection->end(\GuzzleHttp\Psr7\str(new Response(200, [], 'Shutting down echo server.' . PHP_EOL)));
              $connection->end(\GuzzleHttp\Psr7\Message::toString(new Response(200, [], 'Shutting down echo server.' . PHP_EOL)));
              $socket->close();
              return;
          };

          //$connection->write(\GuzzleHttp\Psr7\str($negotiatorResponse));
          $connection->write(\GuzzleHttp\Psr7\Message::toString($negotiatorResponse));

          if ($negotiatorResponse->getStatusCode() !== 101) {
              $connection->end();
              return;
          }

          // there is no need to look through the client requests
          // we support any valid permessage deflate
          $deflateOptions = PermessageDeflateOptions::fromRequestOrResponse($psrRequest)[0];

          $parser = new \Ratchet\RFC6455\Messaging\MessageBuffer($closeFrameChecker,
              function (MessageInterface $message, MessageBuffer $messageBuffer) use (&$aruba_iot_websocket, &$connection) {

                // onData() method is called for each received message, extracted from Websocket frame format
                // But still in protobuf format for Aruba Websocket

                // ----- Analyse message
                if (!$aruba_iot_websocket->onMessage($connection, $message->getPayload())) {
                  ArubaWssTool::log('debug', "Close cnx on onmessage() return");
                  // ----- Close connection
                  //$connection->end(\GuzzleHttp\Psr7\str(new Response(403, [], '' . PHP_EOL)));
                  $connection->end(\GuzzleHttp\Psr7\Message::toString(new Response(403, [], '' . PHP_EOL)));
                  return;
                }

/*
                // ----- If a message need to be sent back, this would be done here
                $v_datamsg = $aruba_iot_websocket->test_send($connection, $message);
                if ($v_datamsg != null) {                
                  $v_message = new \Ratchet\RFC6455\Messaging\Message();
                  $v_frame = new \Ratchet\RFC6455\Messaging\Frame($v_datamsg, true, \Ratchet\RFC6455\Messaging\Frame::OP_BINARY);          
                  $v_message->addFrame($v_frame);
                  $messageBuffer->sendMessage($v_message->getPayload(), true, $v_message->isBinary());
                }
*/

              }, function (FrameInterface $frame) use (&$aruba_iot_websocket, &$connection, &$parser) {
                  switch ($frame->getOpCode()) {
                      case Frame::OP_CLOSE:
                          $aruba_iot_websocket->onClose($connection);
                          $connection->end($frame->getContents());
                          break;
                      case Frame::OP_PING:
                          $aruba_iot_websocket->onPingMessage($connection);
                          $connection->write($parser->newFrame($frame->getPayload(), true, Frame::OP_PONG)->getContents());
                          break;
                  }
              }, true, function () use ($uException) {
                  return $uException;
              },
              null,
              null,
             [$connection, 'write'],
             $deflateOptions);

          // ----- Retire la partie header HTTP, pour ne garder que la payload
          array_shift($parts);
          $parser->onData(implode("\r\n\r\n", $parts));
      });
/*
      $connection->on('end', function () use (&$aruba_iot_websocket, &$connection) {
        $aruba_iot_websocket->onClose($connection);
      });
*/
      $connection->on('close', function () use (&$aruba_iot_websocket, &$connection) {
        $aruba_iot_websocket->onClose($connection);
      });
      $connection->on('error', function (Exception $e) use (&$aruba_iot_websocket, &$connection) {
        //echo 'error: ' . $e->getMessage();
        ArubaWssTool::log('debug', "Received error on connection '".$connection->my_id."' : ".$e->getMessage());
      });
  });


  ArubaWssTool::log('debug', "");
  ArubaWssTool::log('debug', "-----");
  ArubaWssTool::log('debug', "Start Websocket Server Loop (".date("Y-m-d H:i:s").")");
  ArubaWssTool::log('debug', " -> listening on ".$aruba_iot_websocket->getIpAddress().':'.$aruba_iot_websocket->getTcpPort()."");
  ArubaWssTool::log('debug', "-----");

  ArubaWssTool::log('info', "Listening on port ".$aruba_iot_websocket->getIpAddress().":".$aruba_iot_websocket->getTcpPort());

  // ----- Start Websocket loop
    //while (1) { $v=1; }
    $loop->run();

  } catch (\Exception $e) {
    ArubaWssTool::log('error', 'Daemon crash with following error: ' . $e->getMessage());
  }

?>

