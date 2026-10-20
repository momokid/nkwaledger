# Login throttling

Failed logins are counted per account (5 an hour) and per IP (20 an hour). A success clears only the account counter. `/register` is throttled to 5 an hour per IP. Login is constant-time.

Source: the archived phase-2 handoff. Check the code before relying on the numbers.
