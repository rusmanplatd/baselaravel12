<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OAuth 2.0 Flow Result</title>
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
            margin-bottom: 30px;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header.success {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
        }
        
        .header.error {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .status-badge.success {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .status-badge.error {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
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
            font-size: 1.4rem;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        
        .card h3 {
            color: #34495e;
            margin: 20px 0 10px 0;
            font-size: 1.1rem;
        }
        
        .token-container {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 0.85rem;
            line-height: 1.4;
            overflow-x: auto;
            margin: 15px 0;
            position: relative;
        }
        
        .token-container .copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #3498db;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            opacity: 0.8;
        }
        
        .token-container .copy-btn:hover {
            opacity: 1;
        }
        
        .info-grid {
            display: grid;
            gap: 15px;
        }
        
        .info-item {
            display: grid;
            grid-template-columns: 120px 1fr;
            align-items: start;
            gap: 15px;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
        }
        
        .info-value {
            font-family: 'Monaco', 'Consolas', monospace;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 0.9rem;
            border: 1px solid #e9ecef;
            word-break: break-all;
        }
        
        .info-value.json {
            white-space: pre-wrap;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            border: none;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            margin: 5px;
        }
        
        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #27ae60;
        }
        
        .btn-success:hover {
            background: #219a52;
        }
        
        .btn-danger {
            background: #e74c3c;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-secondary {
            background: #95a5a6;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .actions {
            text-align: center;
            margin-top: 30px;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-error {
            background: #fdf2f2;
            border-left-color: #e74c3c;
            color: #721c24;
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
        
        .alert-success {
            background: #d4edda;
            border-left-color: #27ae60;
            color: #155724;
        }
        
        .loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .hidden {
            display: none;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .timing {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-top: 10px;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #3498db;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 15px auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header {{ $success ? 'success' : 'error' }}">
            @if($success)
                <div class="status-badge success">✅ OAuth Flow Completed</div>
                <h1>Success!</h1>
                <p>OAuth 2.0 authorization flow completed successfully</p>
                @if($flow_duration)
                    <div class="timing">Flow completed in {{ $flow_duration }}ms</div>
                @endif
            @else
                <div class="status-badge error">❌ OAuth Flow Failed</div>
                <h1>Error</h1>
                <p>OAuth 2.0 authorization flow failed at {{ $step ?? 'unknown' }} step</p>
            @endif
        </div>

        @if(!$success)
            <!-- Error Information -->
            <div class="grid">
                <div class="card full-width">
                    <h2>🚨 Error Details</h2>
                    <div class="alert alert-error">
                        <h3>{{ $error ?? 'Unknown Error' }}</h3>
                        @if($error_description)
                            <p>{{ $error_description }}</p>
                        @endif
                    </div>

                    @if($step)
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Failed at:</span>
                            <span class="info-value">{{ ucfirst(str_replace('_', ' ', $step)) }}</span>
                        </div>
                    </div>
                    @endif

                    @if(isset($token_response))
                    <h3>🔍 Server Response</h3>
                    <div class="token-container">
                        <button class="copy-btn" onclick="copyToClipboard(this, 'token-response')">Copy</button>
                        <pre id="token-response">{{ json_encode($token_response, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                    @endif
                </div>
            </div>
        @else
            <!-- Success Information -->
            <div class="grid">
                @if($user_info)
                <!-- User Information -->
                <div class="card">
                    <h2>👤 User Information</h2>
                    @if(isset($user_info['name']))
                        <div class="user-avatar">
                            {{ strtoupper(substr($user_info['name'], 0, 1)) }}
                        </div>
                    @endif

                    <div class="info-grid">
                        @foreach($user_info as $key => $value)
                            <div class="info-item">
                                <span class="info-label">{{ ucfirst(str_replace('_', ' ', $key)) }}:</span>
                                <span class="info-value">{{ is_array($value) ? json_encode($value) : $value }}</span>
                            </div>
                        @endforeach
                    </div>

                    @if($user_info_error)
                        <div class="alert alert-warning">
                            <strong>UserInfo Error:</strong> {{ $user_info_error }}
                        </div>
                    @endif
                </div>
                @endif

                <!-- Token Information -->
                <div class="card">
                    <h2>🔑 Tokens</h2>
                    
                    @if(isset($tokens['access_token']))
                    <h3>Access Token</h3>
                    <div class="token-container">
                        <button class="copy-btn" onclick="copyToClipboard(this, 'access-token')">Copy</button>
                        <div id="access-token">{{ $tokens['access_token'] }}</div>
                    </div>

                    <div class="info-grid">
                        @if(isset($tokens['token_type']))
                            <div class="info-item">
                                <span class="info-label">Type:</span>
                                <span class="info-value">{{ $tokens['token_type'] }}</span>
                            </div>
                        @endif
                        @if(isset($tokens['expires_in']))
                            <div class="info-item">
                                <span class="info-label">Expires in:</span>
                                <span class="info-value">{{ $tokens['expires_in'] }} seconds</span>
                            </div>
                        @endif
                        @if(isset($tokens['scope']))
                            <div class="info-item">
                                <span class="info-label">Scope:</span>
                                <span class="info-value">{{ $tokens['scope'] }}</span>
                            </div>
                        @endif
                    </div>
                    @endif

                    @if(isset($tokens['refresh_token']))
                    <h3>Refresh Token</h3>
                    <div class="token-container">
                        <button class="copy-btn" onclick="copyToClipboard(this, 'refresh-token')">Copy</button>
                        <div id="refresh-token">{{ $tokens['refresh_token'] }}</div>
                    </div>
                    @endif
                </div>

                @if(isset($tokens['id_token']))
                <!-- ID Token -->
                <div class="card full-width">
                    <h2>🎫 ID Token (OpenID Connect)</h2>
                    
                    <h3>Raw Token</h3>
                    <div class="token-container">
                        <button class="copy-btn" onclick="copyToClipboard(this, 'id-token-raw')">Copy</button>
                        <div id="id-token-raw">{{ $tokens['id_token'] }}</div>
                    </div>

                    @if($id_token_claims)
                    <h3>Decoded Claims</h3>
                    <div class="token-container">
                        <button class="copy-btn" onclick="copyToClipboard(this, 'id-token-claims')">Copy</button>
                        <pre id="id-token-claims">{{ json_encode($id_token_claims, JSON_PRETTY_PRINT) }}</pre>
                    </div>

                    <div class="alert alert-info">
                        <strong>Note:</strong> ID token signature verification is not implemented in this demo client. 
                        In production, you should verify the token signature using the JWKS endpoint.
                    </div>
                    @endif
                </div>
                @endif

                <!-- Token Operations -->
                @if(isset($tokens['refresh_token']) || isset($tokens['access_token']))
                <div class="card full-width">
                    <h2>🔧 Token Operations</h2>
                    
                    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 20px;">
                        @if(isset($tokens['refresh_token']))
                        <div>
                            <h3>🔄 Refresh Token</h3>
                            <button onclick="refreshToken('{{ $tokens['refresh_token'] }}')" class="btn btn-success">
                                <span id="refresh-loading" class="loading hidden"></span>
                                Refresh Access Token
                            </button>
                            <div id="refresh-result" class="hidden"></div>
                        </div>
                        @endif

                        @if(isset($tokens['access_token']))
                        <div>
                            <h3>🗑️ Revoke Token</h3>
                            <button onclick="revokeToken('{{ $tokens['access_token'] }}', 'access_token')" class="btn btn-danger">
                                <span id="revoke-loading" class="loading hidden"></span>
                                Revoke Access Token
                            </button>
                            <div id="revoke-result" class="hidden"></div>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        @endif

        <!-- Actions -->
        <div class="actions">
            <a href="{{ route('oauth.index') }}" class="btn">
                🏠 Back to OAuth Dashboard
            </a>
            <a href="{{ url('/') }}" class="btn btn-secondary">
                🏠 Home
            </a>
        </div>
    </div>

    <script>
        // Copy to clipboard functionality
        async function copyToClipboard(button, elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent || element.innerText;
            
            try {
                await navigator.clipboard.writeText(text);
                const originalText = button.textContent;
                button.textContent = 'Copied!';
                setTimeout(() => {
                    button.textContent = originalText;
                }, 2000);
            } catch (err) {
                console.error('Failed to copy: ', err);
                button.textContent = 'Failed';
                setTimeout(() => {
                    button.textContent = 'Copy';
                }, 2000);
            }
        }

        // Refresh token functionality
        async function refreshToken(refreshToken) {
            const loadingEl = document.getElementById('refresh-loading');
            const resultEl = document.getElementById('refresh-result');
            
            loadingEl.classList.remove('hidden');
            resultEl.classList.add('hidden');
            
            try {
                const response = await fetch('{{ route("oauth.refresh") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ refresh_token: refreshToken })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultEl.innerHTML = `
                        <div class="alert alert-success">
                            <strong>Success!</strong> Token refreshed successfully.
                            <div class="token-container" style="margin-top: 10px;">
                                <strong>New Access Token:</strong><br>
                                ${data.tokens.access_token}
                            </div>
                        </div>
                    `;
                } else {
                    resultEl.innerHTML = `
                        <div class="alert alert-error">
                            <strong>Error:</strong> ${data.error_description || data.error}
                        </div>
                    `;
                }
                
                resultEl.classList.remove('hidden');
                
            } catch (error) {
                resultEl.innerHTML = `
                    <div class="alert alert-error">
                        <strong>Network Error:</strong> ${error.message}
                    </div>
                `;
                resultEl.classList.remove('hidden');
            } finally {
                loadingEl.classList.add('hidden');
            }
        }

        // Revoke token functionality
        async function revokeToken(token, tokenType) {
            const loadingEl = document.getElementById('revoke-loading');
            const resultEl = document.getElementById('revoke-result');
            
            loadingEl.classList.remove('hidden');
            resultEl.classList.add('hidden');
            
            try {
                const response = await fetch('{{ route("oauth.revoke") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ 
                        token: token,
                        token_type_hint: tokenType
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultEl.innerHTML = `
                        <div class="alert alert-success">
                            <strong>Success!</strong> Token revoked successfully.
                        </div>
                    `;
                } else {
                    resultEl.innerHTML = `
                        <div class="alert alert-error">
                            <strong>Error:</strong> Failed to revoke token.
                        </div>
                    `;
                }
                
                resultEl.classList.remove('hidden');
                
            } catch (error) {
                resultEl.innerHTML = `
                    <div class="alert alert-error">
                        <strong>Network Error:</strong> ${error.message}
                    </div>
                `;
                resultEl.classList.remove('hidden');
            } finally {
                loadingEl.classList.add('hidden');
            }
        }
    </script>
</body>
</html>