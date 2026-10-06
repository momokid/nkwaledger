// every word the PIN gate shows, in one place
export const PIN_TEXT = {
    create: "Create a 4-digit PIN",
    confirm: "Enter the PIN again",
    enter: "Enter your PIN",
    wrong: "Wrong PIN.",
    weak: "That PIN is too easy to guess. Choose another.",
    mismatch: "The PINs do not match. Try again.",
    locked: "Too many wrong tries. Your PIN is locked.",
    safe: "Your unsent records are safe on this phone.",
    forgot: "Forgot your PIN?",
    sendCode: "Send me a code",
    enterCode: "Enter the code we sent you",
    wrongCode: "That code is not right. Try again.",
    expiredCode: "That code has expired. Ask for a new one.",
    couldNotSend: "We could not send the code. Check your connection and try again.",
    tooManyCodes: "Too many wrong codes. Try again later.",
} as const;
