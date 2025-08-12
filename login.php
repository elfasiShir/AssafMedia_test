<?php
define("a328763fe27bba", "TRUE");

session_start();

require_once("config.php");

// Start the session before any output

header("Content-Type: application/json; charset=utf-8");

// Include the CORS function
function cors()
{
    // Allow from any origin
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');    // cache for 1 day
    }

    // Access-Control headers are received during OPTIONS requests
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
            header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

        if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
            header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

        exit(0);
    }
}

// Call the CORS function
cors();

switch ($_GET["data"] ?? null) {
    case "send_otp":
        #region send_otp

        $email = $_GET["email"] ?? null;

        if (!$email) {
            error_log("ERROR: Email not provided");
            echo json_encode(["success" => false, "message" => "Email is required"]);
            die();
        }

        // Generate a random 6-digit OTP
        $otp = rand(100000, 999999);

        // Save OTP to the session
        $_SESSION["otp"] = $otp;
        $_SESSION["otp_createdate"] = time();
        $_SESSION["email"] = $email;

        // Include database connection
        require_once 'modules/mysql.php';

        // Update OTP and creation date in the database
        $otpCreatedate = date('Y-m-d H:i:s');
        //$query = "UPDATE users SET otp = ?, otp_createdate = ? WHERE email = ?";
        $result = mysql_update("users", [
            "otp" => $otp,
            "otp_createdate" => $otpCreatedate
        ], [
            "email" => $email
        ]);
        if (!$result["success"]) {
            error_log("ERROR: Failed to update OTP in the database");
            echo json_encode(["success" => false, "message" => "Failed to update OTP in the database"]);
            die();
        }

        // Use Brevo (formerly Sendinblue) to send the OTP
        // Load the Brevo API key from the environment
        $brevoApiKey = getenv('BREVO_API_KEY');
        if (!$brevoApiKey) {
            error_log("ERROR: Brevo API key is not set in the environment.");
            die("Internal Server Error: Missing API key.");
        }

        $url = "https://api.brevo.com/v3/smtp/email";

        $data = [
            "sender" => ["name" => "Shir alfassi", "email" => "elfasishir@gmail.com"],
            "to" => [["email" => $email]],
            "subject" => "Your OTP Code",
            "htmlContent" => "<p>Your OTP code is: <strong>$otp</strong></p>"
        ];

        $options = [
            "http" => [
                "header" => "Content-Type: application/json\r\napi-key: $brevoApiKey\r\n",
                "method" => "POST",
                "content" => json_encode($data),
            ],
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        if ($response === FALSE) {
            error_log("ERROR: Failed to send OTP");
            echo json_encode(["success" => false, "message" => "Failed to send OTP"]);
            die();
        }

        echo json_encode(["success" => true, "message" => "OTP sent successfully"]);
        die();

        #endregion send_otp
        break;

    case "validate_otp":
        #region validate_otp

        $email = $_GET["email"] ?? null;
        $otp = $_GET["otp"] ?? null;

        if (!$email || !$otp) {
            error_log("ERROR: Email or OTP not provided");
            echo json_encode(["success" => false, "message" => "Email and OTP are required"]);
            die();
        }

        // Include database connection
        require_once 'modules/mysql.php';

        // Check OTP and creation date in the database
        $query = "SELECT `otp`, `otp_createdate` FROM `users` WHERE `email` = ?;";
        $result = mysql_fetch_array($query, [$email]);

        if (!$result) {
            error_log("ERROR: Email not found in the database");
            echo json_encode(["success" => false, "message" => "Invalid email or OTP"]);
            die();
        }

        $storedOtp = $result[0][0];
        $storedOtpCreatedate = $result[0][1];

        $otpCreatedate = strtotime($storedOtpCreatedate);
        $currentTimestamp = time();

        // validate OTP
        if ($storedOtp !== $otp) {
            error_log("ERROR: Invalid OTP");
            echo json_encode(["success" => false, "message" => "Invalid OTP"]);
            die();
        }
        // validate OTP expiration (e.g., 10 minutes)
        if (($currentTimestamp - $otpCreatedate) > 600) {
            error_log("ERROR: OTP expired");
            echo json_encode(["success" => false, "message" => "OTP expired"]);
            die();
        }

        // Succesful login
        $_SESSION['user_logged_in'] = true;
        $_SESSION['email'] = $email;

        echo json_encode(["success" => true, "message" => "OTP validated successfully"]);
        die();

        #endregion validate_otp
        break;

    default:
        echo json_encode(["success" => false, "message" => "Invalid endpoint"]);
        die();
}
