import React from "react";
import { createRoot } from "react-dom/client";
import {
  FluentProvider,
  webLightTheme,
} from "@fluentui/react-components";

import Login from "./components/auth/Login";

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
