<?PHP

    $mTitle = "Test from PHP Script not from AdminPanel...";
    $msg_payload = array (
                        'mtitle' => $mTitle,
                        'mdesc' => $mTitle
                    );

    $mResponse = sendMessage($msg_payload);

    if( strpos( $mResponse, 'error' ) !== false ) echo 'no error';

    var_dump($mResponse);

// Sends Push notification function for OneSignal

    function sendMessage($data) {
        

        $heading = array(
           "en" => $data['mtitle']
        );

        $content      = array(
            "en" => $data['mdesc']
        );


        $fields = array(
            'app_id' => "03bcf763-bfef-4d8c-b94c-ede01f1e18a1",
            'included_segments' => array(
                'All'
            ),
            'data' => array(
                "foo" => "bar"
            ),
            'contents' => $content,
            'headings' => $heading
        );
        
        $fields = json_encode($fields);
        //print("\nJSON sent:\n");
        //print($fields);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic YmUyZmI1YTMtYjk4My00ZTAzLWFlMmItZWE0OWEyZjNlZjdk'
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return $response;
    }


/*
$data['mtitle'] = "Hello User.";
$data['mdesc']  = "Hello User from PHP Code";

$response = sendMessage($data);

if( strpos( $response, 'error' ) !== false ) echo 'no error';

var_dump($response);
*/
/*
$return["allresponses"] = $response;
$return = json_encode($return);

$data = json_decode($response, true);
print_r($data);
$id = $data['id'];
print_r($id);

print("\n\nJSON received:\n");
print($return);
print("\n");
*/
?>