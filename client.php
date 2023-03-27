<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Basic Chat Server</title>
    <style>
        #chat_activity {
            border: 1px solid black;
            padding: 5px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div id="chat_activity">
        <div class="chat_entry" style="font-style: italic;">Chat logs will appear here...</div>
    </div>
    <div id="input_box_container" style="margin-top: 5px; display: none;">
    <form id="chat_form">
        <input id="input_box" type="text" style="width: 500px;" autocomplete="off" />
        <button id="send_button">Send</button>
    </form>
    </div>
    <button id="toggle_connection" style="margin-top: 10px;">Connect to Chat Server</button>
    <div id="root"></div>
    <script>
        let chat_form = document.getElementById('input_box_container');
        let host = 'ws://10.10.10.5:9050/chat_server.php';
        let socket = undefined;
        
        document.getElementById('toggle_connection').addEventListener('click', (e) => {
            if (e.target.textContent === 'Connect to Chat Server') {
                socket = new WebSocket(host);
                e.target.textContent = 'Connecting...';
                e.target.disabled = true;

                socket.addEventListener('readystatechange', () => {
                    console.log('ready state changed');
                });
    
                socket.addEventListener('open', () => {
                    e.target.textContent = 'Disconnect from Chat Server';
                    e.target.disabled = false;
                    insertChatEntry('Connected to server successfully!');
                    chat_form.style.display = 'block';
                });
    
                socket.addEventListener('error', () => {
                    e.target.textContent = 'Connect to Chat Server';
                    insertChatEntry('Websocket connection could not be opened or closed unexpectedly.')
                    chat_form.style.display = 'none';
                });

                socket.addEventListener('close', () => {
                    e.target.textContent = 'Connect to Chat Server';
                    e.target.disabled = false;
                    insertChatEntry('Disconnected from chat server.');
                    chat_form.style.display = 'none';
                });

                socket.addEventListener('message', (e) => {
                    let server_message = JSON.parse(e.data).message;
                    insertChatEntry(server_message);
                });


            } else if (e.target.textContent === 'Disconnect from Chat Server') {
                e.target.textContent = 'Disconnecting...';
                e.target.disabled = true;
                socket.close();
            }
        });

        document.getElementById('chat_form').addEventListener('submit', (e) => {
            e.preventDefault();
            socket.send(document.getElementById('input_box').value);
            document.getElementById('input_box').value = '';
        });



        function insertChatEntry(message)
        {
            let now = new Date();
            let offsetMs = now.getTimezoneOffset() * 60 * 1000;
            let dateLocal = new Date(now.getTime() - offsetMs);
            let timestamp = dateLocal.toISOString().slice(11, 19).replace(/-/g, "/");

            let element = document.createElement('div');
            element.classList.add('chat_entry');
            element.textContent = timestamp + " | " + message;
            document.getElementById('chat_activity').appendChild(element);
        }
    </script>
</body>
</html>