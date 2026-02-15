import React, { useState } from "react";
import {
  Button,
  Input,
  Label,
  Switch,
  Title3,
  Text,
  Divider,
} from "@fluentui/react-components";

export default function Login() {
  const [useOtp, setUseOtp] = useState(true);

  return (
    <div style={{ padding: "2rem", maxWidth: "100%", margin: "0 auto" }}>
      {/* Header */}
      <Title3 block>NkwaLedger</Title3>
      <Text block style={{ marginBottom: "1.5rem", color: "#555" }}>
        Secure access to your farm records.
      </Text>

      <Divider style={{ marginBottom: "1.5rem" }} />

      {/* Phone number */}
      <Label htmlFor="phone">Phone number<br/></Label>
      <Input
        id="phone"
        placeholder="e.g. 024 123 4567"
        type="tel"
        style={{ marginBottom: "1.25rem", width: "100%" }}
      />

      {/* Login method toggle */}
      <Switch
        checked={useOtp}
        onChange={(_, data) => setUseOtp(data.checked)}
        label={
          useOtp
            ? "Login with password"
            : "Login with OTP (SMS code)"
        }
        style={{ marginBottom: "1.25rem" }}
      /><br/>

      {/* Conditional field */}
      {useOtp ? (
        <>
          <Label htmlFor="otp">One-time Password</Label><br/>
          <Input
            id="otp"
            placeholder="Enter code sent to your phone"
            style={{ marginBottom: "1.5rem",width: "100%" }}
          />
        </>
      ) : (
        <>
          <Label htmlFor="Password">Password</Label><br/>
          <Input
            id="password"
            type="password"
            placeholder="Enter your password"
            style={{ marginBottom: "1.5rem" }}
          />
        </>
      )}

      {/* Action */}
      <Button appearance="primary" style={{ width: "100%" }} disabled>
        Continue
      </Button>
    </div>
  );
}
