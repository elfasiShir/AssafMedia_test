import React, { useState } from "react";
import "./LoginPage.css";

const LoginPage = () => {
  const [email, setEmail] = useState("");
  const [otp, setOtp] = useState("");
  const [step, setStep] = useState(1); // 1 = email step, 2 = OTP step

  const handleEmailSubmit = async (e) => {
    e.preventDefault();
    console.log("Email submitted:", email);

    try {
      const response = await fetch("http://localhost:8088/api/send_otp", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ email }),
      });

      if (response.ok) {
        console.log("OTP sent successfully");
        setStep(2);
      } else {
        console.error("Failed to send OTP");
      }
    } catch (error) {
      console.error("Error sending OTP:", error);
    }
  };

  const handleOtpSubmit = async (e) => {
    e.preventDefault();
    console.log("OTP submitted:", otp);

    try {
      const response = await fetch("http://localhost:8088/api/validate_otp", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ email, otp }),
      });

      if (response.ok) {
        console.log("OTP validated successfully");
        // Proceed to the next step or login success
      } else {
        console.error("Failed to validate OTP");
      }
    } catch (error) {
      console.error("Error validating OTP:", error);
    }
  };

  return (
    <div className="login-container">
      <div className="login-box">
        <h2 className="login-header">Login</h2>

        {step === 1 && (
          <form onSubmit={handleEmailSubmit} className="login-form">
            <div className="input-group">
              <label htmlFor="email">Email</label>
              <input
                type="email"
                id="email"
                placeholder="Enter your email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
              />
            </div>
            <button type="submit" className="login-button">
              Send OTP
            </button>
          </form>
        )}

        {step === 2 && (
          <form onSubmit={handleOtpSubmit} className="login-form">
            <div className="input-group">
              <label htmlFor="otp">One-Time Password</label>
              <input
                type="text"
                id="otp"
                placeholder="Enter OTP"
                value={otp}
                onChange={(e) => setOtp(e.target.value)}
                required
              />
            </div>
            <button type="submit" className="login-button">
              Verify OTP
            </button>
          </form>
        )}
      </div>
    </div>
  );
};

export default LoginPage;
