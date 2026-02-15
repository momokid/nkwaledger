import React, { useState } from "react";
import {
  FluentProvider,
  webLightTheme,
  Card,
  Input,
  Button,
  Switch,
  Text,
  Divider
} from "@fluentui/react-components";

export default function Login() {
  const [usePassword, setUsePassword] = useState(true);

  return (
    <FluentProvider theme={webLightTheme}>
      <div style={styles.page}>
        <Card style={styles.card}>
          {/* Logo */}
          <div style={styles.logo}>
            <img src="/images/logo.svg" alt="NkwaLedger" height={48} />
          </div>

          {/* Title */}
          <Text size={600} weight="semibold">
            NkwaLedger
          </Text>
          <Text size={300} style={styles.subtitle}>
            Secure access to your farm records.
          </Text>

          <Divider style={{ margin: "20px 0" }} />

          {/* Phone Number */}
          <Input
            placeholder="e.g. 024 123 4567"
            appearance="outline"
            style={styles.input}
          />

          {/* Toggle */}
          <div style={styles.toggle}>
            <Switch
              checked={usePassword}
              onChange={() => setUsePassword(!usePassword)}
            />
            <Text>
              Login with {usePassword ? "password" : "OTP (SMS)"}
            </Text>
          </div>

          {/* Conditional Field */}
          {usePassword ? (
            <Input
              type="password"
              placeholder="Enter your password"
              appearance="outline"
              style={styles.input}
            />
          ) : (
            <Input
              placeholder="Enter OTP code"
              appearance="outline"
              style={styles.input}
            />
          )}

          {/* Button */}
          <Button
            appearance="primary"
            size="large"
            style={styles.button}
          >
            Continue
          </Button>
        </Card>
      </div>
    </FluentProvider>
  );
}

const styles = {
  page: {
    minHeight: "100vh",
    background: "#f5f5f5",
    display: "flex",
    alignItems: "center",
    justifyContent: "center",
  },
  card: {
    width: 420,
    padding: 32,
    borderRadius: 12,
  },
  logo: {
    textAlign: "center",
    marginBottom: 16,
  },
  subtitle: {
    color: "#616161",
    marginTop: 4,
  },
  input: {
    marginTop: 16,
  },
  toggle: {
    display: "flex",
    alignItems: "center",
    gap: 12,
    marginTop: 16,
  },
  button: {
    marginTop: 24,
  },
};
