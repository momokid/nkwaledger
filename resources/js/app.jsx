import React from "react";
import { createRoot } from "react-dom/client";
import {
  FluentProvider,
  webLightTheme,
} from "@fluentui/react-components";

function Login() {
  return (
    <div style={{ maxWidth: 420, margin: "4rem auto" }}>
      <h2>NkwaLedger Login</h2>
      <p>This is the React + Fluent UI mount.</p>
    </div>
  );
}

const container = document.getElementById("react-login");

if (container) {
  createRoot(container).render(
    <React.StrictMode>
      <FluentProvider theme={webLightTheme}>
        <Login />
      </FluentProvider>
    </React.StrictMode>
  );
}
