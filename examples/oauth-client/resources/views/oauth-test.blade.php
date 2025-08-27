<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OAuth 2.0 Test Client</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .info-box {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .btn {
            background: #007cba;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            font-size: 16px;
        }
        .btn:hover {
            background: #005a87;
        }
        code {
            background: #f8f8f8;
            padding: 2px 6px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <h1>OAuth 2.0 / OpenID Connect Test Client</h1>
    
    <div class="info-box">
        <h3>Test Configuration</h3>
        <p><strong>Client ID:</strong> <code>{{ $clientId }}</code></p>
        <p><strong>Redirect URI:</strong> <code>{{ $redirectUri }}</code></p>
        <p><strong>Scope:</strong> <code>{{ $scope }}</code></p>
        <p><strong>State:</strong> <code>{{ $state }}</code></p>
    </div>
    
    <h2>Authorization Code Flow Test</h2>
    <p>Click the button below to start the OAuth 2.0 authorization code flow. You'll be redirected to the authorization server, where you can log in and grant permissions.</p>
    
    <a href="{{ $authUrl }}" class="btn">Start OAuth Flow</a>
    
    <div class="info-box">
        <h3>What happens next:</h3>
        <ol>
            <li>You'll be redirected to the authorization server</li>
            <li>Log in with your credentials (if not already logged in)</li>
            <li>Grant permissions to this test client</li>
            <li>You'll be redirected back with an authorization code</li>
            <li>The code will be exchanged for access and ID tokens</li>
            <li>User information will be fetched and displayed</li>
        </ol>
    </div>
    
    <h2>Authorization URL</h2>
    <div class="info-box">
        <code style="word-break: break-all;">{{ $authUrl }}</code>
    </div>
    
    <h2>Discovery Endpoints</h2>
    <div class="info-box">
        <p><strong>OAuth 2.0 Discovery:</strong> <a href="http://localhost:8000/.well-known/oauth-authorization-server" target="_blank">/.well-known/oauth-authorization-server</a></p>
        <p><strong>OIDC Discovery:</strong> <a href="http://localhost:8000/.well-known/openid_configuration" target="_blank">/.well-known/openid_configuration</a></p>
    </div>
</body>
</html>