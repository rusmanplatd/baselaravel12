<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OAuth 2.0 / OpenID Connect Client</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f8f9fa;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 2.5rem;
        }
        
        .header p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }
        
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
        
        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .card h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.5rem;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        
        .checkbox-group {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
            margin-top: 10px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        
        .checkbox-item:hover {
            border-color: #3498db;
            background: #f8f9ff;
        }
        
        .checkbox-item input[type="checkbox"] {
            margin-top: 2px;
        }
        
        .checkbox-item.selected {
            border-color: #3498db;
            background: #e3f2fd;
        }
        
        .checkbox-item .scope-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .checkbox-item .scope-description {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-top: 3px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }
        
        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-secondary {
            background: #95a5a6;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .btn-danger {
            background: #e74c3c;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .info-box {
            background: #ecf0f1;
            border: 1px solid #bdc3c7;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .info-box h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .info-item:last-child {
            margin-bottom: 0;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
        }
        
        .info-value {
            font-family: 'Monaco', 'Consolas', monospace;
            background: #2c3e50;
            color: #ecf0f1;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .endpoints {
            display: grid;
            gap: 10px;
        }
        
        .endpoint-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }
        
        .endpoint-name {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .endpoint-url {
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 0.9rem;
            color: #7f8c8d;
        }
        
        .endpoint-url a {
            color: #3498db;
            text-decoration: none;
        }
        
        .endpoint-url a:hover {
            text-decoration: underline;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-indicator.ready {
            background: #27ae60;
        }
        
        .status-indicator.missing {
            background: #e74c3c;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-warning {
            background: #fef9e7;
            border-left-color: #f39c12;
            color: #8a6d3b;
        }
        
        .alert-info {
            background: #e8f4f8;
            border-left-color: #3498db;
            color: #31708f;
        }
        
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>OAuth 2.0 / OpenID Connect Client</h1>
            <p>Test your OAuth 2.0 and OpenID Connect implementation</p>
        </div>

        @if(empty($clientSecret))
        <div class="alert alert-warning full-width">
            <strong>Warning:</strong> OAUTH_CLIENT_SECRET environment variable is not set. 
            Token exchange may fail. Please set this in your .env file.
        </div>
        @endif

        <div class="grid">
            <!-- Authorization Flow -->
            <div class="card">
                <h2>🔐 Authorization Flow</h2>
                <form id="oauth-form" action="{{ route('oauth.authorize') }}" method="POST">
                    @csrf
                    <div class="form-group">
                        <label>Select Scopes:</label>
                        <div class="checkbox-group">
                            @foreach($scopes as $scope => $description)
                            <label class="checkbox-item" data-scope="{{ $scope }}">
                                <input type="checkbox" name="scopes[]" value="{{ $scope }}" 
                                    {{ in_array($scope, ['openid', 'profile', 'email']) ? 'checked' : '' }}>
                                <div>
                                    <div class="scope-name">{{ $scope }}</div>
                                    <div class="scope-description">{{ $description }}</div>
                                </div>
                            </label>
                            @endforeach
                        </div>
                    </div>
                    
                    <button type="submit" class="btn" id="auth-btn">
                        <span class="status-indicator ready"></span>
                        Start OAuth Flow
                    </button>
                </form>

                <div class="info-box">
                    <h3>Flow Steps:</h3>
                    <ol>
                        <li>Select desired scopes above</li>
                        <li>Click "Start OAuth Flow" to begin</li>
                        <li>Authenticate on the authorization server</li>
                        <li>Grant permissions to this client</li>
                        <li>Get redirected back with tokens</li>
                        <li>View user information and token details</li>
                    </ol>
                </div>
            </div>

            <!-- Client Information -->
            <div class="card">
                <h2>🔑 Client Configuration</h2>
                <div class="info-item">
                    <span class="info-label">Client ID:</span>
                    <span class="info-value">a8704536-ee26-4675-b324-741444ffb54e</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Redirect URI:</span>
                    <span class="info-value">{{ url('/oauth/callback') }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Grant Types:</span>
                    <span class="info-value">authorization_code, refresh_token</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Client Secret:</span>
                    <span class="info-value">
                        @if($clientSecret)
                            <span class="status-indicator ready"></span>Configured
                        @else
                            <span class="status-indicator missing"></span>Missing
                        @endif
                    </span>
                </div>

                <div class="info-box">
                    <h3>🔧 Token Operations</h3>
                    <p>After completing the OAuth flow, you'll be able to:</p>
                    <ul style="margin-left: 20px; margin-top: 10px;">
                        <li>View access and ID tokens</li>
                        <li>Test token refresh</li>
                        <li>Revoke tokens</li>
                        <li>Inspect user information</li>
                    </ul>
                </div>
            </div>

            <!-- Discovery Endpoints -->
            <div class="card">
                <h2>🌐 Discovery Endpoints</h2>
                <div class="endpoints">
                    <div class="endpoint-item">
                        <span class="endpoint-name">OAuth 2.0 Discovery</span>
                        <span class="endpoint-url">
                            <a href="http://localhost:8000/.well-known/oauth-authorization-server" target="_blank">
                                /.well-known/oauth-authorization-server
                            </a>
                        </span>
                    </div>
                    <div class="endpoint-item">
                        <span class="endpoint-name">OIDC Discovery</span>
                        <span class="endpoint-url">
                            <a href="http://localhost:8000/.well-known/openid_configuration" target="_blank">
                                /.well-known/openid_configuration
                            </a>
                        </span>
                    </div>
                </div>

                <button onclick="loadDiscoveryInfo()" class="btn btn-secondary" style="margin-top: 20px;">
                    <span id="discovery-loading" class="loading hidden"></span>
                    Load Discovery Info
                </button>

                <div id="discovery-info" class="info-box hidden">
                    <h3>📋 Server Capabilities</h3>
                    <div id="discovery-content"></div>
                </div>
            </div>

            <!-- Help & Documentation -->
            <div class="card">
                <h2>📚 Help & Examples</h2>
                <div class="alert alert-info">
                    <strong>Testing Tips:</strong><br>
                    • Start with basic scopes (openid, profile, email)<br>
                    • Check browser developer tools for detailed requests<br>
                    • Use different browsers to test fresh sessions<br>
                    • Try revoking tokens to test cleanup
                </div>

                <div class="info-box">
                    <h3>🔗 Useful Links</h3>
                    <div class="endpoints">
                        <div class="endpoint-item">
                            <span class="endpoint-name">Authorization Server</span>
                            <span class="endpoint-url">
                                <a href="http://localhost:8000/oauth/clients" target="_blank">Client Management</a>
                            </span>
                        </div>
                        <div class="endpoint-item">
                            <span class="endpoint-name">OAuth 2.0 Spec</span>
                            <span class="endpoint-url">
                                <a href="https://tools.ietf.org/html/rfc6749" target="_blank">RFC 6749</a>
                            </span>
                        </div>
                        <div class="endpoint-item">
                            <span class="endpoint-name">OpenID Connect</span>
                            <span class="endpoint-url">
                                <a href="https://openid.net/specs/openid-connect-core-1_0.html" target="_blank">Specification</a>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Enhanced checkbox interactions
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxItems = document.querySelectorAll('.checkbox-item');
            
            checkboxItems.forEach(item => {
                const checkbox = item.querySelector('input[type="checkbox"]');
                
                // Update visual state
                function updateState() {
                    if (checkbox.checked) {
                        item.classList.add('selected');
                    } else {
                        item.classList.remove('selected');
                    }
                }
                
                // Initialize state
                updateState();
                
                // Handle clicks
                checkbox.addEventListener('change', updateState);
                item.addEventListener('click', function(e) {
                    if (e.target !== checkbox) {
                        checkbox.checked = !checkbox.checked;
                        updateState();
                    }
                });
            });
        });

        // Discovery information loader
        async function loadDiscoveryInfo() {
            const button = document.querySelector('button[onclick="loadDiscoveryInfo()"]');
            const loading = document.getElementById('discovery-loading');
            const infoDiv = document.getElementById('discovery-info');
            const contentDiv = document.getElementById('discovery-content');
            
            loading.classList.remove('hidden');
            button.disabled = true;
            
            try {
                const response = await fetch('{{ route("oauth.discovery") }}');
                const data = await response.json();
                
                if (data.error) {
                    contentDiv.innerHTML = `<div class="alert alert-warning">Error: ${data.error_description || data.error}</div>`;
                } else {
                    let content = '<div class="endpoints">';
                    
                    if (data.oauth2) {
                        content += '<h4 style="margin-bottom: 10px; color: #2c3e50;">OAuth 2.0 Endpoints</h4>';
                        content += `<div class="endpoint-item">
                            <span class="endpoint-name">Authorization</span>
                            <span class="endpoint-url">${data.oauth2.authorization_endpoint || 'N/A'}</span>
                        </div>`;
                        content += `<div class="endpoint-item">
                            <span class="endpoint-name">Token</span>
                            <span class="endpoint-url">${data.oauth2.token_endpoint || 'N/A'}</span>
                        </div>`;
                        content += `<div class="endpoint-item">
                            <span class="endpoint-name">Supported Grants</span>
                            <span class="endpoint-url">${(data.oauth2.grant_types_supported || []).join(', ')}</span>
                        </div>`;
                    }
                    
                    if (data.oidc) {
                        content += '<h4 style="margin: 20px 0 10px; color: #2c3e50;">OpenID Connect Endpoints</h4>';
                        content += `<div class="endpoint-item">
                            <span class="endpoint-name">UserInfo</span>
                            <span class="endpoint-url">${data.oidc.userinfo_endpoint || 'N/A'}</span>
                        </div>`;
                        content += `<div class="endpoint-item">
                            <span class="endpoint-name">JWKS</span>
                            <span class="endpoint-url">${data.oidc.jwks_uri || 'N/A'}</span>
                        </div>`;
                        content += `<div class="endpoint-item">
                            <span class="endpoint-name">Supported Scopes</span>
                            <span class="endpoint-url">${(data.oidc.scopes_supported || []).join(', ')}</span>
                        </div>`;
                    }
                    
                    content += '</div>';
                    contentDiv.innerHTML = content;
                }
                
                infoDiv.classList.remove('hidden');
                
            } catch (error) {
                contentDiv.innerHTML = `<div class="alert alert-warning">Network error: ${error.message}</div>`;
                infoDiv.classList.remove('hidden');
            } finally {
                loading.classList.add('hidden');
                button.disabled = false;
            }
        }
    </script>
</body>
</html>