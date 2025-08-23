# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_AUTH_KEY}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

You can obtain an access token via OAuth 2.0 authorization flow or by using your existing Laravel session. For OAuth flows, see the OAuth 2.0 endpoints below.
