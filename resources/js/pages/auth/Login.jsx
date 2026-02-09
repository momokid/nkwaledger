import React, { useState } from "react";
import {
    Button,
    Input,
    Card,
    Text
} from "@fluentui/react-components";

export default function Login() {
    const [phone, setPhone] = useState("");

    return (
        <div
            style={{
                minHeight: "100vh",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
            }}
        >
            <Card style={{ width: 360 }}>
                <Text size={500} weight="semibold">
                    Login to NkwaLedger
                </Text>

                <Input
                    placeholder="Phone number"
                    value={phone}
                    onChange={(e) => setPhone(e.target.value)}
                    style={{ marginTop: 16 }}
                />

                <Button
                    appearance="primary"
                    style={{ marginTop: 16 }}
                >
                    Send OTP
                </Button>

                <Button
                    appearance="subtle"
                    style={{ marginTop: 8 }}
                >
                    Use password instead
                </Button>
            </Card>
        </div>
    );
}
