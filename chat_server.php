<?php
echo "Starting server...\r\n";
$address = '10.10.10.5';
$port = 9050;

//Create the server socket
$server_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

//Set options
socket_set_option($server_socket, SOL_SOCKET, SO_REUSEADDR, 1);

//Bind the socket
socket_bind($server_socket, $address, $port);

//Begin listening
socket_listen($server_socket);

//Create an array to keep track of all open sockets
$clients = [];

//Add the server socket to our array
$clients[] = $server_socket;

while (true) {
    //Create a copy of the current array of open sockets
    $socket_cache = $clients;
    $r = NULL;
    $w = NULL;
    $e = NULL;
    $duration = NULL;

    //Check to see if any sockets have something for us to read
    if (socket_select($socket_cache, $w, $e, $duration) > 0) {
        /* If the server socket has something to read, it means a client is trying to connect. Process
        this */
        if (in_array($server_socket, $socket_cache)) {
            $new_client = socket_accept($server_socket);
            $client_request = socket_read($new_client, 2000, PHP_BINARY_READ);
            $server_response = generateUpgradeResponse($client_request);

            //If sending upgrade response to client fails, then client disconnected. Go to next iteration
            if (socket_write($new_client, $server_response) === false) {
                continue;
            }

            //If sending initial welcome message fails, then client disconnected. Go to next iteration
            $encoded_message = encodeMessage('{"message": "Server: Welcome!"}');
            if (socket_write($new_client, $encoded_message) === false ) {
                continue;
            }

            //If we make it this far, client successfully connected. Add them to list of clients
            $clients[] = $new_client;
            echo "New user connected." . PHP_EOL;

            /* Remove the server socket from the cache */
            $server_socket_key = array_search($server_socket, $socket_cache);
            unset($socket_cache[$server_socket_key]);
        }

        //See if there are still any sockets to read from. If so, these are client sockets
        if (count($socket_cache) > 0) {
            //Loop through every socket for which there is something to read
            foreach ($socket_cache as $read_from_me) {
                $client_key = array_search($read_from_me, $clients);
                $client_enc_message = socket_read($read_from_me, 2000, PHP_BINARY_READ);
                
                echo "User $client_key sent a message." . PHP_EOL;
                //var_dump(bin2hex(decodeMessage($client_enc_message)));
                
                //If results of socket read is false, then client disconnected. Remove client
                if ($client_enc_message === false) {
                    unset($clients[$client_key]);
                    echo "User $client_key disconnected." . PHP_EOL;
                    continue;
                }

                /* If client messsage is empty, then client disconnected. Remove client */
                if ($client_enc_message === '') {
                    unset($clients[$client_key]);
                    echo "User $client_key disconnected." . PHP_EOL;
                    continue;
                }

                /* If opcode is 8, then client wants to close the connection. Send confirmation
                then remove client */
                if (getOpcode($client_enc_message) === 8) {
                    echo "User $client_key wants to disconnect." . PHP_EOL;
                    socket_write($read_from_me, generateCloseFrame());
                    unset($clients[$client_key]);
                    echo "User $client_key disconnected." . PHP_EOL;
                    continue;
                }

                //Decode the message and trim whitespace
                $client_dec_message = trim(decodeMessage($client_enc_message));

                //If message is empty, skip
                if ($client_dec_message === '') {
                    continue;
                }

                //Place message we'll be broadcasting in JSON
                $message_to_send = new stdClass();
                $message_to_send->message = "User " . $client_key . ": " . $client_dec_message;
                $json = json_encode($message_to_send);

                //Loop through each socket and broadcast message
                foreach ($clients as $write_to_me) {
                    $write_to_me_key = array_search($write_to_me, $clients);
                    //If socket we're writing to is the server, skip
                    if ($write_to_me === $server_socket) {
                        continue;
                    }

                    $write_result = socket_write($write_to_me, encodeMessage($json));
                    echo "Broadcasting message sent by User $client_key to User $write_to_me_key" . PHP_EOL;
                    //If unable to write to client, then they probably disconnected. Remove client.
                    if ($write_result === false) {
                        unset($clients[$client_key]);
                        echo "User $client_key disconnected." . PHP_EOL;
                    }
                }


            
            }
        }
    }
}


/**
 * This generates the proper response to a client's request to upgrade a connection
 * to a websocket
 *
 * @param string $upgrade_request The client's upgrade request
 * @return string The completed response to write to the client to complete the upgrade
 */
function generateUpgradeResponse(string $upgrade_request) : string
{
    //Extract the Sec-WebSocket-Key value
    $matches = [];
    preg_match('/Sec-WebSocket-Key:(.*)/', $upgrade_request, $matches);
    $websocket_key = trim($matches[1]);

    //Generate the Sec-WebSocket-Accept value
    $websocket_accept = $websocket_key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
    $websocket_accept = sha1($websocket_accept, true);
    $websocket_accept = base64_encode($websocket_accept);
    
    //Generate the header block
    $headers = "HTTP/1.1 101 Switching Protocols\r\n";
    $headers .= "Upgrade: websocket\r\n";
    $headers .= "Connection: Upgrade\r\n";
    $headers .= "Sec-WebSocket-Accept: $websocket_accept\r\n";
    $headers .= "\r\n";

    return $headers;
}

/**
 * Applies the proper encoding to a message you want to broadcast
 *
 * @param string $message The message you want to broadcast
 * @return string The properly encoded version of your message that you can broadcast through a socket
 */
function encodeMessage(string $message) : string
{
    $message_length = strlen($message);
    $header = [];
    $header[0] = 129;

    if ($message_length <= 125) {
        $header[1] = $message_length;
    } elseif ($message_length >= 126 && $message_length <= 65535) {
        $header[1] = 126;
        $header[2] = ($message_length >> 8) & 255;
        $header[3] = ($message_length) & 255;
    } else {
        $header[1] = 127;
        $header[2] = ($message_length >> 56) & 255;
        $header[3] = ($message_length >> 48) & 255;
        $header[4] = ($message_length >> 40) & 255;
        $header[5] = ($message_length >> 32) & 255;
        $header[6] = ($message_length >> 24) & 255;
        $header[7] = ($message_length >> 16) & 255;
        $header[8] = ($message_length >> 8) & 255;
        $header[9] = ($message_length) & 255;
    }

    for ($i = 0; $i < count($header); $i++) {
        $header[$i] = chr($header[$i]);
    }

    $header_encoded = implode($header);
    $encoded_message = $header_encoded . $message;

    return $encoded_message;
}

function decodeMessage(string $M) : string
{    
    $M = array_map("ord", str_split($M));
    $L = $M[1] AND 127;

    if ($L == 126)
        $iFM = 4;
    else if ($L == 127)
        $iFM = 10;
    else
        $iFM = 2;

    $Masks = array_slice($M, $iFM, 4);

    $Out = "";
    for ($i = $iFM + 4, $j = 0; $i < count($M); $i++, $j++ ) {
        $Out .= chr($M[$i] ^ $Masks[$j % 4]);
    }
    return $Out;    
}

/**
 * Extracts the opcode from the encoded message
 *
 * @param string $encoded_message The message from the client, still encoded
 * @return integer The opcode associated with the message
 */
function getOpcode(string $encoded_message) : int
{
    $opcode = (ord($encoded_message) & 15);
    return $opcode;
}

function generateCloseFrame($message = '') : string
{
    $message_length = strlen($message);
    $header = [];
    $header[0] = chr(136);
    $header[1] = chr($message_length);

    $header_encoded = implode($header);
    $encoded_message = $header_encoded . $message;

    return $encoded_message;
}



















?>