<?php
/**
 * Aruba Websocket Demo Server
 *
 *
 */
  ini_set('display_errors', '1');

  $loader = require __DIR__ . '/vendor/autoload.php';
  $loader->addPsr4('aruba_telemetry\\', __DIR__);

  use GuzzleHttp\Psr7\Response;
  use Ratchet\RFC6455\Handshake\PermessageDeflateOptions;
  use Ratchet\RFC6455\Messaging\MessageBuffer;
  use Ratchet\RFC6455\Messaging\MessageInterface;
  use Ratchet\RFC6455\Messaging\FrameInterface;
  use Ratchet\RFC6455\Messaging\Frame;


  // ----- Global server options
  $g_att = array();
  $g_att['display_ping'] = false;
  $g_att['use_testing_file'] = '';
  $g_att['server_ip'] = '0.0.0.0';
  $g_att['server_port'] = '8081';


  /**
   * AwssConnection() called when a new valid connection is initiated
   *
   *
   */
  function AwssConnection(React\Socket\ConnectionInterface &$connection) {

    $address = $connection->getRemoteAddress();
    $ip = trim(parse_url($address, PHP_URL_HOST), '[]');
    $port = trim(parse_url($address, PHP_URL_PORT), '[]');


    // ----- Here I add some custom attributes to the connection object,
    // in order to store data that will be used later.
    //$connection->my_id = random_int (1,1000);
    $connection->my_status = 'initiated';      // initiated=valid conx, active=reporter identifed
    $connection->my_id = $ip.':'.$port;
    $connection->my_name = $connection->my_id;   // waiting for reporter name

    echo "New Connection (".$connection->my_id.")\n";
    echo "  - Remote URL : ".$connection->getRemoteAddress()."\n";
    echo "  - Remote IP : ".$ip."\n";
    //echo "  - Local URL :".$connection->getLocalAddress()."\n";
    echo "-----\n";

  }


  /**
   * AwssReceivedMessage() called when a message is received for an existing connection.
   *   This function will decode the protobuf binary message, and show various
   *   ways to display the content in readable format.
   *
   *   This is a ggod place for you to add your own code
   *
   */
  function AwssReceivedMessage(&$connection, $p_msg) {

    $v_name = ($connection==''?'test':$connection->my_name);
    echo "Received message from ".$v_name."\n";

    $telemetry_msg = new aruba_telemetry\Telemetry($p_msg);

    //echo "--------- Telemetry Message :\n";
    //echo $telemetry_msg;
    //echo "---------\n";

    //$meta = new aruba_telemetry\Meta();
    $meta = $telemetry_msg->getMeta();

    echo "--------- Meta :\n";
    echo "  Version: ".$meta->getVersion()."\n";
    echo "  Access Token: ".$meta->getAccessToken()."\n";
    echo "---------\n";
    echo "\n";
    echo "\n";

    //$reporter = new aruba_telemetry\Reporter();
    $reporter = $telemetry_msg->getReporter();

    echo "--------- Reporter :\n";
    echo "  Name: ".$reporter->getName()."\n";
    echo "  Mac: ".MacToString($reporter->getMac())."\n";
    echo "  IPv4: ".($reporter->hasIpv4() ? $reporter->getIpv4() : '-')."\n";
    echo "  IPv6: ".($reporter->hasIpv6() ? $reporter->getIpv6() : '-')."\n";
    echo "  hwType: ".$reporter->getHwType()."\n";
    echo "  swVersion: ".$reporter->getSwVersion()."\n";
    echo "  swBuild: ".$reporter->getSwBuild()."\n";
    echo "  Time: ".date("Y-m-d H:i:s", $reporter->getTime())."\n";
    echo "---------\n";
    echo "\n";
    echo "\n";

    // ----- Update connection info with Reporter name and mac (for display purpose)
    if (($connection !== '') && ($connection->my_status == 'initiated')) {
      $connection->my_status = 'active';
      $connection->my_name = $reporter->getName().'('.MacToString($reporter->getMac()).')';
    }

    // ----- List reported device using embedded display feature
    if ($telemetry_msg->hasReportedList()) {
      $v_col = $telemetry_msg->getReportedList();
      foreach ($v_col as $v_object) {
        echo "--------- Reported device :\n";
        echo "mac: ".($v_object->hasMac() ? MacToString($v_object->getMac()) : '')."\n";
        echo $v_object;
        echo "\n---------\n";
      }


    }
  }

  /**
   * AwssReceivedPing() called when a ping message is received
   *
   */
  function AwssReceivedPing(&$connection) {
    global $g_att;

    if ($g_att['display_ping']) {
      echo "Received ping from ".$connection->my_name." (".date("Y-m-d H:i:s").")\n";
    }
  }


  /**
   * AwssReceivedClose() called when a ping message is received
   *
   */
  function AwssReceivedClose(&$connection) {
    echo "***** Closed Connection from ".$connection->my_name." (".date("Y-m-d H:i:s").")\n";
  }



  /**
   * MacToString() utility to format MAC@ as string
   *
   *
   */
  function MacToString($p_mac) {

    $v_size = $p_mac->getSize();
    if ($v_size != 6) {
      return "";
    }

    $v_data = $p_mac->getContents();
    $_val = unpack('C6parts', $v_data);

    $v_mac = sprintf("%02x:%02x:%02x:%02x:%02x:%02x",$_val['parts1'],$_val['parts2'],$_val['parts3'],$_val['parts4'],$_val['parts5'],$_val['parts6']);

    return strtoupper($v_mac);
  }


  /**
   * Parse server options
   *
   *
   */
  $v_count = 0;
  foreach ($argv as $arg)     {

    if ($arg == '-server_ip') {
      $g_att['server_ip'] = (isset($argv[$v_count+1]) ? $argv[$v_count+1] : '');
    }

    if ($arg == '-server_port') {
      $g_att['server_port'] = (isset($argv[$v_count+1]) ? $argv[$v_count+1] : '');
    }

    if ($arg == '-display_ping') {
      $g_att['display_ping'] = true;
    }

    if ($arg == '-file') {
      $g_att['use_testing_file'] = (isset($argv[$v_count+1]) ? $argv[$v_count+1] : '');
    }

    if (($arg == '-help') || ($arg == '--help')) {
      echo "----- \n";
      echo "php aruba-ws-server [-help] [-server_ip X.X.X.X] [-server_port XXX] [-display_ping] [-file <debug_message_filename>]\n";
      echo "----- \n";
      exit();
    }

    $v_count++;
  }

  // ----- Debug
  // Look for parsing a local stored file (not listening to real websocket client) - debug purpose only
  if ($g_att['use_testing_file'] != '') {
            $fd = fopen($g_att['use_testing_file'], 'r');
            $len = filesize($g_att['use_testing_file']);
            $msg = fread($fd, $len);
            //$msg = fread($fd, 1);
            fclose($fd);
            $v_temp = '';
           AwssReceivedMessage($v_temp, $msg);
           exit();
  }




  /**
   * This section is mainly inherited from Ratchet Websocket sample code.
   *
   *
   */


  $loop   = \React\EventLoop\Factory::create();

  $socket = new \React\Socket\Server($g_att['server_ip'].':'.$g_att['server_port'], $loop);

  $closeFrameChecker = new \Ratchet\RFC6455\Messaging\CloseFrameChecker;
  $negotiator = new \Ratchet\RFC6455\Handshake\ServerNegotiator(new \Ratchet\RFC6455\Handshake\RequestVerifier, PermessageDeflateOptions::permessageDeflateSupported());

  $uException = new \UnderflowException;


  $socket->on('connection', function (React\Socket\ConnectionInterface $connection) use ($negotiator, $closeFrameChecker, $uException, $socket) {
      $headerComplete = false;
      $buffer = '';
      $parser = null;
      $connection->on('data', function ($data) use (&$connection, &$parser, &$headerComplete, &$buffer, $negotiator, $closeFrameChecker, $uException, $socket) {
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

          //echo "Received HTTP Header -----\n";
          //var_dump($parts[0]);
          //echo "\n-----\n";

          // ----- Parse HTTTP Header, perform websocket handcheck
          $headerComplete = true;
          $psrRequest = \GuzzleHttp\Psr7\parse_request($parts[0] . "\r\n\r\n");
          $negotiatorResponse = $negotiator->handshake($psrRequest);

          $negotiatorResponse = $negotiatorResponse->withAddedHeader("Content-Length", "0");

          if ($negotiatorResponse->getStatusCode() !== 101 && $psrRequest->getUri()->getPath() === '/shutdown') {
              $connection->end(\GuzzleHttp\Psr7\str(new Response(200, [], 'Shutting down echo server.' . PHP_EOL)));
              $socket->close();
              return;
          };

          //echo "Negociator response -----\n";
          //var_dump(\GuzzleHttp\Psr7\str($negotiatorResponse));
          //echo "-----\n";

          $connection->write(\GuzzleHttp\Psr7\str($negotiatorResponse));

          if ($negotiatorResponse->getStatusCode() !== 101) {
              $connection->end();
              return;
          }

          // ----- New valid handchecked connection
          AwssConnection($connection);

          // there is no need to look through the client requests
          // we support any valid permessage deflate
          $deflateOptions = PermessageDeflateOptions::fromRequestOrResponse($psrRequest)[0];

          $parser = new \Ratchet\RFC6455\Messaging\MessageBuffer($closeFrameChecker,
              function (MessageInterface $message, MessageBuffer $messageBuffer) use (&$connection) {


                // onData() method is called for each received message, extracted from Websocket frame format
                // But still in protobuf format for Aruba Websocket

                // ----- Analyse message
                AwssReceivedMessage($connection, $message->getPayload());

                // ----- If a message need to be sent back, this would be done here
                //$messageBuffer->sendMessage($message->getPayload(), true, $message->isBinary());

              }, function (FrameInterface $frame) use (&$connection, &$parser) {
                  switch ($frame->getOpCode()) {
                      case Frame::OP_CLOSE:
                          AwssReceivedClose($connection);
                          $connection->end($frame->getContents());
                          break;
                      case Frame::OP_PING:
                          AwssReceivedPing($connection);
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
  });


  echo "\n";
  echo "-----\n";
  echo "Start Websocket Server Loop (".date("Y-m-d H:i:s").")\n";
  echo " -> listening on ".$g_att['server_ip'].":".$g_att['server_port']."\n";
  echo "-----\n";

  $loop->run();
?>

