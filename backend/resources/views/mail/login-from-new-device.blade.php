Hello {{ $user->first_name }},

We noticed a new login to your NkwaLedger account from IP address {{ $ip }} at
{{ $loggedInAt->format('d M Y, H:i') }}.

If this was you, no action is needed.

If you don't recognize this activity, please contact support immediately and consider changing your password.

- The NkwaLedger Team
