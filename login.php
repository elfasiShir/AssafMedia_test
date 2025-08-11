<?php

switch ($_GET["data"] ?? null) {
    case "send_otp":
        #region send_otp

        $email = $_POST["email"] ?? null;

        if (!$email) {
            error_log("ERROR: Email not provided");
            echo json_encode(["success" => false, "message" => "Email is required"]);
            die();
        }

        // Generate a random 6-digit OTP
        $otp = rand(100000, 999999);

        // Save OTP to the database or session (for simplicity, using session here)
        session_start();
        $_SESSION["otp"] = $otp;
        $_SESSION["otp_createdate"] = time();
        $_SESSION["email"] = $email;

        // Include database connection
        require_once 'modules/mysql.php';

        // Update OTP and creation date in the database
        $otpCreatedate = date('Y-m-d H:i:s');
        $query = "UPDATE users SET otp = ?, otp_createdate = ? WHERE email = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('sss', $otp, $otpCreatedate, $email);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
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
            "sender" => ["name" => "YourAppName", "email" => "no-reply@yourapp.com"],
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

        $email = $_POST["email"] ?? null;
        $otp = $_POST["otp"] ?? null;

        if (!$email || !$otp) {
            error_log("ERROR: Email or OTP not provided");
            echo json_encode(["success" => false, "message" => "Email and OTP are required"]);
            die();
        }

        // Include database connection
        require_once 'modules/mysql.php';

        // Check OTP and creation date in the database
        $query = "SELECT otp, otp_createdate FROM users WHERE email = ?";
        $stmt = $mysqli->prepare($query);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            error_log("ERROR: Email not found in the database");
            echo json_encode(["success" => false, "message" => "Invalid email or OTP"]);
            die();
        }

        $row = $result->fetch_assoc();
        $storedOtp = $row['otp'];
        $otpCreatedate = $row['otp_createdate'];

        // Validate OTP
        if ($storedOtp !== $otp) {
            error_log("ERROR: Invalid OTP");
            echo json_encode(["success" => false, "message" => "Invalid OTP"]);
            die();
        }

        // Validate OTP expiration (e.g., 5 minutes)
        $otpTimestamp = strtotime($otpCreatedate);
        $currentTimestamp = time();
        if (($currentTimestamp - $otpTimestamp) > 600) {
            error_log("ERROR: OTP expired");
            echo json_encode(["success" => false, "message" => "OTP expired"]);
            die();
        }

        echo json_encode(["success" => true, "message" => "OTP validated successfully"]);
        die();

        #endregion validate_otp
        break;

    default:
        echo json_encode(["success" => false, "message" => "Invalid endpoint"]);
        die();
}
