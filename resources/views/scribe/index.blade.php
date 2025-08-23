<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="IE=edge,chrome=1" http-equiv="X-UA-Compatible">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Laravel API Documentation</title>

    <link href="https://fonts.googleapis.com/css?family=Open+Sans&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.style.css") }}" media="screen">
    <link rel="stylesheet" href="{{ asset("/vendor/scribe/css/theme-default.print.css") }}" media="print">

    <script src="https://cdn.jsdelivr.net/npm/lodash@4.17.10/lodash.min.js"></script>

    <link rel="stylesheet"
          href="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/styles/obsidian.min.css">
    <script src="https://unpkg.com/@highlightjs/cdn-assets@11.6.0/highlight.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jets/0.14.1/jets.min.js"></script>

    <style id="language-style">
        /* starts out as display none and is replaced with js later  */
                    body .content .bash-example code { display: none; }
                    body .content .javascript-example code { display: none; }
            </style>

    <script>
        var tryItOutBaseUrl = "http://localhost:8000";
        var useCsrf = Boolean();
        var csrfUrl = "/sanctum/csrf-cookie";
    </script>
    <script src="{{ asset("/vendor/scribe/js/tryitout-5.3.0.js") }}"></script>

    <script src="{{ asset("/vendor/scribe/js/theme-default-5.3.0.js") }}"></script>

</head>

<body data-languages="[&quot;bash&quot;,&quot;javascript&quot;]">

<a href="#" id="nav-button">
    <span>
        MENU
        <img src="{{ asset("/vendor/scribe/images/navbar.png") }}" alt="navbar-image"/>
    </span>
</a>
<div class="tocify-wrapper">
    
            <div class="lang-selector">
                                            <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                            <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                    </div>
    
    <div class="search">
        <input type="text" class="search" id="input-search" placeholder="Search">
    </div>

    <div id="toc">
                    <ul id="tocify-header-introduction" class="tocify-header">
                <li class="tocify-item level-1" data-unique="introduction">
                    <a href="#introduction">Introduction</a>
                </li>
                            </ul>
                    <ul id="tocify-header-authenticating-requests" class="tocify-header">
                <li class="tocify-item level-1" data-unique="authenticating-requests">
                    <a href="#authenticating-requests">Authenticating requests</a>
                </li>
                            </ul>
                    <ul id="tocify-header-endpoints" class="tocify-header">
                <li class="tocify-item level-1" data-unique="endpoints">
                    <a href="#endpoints">Endpoints</a>
                </li>
                                    <ul id="tocify-subheader-endpoints" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="endpoints-GETapi-user">
                                <a href="#endpoints-GETapi-user">GET api/user</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-organization-units">
                                <a href="#endpoints-GETapi-v1-organization-units">GET api/v1/organization-units</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-organization-units">
                                <a href="#endpoints-POSTapi-v1-organization-units">POST api/v1/organization-units</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-organization-units--id-">
                                <a href="#endpoints-GETapi-v1-organization-units--id-">GET api/v1/organization-units/{id}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-PUTapi-v1-organization-units--id-">
                                <a href="#endpoints-PUTapi-v1-organization-units--id-">PUT api/v1/organization-units/{id}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-DELETEapi-v1-organization-units--id-">
                                <a href="#endpoints-DELETEapi-v1-organization-units--id-">DELETE api/v1/organization-units/{id}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-organization-units-hierarchy-tree">
                                <a href="#endpoints-GETapi-v1-organization-units-hierarchy-tree">GET api/v1/organization-units/hierarchy/tree</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-organization-units-type--type-">
                                <a href="#endpoints-GETapi-v1-organization-units-type--type-">GET api/v1/organization-units/type/{type}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-organization-position-levels">
                                <a href="#endpoints-GETapi-v1-organization-position-levels">Display a listing of the resource.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-organization-position-levels">
                                <a href="#endpoints-POSTapi-v1-organization-position-levels">Store a newly created resource in storage.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-organization-position-levels--id-">
                                <a href="#endpoints-GETapi-v1-organization-position-levels--id-">Display the specified resource.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-PUTapi-v1-organization-position-levels--id-">
                                <a href="#endpoints-PUTapi-v1-organization-position-levels--id-">Update the specified resource in storage.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-DELETEapi-v1-organization-position-levels--id-">
                                <a href="#endpoints-DELETEapi-v1-organization-position-levels--id-">Remove the specified resource from storage.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-organization-position-levels-active">
                                <a href="#endpoints-GETapi-v1-organization-position-levels-active">Get active organization position levels.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-organization-position-levels-hierarchy">
                                <a href="#endpoints-GETapi-v1-organization-position-levels-hierarchy">Get organization position levels by hierarchy.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-organization-positions">
                                <a href="#endpoints-GETapi-v1-organization-positions">GET api/v1/organization-positions</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-organization-positions">
                                <a href="#endpoints-POSTapi-v1-organization-positions">POST api/v1/organization-positions</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-organization-positions--id-">
                                <a href="#endpoints-GETapi-v1-organization-positions--id-">GET api/v1/organization-positions/{id}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-PUTapi-v1-organization-positions--id-">
                                <a href="#endpoints-PUTapi-v1-organization-positions--id-">PUT api/v1/organization-positions/{id}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-DELETEapi-v1-organization-positions--id-">
                                <a href="#endpoints-DELETEapi-v1-organization-positions--id-">DELETE api/v1/organization-positions/{id}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-organization-positions-available">
                                <a href="#endpoints-GETapi-v1-organization-positions-available">GET api/v1/organization-positions/available</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-organization-positions-level--level-">
                                <a href="#endpoints-GETapi-v1-organization-positions-level--level-">GET api/v1/organization-positions/level/{level}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-organization-positions--organizationPosition_id--incumbents">
                                <a href="#endpoints-GETapi-v1-organization-positions--organizationPosition_id--incumbents">GET api/v1/organization-positions/{organizationPosition_id}/incumbents</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-organization-memberships">
                                <a href="#endpoints-GETapi-v1-organization-memberships">GET api/v1/organization-memberships</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-organization-memberships">
                                <a href="#endpoints-POSTapi-v1-organization-memberships">POST api/v1/organization-memberships</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-organization-memberships--id-">
                                <a href="#endpoints-GETapi-v1-organization-memberships--id-">GET api/v1/organization-memberships/{id}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-PUTapi-v1-organization-memberships--id-">
                                <a href="#endpoints-PUTapi-v1-organization-memberships--id-">PUT api/v1/organization-memberships/{id}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-DELETEapi-v1-organization-memberships--id-">
                                <a href="#endpoints-DELETEapi-v1-organization-memberships--id-">DELETE api/v1/organization-memberships/{id}</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-organization-memberships--organizationMembership_id--activate">
                                <a href="#endpoints-POSTapi-v1-organization-memberships--organizationMembership_id--activate">POST api/v1/organization-memberships/{organizationMembership_id}/activate</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-organization-memberships--organizationMembership_id--deactivate">
                                <a href="#endpoints-POSTapi-v1-organization-memberships--organizationMembership_id--deactivate">POST api/v1/organization-memberships/{organizationMembership_id}/deactivate</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-organization-memberships--organizationMembership_id--terminate">
                                <a href="#endpoints-POSTapi-v1-organization-memberships--organizationMembership_id--terminate">POST api/v1/organization-memberships/{organizationMembership_id}/terminate</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-users--user_id--memberships">
                                <a href="#endpoints-GETapi-v1-users--user_id--memberships">GET api/v1/users/{user_id}/memberships</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-organizations--organization_id--memberships">
                                <a href="#endpoints-GETapi-v1-organizations--organization_id--memberships">GET api/v1/organizations/{organization_id}/memberships</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-board-members">
                                <a href="#endpoints-GETapi-v1-board-members">GET api/v1/board-members</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-executives">
                                <a href="#endpoints-GETapi-v1-executives">GET api/v1/executives</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-auth-webauthn-options">
                                <a href="#endpoints-GETapi-v1-auth-webauthn-options">Get authentication options for WebAuthn login</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-auth-webauthn-authenticate">
                                <a href="#endpoints-POSTapi-v1-auth-webauthn-authenticate">Authenticate using WebAuthn (passwordless login)</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-auth-webauthn-capabilities">
                                <a href="#endpoints-GETapi-v1-auth-webauthn-capabilities">Get WebAuthn capabilities and user agent info</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-auth-webauthn-health">
                                <a href="#endpoints-GETapi-v1-auth-webauthn-health">Test WebAuthn connectivity and server readiness</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-webauthn">
                                <a href="#endpoints-GETapi-v1-webauthn">Get all passkeys for the authenticated user</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-webauthn-register-options">
                                <a href="#endpoints-GETapi-v1-webauthn-register-options">Get registration options for creating a new passkey</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-POSTapi-v1-webauthn-register">
                                <a href="#endpoints-POSTapi-v1-webauthn-register">Register a new passkey</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-PUTapi-v1-webauthn--passkey_id-">
                                <a href="#endpoints-PUTapi-v1-webauthn--passkey_id-">Update passkey name</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-DELETEapi-v1-webauthn--passkey_id-">
                                <a href="#endpoints-DELETEapi-v1-webauthn--passkey_id-">Delete a passkey</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-v1-webauthn-statistics">
                                <a href="#endpoints-GETapi-v1-webauthn-statistics">Get usage statistics for user's passkeys</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="endpoints-GETapi-organization-position-levels">
                                <a href="#endpoints-GETapi-organization-position-levels">Get organization position levels for API/select options.</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-multi-factor-authentication" class="tocify-header">
                <li class="tocify-item level-1" data-unique="multi-factor-authentication">
                    <a href="#multi-factor-authentication">Multi-Factor Authentication</a>
                </li>
                                    <ul id="tocify-subheader-multi-factor-authentication" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="multi-factor-authentication-GETapi-v1-mfa">
                                <a href="#multi-factor-authentication-GETapi-v1-mfa">Get MFA status</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="multi-factor-authentication-POSTapi-v1-mfa">
                                <a href="#multi-factor-authentication-POSTapi-v1-mfa">Initialize MFA setup</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="multi-factor-authentication-PUTapi-v1-mfa">
                                <a href="#multi-factor-authentication-PUTapi-v1-mfa">Confirm and fully enable MFA</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="multi-factor-authentication-DELETEapi-v1-mfa">
                                <a href="#multi-factor-authentication-DELETEapi-v1-mfa">Disable MFA for the authenticated user</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="multi-factor-authentication-POSTapi-v1-mfa-verify">
                                <a href="#multi-factor-authentication-POSTapi-v1-mfa-verify">Verify MFA code</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="multi-factor-authentication-GETapi-v1-mfa-backup-codes-status">
                                <a href="#multi-factor-authentication-GETapi-v1-mfa-backup-codes-status">Get remaining backup codes count</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="multi-factor-authentication-POSTapi-v1-mfa-backup-codes-regenerate">
                                <a href="#multi-factor-authentication-POSTapi-v1-mfa-backup-codes-regenerate">Regenerate backup codes</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-organization-management" class="tocify-header">
                <li class="tocify-item level-1" data-unique="organization-management">
                    <a href="#organization-management">Organization Management</a>
                </li>
                                    <ul id="tocify-subheader-organization-management" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="organization-management-GETapi-v1-organizations">
                                <a href="#organization-management-GETapi-v1-organizations">Get organizations</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="organization-management-POSTapi-v1-organizations">
                                <a href="#organization-management-POSTapi-v1-organizations">Create organization</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="organization-management-GETapi-v1-organizations--id-">
                                <a href="#organization-management-GETapi-v1-organizations--id-">Get organization details</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="organization-management-PUTapi-v1-organizations--id-">
                                <a href="#organization-management-PUTapi-v1-organizations--id-">Update organization</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="organization-management-DELETEapi-v1-organizations--id-">
                                <a href="#organization-management-DELETEapi-v1-organizations--id-">Delete organization</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="organization-management-GETapi-v1-organizations-hierarchy-tree">
                                <a href="#organization-management-GETapi-v1-organizations-hierarchy-tree">Get organization hierarchy</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="organization-management-GETapi-v1-organizations-type--type-">
                                <a href="#organization-management-GETapi-v1-organizations-type--type-">Get organizations by type</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="organization-management-GETapi-v1-organizations--organization_id--members">
                                <a href="#organization-management-GETapi-v1-organizations--organization_id--members">Get organization members</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="organization-management-POSTapi-v1-organizations--organization_id--members">
                                <a href="#organization-management-POSTapi-v1-organizations--organization_id--members">Add member to organization</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="organization-management-PUTapi-v1-organizations--organization_id--members--membership_id-">
                                <a href="#organization-management-PUTapi-v1-organizations--organization_id--members--membership_id-">Update organization membership</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="organization-management-DELETEapi-v1-organizations--organization_id--members--membership_id-">
                                <a href="#organization-management-DELETEapi-v1-organizations--organization_id--members--membership_id-">Remove member from organization</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="organization-management-GETapi-v1-organizations--organization_id--roles">
                                <a href="#organization-management-GETapi-v1-organizations--organization_id--roles">Get organization roles</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="organization-management-POSTapi-v1-organizations--organization_id--roles">
                                <a href="#organization-management-POSTapi-v1-organizations--organization_id--roles">Create organization role</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-role-permission-management" class="tocify-header">
                <li class="tocify-item level-1" data-unique="role-permission-management">
                    <a href="#role-permission-management">Role & Permission Management</a>
                </li>
                                    <ul id="tocify-subheader-role-permission-management" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="role-permission-management-GETapi-v1-roles">
                                <a href="#role-permission-management-GETapi-v1-roles">Get roles</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="role-permission-management-POSTapi-v1-roles">
                                <a href="#role-permission-management-POSTapi-v1-roles">Create role</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="role-permission-management-GETapi-v1-roles--id-">
                                <a href="#role-permission-management-GETapi-v1-roles--id-">Display the specified resource.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="role-permission-management-PUTapi-v1-roles--id-">
                                <a href="#role-permission-management-PUTapi-v1-roles--id-">Update the specified resource in storage.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="role-permission-management-DELETEapi-v1-roles--id-">
                                <a href="#role-permission-management-DELETEapi-v1-roles--id-">Remove the specified resource from storage.</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="role-permission-management-GETapi-v1-permissions">
                                <a href="#role-permission-management-GETapi-v1-permissions">Get all available permissions</a>
                            </li>
                                                                        </ul>
                            </ul>
                    <ul id="tocify-header-user-security" class="tocify-header">
                <li class="tocify-item level-1" data-unique="user-security">
                    <a href="#user-security">User Security</a>
                </li>
                                    <ul id="tocify-subheader-user-security" class="tocify-subheader">
                                                    <li class="tocify-item level-2" data-unique="user-security-GETapi-v1-security">
                                <a href="#user-security-GETapi-v1-security">Get security profile</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="user-security-GETapi-v1-security-activity">
                                <a href="#user-security-GETapi-v1-security-activity">Get security activity and audit log</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="user-security-GETapi-v1-security-recommendations">
                                <a href="#user-security-GETapi-v1-security-recommendations">Get account security recommendations</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="user-security-GETapi-v1-security-settings">
                                <a href="#user-security-GETapi-v1-security-settings">Get security settings summary</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="user-security-PUTapi-v1-security-settings">
                                <a href="#user-security-PUTapi-v1-security-settings">Update security settings</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="user-security-PUTapi-v1-security-password">
                                <a href="#user-security-PUTapi-v1-security-password">Update password</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="user-security-GETapi-v1-security-sessions">
                                <a href="#user-security-GETapi-v1-security-sessions">Get current active sessions</a>
                            </li>
                                                                                <li class="tocify-item level-2" data-unique="user-security-DELETEapi-v1-security-sessions">
                                <a href="#user-security-DELETEapi-v1-security-sessions">Revoke all sessions except current</a>
                            </li>
                                                                        </ul>
                            </ul>
            </div>

    <ul class="toc-footer" id="toc-footer">
                    <li style="padding-bottom: 5px;"><a href="{{ route("scribe.postman") }}">View Postman collection</a></li>
                            <li style="padding-bottom: 5px;"><a href="{{ route("scribe.openapi") }}">View OpenAPI spec</a></li>
                <li><a href="http://github.com/knuckleswtf/scribe">Documentation powered by Scribe ✍</a></li>
    </ul>

    <ul class="toc-footer" id="last-updated">
        <li>Last updated: August 23, 2025</li>
    </ul>
</div>

<div class="page-wrapper">
    <div class="dark-box"></div>
    <div class="content">
        <h1 id="introduction">Introduction</h1>
<p>Laravel 12 + React fullstack application API with OAuth 2.0/OIDC provider, organization management, and comprehensive authentication features.</p>
<aside>
    <strong>Base URL</strong>: <code>http://localhost:8000</code>
</aside>
<pre><code>This documentation aims to provide all the information you need to work with our API.

&lt;aside&gt;As you scroll, you'll see code examples for working with the API in different programming languages in the dark area to the right (or as part of the content on mobile).
You can switch the language used with the tabs at the top right (or from the nav menu at the top left on mobile).&lt;/aside&gt;</code></pre>

        <h1 id="authenticating-requests">Authenticating requests</h1>
<p>To authenticate requests, include an <strong><code>Authorization</code></strong> header with the value <strong><code>"Bearer {YOUR_AUTH_KEY}"</code></strong>.</p>
<p>All authenticated endpoints are marked with a <code>requires authentication</code> badge in the documentation below.</p>
<p>You can obtain an access token via OAuth 2.0 authorization flow or by using your existing Laravel session. For OAuth flows, see the OAuth 2.0 endpoints below.</p>

        <h1 id="endpoints">Endpoints</h1>

    

                                <h2 id="endpoints-GETapi-user">GET api/user</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-user">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/user" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/user"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-user">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-user" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-user"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-user"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-user" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-user">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-user" data-method="GET"
      data-path="api/user"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-user', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-user"
                    onclick="tryItOut('GETapi-user');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-user"
                    onclick="cancelTryOut('GETapi-user');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-user"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/user</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-user"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-user"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-user"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-GETapi-v1-organization-units">GET api/v1/organization-units</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-organization-units">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organization-units" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-units"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organization-units">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">[
    {
        &quot;id&quot;: &quot;01k3ahgd7q224nvfd03a4gv88n&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd3y0y9a6ca0k9v0259t&quot;,
        &quot;unit_code&quot;: &quot;AI001&quot;,
        &quot;name&quot;: &quot;AI Research Division&quot;,
        &quot;unit_type&quot;: &quot;division&quot;,
        &quot;description&quot;: &quot;Artificial intelligence research and development&quot;,
        &quot;parent_unit_id&quot;: null,
        &quot;responsibilities&quot;: [
            &quot;AI research&quot;,
            &quot;Machine learning development&quot;,
            &quot;Algorithm optimization&quot;,
            &quot;AI product development&quot;
        ],
        &quot;authorities&quot;: [
            &quot;Conduct research projects&quot;,
            &quot;Develop AI models&quot;,
            &quot;Publish research findings&quot;,
            &quot;Collaborate with academia&quot;
        ],
        &quot;is_active&quot;: true,
        &quot;sort_order&quot;: 1,
        &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd3y0y9a6ca0k9v0259t&quot;,
            &quot;organization_code&quot;: &quot;SUB002&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Data&quot;,
            &quot;description&quot;: &quot;Data analytics and AI solutions&quot;,
            &quot;address&quot;: &quot;789 Data Drive, Analytics Park&quot;,
            &quot;phone&quot;: &quot;+1-555-0300&quot;,
            &quot;email&quot;: &quot;info@techcorpdata.com&quot;,
            &quot;website&quot;: &quot;https://techcorpdata.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG003&quot;,
            &quot;tax_number&quot;: &quot;TAX003&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;3000000.00&quot;,
            &quot;paid_capital&quot;: &quot;2500000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-06-15T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Data analytics, machine learning, AI consulting&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;David Brown&quot;,
                    &quot;email&quot;: &quot;md@techcorpdata.com&quot;
                },
                &quot;head_of_ai&quot;: {
                    &quot;name&quot;: &quot;Emily Davis&quot;,
                    &quot;email&quot;: &quot;ai@techcorpdata.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd3y0y9a6ca0k9v0259t&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;parent_unit&quot;: null,
        &quot;child_units&quot;: []
    },
    {
        &quot;id&quot;: &quot;01k3ahgd6b11gw0s93tp3rxz5y&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
        &quot;unit_code&quot;: &quot;BOC001&quot;,
        &quot;name&quot;: &quot;Board of Commissioners&quot;,
        &quot;unit_type&quot;: &quot;board_of_commissioners&quot;,
        &quot;description&quot;: &quot;Supervisory board responsible for oversight and strategic guidance&quot;,
        &quot;parent_unit_id&quot;: null,
        &quot;responsibilities&quot;: [
            &quot;Strategic oversight&quot;,
            &quot;Risk management oversight&quot;,
            &quot;Appointment of board of directors&quot;,
            &quot;Approval of major corporate actions&quot;
        ],
        &quot;authorities&quot;: [
            &quot;Approve annual budget&quot;,
            &quot;Appoint and dismiss directors&quot;,
            &quot;Approve major investments&quot;,
            &quot;Set executive compensation&quot;
        ],
        &quot;is_active&quot;: true,
        &quot;sort_order&quot;: 1,
        &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;organization_code&quot;: &quot;HC001&quot;,
            &quot;organization_type&quot;: &quot;holding_company&quot;,
            &quot;parent_organization_id&quot;: null,
            &quot;name&quot;: &quot;TechCorp Holdings&quot;,
            &quot;description&quot;: &quot;Main holding company for technology businesses&quot;,
            &quot;address&quot;: &quot;123 Tech Street, Innovation District&quot;,
            &quot;phone&quot;: &quot;+1-555-0100&quot;,
            &quot;email&quot;: &quot;info@techcorp.com&quot;,
            &quot;website&quot;: &quot;https://techcorp.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG001&quot;,
            &quot;tax_number&quot;: &quot;TAX001&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 7,
                &quot;independent_directors&quot;: 4,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;,
                    &quot;nomination&quot;,
                    &quot;remuneration&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;10000000.00&quot;,
            &quot;paid_capital&quot;: &quot;8500000.00&quot;,
            &quot;establishment_date&quot;: &quot;2020-01-15T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Public Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Investment holding and management&quot;,
            &quot;contact_persons&quot;: {
                &quot;ceo&quot;: {
                    &quot;name&quot;: &quot;John Smith&quot;,
                    &quot;email&quot;: &quot;ceo@techcorp.com&quot;
                },
                &quot;cfo&quot;: {
                    &quot;name&quot;: &quot;Jane Doe&quot;,
                    &quot;email&quot;: &quot;cfo@techcorp.com&quot;
                }
            },
            &quot;level&quot;: 0,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;parent_unit&quot;: null,
        &quot;child_units&quot;: [
            {
                &quot;id&quot;: &quot;01k3ahgd6nvm1ysj003zznhrz4&quot;,
                &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
                &quot;unit_code&quot;: &quot;AC001&quot;,
                &quot;name&quot;: &quot;Audit Committee&quot;,
                &quot;unit_type&quot;: &quot;audit_committee&quot;,
                &quot;description&quot;: &quot;Committee responsible for financial reporting and audit oversight&quot;,
                &quot;parent_unit_id&quot;: &quot;01k3ahgd6b11gw0s93tp3rxz5y&quot;,
                &quot;responsibilities&quot;: [
                    &quot;Financial reporting oversight&quot;,
                    &quot;Internal audit supervision&quot;,
                    &quot;External auditor management&quot;,
                    &quot;Compliance monitoring&quot;
                ],
                &quot;authorities&quot;: [
                    &quot;Review financial statements&quot;,
                    &quot;Appoint internal auditors&quot;,
                    &quot;Review audit findings&quot;,
                    &quot;Recommend corrective actions&quot;
                ],
                &quot;is_active&quot;: true,
                &quot;sort_order&quot;: 3,
                &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
                &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
                &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
                &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
            },
            {
                &quot;id&quot;: &quot;01k3ahgd6s0h9v3gc08nhw9em3&quot;,
                &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
                &quot;unit_code&quot;: &quot;RC001&quot;,
                &quot;name&quot;: &quot;Risk Committee&quot;,
                &quot;unit_type&quot;: &quot;risk_committee&quot;,
                &quot;description&quot;: &quot;Committee responsible for risk management oversight&quot;,
                &quot;parent_unit_id&quot;: &quot;01k3ahgd6b11gw0s93tp3rxz5y&quot;,
                &quot;responsibilities&quot;: [
                    &quot;Risk strategy oversight&quot;,
                    &quot;Risk appetite setting&quot;,
                    &quot;Risk monitoring&quot;,
                    &quot;Crisis management&quot;
                ],
                &quot;authorities&quot;: [
                    &quot;Set risk policies&quot;,
                    &quot;Review risk reports&quot;,
                    &quot;Approve risk limits&quot;,
                    &quot;Escalate major risks&quot;
                ],
                &quot;is_active&quot;: true,
                &quot;sort_order&quot;: 4,
                &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
                &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
                &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
                &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
            }
        ]
    },
    {
        &quot;id&quot;: &quot;01k3ahgd6x41jb3s6w1ekz6jra&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
        &quot;unit_code&quot;: &quot;EXEC001&quot;,
        &quot;name&quot;: &quot;Executive Office&quot;,
        &quot;unit_type&quot;: &quot;department&quot;,
        &quot;description&quot;: &quot;Executive leadership and strategic management&quot;,
        &quot;parent_unit_id&quot;: null,
        &quot;responsibilities&quot;: [
            &quot;Strategic planning&quot;,
            &quot;Corporate governance&quot;,
            &quot;Stakeholder management&quot;,
            &quot;Executive decision making&quot;
        ],
        &quot;authorities&quot;: [
            &quot;Set company direction&quot;,
            &quot;Approve major decisions&quot;,
            &quot;Represent company externally&quot;,
            &quot;Allocate resources&quot;
        ],
        &quot;is_active&quot;: true,
        &quot;sort_order&quot;: 1,
        &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;organization_code&quot;: &quot;SUB001&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Software&quot;,
            &quot;description&quot;: &quot;Software development and consulting services&quot;,
            &quot;address&quot;: &quot;456 Software Ave, Tech City&quot;,
            &quot;phone&quot;: &quot;+1-555-0200&quot;,
            &quot;email&quot;: &quot;info@techcorpsoftware.com&quot;,
            &quot;website&quot;: &quot;https://techcorpsoftware.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG002&quot;,
            &quot;tax_number&quot;: &quot;TAX002&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;5000000.00&quot;,
            &quot;paid_capital&quot;: &quot;4000000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-03-01T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Software development, web applications, mobile apps&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;Mike Johnson&quot;,
                    &quot;email&quot;: &quot;md@techcorpsoftware.com&quot;
                },
                &quot;cto&quot;: {
                    &quot;name&quot;: &quot;Sarah Wilson&quot;,
                    &quot;email&quot;: &quot;cto@techcorpsoftware.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;parent_unit&quot;: null,
        &quot;child_units&quot;: []
    },
    {
        &quot;id&quot;: &quot;01k3ahgd7617t0tmr9jsk9x1fb&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
        &quot;unit_code&quot;: &quot;FEND001&quot;,
        &quot;name&quot;: &quot;Frontend Development Team&quot;,
        &quot;unit_type&quot;: &quot;team&quot;,
        &quot;description&quot;: &quot;User interface and user experience development&quot;,
        &quot;parent_unit_id&quot;: &quot;01k3ahgd72vvytzzw7q4htmrd9&quot;,
        &quot;responsibilities&quot;: [
            &quot;UI/UX development&quot;,
            &quot;Frontend architecture&quot;,
            &quot;User interaction design&quot;,
            &quot;Frontend testing&quot;
        ],
        &quot;authorities&quot;: [
            &quot;Choose frontend frameworks&quot;,
            &quot;Design user interfaces&quot;,
            &quot;Implement frontend features&quot;,
            &quot;Optimize user experience&quot;
        ],
        &quot;is_active&quot;: true,
        &quot;sort_order&quot;: 1,
        &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;organization_code&quot;: &quot;SUB001&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Software&quot;,
            &quot;description&quot;: &quot;Software development and consulting services&quot;,
            &quot;address&quot;: &quot;456 Software Ave, Tech City&quot;,
            &quot;phone&quot;: &quot;+1-555-0200&quot;,
            &quot;email&quot;: &quot;info@techcorpsoftware.com&quot;,
            &quot;website&quot;: &quot;https://techcorpsoftware.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG002&quot;,
            &quot;tax_number&quot;: &quot;TAX002&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;5000000.00&quot;,
            &quot;paid_capital&quot;: &quot;4000000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-03-01T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Software development, web applications, mobile apps&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;Mike Johnson&quot;,
                    &quot;email&quot;: &quot;md@techcorpsoftware.com&quot;
                },
                &quot;cto&quot;: {
                    &quot;name&quot;: &quot;Sarah Wilson&quot;,
                    &quot;email&quot;: &quot;cto@techcorpsoftware.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;parent_unit&quot;: {
            &quot;id&quot;: &quot;01k3ahgd72vvytzzw7q4htmrd9&quot;,
            &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;unit_code&quot;: &quot;ENG001&quot;,
            &quot;name&quot;: &quot;Engineering Division&quot;,
            &quot;unit_type&quot;: &quot;division&quot;,
            &quot;description&quot;: &quot;Software engineering and development&quot;,
            &quot;parent_unit_id&quot;: null,
            &quot;responsibilities&quot;: [
                &quot;Software development&quot;,
                &quot;Technical architecture&quot;,
                &quot;Code quality assurance&quot;,
                &quot;Development methodology&quot;
            ],
            &quot;authorities&quot;: [
                &quot;Define technical standards&quot;,
                &quot;Approve technical designs&quot;,
                &quot;Manage development teams&quot;,
                &quot;Release software products&quot;
            ],
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 2,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;child_units&quot;: []
    },
    {
        &quot;id&quot;: &quot;01k3ahgd7agwyj7t6x83pked0q&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
        &quot;unit_code&quot;: &quot;BEND001&quot;,
        &quot;name&quot;: &quot;Backend Development Team&quot;,
        &quot;unit_type&quot;: &quot;team&quot;,
        &quot;description&quot;: &quot;Server-side development and API services&quot;,
        &quot;parent_unit_id&quot;: &quot;01k3ahgd72vvytzzw7q4htmrd9&quot;,
        &quot;responsibilities&quot;: [
            &quot;API development&quot;,
            &quot;Database design&quot;,
            &quot;Server architecture&quot;,
            &quot;Backend testing&quot;
        ],
        &quot;authorities&quot;: [
            &quot;Design database schemas&quot;,
            &quot;Implement business logic&quot;,
            &quot;Develop APIs&quot;,
            &quot;Optimize performance&quot;
        ],
        &quot;is_active&quot;: true,
        &quot;sort_order&quot;: 2,
        &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;organization_code&quot;: &quot;SUB001&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Software&quot;,
            &quot;description&quot;: &quot;Software development and consulting services&quot;,
            &quot;address&quot;: &quot;456 Software Ave, Tech City&quot;,
            &quot;phone&quot;: &quot;+1-555-0200&quot;,
            &quot;email&quot;: &quot;info@techcorpsoftware.com&quot;,
            &quot;website&quot;: &quot;https://techcorpsoftware.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG002&quot;,
            &quot;tax_number&quot;: &quot;TAX002&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;5000000.00&quot;,
            &quot;paid_capital&quot;: &quot;4000000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-03-01T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Software development, web applications, mobile apps&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;Mike Johnson&quot;,
                    &quot;email&quot;: &quot;md@techcorpsoftware.com&quot;
                },
                &quot;cto&quot;: {
                    &quot;name&quot;: &quot;Sarah Wilson&quot;,
                    &quot;email&quot;: &quot;cto@techcorpsoftware.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;parent_unit&quot;: {
            &quot;id&quot;: &quot;01k3ahgd72vvytzzw7q4htmrd9&quot;,
            &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;unit_code&quot;: &quot;ENG001&quot;,
            &quot;name&quot;: &quot;Engineering Division&quot;,
            &quot;unit_type&quot;: &quot;division&quot;,
            &quot;description&quot;: &quot;Software engineering and development&quot;,
            &quot;parent_unit_id&quot;: null,
            &quot;responsibilities&quot;: [
                &quot;Software development&quot;,
                &quot;Technical architecture&quot;,
                &quot;Code quality assurance&quot;,
                &quot;Development methodology&quot;
            ],
            &quot;authorities&quot;: [
                &quot;Define technical standards&quot;,
                &quot;Approve technical designs&quot;,
                &quot;Manage development teams&quot;,
                &quot;Release software products&quot;
            ],
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 2,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;child_units&quot;: []
    },
    {
        &quot;id&quot;: &quot;01k3ahgd6h94mgvsspwdksgzyv&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
        &quot;unit_code&quot;: &quot;BOD001&quot;,
        &quot;name&quot;: &quot;Board of Directors&quot;,
        &quot;unit_type&quot;: &quot;board_of_directors&quot;,
        &quot;description&quot;: &quot;Executive board responsible for day-to-day management&quot;,
        &quot;parent_unit_id&quot;: null,
        &quot;responsibilities&quot;: [
            &quot;Corporate management&quot;,
            &quot;Strategic execution&quot;,
            &quot;Financial performance&quot;,
            &quot;Operational oversight&quot;
        ],
        &quot;authorities&quot;: [
            &quot;Execute business strategy&quot;,
            &quot;Manage operations&quot;,
            &quot;Make operational decisions&quot;,
            &quot;Report to commissioners&quot;
        ],
        &quot;is_active&quot;: true,
        &quot;sort_order&quot;: 2,
        &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;organization_code&quot;: &quot;HC001&quot;,
            &quot;organization_type&quot;: &quot;holding_company&quot;,
            &quot;parent_organization_id&quot;: null,
            &quot;name&quot;: &quot;TechCorp Holdings&quot;,
            &quot;description&quot;: &quot;Main holding company for technology businesses&quot;,
            &quot;address&quot;: &quot;123 Tech Street, Innovation District&quot;,
            &quot;phone&quot;: &quot;+1-555-0100&quot;,
            &quot;email&quot;: &quot;info@techcorp.com&quot;,
            &quot;website&quot;: &quot;https://techcorp.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG001&quot;,
            &quot;tax_number&quot;: &quot;TAX001&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 7,
                &quot;independent_directors&quot;: 4,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;,
                    &quot;nomination&quot;,
                    &quot;remuneration&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;10000000.00&quot;,
            &quot;paid_capital&quot;: &quot;8500000.00&quot;,
            &quot;establishment_date&quot;: &quot;2020-01-15T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Public Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Investment holding and management&quot;,
            &quot;contact_persons&quot;: {
                &quot;ceo&quot;: {
                    &quot;name&quot;: &quot;John Smith&quot;,
                    &quot;email&quot;: &quot;ceo@techcorp.com&quot;
                },
                &quot;cfo&quot;: {
                    &quot;name&quot;: &quot;Jane Doe&quot;,
                    &quot;email&quot;: &quot;cfo@techcorp.com&quot;
                }
            },
            &quot;level&quot;: 0,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;parent_unit&quot;: null,
        &quot;child_units&quot;: []
    },
    {
        &quot;id&quot;: &quot;01k3ahgd7tzm96vdd1qmn3xzt9&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd3y0y9a6ca0k9v0259t&quot;,
        &quot;unit_code&quot;: &quot;DATA001&quot;,
        &quot;name&quot;: &quot;Data Analytics Department&quot;,
        &quot;unit_type&quot;: &quot;department&quot;,
        &quot;description&quot;: &quot;Data analysis and business intelligence&quot;,
        &quot;parent_unit_id&quot;: null,
        &quot;responsibilities&quot;: [
            &quot;Data analysis&quot;,
            &quot;Business intelligence&quot;,
            &quot;Data visualization&quot;,
            &quot;Reporting and insights&quot;
        ],
        &quot;authorities&quot;: [
            &quot;Access company data&quot;,
            &quot;Generate reports&quot;,
            &quot;Provide recommendations&quot;,
            &quot;Implement analytics solutions&quot;
        ],
        &quot;is_active&quot;: true,
        &quot;sort_order&quot;: 2,
        &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd3y0y9a6ca0k9v0259t&quot;,
            &quot;organization_code&quot;: &quot;SUB002&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Data&quot;,
            &quot;description&quot;: &quot;Data analytics and AI solutions&quot;,
            &quot;address&quot;: &quot;789 Data Drive, Analytics Park&quot;,
            &quot;phone&quot;: &quot;+1-555-0300&quot;,
            &quot;email&quot;: &quot;info@techcorpdata.com&quot;,
            &quot;website&quot;: &quot;https://techcorpdata.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG003&quot;,
            &quot;tax_number&quot;: &quot;TAX003&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;3000000.00&quot;,
            &quot;paid_capital&quot;: &quot;2500000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-06-15T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Data analytics, machine learning, AI consulting&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;David Brown&quot;,
                    &quot;email&quot;: &quot;md@techcorpdata.com&quot;
                },
                &quot;head_of_ai&quot;: {
                    &quot;name&quot;: &quot;Emily Davis&quot;,
                    &quot;email&quot;: &quot;ai@techcorpdata.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd3y0y9a6ca0k9v0259t&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;parent_unit&quot;: null,
        &quot;child_units&quot;: []
    },
    {
        &quot;id&quot;: &quot;01k3ahgd72vvytzzw7q4htmrd9&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
        &quot;unit_code&quot;: &quot;ENG001&quot;,
        &quot;name&quot;: &quot;Engineering Division&quot;,
        &quot;unit_type&quot;: &quot;division&quot;,
        &quot;description&quot;: &quot;Software engineering and development&quot;,
        &quot;parent_unit_id&quot;: null,
        &quot;responsibilities&quot;: [
            &quot;Software development&quot;,
            &quot;Technical architecture&quot;,
            &quot;Code quality assurance&quot;,
            &quot;Development methodology&quot;
        ],
        &quot;authorities&quot;: [
            &quot;Define technical standards&quot;,
            &quot;Approve technical designs&quot;,
            &quot;Manage development teams&quot;,
            &quot;Release software products&quot;
        ],
        &quot;is_active&quot;: true,
        &quot;sort_order&quot;: 2,
        &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;organization_code&quot;: &quot;SUB001&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Software&quot;,
            &quot;description&quot;: &quot;Software development and consulting services&quot;,
            &quot;address&quot;: &quot;456 Software Ave, Tech City&quot;,
            &quot;phone&quot;: &quot;+1-555-0200&quot;,
            &quot;email&quot;: &quot;info@techcorpsoftware.com&quot;,
            &quot;website&quot;: &quot;https://techcorpsoftware.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG002&quot;,
            &quot;tax_number&quot;: &quot;TAX002&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;5000000.00&quot;,
            &quot;paid_capital&quot;: &quot;4000000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-03-01T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Software development, web applications, mobile apps&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;Mike Johnson&quot;,
                    &quot;email&quot;: &quot;md@techcorpsoftware.com&quot;
                },
                &quot;cto&quot;: {
                    &quot;name&quot;: &quot;Sarah Wilson&quot;,
                    &quot;email&quot;: &quot;cto@techcorpsoftware.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;parent_unit&quot;: null,
        &quot;child_units&quot;: [
            {
                &quot;id&quot;: &quot;01k3ahgd7617t0tmr9jsk9x1fb&quot;,
                &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
                &quot;unit_code&quot;: &quot;FEND001&quot;,
                &quot;name&quot;: &quot;Frontend Development Team&quot;,
                &quot;unit_type&quot;: &quot;team&quot;,
                &quot;description&quot;: &quot;User interface and user experience development&quot;,
                &quot;parent_unit_id&quot;: &quot;01k3ahgd72vvytzzw7q4htmrd9&quot;,
                &quot;responsibilities&quot;: [
                    &quot;UI/UX development&quot;,
                    &quot;Frontend architecture&quot;,
                    &quot;User interaction design&quot;,
                    &quot;Frontend testing&quot;
                ],
                &quot;authorities&quot;: [
                    &quot;Choose frontend frameworks&quot;,
                    &quot;Design user interfaces&quot;,
                    &quot;Implement frontend features&quot;,
                    &quot;Optimize user experience&quot;
                ],
                &quot;is_active&quot;: true,
                &quot;sort_order&quot;: 1,
                &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
                &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
                &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
                &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
            },
            {
                &quot;id&quot;: &quot;01k3ahgd7agwyj7t6x83pked0q&quot;,
                &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
                &quot;unit_code&quot;: &quot;BEND001&quot;,
                &quot;name&quot;: &quot;Backend Development Team&quot;,
                &quot;unit_type&quot;: &quot;team&quot;,
                &quot;description&quot;: &quot;Server-side development and API services&quot;,
                &quot;parent_unit_id&quot;: &quot;01k3ahgd72vvytzzw7q4htmrd9&quot;,
                &quot;responsibilities&quot;: [
                    &quot;API development&quot;,
                    &quot;Database design&quot;,
                    &quot;Server architecture&quot;,
                    &quot;Backend testing&quot;
                ],
                &quot;authorities&quot;: [
                    &quot;Design database schemas&quot;,
                    &quot;Implement business logic&quot;,
                    &quot;Develop APIs&quot;,
                    &quot;Optimize performance&quot;
                ],
                &quot;is_active&quot;: true,
                &quot;sort_order&quot;: 2,
                &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
                &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
                &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
                &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
            }
        ]
    },
    {
        &quot;id&quot;: &quot;01k3ahgd6nvm1ysj003zznhrz4&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
        &quot;unit_code&quot;: &quot;AC001&quot;,
        &quot;name&quot;: &quot;Audit Committee&quot;,
        &quot;unit_type&quot;: &quot;audit_committee&quot;,
        &quot;description&quot;: &quot;Committee responsible for financial reporting and audit oversight&quot;,
        &quot;parent_unit_id&quot;: &quot;01k3ahgd6b11gw0s93tp3rxz5y&quot;,
        &quot;responsibilities&quot;: [
            &quot;Financial reporting oversight&quot;,
            &quot;Internal audit supervision&quot;,
            &quot;External auditor management&quot;,
            &quot;Compliance monitoring&quot;
        ],
        &quot;authorities&quot;: [
            &quot;Review financial statements&quot;,
            &quot;Appoint internal auditors&quot;,
            &quot;Review audit findings&quot;,
            &quot;Recommend corrective actions&quot;
        ],
        &quot;is_active&quot;: true,
        &quot;sort_order&quot;: 3,
        &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;organization_code&quot;: &quot;HC001&quot;,
            &quot;organization_type&quot;: &quot;holding_company&quot;,
            &quot;parent_organization_id&quot;: null,
            &quot;name&quot;: &quot;TechCorp Holdings&quot;,
            &quot;description&quot;: &quot;Main holding company for technology businesses&quot;,
            &quot;address&quot;: &quot;123 Tech Street, Innovation District&quot;,
            &quot;phone&quot;: &quot;+1-555-0100&quot;,
            &quot;email&quot;: &quot;info@techcorp.com&quot;,
            &quot;website&quot;: &quot;https://techcorp.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG001&quot;,
            &quot;tax_number&quot;: &quot;TAX001&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 7,
                &quot;independent_directors&quot;: 4,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;,
                    &quot;nomination&quot;,
                    &quot;remuneration&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;10000000.00&quot;,
            &quot;paid_capital&quot;: &quot;8500000.00&quot;,
            &quot;establishment_date&quot;: &quot;2020-01-15T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Public Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Investment holding and management&quot;,
            &quot;contact_persons&quot;: {
                &quot;ceo&quot;: {
                    &quot;name&quot;: &quot;John Smith&quot;,
                    &quot;email&quot;: &quot;ceo@techcorp.com&quot;
                },
                &quot;cfo&quot;: {
                    &quot;name&quot;: &quot;Jane Doe&quot;,
                    &quot;email&quot;: &quot;cfo@techcorp.com&quot;
                }
            },
            &quot;level&quot;: 0,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;parent_unit&quot;: {
            &quot;id&quot;: &quot;01k3ahgd6b11gw0s93tp3rxz5y&quot;,
            &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;unit_code&quot;: &quot;BOC001&quot;,
            &quot;name&quot;: &quot;Board of Commissioners&quot;,
            &quot;unit_type&quot;: &quot;board_of_commissioners&quot;,
            &quot;description&quot;: &quot;Supervisory board responsible for oversight and strategic guidance&quot;,
            &quot;parent_unit_id&quot;: null,
            &quot;responsibilities&quot;: [
                &quot;Strategic oversight&quot;,
                &quot;Risk management oversight&quot;,
                &quot;Appointment of board of directors&quot;,
                &quot;Approval of major corporate actions&quot;
            ],
            &quot;authorities&quot;: [
                &quot;Approve annual budget&quot;,
                &quot;Appoint and dismiss directors&quot;,
                &quot;Approve major investments&quot;,
                &quot;Set executive compensation&quot;
            ],
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 1,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;child_units&quot;: []
    },
    {
        &quot;id&quot;: &quot;01k3ahgd7ejcsgdrma1n847yq0&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
        &quot;unit_code&quot;: &quot;QA001&quot;,
        &quot;name&quot;: &quot;Quality Assurance Department&quot;,
        &quot;unit_type&quot;: &quot;department&quot;,
        &quot;description&quot;: &quot;Software testing and quality control&quot;,
        &quot;parent_unit_id&quot;: null,
        &quot;responsibilities&quot;: [
            &quot;Test planning&quot;,
            &quot;Test execution&quot;,
            &quot;Bug reporting&quot;,
            &quot;Quality metrics&quot;
        ],
        &quot;authorities&quot;: [
            &quot;Approve software releases&quot;,
            &quot;Define testing standards&quot;,
            &quot;Block defective releases&quot;,
            &quot;Report quality metrics&quot;
        ],
        &quot;is_active&quot;: true,
        &quot;sort_order&quot;: 3,
        &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;organization_code&quot;: &quot;SUB001&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Software&quot;,
            &quot;description&quot;: &quot;Software development and consulting services&quot;,
            &quot;address&quot;: &quot;456 Software Ave, Tech City&quot;,
            &quot;phone&quot;: &quot;+1-555-0200&quot;,
            &quot;email&quot;: &quot;info@techcorpsoftware.com&quot;,
            &quot;website&quot;: &quot;https://techcorpsoftware.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG002&quot;,
            &quot;tax_number&quot;: &quot;TAX002&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;5000000.00&quot;,
            &quot;paid_capital&quot;: &quot;4000000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-03-01T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Software development, web applications, mobile apps&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;Mike Johnson&quot;,
                    &quot;email&quot;: &quot;md@techcorpsoftware.com&quot;
                },
                &quot;cto&quot;: {
                    &quot;name&quot;: &quot;Sarah Wilson&quot;,
                    &quot;email&quot;: &quot;cto@techcorpsoftware.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;parent_unit&quot;: null,
        &quot;child_units&quot;: []
    },
    {
        &quot;id&quot;: &quot;01k3ahgd7kd4g95dkq96mtknvn&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
        &quot;unit_code&quot;: &quot;HR001&quot;,
        &quot;name&quot;: &quot;Human Resources&quot;,
        &quot;unit_type&quot;: &quot;department&quot;,
        &quot;description&quot;: &quot;Human resource management and development&quot;,
        &quot;parent_unit_id&quot;: null,
        &quot;responsibilities&quot;: [
            &quot;Talent acquisition&quot;,
            &quot;Employee development&quot;,
            &quot;Performance management&quot;,
            &quot;HR policy implementation&quot;
        ],
        &quot;authorities&quot;: [
            &quot;Hire employees&quot;,
            &quot;Conduct performance reviews&quot;,
            &quot;Implement HR policies&quot;,
            &quot;Manage compensation&quot;
        ],
        &quot;is_active&quot;: true,
        &quot;sort_order&quot;: 4,
        &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;organization_code&quot;: &quot;SUB001&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Software&quot;,
            &quot;description&quot;: &quot;Software development and consulting services&quot;,
            &quot;address&quot;: &quot;456 Software Ave, Tech City&quot;,
            &quot;phone&quot;: &quot;+1-555-0200&quot;,
            &quot;email&quot;: &quot;info@techcorpsoftware.com&quot;,
            &quot;website&quot;: &quot;https://techcorpsoftware.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG002&quot;,
            &quot;tax_number&quot;: &quot;TAX002&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;5000000.00&quot;,
            &quot;paid_capital&quot;: &quot;4000000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-03-01T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Software development, web applications, mobile apps&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;Mike Johnson&quot;,
                    &quot;email&quot;: &quot;md@techcorpsoftware.com&quot;
                },
                &quot;cto&quot;: {
                    &quot;name&quot;: &quot;Sarah Wilson&quot;,
                    &quot;email&quot;: &quot;cto@techcorpsoftware.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;parent_unit&quot;: null,
        &quot;child_units&quot;: []
    },
    {
        &quot;id&quot;: &quot;01k3ahgd6s0h9v3gc08nhw9em3&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
        &quot;unit_code&quot;: &quot;RC001&quot;,
        &quot;name&quot;: &quot;Risk Committee&quot;,
        &quot;unit_type&quot;: &quot;risk_committee&quot;,
        &quot;description&quot;: &quot;Committee responsible for risk management oversight&quot;,
        &quot;parent_unit_id&quot;: &quot;01k3ahgd6b11gw0s93tp3rxz5y&quot;,
        &quot;responsibilities&quot;: [
            &quot;Risk strategy oversight&quot;,
            &quot;Risk appetite setting&quot;,
            &quot;Risk monitoring&quot;,
            &quot;Crisis management&quot;
        ],
        &quot;authorities&quot;: [
            &quot;Set risk policies&quot;,
            &quot;Review risk reports&quot;,
            &quot;Approve risk limits&quot;,
            &quot;Escalate major risks&quot;
        ],
        &quot;is_active&quot;: true,
        &quot;sort_order&quot;: 4,
        &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;organization_code&quot;: &quot;HC001&quot;,
            &quot;organization_type&quot;: &quot;holding_company&quot;,
            &quot;parent_organization_id&quot;: null,
            &quot;name&quot;: &quot;TechCorp Holdings&quot;,
            &quot;description&quot;: &quot;Main holding company for technology businesses&quot;,
            &quot;address&quot;: &quot;123 Tech Street, Innovation District&quot;,
            &quot;phone&quot;: &quot;+1-555-0100&quot;,
            &quot;email&quot;: &quot;info@techcorp.com&quot;,
            &quot;website&quot;: &quot;https://techcorp.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG001&quot;,
            &quot;tax_number&quot;: &quot;TAX001&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 7,
                &quot;independent_directors&quot;: 4,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;,
                    &quot;nomination&quot;,
                    &quot;remuneration&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;10000000.00&quot;,
            &quot;paid_capital&quot;: &quot;8500000.00&quot;,
            &quot;establishment_date&quot;: &quot;2020-01-15T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Public Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Investment holding and management&quot;,
            &quot;contact_persons&quot;: {
                &quot;ceo&quot;: {
                    &quot;name&quot;: &quot;John Smith&quot;,
                    &quot;email&quot;: &quot;ceo@techcorp.com&quot;
                },
                &quot;cfo&quot;: {
                    &quot;name&quot;: &quot;Jane Doe&quot;,
                    &quot;email&quot;: &quot;cfo@techcorp.com&quot;
                }
            },
            &quot;level&quot;: 0,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;parent_unit&quot;: {
            &quot;id&quot;: &quot;01k3ahgd6b11gw0s93tp3rxz5y&quot;,
            &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;unit_code&quot;: &quot;BOC001&quot;,
            &quot;name&quot;: &quot;Board of Commissioners&quot;,
            &quot;unit_type&quot;: &quot;board_of_commissioners&quot;,
            &quot;description&quot;: &quot;Supervisory board responsible for oversight and strategic guidance&quot;,
            &quot;parent_unit_id&quot;: null,
            &quot;responsibilities&quot;: [
                &quot;Strategic oversight&quot;,
                &quot;Risk management oversight&quot;,
                &quot;Appointment of board of directors&quot;,
                &quot;Approval of major corporate actions&quot;
            ],
            &quot;authorities&quot;: [
                &quot;Approve annual budget&quot;,
                &quot;Appoint and dismiss directors&quot;,
                &quot;Approve major investments&quot;,
                &quot;Set executive compensation&quot;
            ],
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 1,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;child_units&quot;: []
    }
]</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organization-units" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organization-units"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organization-units"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organization-units" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organization-units">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organization-units" data-method="GET"
      data-path="api/v1/organization-units"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organization-units', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organization-units"
                    onclick="tryItOut('GETapi-v1-organization-units');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organization-units"
                    onclick="cancelTryOut('GETapi-v1-organization-units');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organization-units"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organization-units</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organization-units"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organization-units"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organization-units"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-POSTapi-v1-organization-units">POST api/v1/organization-units</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-organization-units">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/organization-units" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"organization_id\": 1,
    \"unit_code\": \"HR-001\",
    \"name\": \"Human Resources Department\",
    \"unit_type\": \"department\",
    \"description\": \"Handles employee relations and recruitment\",
    \"parent_unit_id\": 1,
    \"responsibilities\": [
        \"Employee management\",
        \"Recruitment\"
    ],
    \"authorities\": [
        \"Hiring decisions\",
        \"Policy enforcement\"
    ],
    \"is_active\": false,
    \"sort_order\": 1
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-units"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "organization_id": 1,
    "unit_code": "HR-001",
    "name": "Human Resources Department",
    "unit_type": "department",
    "description": "Handles employee relations and recruitment",
    "parent_unit_id": 1,
    "responsibilities": [
        "Employee management",
        "Recruitment"
    ],
    "authorities": [
        "Hiring decisions",
        "Policy enforcement"
    ],
    "is_active": false,
    "sort_order": 1
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-organization-units">
</span>
<span id="execution-results-POSTapi-v1-organization-units" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-organization-units"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-organization-units"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-organization-units" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-organization-units">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-organization-units" data-method="POST"
      data-path="api/v1/organization-units"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-organization-units', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-organization-units"
                    onclick="tryItOut('POSTapi-v1-organization-units');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-organization-units"
                    onclick="cancelTryOut('POSTapi-v1-organization-units');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-organization-units"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/organization-units</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-organization-units"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-organization-units"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-organization-units"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>organization_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="organization_id"                data-endpoint="POSTapi-v1-organization-units"
               value="1"
               data-component="body">
    <br>
<p>ID of the organization this unit belongs to. The <code>id</code> of an existing record in the organizations table. Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>unit_code</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="unit_code"                data-endpoint="POSTapi-v1-organization-units"
               value="HR-001"
               data-component="body">
    <br>
<p>Unique code for the organization unit. Example: <code>HR-001</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="POSTapi-v1-organization-units"
               value="Human Resources Department"
               data-component="body">
    <br>
<p>Name of the organization unit. Must not be greater than 255 characters. Example: <code>Human Resources Department</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>unit_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="unit_type"                data-endpoint="POSTapi-v1-organization-units"
               value="department"
               data-component="body">
    <br>
<p>Type of organization unit. Example: <code>department</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>board_of_commissioners</code></li> <li><code>board_of_directors</code></li> <li><code>executive_committee</code></li> <li><code>audit_committee</code></li> <li><code>risk_committee</code></li> <li><code>nomination_committee</code></li> <li><code>remuneration_committee</code></li> <li><code>division</code></li> <li><code>department</code></li> <li><code>section</code></li> <li><code>team</code></li> <li><code>branch_office</code></li> <li><code>representative_office</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>description</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="description"                data-endpoint="POSTapi-v1-organization-units"
               value="Handles employee relations and recruitment"
               data-component="body">
    <br>
<p>Description of the organization unit. Example: <code>Handles employee relations and recruitment</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>parent_unit_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="parent_unit_id"                data-endpoint="POSTapi-v1-organization-units"
               value="1"
               data-component="body">
    <br>
<p>ID of parent organization unit (optional). The <code>id</code> of an existing record in the organization_units table. Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>responsibilities</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="responsibilities"                data-endpoint="POSTapi-v1-organization-units"
               value=""
               data-component="body">
    <br>
<p>Array of responsibilities.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>authorities</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="authorities"                data-endpoint="POSTapi-v1-organization-units"
               value=""
               data-component="body">
    <br>
<p>Array of authorities.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>is_active</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
                <label data-endpoint="POSTapi-v1-organization-units" style="display: none">
            <input type="radio" name="is_active"
                   value="true"
                   data-endpoint="POSTapi-v1-organization-units"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-v1-organization-units" style="display: none">
            <input type="radio" name="is_active"
                   value="false"
                   data-endpoint="POSTapi-v1-organization-units"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Whether the unit is active. Example: <code>false</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>sort_order</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="sort_order"                data-endpoint="POSTapi-v1-organization-units"
               value="1"
               data-component="body">
    <br>
<p>Sort order for display. Must be at least 0. Example: <code>1</code></p>
        </div>
        </form>

                    <h2 id="endpoints-GETapi-v1-organization-units--id-">GET api/v1/organization-units/{id}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-organization-units--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organization-units/01k3ahgd6b11gw0s93tp3rxz5y" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-units/01k3ahgd6b11gw0s93tp3rxz5y"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organization-units--id-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: &quot;01k3ahgd6b11gw0s93tp3rxz5y&quot;,
    &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
    &quot;unit_code&quot;: &quot;BOC001&quot;,
    &quot;name&quot;: &quot;Board of Commissioners&quot;,
    &quot;unit_type&quot;: &quot;board_of_commissioners&quot;,
    &quot;description&quot;: &quot;Supervisory board responsible for oversight and strategic guidance&quot;,
    &quot;parent_unit_id&quot;: null,
    &quot;responsibilities&quot;: [
        &quot;Strategic oversight&quot;,
        &quot;Risk management oversight&quot;,
        &quot;Appointment of board of directors&quot;,
        &quot;Approval of major corporate actions&quot;
    ],
    &quot;authorities&quot;: [
        &quot;Approve annual budget&quot;,
        &quot;Appoint and dismiss directors&quot;,
        &quot;Approve major investments&quot;,
        &quot;Set executive compensation&quot;
    ],
    &quot;is_active&quot;: true,
    &quot;sort_order&quot;: 1,
    &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
    &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
    &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
    &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
    &quot;organization&quot;: {
        &quot;id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
        &quot;organization_code&quot;: &quot;HC001&quot;,
        &quot;organization_type&quot;: &quot;holding_company&quot;,
        &quot;parent_organization_id&quot;: null,
        &quot;name&quot;: &quot;TechCorp Holdings&quot;,
        &quot;description&quot;: &quot;Main holding company for technology businesses&quot;,
        &quot;address&quot;: &quot;123 Tech Street, Innovation District&quot;,
        &quot;phone&quot;: &quot;+1-555-0100&quot;,
        &quot;email&quot;: &quot;info@techcorp.com&quot;,
        &quot;website&quot;: &quot;https://techcorp.com&quot;,
        &quot;is_active&quot;: true,
        &quot;registration_number&quot;: &quot;REG001&quot;,
        &quot;tax_number&quot;: &quot;TAX001&quot;,
        &quot;governance_structure&quot;: {
            &quot;board_size&quot;: 7,
            &quot;independent_directors&quot;: 4,
            &quot;committees&quot;: [
                &quot;audit&quot;,
                &quot;risk&quot;,
                &quot;nomination&quot;,
                &quot;remuneration&quot;
            ]
        },
        &quot;authorized_capital&quot;: &quot;10000000.00&quot;,
        &quot;paid_capital&quot;: &quot;8500000.00&quot;,
        &quot;establishment_date&quot;: &quot;2020-01-15T00:00:00.000000Z&quot;,
        &quot;legal_status&quot;: &quot;Public Limited Company&quot;,
        &quot;business_activities&quot;: &quot;Investment holding and management&quot;,
        &quot;contact_persons&quot;: {
            &quot;ceo&quot;: {
                &quot;name&quot;: &quot;John Smith&quot;,
                &quot;email&quot;: &quot;ceo@techcorp.com&quot;
            },
            &quot;cfo&quot;: {
                &quot;name&quot;: &quot;Jane Doe&quot;,
                &quot;email&quot;: &quot;cfo@techcorp.com&quot;
            }
        },
        &quot;level&quot;: 0,
        &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
        &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
    },
    &quot;parent_unit&quot;: null,
    &quot;child_units&quot;: [
        {
            &quot;id&quot;: &quot;01k3ahgd6nvm1ysj003zznhrz4&quot;,
            &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;unit_code&quot;: &quot;AC001&quot;,
            &quot;name&quot;: &quot;Audit Committee&quot;,
            &quot;unit_type&quot;: &quot;audit_committee&quot;,
            &quot;description&quot;: &quot;Committee responsible for financial reporting and audit oversight&quot;,
            &quot;parent_unit_id&quot;: &quot;01k3ahgd6b11gw0s93tp3rxz5y&quot;,
            &quot;responsibilities&quot;: [
                &quot;Financial reporting oversight&quot;,
                &quot;Internal audit supervision&quot;,
                &quot;External auditor management&quot;,
                &quot;Compliance monitoring&quot;
            ],
            &quot;authorities&quot;: [
                &quot;Review financial statements&quot;,
                &quot;Appoint internal auditors&quot;,
                &quot;Review audit findings&quot;,
                &quot;Recommend corrective actions&quot;
            ],
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 3,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;positions&quot;: []
        },
        {
            &quot;id&quot;: &quot;01k3ahgd6s0h9v3gc08nhw9em3&quot;,
            &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;unit_code&quot;: &quot;RC001&quot;,
            &quot;name&quot;: &quot;Risk Committee&quot;,
            &quot;unit_type&quot;: &quot;risk_committee&quot;,
            &quot;description&quot;: &quot;Committee responsible for risk management oversight&quot;,
            &quot;parent_unit_id&quot;: &quot;01k3ahgd6b11gw0s93tp3rxz5y&quot;,
            &quot;responsibilities&quot;: [
                &quot;Risk strategy oversight&quot;,
                &quot;Risk appetite setting&quot;,
                &quot;Risk monitoring&quot;,
                &quot;Crisis management&quot;
            ],
            &quot;authorities&quot;: [
                &quot;Set risk policies&quot;,
                &quot;Review risk reports&quot;,
                &quot;Approve risk limits&quot;,
                &quot;Escalate major risks&quot;
            ],
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 4,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;positions&quot;: []
        }
    ],
    &quot;positions&quot;: [
        {
            &quot;id&quot;: &quot;01k3ahgd85gtq5jaencdx3wxmb&quot;,
            &quot;organization_unit_id&quot;: &quot;01k3ahgd6b11gw0s93tp3rxz5y&quot;,
            &quot;position_code&quot;: &quot;POS001&quot;,
            &quot;organization_position_level_id&quot;: &quot;01k3ahgd0k990naes55gq52hfn&quot;,
            &quot;title&quot;: &quot;Chairman of Board of Commissioners&quot;,
            &quot;job_description&quot;: &quot;Lead the board of commissioners and ensure effective governance oversight&quot;,
            &quot;qualifications&quot;: [
                &quot;Minimum 15 years of executive experience&quot;,
                &quot;Strong leadership and governance experience&quot;,
                &quot;Understanding of corporate governance principles&quot;,
                &quot;Board certification preferred&quot;
            ],
            &quot;responsibilities&quot;: [
                &quot;Chair board meetings&quot;,
                &quot;Provide strategic oversight&quot;,
                &quot;Ensure compliance with regulations&quot;,
                &quot;Evaluate board performance&quot;
            ],
            &quot;min_salary&quot;: &quot;500000.00&quot;,
            &quot;max_salary&quot;: &quot;800000.00&quot;,
            &quot;is_active&quot;: true,
            &quot;max_incumbents&quot;: 1,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;active_memberships&quot;: []
        },
        {
            &quot;id&quot;: &quot;01k3ahgd8a68771czkbkam85a3&quot;,
            &quot;organization_unit_id&quot;: &quot;01k3ahgd6b11gw0s93tp3rxz5y&quot;,
            &quot;position_code&quot;: &quot;POS002&quot;,
            &quot;organization_position_level_id&quot;: &quot;01k3ahgd0k990naes55gq52hfn&quot;,
            &quot;title&quot;: &quot;Commissioner&quot;,
            &quot;job_description&quot;: &quot;Provide governance oversight and strategic guidance&quot;,
            &quot;qualifications&quot;: [
                &quot;Minimum 10 years of senior management experience&quot;,
                &quot;Relevant industry knowledge&quot;,
                &quot;Strong analytical and strategic thinking skills&quot;,
                &quot;Board experience preferred&quot;
            ],
            &quot;responsibilities&quot;: [
                &quot;Participate in board meetings&quot;,
                &quot;Review strategic plans&quot;,
                &quot;Monitor risk management&quot;,
                &quot;Ensure regulatory compliance&quot;
            ],
            &quot;min_salary&quot;: &quot;300000.00&quot;,
            &quot;max_salary&quot;: &quot;500000.00&quot;,
            &quot;is_active&quot;: true,
            &quot;max_incumbents&quot;: 4,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;active_memberships&quot;: []
        }
    ],
    &quot;memberships&quot;: []
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organization-units--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organization-units--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organization-units--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organization-units--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organization-units--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organization-units--id-" data-method="GET"
      data-path="api/v1/organization-units/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organization-units--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organization-units--id-"
                    onclick="tryItOut('GETapi-v1-organization-units--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organization-units--id-"
                    onclick="cancelTryOut('GETapi-v1-organization-units--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organization-units--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organization-units/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organization-units--id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organization-units--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organization-units--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-v1-organization-units--id-"
               value="01k3ahgd6b11gw0s93tp3rxz5y"
               data-component="url">
    <br>
<p>The ID of the organization unit. Example: <code>01k3ahgd6b11gw0s93tp3rxz5y</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-PUTapi-v1-organization-units--id-">PUT api/v1/organization-units/{id}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PUTapi-v1-organization-units--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:8000/api/v1/organization-units/01k3ahgd6b11gw0s93tp3rxz5y" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"unit_code\": \"HR-001\",
    \"name\": \"Human Resources Department\",
    \"unit_type\": \"department\",
    \"description\": \"Handles employee relations and recruitment\",
    \"parent_unit_id\": 1,
    \"responsibilities\": [
        \"Employee management\",
        \"Recruitment\"
    ],
    \"authorities\": [
        \"Hiring decisions\",
        \"Policy enforcement\"
    ],
    \"is_active\": false,
    \"sort_order\": 1
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-units/01k3ahgd6b11gw0s93tp3rxz5y"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "unit_code": "HR-001",
    "name": "Human Resources Department",
    "unit_type": "department",
    "description": "Handles employee relations and recruitment",
    "parent_unit_id": 1,
    "responsibilities": [
        "Employee management",
        "Recruitment"
    ],
    "authorities": [
        "Hiring decisions",
        "Policy enforcement"
    ],
    "is_active": false,
    "sort_order": 1
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-organization-units--id-">
</span>
<span id="execution-results-PUTapi-v1-organization-units--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-organization-units--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-organization-units--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-organization-units--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-organization-units--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-organization-units--id-" data-method="PUT"
      data-path="api/v1/organization-units/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-organization-units--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-v1-organization-units--id-"
                    onclick="tryItOut('PUTapi-v1-organization-units--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-v1-organization-units--id-"
                    onclick="cancelTryOut('PUTapi-v1-organization-units--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-v1-organization-units--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/organization-units/{id}</code></b>
        </p>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/v1/organization-units/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-v1-organization-units--id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-organization-units--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-organization-units--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PUTapi-v1-organization-units--id-"
               value="01k3ahgd6b11gw0s93tp3rxz5y"
               data-component="url">
    <br>
<p>The ID of the organization unit. Example: <code>01k3ahgd6b11gw0s93tp3rxz5y</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>unit_code</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="unit_code"                data-endpoint="PUTapi-v1-organization-units--id-"
               value="HR-001"
               data-component="body">
    <br>
<p>Unique code for the organization unit. Example: <code>HR-001</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="PUTapi-v1-organization-units--id-"
               value="Human Resources Department"
               data-component="body">
    <br>
<p>Name of the organization unit. Must not be greater than 255 characters. Example: <code>Human Resources Department</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>unit_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="unit_type"                data-endpoint="PUTapi-v1-organization-units--id-"
               value="department"
               data-component="body">
    <br>
<p>Type of organization unit. Example: <code>department</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>board_of_commissioners</code></li> <li><code>board_of_directors</code></li> <li><code>executive_committee</code></li> <li><code>audit_committee</code></li> <li><code>risk_committee</code></li> <li><code>nomination_committee</code></li> <li><code>remuneration_committee</code></li> <li><code>division</code></li> <li><code>department</code></li> <li><code>section</code></li> <li><code>team</code></li> <li><code>branch_office</code></li> <li><code>representative_office</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>description</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="description"                data-endpoint="PUTapi-v1-organization-units--id-"
               value="Handles employee relations and recruitment"
               data-component="body">
    <br>
<p>Description of the organization unit. Example: <code>Handles employee relations and recruitment</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>parent_unit_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="parent_unit_id"                data-endpoint="PUTapi-v1-organization-units--id-"
               value="1"
               data-component="body">
    <br>
<p>ID of parent organization unit (optional). The <code>id</code> of an existing record in the organization_units table. Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>responsibilities</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="responsibilities"                data-endpoint="PUTapi-v1-organization-units--id-"
               value=""
               data-component="body">
    <br>
<p>Array of responsibilities.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>authorities</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="authorities"                data-endpoint="PUTapi-v1-organization-units--id-"
               value=""
               data-component="body">
    <br>
<p>Array of authorities.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>is_active</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
                <label data-endpoint="PUTapi-v1-organization-units--id-" style="display: none">
            <input type="radio" name="is_active"
                   value="true"
                   data-endpoint="PUTapi-v1-organization-units--id-"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PUTapi-v1-organization-units--id-" style="display: none">
            <input type="radio" name="is_active"
                   value="false"
                   data-endpoint="PUTapi-v1-organization-units--id-"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Whether the unit is active. Example: <code>false</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>sort_order</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="sort_order"                data-endpoint="PUTapi-v1-organization-units--id-"
               value="1"
               data-component="body">
    <br>
<p>Sort order for display. Must be at least 0. Example: <code>1</code></p>
        </div>
        </form>

                    <h2 id="endpoints-DELETEapi-v1-organization-units--id-">DELETE api/v1/organization-units/{id}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-DELETEapi-v1-organization-units--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:8000/api/v1/organization-units/01k3ahgd6b11gw0s93tp3rxz5y" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-units/01k3ahgd6b11gw0s93tp3rxz5y"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-organization-units--id-">
</span>
<span id="execution-results-DELETEapi-v1-organization-units--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-organization-units--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-organization-units--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-organization-units--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-organization-units--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-organization-units--id-" data-method="DELETE"
      data-path="api/v1/organization-units/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-organization-units--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-v1-organization-units--id-"
                    onclick="tryItOut('DELETEapi-v1-organization-units--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-v1-organization-units--id-"
                    onclick="cancelTryOut('DELETEapi-v1-organization-units--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-v1-organization-units--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/organization-units/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-organization-units--id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-organization-units--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-organization-units--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="DELETEapi-v1-organization-units--id-"
               value="01k3ahgd6b11gw0s93tp3rxz5y"
               data-component="url">
    <br>
<p>The ID of the organization unit. Example: <code>01k3ahgd6b11gw0s93tp3rxz5y</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-GETapi-v1-organization-units-hierarchy-tree">GET api/v1/organization-units/hierarchy/tree</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-organization-units-hierarchy-tree">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organization-units/hierarchy/tree" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-units/hierarchy/tree"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organization-units-hierarchy-tree">
            <blockquote>
            <p>Example response (400):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;organization_id is required&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organization-units-hierarchy-tree" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organization-units-hierarchy-tree"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organization-units-hierarchy-tree"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organization-units-hierarchy-tree" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organization-units-hierarchy-tree">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organization-units-hierarchy-tree" data-method="GET"
      data-path="api/v1/organization-units/hierarchy/tree"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organization-units-hierarchy-tree', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organization-units-hierarchy-tree"
                    onclick="tryItOut('GETapi-v1-organization-units-hierarchy-tree');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organization-units-hierarchy-tree"
                    onclick="cancelTryOut('GETapi-v1-organization-units-hierarchy-tree');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organization-units-hierarchy-tree"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organization-units/hierarchy/tree</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organization-units-hierarchy-tree"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organization-units-hierarchy-tree"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organization-units-hierarchy-tree"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-GETapi-v1-organization-units-type--type-">GET api/v1/organization-units/type/{type}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-organization-units-type--type-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organization-units/type/architecto" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-units/type/architecto"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organization-units-type--type-">
            <blockquote>
            <p>Example response (400):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Invalid type. Must be governance or operational&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organization-units-type--type-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organization-units-type--type-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organization-units-type--type-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organization-units-type--type-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organization-units-type--type-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organization-units-type--type-" data-method="GET"
      data-path="api/v1/organization-units/type/{type}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organization-units-type--type-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organization-units-type--type-"
                    onclick="tryItOut('GETapi-v1-organization-units-type--type-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organization-units-type--type-"
                    onclick="cancelTryOut('GETapi-v1-organization-units-type--type-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organization-units-type--type-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organization-units/type/{type}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organization-units-type--type-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organization-units-type--type-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organization-units-type--type-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="type"                data-endpoint="GETapi-v1-organization-units-type--type-"
               value="architecto"
               data-component="url">
    <br>
<p>The type. Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-GETapi-v1-organization-position-levels">Display a listing of the resource.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-organization-position-levels">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organization-position-levels" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-position-levels"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organization-position-levels">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: &quot;01k3ahgd0k990naes55gq52hfn&quot;,
            &quot;code&quot;: &quot;board_member&quot;,
            &quot;name&quot;: &quot;Board Member&quot;,
            &quot;description&quot;: &quot;Board of directors/commissioners member&quot;,
            &quot;hierarchy_level&quot;: 1,
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 1,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;updated_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;organization_positions_count&quot;: 2
        },
        {
            &quot;id&quot;: &quot;01k3ahgd0txgyfe2hhhgpgptmw&quot;,
            &quot;code&quot;: &quot;c_level&quot;,
            &quot;name&quot;: &quot;C-Level&quot;,
            &quot;description&quot;: &quot;Chief executive positions (CEO, CTO, CFO, etc.)&quot;,
            &quot;hierarchy_level&quot;: 2,
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 2,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;updated_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;organization_positions_count&quot;: 4
        },
        {
            &quot;id&quot;: &quot;01k3ahgd108v9qyyem04dv0n00&quot;,
            &quot;code&quot;: &quot;vice_president&quot;,
            &quot;name&quot;: &quot;Vice President&quot;,
            &quot;description&quot;: &quot;Vice president level positions&quot;,
            &quot;hierarchy_level&quot;: 3,
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 3,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;updated_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;organization_positions_count&quot;: 1
        },
        {
            &quot;id&quot;: &quot;01k3ahgd161cajfnyy5nn43ddg&quot;,
            &quot;code&quot;: &quot;director&quot;,
            &quot;name&quot;: &quot;Director&quot;,
            &quot;description&quot;: &quot;Director level positions&quot;,
            &quot;hierarchy_level&quot;: 4,
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 4,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;updated_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;organization_positions_count&quot;: 1
        },
        {
            &quot;id&quot;: &quot;01k3ahgd1c3avm20mtzbyv1zas&quot;,
            &quot;code&quot;: &quot;senior_manager&quot;,
            &quot;name&quot;: &quot;Senior Manager&quot;,
            &quot;description&quot;: &quot;Senior management positions&quot;,
            &quot;hierarchy_level&quot;: 5,
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 5,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;updated_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;organization_positions_count&quot;: 0
        },
        {
            &quot;id&quot;: &quot;01k3ahgd1hb29v5gmwsk01ntwc&quot;,
            &quot;code&quot;: &quot;manager&quot;,
            &quot;name&quot;: &quot;Manager&quot;,
            &quot;description&quot;: &quot;Management positions&quot;,
            &quot;hierarchy_level&quot;: 6,
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 6,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;updated_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;organization_positions_count&quot;: 1
        },
        {
            &quot;id&quot;: &quot;01k3ahgd1psz47cgwy1c7mrpx4&quot;,
            &quot;code&quot;: &quot;assistant_manager&quot;,
            &quot;name&quot;: &quot;Assistant Manager&quot;,
            &quot;description&quot;: &quot;Assistant management positions&quot;,
            &quot;hierarchy_level&quot;: 7,
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 7,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;updated_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;organization_positions_count&quot;: 0
        },
        {
            &quot;id&quot;: &quot;01k3ahgd1w4grvn90j7n3wtnj5&quot;,
            &quot;code&quot;: &quot;supervisor&quot;,
            &quot;name&quot;: &quot;Supervisor&quot;,
            &quot;description&quot;: &quot;Supervisory positions&quot;,
            &quot;hierarchy_level&quot;: 8,
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 8,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;updated_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;organization_positions_count&quot;: 0
        },
        {
            &quot;id&quot;: &quot;01k3ahgd21jf54ft67k9wcy7sy&quot;,
            &quot;code&quot;: &quot;senior_staff&quot;,
            &quot;name&quot;: &quot;Senior Staff&quot;,
            &quot;description&quot;: &quot;Senior staff positions&quot;,
            &quot;hierarchy_level&quot;: 9,
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 9,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;updated_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;organization_positions_count&quot;: 2
        },
        {
            &quot;id&quot;: &quot;01k3ahgd26zx9f3d9fp6qq342n&quot;,
            &quot;code&quot;: &quot;staff&quot;,
            &quot;name&quot;: &quot;Staff&quot;,
            &quot;description&quot;: &quot;Staff positions&quot;,
            &quot;hierarchy_level&quot;: 10,
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 10,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;updated_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;organization_positions_count&quot;: 4
        },
        {
            &quot;id&quot;: &quot;01k3ahgd2bp5f4nyvantyfj3vq&quot;,
            &quot;code&quot;: &quot;junior_staff&quot;,
            &quot;name&quot;: &quot;Junior Staff&quot;,
            &quot;description&quot;: &quot;Junior staff positions&quot;,
            &quot;hierarchy_level&quot;: 11,
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 11,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;updated_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
            &quot;organization_positions_count&quot;: 0
        }
    ],
    &quot;message&quot;: &quot;Organization position levels retrieved successfully&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organization-position-levels" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organization-position-levels"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organization-position-levels"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organization-position-levels" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organization-position-levels">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organization-position-levels" data-method="GET"
      data-path="api/v1/organization-position-levels"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organization-position-levels', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organization-position-levels"
                    onclick="tryItOut('GETapi-v1-organization-position-levels');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organization-position-levels"
                    onclick="cancelTryOut('GETapi-v1-organization-position-levels');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organization-position-levels"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organization-position-levels</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organization-position-levels"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organization-position-levels"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organization-position-levels"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-POSTapi-v1-organization-position-levels">Store a newly created resource in storage.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-organization-position-levels">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/organization-position-levels" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"code\": \"L1\",
    \"name\": \"Entry Level\",
    \"description\": \"Entry level positions for new hires\",
    \"hierarchy_level\": 1,
    \"is_active\": false,
    \"sort_order\": 1
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-position-levels"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "code": "L1",
    "name": "Entry Level",
    "description": "Entry level positions for new hires",
    "hierarchy_level": 1,
    "is_active": false,
    "sort_order": 1
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-organization-position-levels">
</span>
<span id="execution-results-POSTapi-v1-organization-position-levels" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-organization-position-levels"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-organization-position-levels"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-organization-position-levels" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-organization-position-levels">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-organization-position-levels" data-method="POST"
      data-path="api/v1/organization-position-levels"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-organization-position-levels', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-organization-position-levels"
                    onclick="tryItOut('POSTapi-v1-organization-position-levels');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-organization-position-levels"
                    onclick="cancelTryOut('POSTapi-v1-organization-position-levels');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-organization-position-levels"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/organization-position-levels</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-organization-position-levels"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-organization-position-levels"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-organization-position-levels"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>code</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="code"                data-endpoint="POSTapi-v1-organization-position-levels"
               value="L1"
               data-component="body">
    <br>
<p>Unique code for the position level. Must not be greater than 255 characters. Example: <code>L1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="POSTapi-v1-organization-position-levels"
               value="Entry Level"
               data-component="body">
    <br>
<p>Name of the position level. Must not be greater than 255 characters. Example: <code>Entry Level</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>description</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="description"                data-endpoint="POSTapi-v1-organization-position-levels"
               value="Entry level positions for new hires"
               data-component="body">
    <br>
<p>Description of the position level. Example: <code>Entry level positions for new hires</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>hierarchy_level</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="hierarchy_level"                data-endpoint="POSTapi-v1-organization-position-levels"
               value="1"
               data-component="body">
    <br>
<p>Hierarchical level number. Must be at least 1. Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>is_active</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
                <label data-endpoint="POSTapi-v1-organization-position-levels" style="display: none">
            <input type="radio" name="is_active"
                   value="true"
                   data-endpoint="POSTapi-v1-organization-position-levels"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-v1-organization-position-levels" style="display: none">
            <input type="radio" name="is_active"
                   value="false"
                   data-endpoint="POSTapi-v1-organization-position-levels"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Whether the position level is active. Example: <code>false</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>sort_order</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="sort_order"                data-endpoint="POSTapi-v1-organization-position-levels"
               value="1"
               data-component="body">
    <br>
<p>Sort order for display. Example: <code>1</code></p>
        </div>
        </form>

                    <h2 id="endpoints-GETapi-v1-organization-position-levels--id-">Display the specified resource.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-organization-position-levels--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organization-position-levels/01k3ahgd0k990naes55gq52hfn" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-position-levels/01k3ahgd0k990naes55gq52hfn"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organization-position-levels--id-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: &quot;01k3ahgd0k990naes55gq52hfn&quot;,
        &quot;code&quot;: &quot;board_member&quot;,
        &quot;name&quot;: &quot;Board Member&quot;,
        &quot;description&quot;: &quot;Board of directors/commissioners member&quot;,
        &quot;hierarchy_level&quot;: 1,
        &quot;is_active&quot;: true,
        &quot;sort_order&quot;: 1,
        &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
        &quot;created_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
        &quot;updated_by&quot;: &quot;01K3AHGD0HCPGVQ2YHBD42C867&quot;,
        &quot;organization_positions&quot;: [
            {
                &quot;id&quot;: &quot;01k3ahgd85gtq5jaencdx3wxmb&quot;,
                &quot;organization_unit_id&quot;: &quot;01k3ahgd6b11gw0s93tp3rxz5y&quot;,
                &quot;position_code&quot;: &quot;POS001&quot;,
                &quot;organization_position_level_id&quot;: &quot;01k3ahgd0k990naes55gq52hfn&quot;,
                &quot;title&quot;: &quot;Chairman of Board of Commissioners&quot;,
                &quot;job_description&quot;: &quot;Lead the board of commissioners and ensure effective governance oversight&quot;,
                &quot;qualifications&quot;: [
                    &quot;Minimum 15 years of executive experience&quot;,
                    &quot;Strong leadership and governance experience&quot;,
                    &quot;Understanding of corporate governance principles&quot;,
                    &quot;Board certification preferred&quot;
                ],
                &quot;responsibilities&quot;: [
                    &quot;Chair board meetings&quot;,
                    &quot;Provide strategic oversight&quot;,
                    &quot;Ensure compliance with regulations&quot;,
                    &quot;Evaluate board performance&quot;
                ],
                &quot;min_salary&quot;: &quot;500000.00&quot;,
                &quot;max_salary&quot;: &quot;800000.00&quot;,
                &quot;is_active&quot;: true,
                &quot;max_incumbents&quot;: 1,
                &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
                &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
                &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
                &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
                &quot;organization_unit&quot;: {
                    &quot;id&quot;: &quot;01k3ahgd6b11gw0s93tp3rxz5y&quot;,
                    &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
                    &quot;unit_code&quot;: &quot;BOC001&quot;,
                    &quot;name&quot;: &quot;Board of Commissioners&quot;,
                    &quot;unit_type&quot;: &quot;board_of_commissioners&quot;,
                    &quot;description&quot;: &quot;Supervisory board responsible for oversight and strategic guidance&quot;,
                    &quot;parent_unit_id&quot;: null,
                    &quot;responsibilities&quot;: [
                        &quot;Strategic oversight&quot;,
                        &quot;Risk management oversight&quot;,
                        &quot;Appointment of board of directors&quot;,
                        &quot;Approval of major corporate actions&quot;
                    ],
                    &quot;authorities&quot;: [
                        &quot;Approve annual budget&quot;,
                        &quot;Appoint and dismiss directors&quot;,
                        &quot;Approve major investments&quot;,
                        &quot;Set executive compensation&quot;
                    ],
                    &quot;is_active&quot;: true,
                    &quot;sort_order&quot;: 1,
                    &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
                    &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
                    &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
                    &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
                }
            },
            {
                &quot;id&quot;: &quot;01k3ahgd8a68771czkbkam85a3&quot;,
                &quot;organization_unit_id&quot;: &quot;01k3ahgd6b11gw0s93tp3rxz5y&quot;,
                &quot;position_code&quot;: &quot;POS002&quot;,
                &quot;organization_position_level_id&quot;: &quot;01k3ahgd0k990naes55gq52hfn&quot;,
                &quot;title&quot;: &quot;Commissioner&quot;,
                &quot;job_description&quot;: &quot;Provide governance oversight and strategic guidance&quot;,
                &quot;qualifications&quot;: [
                    &quot;Minimum 10 years of senior management experience&quot;,
                    &quot;Relevant industry knowledge&quot;,
                    &quot;Strong analytical and strategic thinking skills&quot;,
                    &quot;Board experience preferred&quot;
                ],
                &quot;responsibilities&quot;: [
                    &quot;Participate in board meetings&quot;,
                    &quot;Review strategic plans&quot;,
                    &quot;Monitor risk management&quot;,
                    &quot;Ensure regulatory compliance&quot;
                ],
                &quot;min_salary&quot;: &quot;300000.00&quot;,
                &quot;max_salary&quot;: &quot;500000.00&quot;,
                &quot;is_active&quot;: true,
                &quot;max_incumbents&quot;: 4,
                &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
                &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
                &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
                &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
                &quot;organization_unit&quot;: {
                    &quot;id&quot;: &quot;01k3ahgd6b11gw0s93tp3rxz5y&quot;,
                    &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
                    &quot;unit_code&quot;: &quot;BOC001&quot;,
                    &quot;name&quot;: &quot;Board of Commissioners&quot;,
                    &quot;unit_type&quot;: &quot;board_of_commissioners&quot;,
                    &quot;description&quot;: &quot;Supervisory board responsible for oversight and strategic guidance&quot;,
                    &quot;parent_unit_id&quot;: null,
                    &quot;responsibilities&quot;: [
                        &quot;Strategic oversight&quot;,
                        &quot;Risk management oversight&quot;,
                        &quot;Appointment of board of directors&quot;,
                        &quot;Approval of major corporate actions&quot;
                    ],
                    &quot;authorities&quot;: [
                        &quot;Approve annual budget&quot;,
                        &quot;Appoint and dismiss directors&quot;,
                        &quot;Approve major investments&quot;,
                        &quot;Set executive compensation&quot;
                    ],
                    &quot;is_active&quot;: true,
                    &quot;sort_order&quot;: 1,
                    &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
                    &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
                    &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
                    &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
                }
            }
        ]
    },
    &quot;message&quot;: &quot;Organization position level retrieved successfully&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organization-position-levels--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organization-position-levels--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organization-position-levels--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organization-position-levels--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organization-position-levels--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organization-position-levels--id-" data-method="GET"
      data-path="api/v1/organization-position-levels/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organization-position-levels--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organization-position-levels--id-"
                    onclick="tryItOut('GETapi-v1-organization-position-levels--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organization-position-levels--id-"
                    onclick="cancelTryOut('GETapi-v1-organization-position-levels--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organization-position-levels--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organization-position-levels/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organization-position-levels--id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organization-position-levels--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organization-position-levels--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-v1-organization-position-levels--id-"
               value="01k3ahgd0k990naes55gq52hfn"
               data-component="url">
    <br>
<p>The ID of the organization position level. Example: <code>01k3ahgd0k990naes55gq52hfn</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-PUTapi-v1-organization-position-levels--id-">Update the specified resource in storage.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PUTapi-v1-organization-position-levels--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:8000/api/v1/organization-position-levels/01k3ahgd0k990naes55gq52hfn" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"code\": \"L1\",
    \"name\": \"Entry Level\",
    \"description\": \"Entry level positions for new hires\",
    \"hierarchy_level\": 1,
    \"is_active\": false,
    \"sort_order\": 1
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-position-levels/01k3ahgd0k990naes55gq52hfn"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "code": "L1",
    "name": "Entry Level",
    "description": "Entry level positions for new hires",
    "hierarchy_level": 1,
    "is_active": false,
    "sort_order": 1
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-organization-position-levels--id-">
</span>
<span id="execution-results-PUTapi-v1-organization-position-levels--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-organization-position-levels--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-organization-position-levels--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-organization-position-levels--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-organization-position-levels--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-organization-position-levels--id-" data-method="PUT"
      data-path="api/v1/organization-position-levels/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-organization-position-levels--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-v1-organization-position-levels--id-"
                    onclick="tryItOut('PUTapi-v1-organization-position-levels--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-v1-organization-position-levels--id-"
                    onclick="cancelTryOut('PUTapi-v1-organization-position-levels--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-v1-organization-position-levels--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/organization-position-levels/{id}</code></b>
        </p>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/v1/organization-position-levels/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-v1-organization-position-levels--id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-organization-position-levels--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-organization-position-levels--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PUTapi-v1-organization-position-levels--id-"
               value="01k3ahgd0k990naes55gq52hfn"
               data-component="url">
    <br>
<p>The ID of the organization position level. Example: <code>01k3ahgd0k990naes55gq52hfn</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>code</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="code"                data-endpoint="PUTapi-v1-organization-position-levels--id-"
               value="L1"
               data-component="body">
    <br>
<p>Unique code for the position level. Must not be greater than 255 characters. Example: <code>L1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="PUTapi-v1-organization-position-levels--id-"
               value="Entry Level"
               data-component="body">
    <br>
<p>Name of the position level. Must not be greater than 255 characters. Example: <code>Entry Level</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>description</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="description"                data-endpoint="PUTapi-v1-organization-position-levels--id-"
               value="Entry level positions for new hires"
               data-component="body">
    <br>
<p>Description of the position level. Example: <code>Entry level positions for new hires</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>hierarchy_level</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="hierarchy_level"                data-endpoint="PUTapi-v1-organization-position-levels--id-"
               value="1"
               data-component="body">
    <br>
<p>Hierarchical level number. Must be at least 1. Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>is_active</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
                <label data-endpoint="PUTapi-v1-organization-position-levels--id-" style="display: none">
            <input type="radio" name="is_active"
                   value="true"
                   data-endpoint="PUTapi-v1-organization-position-levels--id-"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PUTapi-v1-organization-position-levels--id-" style="display: none">
            <input type="radio" name="is_active"
                   value="false"
                   data-endpoint="PUTapi-v1-organization-position-levels--id-"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Whether the position level is active. Example: <code>false</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>sort_order</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="sort_order"                data-endpoint="PUTapi-v1-organization-position-levels--id-"
               value="1"
               data-component="body">
    <br>
<p>Sort order for display. Example: <code>1</code></p>
        </div>
        </form>

                    <h2 id="endpoints-DELETEapi-v1-organization-position-levels--id-">Remove the specified resource from storage.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-DELETEapi-v1-organization-position-levels--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:8000/api/v1/organization-position-levels/01k3ahgd0k990naes55gq52hfn" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-position-levels/01k3ahgd0k990naes55gq52hfn"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-organization-position-levels--id-">
</span>
<span id="execution-results-DELETEapi-v1-organization-position-levels--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-organization-position-levels--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-organization-position-levels--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-organization-position-levels--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-organization-position-levels--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-organization-position-levels--id-" data-method="DELETE"
      data-path="api/v1/organization-position-levels/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-organization-position-levels--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-v1-organization-position-levels--id-"
                    onclick="tryItOut('DELETEapi-v1-organization-position-levels--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-v1-organization-position-levels--id-"
                    onclick="cancelTryOut('DELETEapi-v1-organization-position-levels--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-v1-organization-position-levels--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/organization-position-levels/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-organization-position-levels--id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-organization-position-levels--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-organization-position-levels--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="DELETEapi-v1-organization-position-levels--id-"
               value="01k3ahgd0k990naes55gq52hfn"
               data-component="url">
    <br>
<p>The ID of the organization position level. Example: <code>01k3ahgd0k990naes55gq52hfn</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-GETapi-v1-organization-position-levels-active">Get active organization position levels.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-organization-position-levels-active">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organization-position-levels/active" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-position-levels/active"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organization-position-levels-active">
            <blockquote>
            <p>Example response (404):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;No query results for model [App\\Models\\OrganizationPositionLevel] active&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organization-position-levels-active" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organization-position-levels-active"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organization-position-levels-active"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organization-position-levels-active" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organization-position-levels-active">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organization-position-levels-active" data-method="GET"
      data-path="api/v1/organization-position-levels/active"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organization-position-levels-active', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organization-position-levels-active"
                    onclick="tryItOut('GETapi-v1-organization-position-levels-active');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organization-position-levels-active"
                    onclick="cancelTryOut('GETapi-v1-organization-position-levels-active');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organization-position-levels-active"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organization-position-levels/active</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organization-position-levels-active"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organization-position-levels-active"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organization-position-levels-active"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-GETapi-v1-organization-position-levels-hierarchy">Get organization position levels by hierarchy.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-organization-position-levels-hierarchy">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organization-position-levels/hierarchy" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-position-levels/hierarchy"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organization-position-levels-hierarchy">
            <blockquote>
            <p>Example response (404):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;No query results for model [App\\Models\\OrganizationPositionLevel] hierarchy&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organization-position-levels-hierarchy" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organization-position-levels-hierarchy"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organization-position-levels-hierarchy"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organization-position-levels-hierarchy" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organization-position-levels-hierarchy">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organization-position-levels-hierarchy" data-method="GET"
      data-path="api/v1/organization-position-levels/hierarchy"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organization-position-levels-hierarchy', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organization-position-levels-hierarchy"
                    onclick="tryItOut('GETapi-v1-organization-position-levels-hierarchy');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organization-position-levels-hierarchy"
                    onclick="cancelTryOut('GETapi-v1-organization-position-levels-hierarchy');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organization-position-levels-hierarchy"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organization-position-levels/hierarchy</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organization-position-levels-hierarchy"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organization-position-levels-hierarchy"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organization-position-levels-hierarchy"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-GETapi-v1-organization-positions">GET api/v1/organization-positions</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-organization-positions">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organization-positions" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-positions"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organization-positions">
            <blockquote>
            <p>Example response (500):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Server Error&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organization-positions" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organization-positions"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organization-positions"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organization-positions" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organization-positions">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organization-positions" data-method="GET"
      data-path="api/v1/organization-positions"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organization-positions', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organization-positions"
                    onclick="tryItOut('GETapi-v1-organization-positions');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organization-positions"
                    onclick="cancelTryOut('GETapi-v1-organization-positions');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organization-positions"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organization-positions</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organization-positions"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organization-positions"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organization-positions"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-POSTapi-v1-organization-positions">POST api/v1/organization-positions</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-organization-positions">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/organization-positions" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"organization_unit_id\": 1,
    \"position_code\": \"HR-DIR-001\",
    \"title\": \"HR Director\",
    \"position_level\": \"director\",
    \"job_description\": \"Responsible for managing HR operations and strategy\",
    \"qualifications\": [
        \"Bachelor degree in HR\",
        \"Minimum 5 years experience\"
    ],
    \"responsibilities\": [
        \"Team management\",
        \"Strategic planning\"
    ],
    \"min_salary\": 50000,
    \"max_salary\": 80000,
    \"is_active\": false,
    \"max_incumbents\": 1
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-positions"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "organization_unit_id": 1,
    "position_code": "HR-DIR-001",
    "title": "HR Director",
    "position_level": "director",
    "job_description": "Responsible for managing HR operations and strategy",
    "qualifications": [
        "Bachelor degree in HR",
        "Minimum 5 years experience"
    ],
    "responsibilities": [
        "Team management",
        "Strategic planning"
    ],
    "min_salary": 50000,
    "max_salary": 80000,
    "is_active": false,
    "max_incumbents": 1
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-organization-positions">
</span>
<span id="execution-results-POSTapi-v1-organization-positions" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-organization-positions"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-organization-positions"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-organization-positions" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-organization-positions">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-organization-positions" data-method="POST"
      data-path="api/v1/organization-positions"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-organization-positions', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-organization-positions"
                    onclick="tryItOut('POSTapi-v1-organization-positions');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-organization-positions"
                    onclick="cancelTryOut('POSTapi-v1-organization-positions');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-organization-positions"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/organization-positions</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-organization-positions"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-organization-positions"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-organization-positions"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>organization_unit_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="organization_unit_id"                data-endpoint="POSTapi-v1-organization-positions"
               value="1"
               data-component="body">
    <br>
<p>ID of the organization unit this position belongs to. The <code>id</code> of an existing record in the organization_units table. Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>position_code</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="position_code"                data-endpoint="POSTapi-v1-organization-positions"
               value="HR-DIR-001"
               data-component="body">
    <br>
<p>Unique code for the position. Example: <code>HR-DIR-001</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>title</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="title"                data-endpoint="POSTapi-v1-organization-positions"
               value="HR Director"
               data-component="body">
    <br>
<p>Title of the position. Must not be greater than 255 characters. Example: <code>HR Director</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>position_level</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="position_level"                data-endpoint="POSTapi-v1-organization-positions"
               value="director"
               data-component="body">
    <br>
<p>Level of the position in organizational hierarchy. Example: <code>director</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>board_member</code></li> <li><code>c_level</code></li> <li><code>vice_president</code></li> <li><code>director</code></li> <li><code>senior_manager</code></li> <li><code>manager</code></li> <li><code>assistant_manager</code></li> <li><code>supervisor</code></li> <li><code>senior_staff</code></li> <li><code>staff</code></li> <li><code>junior_staff</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>job_description</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="job_description"                data-endpoint="POSTapi-v1-organization-positions"
               value="Responsible for managing HR operations and strategy"
               data-component="body">
    <br>
<p>Detailed job description. Example: <code>Responsible for managing HR operations and strategy</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>qualifications</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="qualifications"                data-endpoint="POSTapi-v1-organization-positions"
               value=""
               data-component="body">
    <br>
<p>Array of required qualifications.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>responsibilities</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="responsibilities"                data-endpoint="POSTapi-v1-organization-positions"
               value=""
               data-component="body">
    <br>
<p>Array of key responsibilities.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>min_salary</code></b>&nbsp;&nbsp;
<small>number</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="min_salary"                data-endpoint="POSTapi-v1-organization-positions"
               value="50000"
               data-component="body">
    <br>
<p>Minimum salary for this position. Must be at least 0. Example: <code>50000</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>max_salary</code></b>&nbsp;&nbsp;
<small>number</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="max_salary"                data-endpoint="POSTapi-v1-organization-positions"
               value="80000"
               data-component="body">
    <br>
<p>Maximum salary for this position. Must be at least 0. Example: <code>80000</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>is_active</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
                <label data-endpoint="POSTapi-v1-organization-positions" style="display: none">
            <input type="radio" name="is_active"
                   value="true"
                   data-endpoint="POSTapi-v1-organization-positions"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="POSTapi-v1-organization-positions" style="display: none">
            <input type="radio" name="is_active"
                   value="false"
                   data-endpoint="POSTapi-v1-organization-positions"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Whether the position is active. Example: <code>false</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>max_incumbents</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="max_incumbents"                data-endpoint="POSTapi-v1-organization-positions"
               value="1"
               data-component="body">
    <br>
<p>Maximum number of people who can hold this position. Must be at least 1. Example: <code>1</code></p>
        </div>
        </form>

                    <h2 id="endpoints-GETapi-v1-organization-positions--id-">GET api/v1/organization-positions/{id}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-organization-positions--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organization-positions/01k3ahgd85gtq5jaencdx3wxmb" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-positions/01k3ahgd85gtq5jaencdx3wxmb"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organization-positions--id-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: &quot;01k3ahgd85gtq5jaencdx3wxmb&quot;,
    &quot;organization_unit_id&quot;: &quot;01k3ahgd6b11gw0s93tp3rxz5y&quot;,
    &quot;position_code&quot;: &quot;POS001&quot;,
    &quot;organization_position_level_id&quot;: &quot;01k3ahgd0k990naes55gq52hfn&quot;,
    &quot;title&quot;: &quot;Chairman of Board of Commissioners&quot;,
    &quot;job_description&quot;: &quot;Lead the board of commissioners and ensure effective governance oversight&quot;,
    &quot;qualifications&quot;: [
        &quot;Minimum 15 years of executive experience&quot;,
        &quot;Strong leadership and governance experience&quot;,
        &quot;Understanding of corporate governance principles&quot;,
        &quot;Board certification preferred&quot;
    ],
    &quot;responsibilities&quot;: [
        &quot;Chair board meetings&quot;,
        &quot;Provide strategic oversight&quot;,
        &quot;Ensure compliance with regulations&quot;,
        &quot;Evaluate board performance&quot;
    ],
    &quot;min_salary&quot;: &quot;500000.00&quot;,
    &quot;max_salary&quot;: &quot;800000.00&quot;,
    &quot;is_active&quot;: true,
    &quot;max_incumbents&quot;: 1,
    &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
    &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
    &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
    &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
    &quot;organization_unit&quot;: {
        &quot;id&quot;: &quot;01k3ahgd6b11gw0s93tp3rxz5y&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
        &quot;unit_code&quot;: &quot;BOC001&quot;,
        &quot;name&quot;: &quot;Board of Commissioners&quot;,
        &quot;unit_type&quot;: &quot;board_of_commissioners&quot;,
        &quot;description&quot;: &quot;Supervisory board responsible for oversight and strategic guidance&quot;,
        &quot;parent_unit_id&quot;: null,
        &quot;responsibilities&quot;: [
            &quot;Strategic oversight&quot;,
            &quot;Risk management oversight&quot;,
            &quot;Appointment of board of directors&quot;,
            &quot;Approval of major corporate actions&quot;
        ],
        &quot;authorities&quot;: [
            &quot;Approve annual budget&quot;,
            &quot;Appoint and dismiss directors&quot;,
            &quot;Approve major investments&quot;,
            &quot;Set executive compensation&quot;
        ],
        &quot;is_active&quot;: true,
        &quot;sort_order&quot;: 1,
        &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;organization_code&quot;: &quot;HC001&quot;,
            &quot;organization_type&quot;: &quot;holding_company&quot;,
            &quot;parent_organization_id&quot;: null,
            &quot;name&quot;: &quot;TechCorp Holdings&quot;,
            &quot;description&quot;: &quot;Main holding company for technology businesses&quot;,
            &quot;address&quot;: &quot;123 Tech Street, Innovation District&quot;,
            &quot;phone&quot;: &quot;+1-555-0100&quot;,
            &quot;email&quot;: &quot;info@techcorp.com&quot;,
            &quot;website&quot;: &quot;https://techcorp.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG001&quot;,
            &quot;tax_number&quot;: &quot;TAX001&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 7,
                &quot;independent_directors&quot;: 4,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;,
                    &quot;nomination&quot;,
                    &quot;remuneration&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;10000000.00&quot;,
            &quot;paid_capital&quot;: &quot;8500000.00&quot;,
            &quot;establishment_date&quot;: &quot;2020-01-15T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Public Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Investment holding and management&quot;,
            &quot;contact_persons&quot;: {
                &quot;ceo&quot;: {
                    &quot;name&quot;: &quot;John Smith&quot;,
                    &quot;email&quot;: &quot;ceo@techcorp.com&quot;
                },
                &quot;cfo&quot;: {
                    &quot;name&quot;: &quot;Jane Doe&quot;,
                    &quot;email&quot;: &quot;cfo@techcorp.com&quot;
                }
            },
            &quot;level&quot;: 0,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        }
    },
    &quot;active_memberships&quot;: [],
    &quot;memberships&quot;: []
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organization-positions--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organization-positions--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organization-positions--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organization-positions--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organization-positions--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organization-positions--id-" data-method="GET"
      data-path="api/v1/organization-positions/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organization-positions--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organization-positions--id-"
                    onclick="tryItOut('GETapi-v1-organization-positions--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organization-positions--id-"
                    onclick="cancelTryOut('GETapi-v1-organization-positions--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organization-positions--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organization-positions/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organization-positions--id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organization-positions--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organization-positions--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-v1-organization-positions--id-"
               value="01k3ahgd85gtq5jaencdx3wxmb"
               data-component="url">
    <br>
<p>The ID of the organization position. Example: <code>01k3ahgd85gtq5jaencdx3wxmb</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-PUTapi-v1-organization-positions--id-">PUT api/v1/organization-positions/{id}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PUTapi-v1-organization-positions--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:8000/api/v1/organization-positions/01k3ahgd85gtq5jaencdx3wxmb" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"position_code\": \"HR-DIR-001\",
    \"title\": \"HR Director\",
    \"position_level\": \"director\",
    \"job_description\": \"Responsible for managing HR operations and strategy\",
    \"qualifications\": [
        \"Bachelor degree in HR\",
        \"Minimum 5 years experience\"
    ],
    \"responsibilities\": [
        \"Team management\",
        \"Strategic planning\"
    ],
    \"min_salary\": 50000,
    \"max_salary\": 80000,
    \"is_active\": false,
    \"max_incumbents\": 1
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-positions/01k3ahgd85gtq5jaencdx3wxmb"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "position_code": "HR-DIR-001",
    "title": "HR Director",
    "position_level": "director",
    "job_description": "Responsible for managing HR operations and strategy",
    "qualifications": [
        "Bachelor degree in HR",
        "Minimum 5 years experience"
    ],
    "responsibilities": [
        "Team management",
        "Strategic planning"
    ],
    "min_salary": 50000,
    "max_salary": 80000,
    "is_active": false,
    "max_incumbents": 1
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-organization-positions--id-">
</span>
<span id="execution-results-PUTapi-v1-organization-positions--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-organization-positions--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-organization-positions--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-organization-positions--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-organization-positions--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-organization-positions--id-" data-method="PUT"
      data-path="api/v1/organization-positions/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-organization-positions--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-v1-organization-positions--id-"
                    onclick="tryItOut('PUTapi-v1-organization-positions--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-v1-organization-positions--id-"
                    onclick="cancelTryOut('PUTapi-v1-organization-positions--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-v1-organization-positions--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/organization-positions/{id}</code></b>
        </p>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/v1/organization-positions/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-v1-organization-positions--id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-organization-positions--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-organization-positions--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PUTapi-v1-organization-positions--id-"
               value="01k3ahgd85gtq5jaencdx3wxmb"
               data-component="url">
    <br>
<p>The ID of the organization position. Example: <code>01k3ahgd85gtq5jaencdx3wxmb</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>position_code</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="position_code"                data-endpoint="PUTapi-v1-organization-positions--id-"
               value="HR-DIR-001"
               data-component="body">
    <br>
<p>Unique code for the position. Example: <code>HR-DIR-001</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>title</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="title"                data-endpoint="PUTapi-v1-organization-positions--id-"
               value="HR Director"
               data-component="body">
    <br>
<p>Title of the position. Must not be greater than 255 characters. Example: <code>HR Director</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>position_level</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="position_level"                data-endpoint="PUTapi-v1-organization-positions--id-"
               value="director"
               data-component="body">
    <br>
<p>Level of the position in organizational hierarchy. Example: <code>director</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>board_member</code></li> <li><code>c_level</code></li> <li><code>vice_president</code></li> <li><code>director</code></li> <li><code>senior_manager</code></li> <li><code>manager</code></li> <li><code>assistant_manager</code></li> <li><code>supervisor</code></li> <li><code>senior_staff</code></li> <li><code>staff</code></li> <li><code>junior_staff</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>job_description</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="job_description"                data-endpoint="PUTapi-v1-organization-positions--id-"
               value="Responsible for managing HR operations and strategy"
               data-component="body">
    <br>
<p>Detailed job description. Example: <code>Responsible for managing HR operations and strategy</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>qualifications</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="qualifications"                data-endpoint="PUTapi-v1-organization-positions--id-"
               value=""
               data-component="body">
    <br>
<p>Array of required qualifications.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>responsibilities</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="responsibilities"                data-endpoint="PUTapi-v1-organization-positions--id-"
               value=""
               data-component="body">
    <br>
<p>Array of key responsibilities.</p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>min_salary</code></b>&nbsp;&nbsp;
<small>number</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="min_salary"                data-endpoint="PUTapi-v1-organization-positions--id-"
               value="50000"
               data-component="body">
    <br>
<p>Minimum salary for this position. Must be at least 0. Example: <code>50000</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>max_salary</code></b>&nbsp;&nbsp;
<small>number</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="max_salary"                data-endpoint="PUTapi-v1-organization-positions--id-"
               value="80000"
               data-component="body">
    <br>
<p>Maximum salary for this position. Must be at least 0. Example: <code>80000</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>is_active</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
                <label data-endpoint="PUTapi-v1-organization-positions--id-" style="display: none">
            <input type="radio" name="is_active"
                   value="true"
                   data-endpoint="PUTapi-v1-organization-positions--id-"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PUTapi-v1-organization-positions--id-" style="display: none">
            <input type="radio" name="is_active"
                   value="false"
                   data-endpoint="PUTapi-v1-organization-positions--id-"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Whether the position is active. Example: <code>false</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>max_incumbents</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="max_incumbents"                data-endpoint="PUTapi-v1-organization-positions--id-"
               value="1"
               data-component="body">
    <br>
<p>Maximum number of people who can hold this position. Must be at least 1. Example: <code>1</code></p>
        </div>
        </form>

                    <h2 id="endpoints-DELETEapi-v1-organization-positions--id-">DELETE api/v1/organization-positions/{id}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-DELETEapi-v1-organization-positions--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:8000/api/v1/organization-positions/01k3ahgd85gtq5jaencdx3wxmb" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-positions/01k3ahgd85gtq5jaencdx3wxmb"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-organization-positions--id-">
</span>
<span id="execution-results-DELETEapi-v1-organization-positions--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-organization-positions--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-organization-positions--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-organization-positions--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-organization-positions--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-organization-positions--id-" data-method="DELETE"
      data-path="api/v1/organization-positions/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-organization-positions--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-v1-organization-positions--id-"
                    onclick="tryItOut('DELETEapi-v1-organization-positions--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-v1-organization-positions--id-"
                    onclick="cancelTryOut('DELETEapi-v1-organization-positions--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-v1-organization-positions--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/organization-positions/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-organization-positions--id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-organization-positions--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-organization-positions--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="DELETEapi-v1-organization-positions--id-"
               value="01k3ahgd85gtq5jaencdx3wxmb"
               data-component="url">
    <br>
<p>The ID of the organization position. Example: <code>01k3ahgd85gtq5jaencdx3wxmb</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-GETapi-v1-organization-positions-available">GET api/v1/organization-positions/available</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-organization-positions-available">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organization-positions/available" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-positions/available"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organization-positions-available">
            <blockquote>
            <p>Example response (404):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;No query results for model [App\\Models\\OrganizationPosition] available&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organization-positions-available" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organization-positions-available"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organization-positions-available"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organization-positions-available" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organization-positions-available">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organization-positions-available" data-method="GET"
      data-path="api/v1/organization-positions/available"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organization-positions-available', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organization-positions-available"
                    onclick="tryItOut('GETapi-v1-organization-positions-available');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organization-positions-available"
                    onclick="cancelTryOut('GETapi-v1-organization-positions-available');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organization-positions-available"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organization-positions/available</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organization-positions-available"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organization-positions-available"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organization-positions-available"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-GETapi-v1-organization-positions-level--level-">GET api/v1/organization-positions/level/{level}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-organization-positions-level--level-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organization-positions/level/architecto" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-positions/level/architecto"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organization-positions-level--level-">
            <blockquote>
            <p>Example response (400):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Invalid position level&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organization-positions-level--level-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organization-positions-level--level-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organization-positions-level--level-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organization-positions-level--level-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organization-positions-level--level-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organization-positions-level--level-" data-method="GET"
      data-path="api/v1/organization-positions/level/{level}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organization-positions-level--level-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organization-positions-level--level-"
                    onclick="tryItOut('GETapi-v1-organization-positions-level--level-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organization-positions-level--level-"
                    onclick="cancelTryOut('GETapi-v1-organization-positions-level--level-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organization-positions-level--level-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organization-positions/level/{level}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organization-positions-level--level-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organization-positions-level--level-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organization-positions-level--level-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>level</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="level"                data-endpoint="GETapi-v1-organization-positions-level--level-"
               value="architecto"
               data-component="url">
    <br>
<p>The level. Example: <code>architecto</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-GETapi-v1-organization-positions--organizationPosition_id--incumbents">GET api/v1/organization-positions/{organizationPosition_id}/incumbents</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-organization-positions--organizationPosition_id--incumbents">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organization-positions/01k3ahgd85gtq5jaencdx3wxmb/incumbents" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-positions/01k3ahgd85gtq5jaencdx3wxmb/incumbents"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organization-positions--organizationPosition_id--incumbents">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;position&quot;: &quot;Chairman of Board of Commissioners&quot;,
    &quot;max_incumbents&quot;: 1,
    &quot;current_incumbents&quot;: 0,
    &quot;available_slots&quot;: 1,
    &quot;incumbents&quot;: []
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organization-positions--organizationPosition_id--incumbents" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organization-positions--organizationPosition_id--incumbents"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organization-positions--organizationPosition_id--incumbents"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organization-positions--organizationPosition_id--incumbents" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organization-positions--organizationPosition_id--incumbents">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organization-positions--organizationPosition_id--incumbents" data-method="GET"
      data-path="api/v1/organization-positions/{organizationPosition_id}/incumbents"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organization-positions--organizationPosition_id--incumbents', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organization-positions--organizationPosition_id--incumbents"
                    onclick="tryItOut('GETapi-v1-organization-positions--organizationPosition_id--incumbents');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organization-positions--organizationPosition_id--incumbents"
                    onclick="cancelTryOut('GETapi-v1-organization-positions--organizationPosition_id--incumbents');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organization-positions--organizationPosition_id--incumbents"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organization-positions/{organizationPosition_id}/incumbents</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organization-positions--organizationPosition_id--incumbents"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organization-positions--organizationPosition_id--incumbents"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organization-positions--organizationPosition_id--incumbents"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>organizationPosition_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="organizationPosition_id"                data-endpoint="GETapi-v1-organization-positions--organizationPosition_id--incumbents"
               value="01k3ahgd85gtq5jaencdx3wxmb"
               data-component="url">
    <br>
<p>The ID of the organizationPosition. Example: <code>01k3ahgd85gtq5jaencdx3wxmb</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-GETapi-v1-organization-memberships">GET api/v1/organization-memberships</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-organization-memberships">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organization-memberships" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-memberships"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organization-memberships">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">[
    {
        &quot;id&quot;: &quot;01k3ahgffw63x0k6eg7rx124se&quot;,
        &quot;user_id&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
        &quot;organization_unit_id&quot;: &quot;01k3ahgd72vvytzzw7q4htmrd9&quot;,
        &quot;organization_position_id&quot;: null,
        &quot;membership_type&quot;: &quot;consultant&quot;,
        &quot;start_date&quot;: &quot;2024-01-01T00:00:00.000000Z&quot;,
        &quot;end_date&quot;: &quot;2024-12-31T00:00:00.000000Z&quot;,
        &quot;status&quot;: &quot;active&quot;,
        &quot;additional_roles&quot;: [
            &quot;technical_advisory&quot;,
            &quot;code_review&quot;
        ],
        &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;user&quot;: {
            &quot;id&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;name&quot;: &quot;Test User&quot;,
            &quot;email&quot;: &quot;test@example.com&quot;,
            &quot;email_verified_at&quot;: &quot;2025-08-23T04:03:09.000000Z&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: null,
            &quot;updated_by&quot;: null
        },
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;organization_code&quot;: &quot;SUB001&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Software&quot;,
            &quot;description&quot;: &quot;Software development and consulting services&quot;,
            &quot;address&quot;: &quot;456 Software Ave, Tech City&quot;,
            &quot;phone&quot;: &quot;+1-555-0200&quot;,
            &quot;email&quot;: &quot;info@techcorpsoftware.com&quot;,
            &quot;website&quot;: &quot;https://techcorpsoftware.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG002&quot;,
            &quot;tax_number&quot;: &quot;TAX002&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;5000000.00&quot;,
            &quot;paid_capital&quot;: &quot;4000000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-03-01T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Software development, web applications, mobile apps&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;Mike Johnson&quot;,
                    &quot;email&quot;: &quot;md@techcorpsoftware.com&quot;
                },
                &quot;cto&quot;: {
                    &quot;name&quot;: &quot;Sarah Wilson&quot;,
                    &quot;email&quot;: &quot;cto@techcorpsoftware.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_unit&quot;: {
            &quot;id&quot;: &quot;01k3ahgd72vvytzzw7q4htmrd9&quot;,
            &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;unit_code&quot;: &quot;ENG001&quot;,
            &quot;name&quot;: &quot;Engineering Division&quot;,
            &quot;unit_type&quot;: &quot;division&quot;,
            &quot;description&quot;: &quot;Software engineering and development&quot;,
            &quot;parent_unit_id&quot;: null,
            &quot;responsibilities&quot;: [
                &quot;Software development&quot;,
                &quot;Technical architecture&quot;,
                &quot;Code quality assurance&quot;,
                &quot;Development methodology&quot;
            ],
            &quot;authorities&quot;: [
                &quot;Define technical standards&quot;,
                &quot;Approve technical designs&quot;,
                &quot;Manage development teams&quot;,
                &quot;Release software products&quot;
            ],
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 2,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_position&quot;: null
    },
    {
        &quot;id&quot;: &quot;01k3ahgffdc86axx81npw0gwf7&quot;,
        &quot;user_id&quot;: &quot;01k3ahgfctw5yv4vnjyaxmtmy4&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
        &quot;organization_unit_id&quot;: &quot;01k3ahgd7agwyj7t6x83pked0q&quot;,
        &quot;organization_position_id&quot;: &quot;01k3ahgd9jb70rjba8hw9zpzny&quot;,
        &quot;membership_type&quot;: &quot;employee&quot;,
        &quot;start_date&quot;: &quot;2022-02-01T00:00:00.000000Z&quot;,
        &quot;end_date&quot;: null,
        &quot;status&quot;: &quot;active&quot;,
        &quot;additional_roles&quot;: [
            &quot;api_development&quot;,
            &quot;integration&quot;
        ],
        &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;user&quot;: {
            &quot;id&quot;: &quot;01k3ahgfctw5yv4vnjyaxmtmy4&quot;,
            &quot;name&quot;: &quot;Alex Thompson&quot;,
            &quot;email&quot;: &quot;alex.thompson@techcorpsoftware.com&quot;,
            &quot;email_verified_at&quot;: &quot;2025-08-23T04:03:12.000000Z&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;organization_code&quot;: &quot;SUB001&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Software&quot;,
            &quot;description&quot;: &quot;Software development and consulting services&quot;,
            &quot;address&quot;: &quot;456 Software Ave, Tech City&quot;,
            &quot;phone&quot;: &quot;+1-555-0200&quot;,
            &quot;email&quot;: &quot;info@techcorpsoftware.com&quot;,
            &quot;website&quot;: &quot;https://techcorpsoftware.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG002&quot;,
            &quot;tax_number&quot;: &quot;TAX002&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;5000000.00&quot;,
            &quot;paid_capital&quot;: &quot;4000000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-03-01T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Software development, web applications, mobile apps&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;Mike Johnson&quot;,
                    &quot;email&quot;: &quot;md@techcorpsoftware.com&quot;
                },
                &quot;cto&quot;: {
                    &quot;name&quot;: &quot;Sarah Wilson&quot;,
                    &quot;email&quot;: &quot;cto@techcorpsoftware.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_unit&quot;: {
            &quot;id&quot;: &quot;01k3ahgd7agwyj7t6x83pked0q&quot;,
            &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;unit_code&quot;: &quot;BEND001&quot;,
            &quot;name&quot;: &quot;Backend Development Team&quot;,
            &quot;unit_type&quot;: &quot;team&quot;,
            &quot;description&quot;: &quot;Server-side development and API services&quot;,
            &quot;parent_unit_id&quot;: &quot;01k3ahgd72vvytzzw7q4htmrd9&quot;,
            &quot;responsibilities&quot;: [
                &quot;API development&quot;,
                &quot;Database design&quot;,
                &quot;Server architecture&quot;,
                &quot;Backend testing&quot;
            ],
            &quot;authorities&quot;: [
                &quot;Design database schemas&quot;,
                &quot;Implement business logic&quot;,
                &quot;Develop APIs&quot;,
                &quot;Optimize performance&quot;
            ],
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 2,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_position&quot;: {
            &quot;id&quot;: &quot;01k3ahgd9jb70rjba8hw9zpzny&quot;,
            &quot;organization_unit_id&quot;: &quot;01k3ahgd7agwyj7t6x83pked0q&quot;,
            &quot;position_code&quot;: &quot;POS011&quot;,
            &quot;organization_position_level_id&quot;: &quot;01k3ahgd26zx9f3d9fp6qq342n&quot;,
            &quot;title&quot;: &quot;Backend Developer&quot;,
            &quot;job_description&quot;: &quot;Develop and maintain backend services and APIs&quot;,
            &quot;qualifications&quot;: [
                &quot;Computer Science degree or equivalent experience&quot;,
                &quot;Minimum 2 years of backend development experience&quot;,
                &quot;Proficiency in PHP, Laravel, and database technologies&quot;,
                &quot;Understanding of RESTful API design&quot;
            ],
            &quot;responsibilities&quot;: [
                &quot;Implement business logic&quot;,
                &quot;Develop REST APIs&quot;,
                &quot;Write database queries&quot;,
                &quot;Ensure code quality and testing&quot;
            ],
            &quot;min_salary&quot;: &quot;85000.00&quot;,
            &quot;max_salary&quot;: &quot;125000.00&quot;,
            &quot;is_active&quot;: true,
            &quot;max_incumbents&quot;: 5,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        }
    },
    {
        &quot;id&quot;: &quot;01k3ahgff5ez4nnypfvxnjranq&quot;,
        &quot;user_id&quot;: &quot;01k3ahgfcc1vhk253np3mm3xg3&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
        &quot;organization_unit_id&quot;: &quot;01k3ahgd7617t0tmr9jsk9x1fb&quot;,
        &quot;organization_position_id&quot;: &quot;01k3ahgd9ahwkbk06q491zfnym&quot;,
        &quot;membership_type&quot;: &quot;employee&quot;,
        &quot;start_date&quot;: &quot;2022-01-15T00:00:00.000000Z&quot;,
        &quot;end_date&quot;: null,
        &quot;status&quot;: &quot;active&quot;,
        &quot;additional_roles&quot;: [
            &quot;component_development&quot;,
            &quot;testing&quot;
        ],
        &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;user&quot;: {
            &quot;id&quot;: &quot;01k3ahgfcc1vhk253np3mm3xg3&quot;,
            &quot;name&quot;: &quot;Michael Chen&quot;,
            &quot;email&quot;: &quot;michael.chen@techcorpsoftware.com&quot;,
            &quot;email_verified_at&quot;: &quot;2025-08-23T04:03:11.000000Z&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;organization_code&quot;: &quot;SUB001&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Software&quot;,
            &quot;description&quot;: &quot;Software development and consulting services&quot;,
            &quot;address&quot;: &quot;456 Software Ave, Tech City&quot;,
            &quot;phone&quot;: &quot;+1-555-0200&quot;,
            &quot;email&quot;: &quot;info@techcorpsoftware.com&quot;,
            &quot;website&quot;: &quot;https://techcorpsoftware.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG002&quot;,
            &quot;tax_number&quot;: &quot;TAX002&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;5000000.00&quot;,
            &quot;paid_capital&quot;: &quot;4000000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-03-01T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Software development, web applications, mobile apps&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;Mike Johnson&quot;,
                    &quot;email&quot;: &quot;md@techcorpsoftware.com&quot;
                },
                &quot;cto&quot;: {
                    &quot;name&quot;: &quot;Sarah Wilson&quot;,
                    &quot;email&quot;: &quot;cto@techcorpsoftware.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_unit&quot;: {
            &quot;id&quot;: &quot;01k3ahgd7617t0tmr9jsk9x1fb&quot;,
            &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;unit_code&quot;: &quot;FEND001&quot;,
            &quot;name&quot;: &quot;Frontend Development Team&quot;,
            &quot;unit_type&quot;: &quot;team&quot;,
            &quot;description&quot;: &quot;User interface and user experience development&quot;,
            &quot;parent_unit_id&quot;: &quot;01k3ahgd72vvytzzw7q4htmrd9&quot;,
            &quot;responsibilities&quot;: [
                &quot;UI/UX development&quot;,
                &quot;Frontend architecture&quot;,
                &quot;User interaction design&quot;,
                &quot;Frontend testing&quot;
            ],
            &quot;authorities&quot;: [
                &quot;Choose frontend frameworks&quot;,
                &quot;Design user interfaces&quot;,
                &quot;Implement frontend features&quot;,
                &quot;Optimize user experience&quot;
            ],
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 1,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_position&quot;: {
            &quot;id&quot;: &quot;01k3ahgd9ahwkbk06q491zfnym&quot;,
            &quot;organization_unit_id&quot;: &quot;01k3ahgd7617t0tmr9jsk9x1fb&quot;,
            &quot;position_code&quot;: &quot;POS009&quot;,
            &quot;organization_position_level_id&quot;: &quot;01k3ahgd26zx9f3d9fp6qq342n&quot;,
            &quot;title&quot;: &quot;Frontend Developer&quot;,
            &quot;job_description&quot;: &quot;Develop and maintain frontend applications&quot;,
            &quot;qualifications&quot;: [
                &quot;Computer Science degree or equivalent experience&quot;,
                &quot;Minimum 2 years of frontend development experience&quot;,
                &quot;Proficiency in HTML, CSS, JavaScript, and React&quot;,
                &quot;Understanding of responsive design principles&quot;
            ],
            &quot;responsibilities&quot;: [
                &quot;Implement UI components&quot;,
                &quot;Collaborate with designers and backend developers&quot;,
                &quot;Write clean, maintainable code&quot;,
                &quot;Participate in code reviews&quot;
            ],
            &quot;min_salary&quot;: &quot;80000.00&quot;,
            &quot;max_salary&quot;: &quot;120000.00&quot;,
            &quot;is_active&quot;: true,
            &quot;max_incumbents&quot;: 5,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        }
    },
    {
        &quot;id&quot;: &quot;01k3ahgffrn0bwzqfbgk0jthhs&quot;,
        &quot;user_id&quot;: &quot;01k3ahgfbpjhssg8awdvcr3593&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd3y0y9a6ca0k9v0259t&quot;,
        &quot;organization_unit_id&quot;: &quot;01k3ahgd7q224nvfd03a4gv88n&quot;,
        &quot;organization_position_id&quot;: null,
        &quot;membership_type&quot;: &quot;employee&quot;,
        &quot;start_date&quot;: &quot;2021-07-01T00:00:00.000000Z&quot;,
        &quot;end_date&quot;: null,
        &quot;status&quot;: &quot;active&quot;,
        &quot;additional_roles&quot;: [
            &quot;head_of_ai&quot;,
            &quot;research_leadership&quot;
        ],
        &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;user&quot;: {
            &quot;id&quot;: &quot;01k3ahgfbpjhssg8awdvcr3593&quot;,
            &quot;name&quot;: &quot;Emily Davis&quot;,
            &quot;email&quot;: &quot;emily.davis@techcorpdata.com&quot;,
            &quot;email_verified_at&quot;: &quot;2025-08-23T04:03:11.000000Z&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd3y0y9a6ca0k9v0259t&quot;,
            &quot;organization_code&quot;: &quot;SUB002&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Data&quot;,
            &quot;description&quot;: &quot;Data analytics and AI solutions&quot;,
            &quot;address&quot;: &quot;789 Data Drive, Analytics Park&quot;,
            &quot;phone&quot;: &quot;+1-555-0300&quot;,
            &quot;email&quot;: &quot;info@techcorpdata.com&quot;,
            &quot;website&quot;: &quot;https://techcorpdata.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG003&quot;,
            &quot;tax_number&quot;: &quot;TAX003&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;3000000.00&quot;,
            &quot;paid_capital&quot;: &quot;2500000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-06-15T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Data analytics, machine learning, AI consulting&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;David Brown&quot;,
                    &quot;email&quot;: &quot;md@techcorpdata.com&quot;
                },
                &quot;head_of_ai&quot;: {
                    &quot;name&quot;: &quot;Emily Davis&quot;,
                    &quot;email&quot;: &quot;ai@techcorpdata.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd3y0y9a6ca0k9v0259t&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_unit&quot;: {
            &quot;id&quot;: &quot;01k3ahgd7q224nvfd03a4gv88n&quot;,
            &quot;organization_id&quot;: &quot;01k3ahgd3y0y9a6ca0k9v0259t&quot;,
            &quot;unit_code&quot;: &quot;AI001&quot;,
            &quot;name&quot;: &quot;AI Research Division&quot;,
            &quot;unit_type&quot;: &quot;division&quot;,
            &quot;description&quot;: &quot;Artificial intelligence research and development&quot;,
            &quot;parent_unit_id&quot;: null,
            &quot;responsibilities&quot;: [
                &quot;AI research&quot;,
                &quot;Machine learning development&quot;,
                &quot;Algorithm optimization&quot;,
                &quot;AI product development&quot;
            ],
            &quot;authorities&quot;: [
                &quot;Conduct research projects&quot;,
                &quot;Develop AI models&quot;,
                &quot;Publish research findings&quot;,
                &quot;Collaborate with academia&quot;
            ],
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 1,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_position&quot;: null
    },
    {
        &quot;id&quot;: &quot;01k3ahgffh8054n2jfvdb1zp5w&quot;,
        &quot;user_id&quot;: &quot;01k3ahgfd1chnj9bqxbdgfesc8&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
        &quot;organization_unit_id&quot;: &quot;01k3ahgd7ejcsgdrma1n847yq0&quot;,
        &quot;organization_position_id&quot;: &quot;01k3ahgd9qgjvege6cqt709pe4&quot;,
        &quot;membership_type&quot;: &quot;employee&quot;,
        &quot;start_date&quot;: &quot;2021-07-01T00:00:00.000000Z&quot;,
        &quot;end_date&quot;: null,
        &quot;status&quot;: &quot;active&quot;,
        &quot;additional_roles&quot;: [
            &quot;process_definition&quot;,
            &quot;quality_metrics&quot;
        ],
        &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;user&quot;: {
            &quot;id&quot;: &quot;01k3ahgfd1chnj9bqxbdgfesc8&quot;,
            &quot;name&quot;: &quot;Maria Rodriguez&quot;,
            &quot;email&quot;: &quot;maria.rodriguez@techcorpsoftware.com&quot;,
            &quot;email_verified_at&quot;: &quot;2025-08-23T04:03:12.000000Z&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;organization_code&quot;: &quot;SUB001&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Software&quot;,
            &quot;description&quot;: &quot;Software development and consulting services&quot;,
            &quot;address&quot;: &quot;456 Software Ave, Tech City&quot;,
            &quot;phone&quot;: &quot;+1-555-0200&quot;,
            &quot;email&quot;: &quot;info@techcorpsoftware.com&quot;,
            &quot;website&quot;: &quot;https://techcorpsoftware.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG002&quot;,
            &quot;tax_number&quot;: &quot;TAX002&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;5000000.00&quot;,
            &quot;paid_capital&quot;: &quot;4000000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-03-01T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Software development, web applications, mobile apps&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;Mike Johnson&quot;,
                    &quot;email&quot;: &quot;md@techcorpsoftware.com&quot;
                },
                &quot;cto&quot;: {
                    &quot;name&quot;: &quot;Sarah Wilson&quot;,
                    &quot;email&quot;: &quot;cto@techcorpsoftware.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_unit&quot;: {
            &quot;id&quot;: &quot;01k3ahgd7ejcsgdrma1n847yq0&quot;,
            &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;unit_code&quot;: &quot;QA001&quot;,
            &quot;name&quot;: &quot;Quality Assurance Department&quot;,
            &quot;unit_type&quot;: &quot;department&quot;,
            &quot;description&quot;: &quot;Software testing and quality control&quot;,
            &quot;parent_unit_id&quot;: null,
            &quot;responsibilities&quot;: [
                &quot;Test planning&quot;,
                &quot;Test execution&quot;,
                &quot;Bug reporting&quot;,
                &quot;Quality metrics&quot;
            ],
            &quot;authorities&quot;: [
                &quot;Approve software releases&quot;,
                &quot;Define testing standards&quot;,
                &quot;Block defective releases&quot;,
                &quot;Report quality metrics&quot;
            ],
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 3,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_position&quot;: {
            &quot;id&quot;: &quot;01k3ahgd9qgjvege6cqt709pe4&quot;,
            &quot;organization_unit_id&quot;: &quot;01k3ahgd7ejcsgdrma1n847yq0&quot;,
            &quot;position_code&quot;: &quot;POS012&quot;,
            &quot;organization_position_level_id&quot;: &quot;01k3ahgd1hb29v5gmwsk01ntwc&quot;,
            &quot;title&quot;: &quot;QA Manager&quot;,
            &quot;job_description&quot;: &quot;Lead quality assurance processes and team&quot;,
            &quot;qualifications&quot;: [
                &quot;Computer Science degree or equivalent experience&quot;,
                &quot;Minimum 5 years of QA experience&quot;,
                &quot;Strong knowledge of testing methodologies&quot;,
                &quot;Leadership and team management skills&quot;
            ],
            &quot;responsibilities&quot;: [
                &quot;Define testing strategies&quot;,
                &quot;Manage QA team&quot;,
                &quot;Ensure product quality&quot;,
                &quot;Implement QA processes&quot;
            ],
            &quot;min_salary&quot;: &quot;100000.00&quot;,
            &quot;max_salary&quot;: &quot;150000.00&quot;,
            &quot;is_active&quot;: true,
            &quot;max_incumbents&quot;: 1,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        }
    },
    {
        &quot;id&quot;: &quot;01k3ahgffmg6acjpsrtg9s7rws&quot;,
        &quot;user_id&quot;: &quot;01k3ahgfbfpb4v8pchgbdynhcp&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd3y0y9a6ca0k9v0259t&quot;,
        &quot;organization_unit_id&quot;: null,
        &quot;organization_position_id&quot;: null,
        &quot;membership_type&quot;: &quot;employee&quot;,
        &quot;start_date&quot;: &quot;2021-06-15T00:00:00.000000Z&quot;,
        &quot;end_date&quot;: null,
        &quot;status&quot;: &quot;active&quot;,
        &quot;additional_roles&quot;: [
            &quot;managing_director&quot;,
            &quot;ai_strategy&quot;
        ],
        &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;user&quot;: {
            &quot;id&quot;: &quot;01k3ahgfbfpb4v8pchgbdynhcp&quot;,
            &quot;name&quot;: &quot;David Brown&quot;,
            &quot;email&quot;: &quot;david.brown@techcorpdata.com&quot;,
            &quot;email_verified_at&quot;: &quot;2025-08-23T04:03:11.000000Z&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd3y0y9a6ca0k9v0259t&quot;,
            &quot;organization_code&quot;: &quot;SUB002&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Data&quot;,
            &quot;description&quot;: &quot;Data analytics and AI solutions&quot;,
            &quot;address&quot;: &quot;789 Data Drive, Analytics Park&quot;,
            &quot;phone&quot;: &quot;+1-555-0300&quot;,
            &quot;email&quot;: &quot;info@techcorpdata.com&quot;,
            &quot;website&quot;: &quot;https://techcorpdata.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG003&quot;,
            &quot;tax_number&quot;: &quot;TAX003&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;3000000.00&quot;,
            &quot;paid_capital&quot;: &quot;2500000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-06-15T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Data analytics, machine learning, AI consulting&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;David Brown&quot;,
                    &quot;email&quot;: &quot;md@techcorpdata.com&quot;
                },
                &quot;head_of_ai&quot;: {
                    &quot;name&quot;: &quot;Emily Davis&quot;,
                    &quot;email&quot;: &quot;ai@techcorpdata.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd3y0y9a6ca0k9v0259t&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_unit&quot;: null,
        &quot;organization_position&quot;: null
    },
    {
        &quot;id&quot;: &quot;01k3ahgff93tjcyd72eas8msbw&quot;,
        &quot;user_id&quot;: &quot;01k3ahgfckzqpafd34g18jm245&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
        &quot;organization_unit_id&quot;: &quot;01k3ahgd7agwyj7t6x83pked0q&quot;,
        &quot;organization_position_id&quot;: &quot;01k3ahgd9ez9ddc35xsdbg57rm&quot;,
        &quot;membership_type&quot;: &quot;employee&quot;,
        &quot;start_date&quot;: &quot;2021-06-01T00:00:00.000000Z&quot;,
        &quot;end_date&quot;: null,
        &quot;status&quot;: &quot;active&quot;,
        &quot;additional_roles&quot;: [
            &quot;system_architecture&quot;,
            &quot;database_design&quot;
        ],
        &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;user&quot;: {
            &quot;id&quot;: &quot;01k3ahgfckzqpafd34g18jm245&quot;,
            &quot;name&quot;: &quot;Jennifer Martinez&quot;,
            &quot;email&quot;: &quot;jennifer.martinez@techcorpsoftware.com&quot;,
            &quot;email_verified_at&quot;: &quot;2025-08-23T04:03:11.000000Z&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;organization_code&quot;: &quot;SUB001&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Software&quot;,
            &quot;description&quot;: &quot;Software development and consulting services&quot;,
            &quot;address&quot;: &quot;456 Software Ave, Tech City&quot;,
            &quot;phone&quot;: &quot;+1-555-0200&quot;,
            &quot;email&quot;: &quot;info@techcorpsoftware.com&quot;,
            &quot;website&quot;: &quot;https://techcorpsoftware.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG002&quot;,
            &quot;tax_number&quot;: &quot;TAX002&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;5000000.00&quot;,
            &quot;paid_capital&quot;: &quot;4000000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-03-01T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Software development, web applications, mobile apps&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;Mike Johnson&quot;,
                    &quot;email&quot;: &quot;md@techcorpsoftware.com&quot;
                },
                &quot;cto&quot;: {
                    &quot;name&quot;: &quot;Sarah Wilson&quot;,
                    &quot;email&quot;: &quot;cto@techcorpsoftware.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_unit&quot;: {
            &quot;id&quot;: &quot;01k3ahgd7agwyj7t6x83pked0q&quot;,
            &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;unit_code&quot;: &quot;BEND001&quot;,
            &quot;name&quot;: &quot;Backend Development Team&quot;,
            &quot;unit_type&quot;: &quot;team&quot;,
            &quot;description&quot;: &quot;Server-side development and API services&quot;,
            &quot;parent_unit_id&quot;: &quot;01k3ahgd72vvytzzw7q4htmrd9&quot;,
            &quot;responsibilities&quot;: [
                &quot;API development&quot;,
                &quot;Database design&quot;,
                &quot;Server architecture&quot;,
                &quot;Backend testing&quot;
            ],
            &quot;authorities&quot;: [
                &quot;Design database schemas&quot;,
                &quot;Implement business logic&quot;,
                &quot;Develop APIs&quot;,
                &quot;Optimize performance&quot;
            ],
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 2,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_position&quot;: {
            &quot;id&quot;: &quot;01k3ahgd9ez9ddc35xsdbg57rm&quot;,
            &quot;organization_unit_id&quot;: &quot;01k3ahgd7agwyj7t6x83pked0q&quot;,
            &quot;position_code&quot;: &quot;POS010&quot;,
            &quot;organization_position_level_id&quot;: &quot;01k3ahgd21jf54ft67k9wcy7sy&quot;,
            &quot;title&quot;: &quot;Senior Backend Developer&quot;,
            &quot;job_description&quot;: &quot;Lead backend development and system architecture&quot;,
            &quot;qualifications&quot;: [
                &quot;Computer Science degree or equivalent experience&quot;,
                &quot;Minimum 5 years of backend development experience&quot;,
                &quot;Expertise in PHP, Laravel, databases, and API development&quot;,
                &quot;Strong system design and architecture skills&quot;
            ],
            &quot;responsibilities&quot;: [
                &quot;Design and implement backend systems&quot;,
                &quot;Develop and maintain APIs&quot;,
                &quot;Optimize database performance&quot;,
                &quot;Mentor junior developers&quot;
            ],
            &quot;min_salary&quot;: &quot;125000.00&quot;,
            &quot;max_salary&quot;: &quot;185000.00&quot;,
            &quot;is_active&quot;: true,
            &quot;max_incumbents&quot;: 3,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        }
    },
    {
        &quot;id&quot;: &quot;01k3ahgff0kxdbrkz96953rsat&quot;,
        &quot;user_id&quot;: &quot;01k3ahgfc5ds3qg9ybgqmcb34p&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
        &quot;organization_unit_id&quot;: &quot;01k3ahgd7617t0tmr9jsk9x1fb&quot;,
        &quot;organization_position_id&quot;: &quot;01k3ahgd978fzteh233gh855eq&quot;,
        &quot;membership_type&quot;: &quot;employee&quot;,
        &quot;start_date&quot;: &quot;2021-05-01T00:00:00.000000Z&quot;,
        &quot;end_date&quot;: null,
        &quot;status&quot;: &quot;active&quot;,
        &quot;additional_roles&quot;: [
            &quot;ui_architecture&quot;,
            &quot;mentoring&quot;
        ],
        &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;user&quot;: {
            &quot;id&quot;: &quot;01k3ahgfc5ds3qg9ybgqmcb34p&quot;,
            &quot;name&quot;: &quot;Lisa Anderson&quot;,
            &quot;email&quot;: &quot;lisa.anderson@techcorpsoftware.com&quot;,
            &quot;email_verified_at&quot;: &quot;2025-08-23T04:03:11.000000Z&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;organization_code&quot;: &quot;SUB001&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Software&quot;,
            &quot;description&quot;: &quot;Software development and consulting services&quot;,
            &quot;address&quot;: &quot;456 Software Ave, Tech City&quot;,
            &quot;phone&quot;: &quot;+1-555-0200&quot;,
            &quot;email&quot;: &quot;info@techcorpsoftware.com&quot;,
            &quot;website&quot;: &quot;https://techcorpsoftware.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG002&quot;,
            &quot;tax_number&quot;: &quot;TAX002&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;5000000.00&quot;,
            &quot;paid_capital&quot;: &quot;4000000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-03-01T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Software development, web applications, mobile apps&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;Mike Johnson&quot;,
                    &quot;email&quot;: &quot;md@techcorpsoftware.com&quot;
                },
                &quot;cto&quot;: {
                    &quot;name&quot;: &quot;Sarah Wilson&quot;,
                    &quot;email&quot;: &quot;cto@techcorpsoftware.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_unit&quot;: {
            &quot;id&quot;: &quot;01k3ahgd7617t0tmr9jsk9x1fb&quot;,
            &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;unit_code&quot;: &quot;FEND001&quot;,
            &quot;name&quot;: &quot;Frontend Development Team&quot;,
            &quot;unit_type&quot;: &quot;team&quot;,
            &quot;description&quot;: &quot;User interface and user experience development&quot;,
            &quot;parent_unit_id&quot;: &quot;01k3ahgd72vvytzzw7q4htmrd9&quot;,
            &quot;responsibilities&quot;: [
                &quot;UI/UX development&quot;,
                &quot;Frontend architecture&quot;,
                &quot;User interaction design&quot;,
                &quot;Frontend testing&quot;
            ],
            &quot;authorities&quot;: [
                &quot;Choose frontend frameworks&quot;,
                &quot;Design user interfaces&quot;,
                &quot;Implement frontend features&quot;,
                &quot;Optimize user experience&quot;
            ],
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 1,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_position&quot;: {
            &quot;id&quot;: &quot;01k3ahgd978fzteh233gh855eq&quot;,
            &quot;organization_unit_id&quot;: &quot;01k3ahgd7617t0tmr9jsk9x1fb&quot;,
            &quot;position_code&quot;: &quot;POS008&quot;,
            &quot;organization_position_level_id&quot;: &quot;01k3ahgd21jf54ft67k9wcy7sy&quot;,
            &quot;title&quot;: &quot;Senior Frontend Developer&quot;,
            &quot;job_description&quot;: &quot;Lead frontend development and mentor team members&quot;,
            &quot;qualifications&quot;: [
                &quot;Computer Science degree or equivalent experience&quot;,
                &quot;Minimum 5 years of frontend development experience&quot;,
                &quot;Expertise in React, TypeScript, and modern frontend technologies&quot;,
                &quot;Strong problem-solving and communication skills&quot;
            ],
            &quot;responsibilities&quot;: [
                &quot;Develop complex frontend features&quot;,
                &quot;Mentor junior developers&quot;,
                &quot;Define frontend architecture&quot;,
                &quot;Ensure code quality and best practices&quot;
            ],
            &quot;min_salary&quot;: &quot;120000.00&quot;,
            &quot;max_salary&quot;: &quot;180000.00&quot;,
            &quot;is_active&quot;: true,
            &quot;max_incumbents&quot;: 3,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        }
    },
    {
        &quot;id&quot;: &quot;01k3ahgfewgfqcdsdnz1wbmcmb&quot;,
        &quot;user_id&quot;: &quot;01k3ahgfby1kzh6m5exc2q53bs&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
        &quot;organization_unit_id&quot;: &quot;01k3ahgd72vvytzzw7q4htmrd9&quot;,
        &quot;organization_position_id&quot;: &quot;01k3ahgd91pc5p0h9n582jm5t7&quot;,
        &quot;membership_type&quot;: &quot;employee&quot;,
        &quot;start_date&quot;: &quot;2021-04-01T00:00:00.000000Z&quot;,
        &quot;end_date&quot;: null,
        &quot;status&quot;: &quot;active&quot;,
        &quot;additional_roles&quot;: [
            &quot;team_leadership&quot;,
            &quot;process_improvement&quot;
        ],
        &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;user&quot;: {
            &quot;id&quot;: &quot;01k3ahgfby1kzh6m5exc2q53bs&quot;,
            &quot;name&quot;: &quot;Robert Taylor&quot;,
            &quot;email&quot;: &quot;robert.taylor@techcorpsoftware.com&quot;,
            &quot;email_verified_at&quot;: &quot;2025-08-23T04:03:11.000000Z&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;organization_code&quot;: &quot;SUB001&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Software&quot;,
            &quot;description&quot;: &quot;Software development and consulting services&quot;,
            &quot;address&quot;: &quot;456 Software Ave, Tech City&quot;,
            &quot;phone&quot;: &quot;+1-555-0200&quot;,
            &quot;email&quot;: &quot;info@techcorpsoftware.com&quot;,
            &quot;website&quot;: &quot;https://techcorpsoftware.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG002&quot;,
            &quot;tax_number&quot;: &quot;TAX002&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;5000000.00&quot;,
            &quot;paid_capital&quot;: &quot;4000000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-03-01T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Software development, web applications, mobile apps&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;Mike Johnson&quot;,
                    &quot;email&quot;: &quot;md@techcorpsoftware.com&quot;
                },
                &quot;cto&quot;: {
                    &quot;name&quot;: &quot;Sarah Wilson&quot;,
                    &quot;email&quot;: &quot;cto@techcorpsoftware.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_unit&quot;: {
            &quot;id&quot;: &quot;01k3ahgd72vvytzzw7q4htmrd9&quot;,
            &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;unit_code&quot;: &quot;ENG001&quot;,
            &quot;name&quot;: &quot;Engineering Division&quot;,
            &quot;unit_type&quot;: &quot;division&quot;,
            &quot;description&quot;: &quot;Software engineering and development&quot;,
            &quot;parent_unit_id&quot;: null,
            &quot;responsibilities&quot;: [
                &quot;Software development&quot;,
                &quot;Technical architecture&quot;,
                &quot;Code quality assurance&quot;,
                &quot;Development methodology&quot;
            ],
            &quot;authorities&quot;: [
                &quot;Define technical standards&quot;,
                &quot;Approve technical designs&quot;,
                &quot;Manage development teams&quot;,
                &quot;Release software products&quot;
            ],
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 2,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_position&quot;: {
            &quot;id&quot;: &quot;01k3ahgd91pc5p0h9n582jm5t7&quot;,
            &quot;organization_unit_id&quot;: &quot;01k3ahgd72vvytzzw7q4htmrd9&quot;,
            &quot;position_code&quot;: &quot;POS007&quot;,
            &quot;organization_position_level_id&quot;: &quot;01k3ahgd108v9qyyem04dv0n00&quot;,
            &quot;title&quot;: &quot;Vice President of Engineering&quot;,
            &quot;job_description&quot;: &quot;Lead engineering organization and technical excellence&quot;,
            &quot;qualifications&quot;: [
                &quot;Computer Science or Engineering degree&quot;,
                &quot;Minimum 8 years of engineering management experience&quot;,
                &quot;Strong technical leadership skills&quot;,
                &quot;Experience scaling engineering teams&quot;
            ],
            &quot;responsibilities&quot;: [
                &quot;Lead engineering organization&quot;,
                &quot;Drive technical excellence&quot;,
                &quot;Manage engineering teams&quot;,
                &quot;Define development processes&quot;
            ],
            &quot;min_salary&quot;: &quot;250000.00&quot;,
            &quot;max_salary&quot;: &quot;400000.00&quot;,
            &quot;is_active&quot;: true,
            &quot;max_incumbents&quot;: 1,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        }
    },
    {
        &quot;id&quot;: &quot;01k3ahgfer0xbryw8zmex237yr&quot;,
        &quot;user_id&quot;: &quot;01k3ahgfb76rvk7k16ktnb96gk&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
        &quot;organization_unit_id&quot;: &quot;01k3ahgd6x41jb3s6w1ekz6jra&quot;,
        &quot;organization_position_id&quot;: &quot;01k3ahgd8w9pws2hr4de24r83s&quot;,
        &quot;membership_type&quot;: &quot;employee&quot;,
        &quot;start_date&quot;: &quot;2021-03-15T00:00:00.000000Z&quot;,
        &quot;end_date&quot;: null,
        &quot;status&quot;: &quot;active&quot;,
        &quot;additional_roles&quot;: [
            &quot;innovation&quot;,
            &quot;technical_strategy&quot;
        ],
        &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;user&quot;: {
            &quot;id&quot;: &quot;01k3ahgfb76rvk7k16ktnb96gk&quot;,
            &quot;name&quot;: &quot;Sarah Wilson&quot;,
            &quot;email&quot;: &quot;sarah.wilson@techcorpsoftware.com&quot;,
            &quot;email_verified_at&quot;: &quot;2025-08-23T04:03:10.000000Z&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;organization_code&quot;: &quot;SUB001&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Software&quot;,
            &quot;description&quot;: &quot;Software development and consulting services&quot;,
            &quot;address&quot;: &quot;456 Software Ave, Tech City&quot;,
            &quot;phone&quot;: &quot;+1-555-0200&quot;,
            &quot;email&quot;: &quot;info@techcorpsoftware.com&quot;,
            &quot;website&quot;: &quot;https://techcorpsoftware.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG002&quot;,
            &quot;tax_number&quot;: &quot;TAX002&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;5000000.00&quot;,
            &quot;paid_capital&quot;: &quot;4000000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-03-01T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Software development, web applications, mobile apps&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;Mike Johnson&quot;,
                    &quot;email&quot;: &quot;md@techcorpsoftware.com&quot;
                },
                &quot;cto&quot;: {
                    &quot;name&quot;: &quot;Sarah Wilson&quot;,
                    &quot;email&quot;: &quot;cto@techcorpsoftware.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_unit&quot;: {
            &quot;id&quot;: &quot;01k3ahgd6x41jb3s6w1ekz6jra&quot;,
            &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;unit_code&quot;: &quot;EXEC001&quot;,
            &quot;name&quot;: &quot;Executive Office&quot;,
            &quot;unit_type&quot;: &quot;department&quot;,
            &quot;description&quot;: &quot;Executive leadership and strategic management&quot;,
            &quot;parent_unit_id&quot;: null,
            &quot;responsibilities&quot;: [
                &quot;Strategic planning&quot;,
                &quot;Corporate governance&quot;,
                &quot;Stakeholder management&quot;,
                &quot;Executive decision making&quot;
            ],
            &quot;authorities&quot;: [
                &quot;Set company direction&quot;,
                &quot;Approve major decisions&quot;,
                &quot;Represent company externally&quot;,
                &quot;Allocate resources&quot;
            ],
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 1,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_position&quot;: {
            &quot;id&quot;: &quot;01k3ahgd8w9pws2hr4de24r83s&quot;,
            &quot;organization_unit_id&quot;: &quot;01k3ahgd6x41jb3s6w1ekz6jra&quot;,
            &quot;position_code&quot;: &quot;POS006&quot;,
            &quot;organization_position_level_id&quot;: &quot;01k3ahgd0txgyfe2hhhgpgptmw&quot;,
            &quot;title&quot;: &quot;Chief Technology Officer&quot;,
            &quot;job_description&quot;: &quot;Lead technology strategy and innovation&quot;,
            &quot;qualifications&quot;: [
                &quot;Computer Science or Engineering degree&quot;,
                &quot;Minimum 10 years of technology leadership experience&quot;,
                &quot;Strong technical and management skills&quot;,
                &quot;Experience with enterprise software development&quot;
            ],
            &quot;responsibilities&quot;: [
                &quot;Define technology strategy&quot;,
                &quot;Lead engineering teams&quot;,
                &quot;Drive technical innovation&quot;,
                &quot;Ensure technology excellence&quot;
            ],
            &quot;min_salary&quot;: &quot;350000.00&quot;,
            &quot;max_salary&quot;: &quot;600000.00&quot;,
            &quot;is_active&quot;: true,
            &quot;max_incumbents&quot;: 1,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        }
    },
    {
        &quot;id&quot;: &quot;01k3ahgfekaq57sjppwnmqt4gg&quot;,
        &quot;user_id&quot;: &quot;01k3ahgfb0hpqj1y3q9185cb6s&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
        &quot;organization_unit_id&quot;: &quot;01k3ahgd6x41jb3s6w1ekz6jra&quot;,
        &quot;organization_position_id&quot;: &quot;01k3ahgd8rsgrz4mas66c1mcgf&quot;,
        &quot;membership_type&quot;: &quot;employee&quot;,
        &quot;start_date&quot;: &quot;2021-03-01T00:00:00.000000Z&quot;,
        &quot;end_date&quot;: null,
        &quot;status&quot;: &quot;active&quot;,
        &quot;additional_roles&quot;: [
            &quot;business_development&quot;,
            &quot;client_relations&quot;
        ],
        &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;user&quot;: {
            &quot;id&quot;: &quot;01k3ahgfb0hpqj1y3q9185cb6s&quot;,
            &quot;name&quot;: &quot;Mike Johnson&quot;,
            &quot;email&quot;: &quot;mike.johnson@techcorpsoftware.com&quot;,
            &quot;email_verified_at&quot;: &quot;2025-08-23T04:03:10.000000Z&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;organization_code&quot;: &quot;SUB001&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Software&quot;,
            &quot;description&quot;: &quot;Software development and consulting services&quot;,
            &quot;address&quot;: &quot;456 Software Ave, Tech City&quot;,
            &quot;phone&quot;: &quot;+1-555-0200&quot;,
            &quot;email&quot;: &quot;info@techcorpsoftware.com&quot;,
            &quot;website&quot;: &quot;https://techcorpsoftware.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG002&quot;,
            &quot;tax_number&quot;: &quot;TAX002&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;5000000.00&quot;,
            &quot;paid_capital&quot;: &quot;4000000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-03-01T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Software development, web applications, mobile apps&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;Mike Johnson&quot;,
                    &quot;email&quot;: &quot;md@techcorpsoftware.com&quot;
                },
                &quot;cto&quot;: {
                    &quot;name&quot;: &quot;Sarah Wilson&quot;,
                    &quot;email&quot;: &quot;cto@techcorpsoftware.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_unit&quot;: {
            &quot;id&quot;: &quot;01k3ahgd6x41jb3s6w1ekz6jra&quot;,
            &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;unit_code&quot;: &quot;EXEC001&quot;,
            &quot;name&quot;: &quot;Executive Office&quot;,
            &quot;unit_type&quot;: &quot;department&quot;,
            &quot;description&quot;: &quot;Executive leadership and strategic management&quot;,
            &quot;parent_unit_id&quot;: null,
            &quot;responsibilities&quot;: [
                &quot;Strategic planning&quot;,
                &quot;Corporate governance&quot;,
                &quot;Stakeholder management&quot;,
                &quot;Executive decision making&quot;
            ],
            &quot;authorities&quot;: [
                &quot;Set company direction&quot;,
                &quot;Approve major decisions&quot;,
                &quot;Represent company externally&quot;,
                &quot;Allocate resources&quot;
            ],
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 1,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_position&quot;: {
            &quot;id&quot;: &quot;01k3ahgd8rsgrz4mas66c1mcgf&quot;,
            &quot;organization_unit_id&quot;: &quot;01k3ahgd6x41jb3s6w1ekz6jra&quot;,
            &quot;position_code&quot;: &quot;POS005&quot;,
            &quot;organization_position_level_id&quot;: &quot;01k3ahgd0txgyfe2hhhgpgptmw&quot;,
            &quot;title&quot;: &quot;Managing Director&quot;,
            &quot;job_description&quot;: &quot;Lead TechCorp Software operations and strategic execution&quot;,
            &quot;qualifications&quot;: [
                &quot;Advanced degree in business or technology&quot;,
                &quot;Minimum 12 years of software industry experience&quot;,
                &quot;Strong leadership and management skills&quot;,
                &quot;Proven track record in business growth&quot;
            ],
            &quot;responsibilities&quot;: [
                &quot;Execute business strategy&quot;,
                &quot;Manage day-to-day operations&quot;,
                &quot;Lead management team&quot;,
                &quot;Drive revenue growth&quot;
            ],
            &quot;min_salary&quot;: &quot;400000.00&quot;,
            &quot;max_salary&quot;: &quot;700000.00&quot;,
            &quot;is_active&quot;: true,
            &quot;max_incumbents&quot;: 1,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        }
    },
    {
        &quot;id&quot;: &quot;01k3ahgfedxsyr884rzzhafwa6&quot;,
        &quot;user_id&quot;: &quot;01k3ahgfasbsh1xp27bx6wy9pq&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
        &quot;organization_unit_id&quot;: &quot;01k3ahgd6h94mgvsspwdksgzyv&quot;,
        &quot;organization_position_id&quot;: &quot;01k3ahgd8j3cgapgs5ypb4d39z&quot;,
        &quot;membership_type&quot;: &quot;board_member&quot;,
        &quot;start_date&quot;: &quot;2020-02-01T00:00:00.000000Z&quot;,
        &quot;end_date&quot;: null,
        &quot;status&quot;: &quot;active&quot;,
        &quot;additional_roles&quot;: [
            &quot;financial_oversight&quot;,
            &quot;risk_management&quot;
        ],
        &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;user&quot;: {
            &quot;id&quot;: &quot;01k3ahgfasbsh1xp27bx6wy9pq&quot;,
            &quot;name&quot;: &quot;Jane Doe&quot;,
            &quot;email&quot;: &quot;jane.doe@techcorp.com&quot;,
            &quot;email_verified_at&quot;: &quot;2025-08-23T04:03:10.000000Z&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;organization_code&quot;: &quot;HC001&quot;,
            &quot;organization_type&quot;: &quot;holding_company&quot;,
            &quot;parent_organization_id&quot;: null,
            &quot;name&quot;: &quot;TechCorp Holdings&quot;,
            &quot;description&quot;: &quot;Main holding company for technology businesses&quot;,
            &quot;address&quot;: &quot;123 Tech Street, Innovation District&quot;,
            &quot;phone&quot;: &quot;+1-555-0100&quot;,
            &quot;email&quot;: &quot;info@techcorp.com&quot;,
            &quot;website&quot;: &quot;https://techcorp.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG001&quot;,
            &quot;tax_number&quot;: &quot;TAX001&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 7,
                &quot;independent_directors&quot;: 4,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;,
                    &quot;nomination&quot;,
                    &quot;remuneration&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;10000000.00&quot;,
            &quot;paid_capital&quot;: &quot;8500000.00&quot;,
            &quot;establishment_date&quot;: &quot;2020-01-15T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Public Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Investment holding and management&quot;,
            &quot;contact_persons&quot;: {
                &quot;ceo&quot;: {
                    &quot;name&quot;: &quot;John Smith&quot;,
                    &quot;email&quot;: &quot;ceo@techcorp.com&quot;
                },
                &quot;cfo&quot;: {
                    &quot;name&quot;: &quot;Jane Doe&quot;,
                    &quot;email&quot;: &quot;cfo@techcorp.com&quot;
                }
            },
            &quot;level&quot;: 0,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_unit&quot;: {
            &quot;id&quot;: &quot;01k3ahgd6h94mgvsspwdksgzyv&quot;,
            &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;unit_code&quot;: &quot;BOD001&quot;,
            &quot;name&quot;: &quot;Board of Directors&quot;,
            &quot;unit_type&quot;: &quot;board_of_directors&quot;,
            &quot;description&quot;: &quot;Executive board responsible for day-to-day management&quot;,
            &quot;parent_unit_id&quot;: null,
            &quot;responsibilities&quot;: [
                &quot;Corporate management&quot;,
                &quot;Strategic execution&quot;,
                &quot;Financial performance&quot;,
                &quot;Operational oversight&quot;
            ],
            &quot;authorities&quot;: [
                &quot;Execute business strategy&quot;,
                &quot;Manage operations&quot;,
                &quot;Make operational decisions&quot;,
                &quot;Report to commissioners&quot;
            ],
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 2,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_position&quot;: {
            &quot;id&quot;: &quot;01k3ahgd8j3cgapgs5ypb4d39z&quot;,
            &quot;organization_unit_id&quot;: &quot;01k3ahgd6h94mgvsspwdksgzyv&quot;,
            &quot;position_code&quot;: &quot;POS004&quot;,
            &quot;organization_position_level_id&quot;: &quot;01k3ahgd0txgyfe2hhhgpgptmw&quot;,
            &quot;title&quot;: &quot;Chief Financial Officer&quot;,
            &quot;job_description&quot;: &quot;Manage financial strategy and operations&quot;,
            &quot;qualifications&quot;: [
                &quot;CPA or equivalent professional certification&quot;,
                &quot;Minimum 12 years of finance experience&quot;,
                &quot;Experience in public companies preferred&quot;,
                &quot;Strong analytical and strategic skills&quot;
            ],
            &quot;responsibilities&quot;: [
                &quot;Oversee financial planning and analysis&quot;,
                &quot;Manage investor relations&quot;,
                &quot;Ensure regulatory compliance&quot;,
                &quot;Lead finance team&quot;
            ],
            &quot;min_salary&quot;: &quot;600000.00&quot;,
            &quot;max_salary&quot;: &quot;1000000.00&quot;,
            &quot;is_active&quot;: true,
            &quot;max_incumbents&quot;: 1,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        }
    },
    {
        &quot;id&quot;: &quot;01k3ahgfe5595rkvqzvxrdrg5t&quot;,
        &quot;user_id&quot;: &quot;01k3ahgfaja8jn926d2bx33dcd&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
        &quot;organization_unit_id&quot;: &quot;01k3ahgd6h94mgvsspwdksgzyv&quot;,
        &quot;organization_position_id&quot;: &quot;01k3ahgd8ea6hxx4dmkpecwrk1&quot;,
        &quot;membership_type&quot;: &quot;board_member&quot;,
        &quot;start_date&quot;: &quot;2020-01-15T00:00:00.000000Z&quot;,
        &quot;end_date&quot;: null,
        &quot;status&quot;: &quot;active&quot;,
        &quot;additional_roles&quot;: [
            &quot;strategic_planning&quot;,
            &quot;investor_relations&quot;
        ],
        &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;user&quot;: {
            &quot;id&quot;: &quot;01k3ahgfaja8jn926d2bx33dcd&quot;,
            &quot;name&quot;: &quot;John Smith&quot;,
            &quot;email&quot;: &quot;john.smith@techcorp.com&quot;,
            &quot;email_verified_at&quot;: &quot;2025-08-23T04:03:10.000000Z&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;organization_code&quot;: &quot;HC001&quot;,
            &quot;organization_type&quot;: &quot;holding_company&quot;,
            &quot;parent_organization_id&quot;: null,
            &quot;name&quot;: &quot;TechCorp Holdings&quot;,
            &quot;description&quot;: &quot;Main holding company for technology businesses&quot;,
            &quot;address&quot;: &quot;123 Tech Street, Innovation District&quot;,
            &quot;phone&quot;: &quot;+1-555-0100&quot;,
            &quot;email&quot;: &quot;info@techcorp.com&quot;,
            &quot;website&quot;: &quot;https://techcorp.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG001&quot;,
            &quot;tax_number&quot;: &quot;TAX001&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 7,
                &quot;independent_directors&quot;: 4,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;,
                    &quot;nomination&quot;,
                    &quot;remuneration&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;10000000.00&quot;,
            &quot;paid_capital&quot;: &quot;8500000.00&quot;,
            &quot;establishment_date&quot;: &quot;2020-01-15T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Public Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Investment holding and management&quot;,
            &quot;contact_persons&quot;: {
                &quot;ceo&quot;: {
                    &quot;name&quot;: &quot;John Smith&quot;,
                    &quot;email&quot;: &quot;ceo@techcorp.com&quot;
                },
                &quot;cfo&quot;: {
                    &quot;name&quot;: &quot;Jane Doe&quot;,
                    &quot;email&quot;: &quot;cfo@techcorp.com&quot;
                }
            },
            &quot;level&quot;: 0,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_unit&quot;: {
            &quot;id&quot;: &quot;01k3ahgd6h94mgvsspwdksgzyv&quot;,
            &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;unit_code&quot;: &quot;BOD001&quot;,
            &quot;name&quot;: &quot;Board of Directors&quot;,
            &quot;unit_type&quot;: &quot;board_of_directors&quot;,
            &quot;description&quot;: &quot;Executive board responsible for day-to-day management&quot;,
            &quot;parent_unit_id&quot;: null,
            &quot;responsibilities&quot;: [
                &quot;Corporate management&quot;,
                &quot;Strategic execution&quot;,
                &quot;Financial performance&quot;,
                &quot;Operational oversight&quot;
            ],
            &quot;authorities&quot;: [
                &quot;Execute business strategy&quot;,
                &quot;Manage operations&quot;,
                &quot;Make operational decisions&quot;,
                &quot;Report to commissioners&quot;
            ],
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 2,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_position&quot;: {
            &quot;id&quot;: &quot;01k3ahgd8ea6hxx4dmkpecwrk1&quot;,
            &quot;organization_unit_id&quot;: &quot;01k3ahgd6h94mgvsspwdksgzyv&quot;,
            &quot;position_code&quot;: &quot;POS003&quot;,
            &quot;organization_position_level_id&quot;: &quot;01k3ahgd0txgyfe2hhhgpgptmw&quot;,
            &quot;title&quot;: &quot;Chief Executive Officer&quot;,
            &quot;job_description&quot;: &quot;Lead the organization and execute strategic initiatives&quot;,
            &quot;qualifications&quot;: [
                &quot;MBA or equivalent advanced degree&quot;,
                &quot;Minimum 15 years of executive experience&quot;,
                &quot;Proven track record in technology industry&quot;,
                &quot;Strong leadership and communication skills&quot;
            ],
            &quot;responsibilities&quot;: [
                &quot;Develop and execute corporate strategy&quot;,
                &quot;Lead executive team&quot;,
                &quot;Represent company to stakeholders&quot;,
                &quot;Drive business growth and profitability&quot;
            ],
            &quot;min_salary&quot;: &quot;800000.00&quot;,
            &quot;max_salary&quot;: &quot;1500000.00&quot;,
            &quot;is_active&quot;: true,
            &quot;max_incumbents&quot;: 1,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        }
    }
]</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organization-memberships" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organization-memberships"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organization-memberships"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organization-memberships" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organization-memberships">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organization-memberships" data-method="GET"
      data-path="api/v1/organization-memberships"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organization-memberships', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organization-memberships"
                    onclick="tryItOut('GETapi-v1-organization-memberships');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organization-memberships"
                    onclick="cancelTryOut('GETapi-v1-organization-memberships');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organization-memberships"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organization-memberships</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organization-memberships"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organization-memberships"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organization-memberships"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-POSTapi-v1-organization-memberships">POST api/v1/organization-memberships</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-organization-memberships">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/organization-memberships" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"user_id\": 1,
    \"organization_id\": 1,
    \"organization_unit_id\": 1,
    \"organization_position_id\": 1,
    \"membership_type\": \"employee\",
    \"start_date\": \"2024-01-01\",
    \"end_date\": \"2024-12-31\",
    \"status\": \"active\",
    \"additional_roles\": [
        1,
        2
    ]
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-memberships"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "user_id": 1,
    "organization_id": 1,
    "organization_unit_id": 1,
    "organization_position_id": 1,
    "membership_type": "employee",
    "start_date": "2024-01-01",
    "end_date": "2024-12-31",
    "status": "active",
    "additional_roles": [
        1,
        2
    ]
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-organization-memberships">
</span>
<span id="execution-results-POSTapi-v1-organization-memberships" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-organization-memberships"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-organization-memberships"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-organization-memberships" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-organization-memberships">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-organization-memberships" data-method="POST"
      data-path="api/v1/organization-memberships"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-organization-memberships', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-organization-memberships"
                    onclick="tryItOut('POSTapi-v1-organization-memberships');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-organization-memberships"
                    onclick="cancelTryOut('POSTapi-v1-organization-memberships');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-organization-memberships"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/organization-memberships</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-organization-memberships"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-organization-memberships"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-organization-memberships"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>user_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="user_id"                data-endpoint="POSTapi-v1-organization-memberships"
               value="1"
               data-component="body">
    <br>
<p>ID of the user to create membership for. The <code>id</code> of an existing record in the users table. Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>organization_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="organization_id"                data-endpoint="POSTapi-v1-organization-memberships"
               value="1"
               data-component="body">
    <br>
<p>ID of the organization. The <code>id</code> of an existing record in the organizations table. Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>organization_unit_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="organization_unit_id"                data-endpoint="POSTapi-v1-organization-memberships"
               value="1"
               data-component="body">
    <br>
<p>ID of the organization unit (optional). The <code>id</code> of an existing record in the organization_units table. Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>organization_position_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="organization_position_id"                data-endpoint="POSTapi-v1-organization-memberships"
               value="1"
               data-component="body">
    <br>
<p>ID of the organization position (optional). The <code>id</code> of an existing record in the organization_positions table. Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>membership_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="membership_type"                data-endpoint="POSTapi-v1-organization-memberships"
               value="employee"
               data-component="body">
    <br>
<p>Type of membership. Example: <code>employee</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>employee</code></li> <li><code>board_member</code></li> <li><code>consultant</code></li> <li><code>contractor</code></li> <li><code>intern</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>start_date</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="start_date"                data-endpoint="POSTapi-v1-organization-memberships"
               value="2024-01-01"
               data-component="body">
    <br>
<p>Start date of membership. Must be a valid date. Example: <code>2024-01-01</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>end_date</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="end_date"                data-endpoint="POSTapi-v1-organization-memberships"
               value="2024-12-31"
               data-component="body">
    <br>
<p>End date of membership (optional). Must be a valid date. Must be a date after <code>start_date</code>. Example: <code>2024-12-31</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>status</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="status"                data-endpoint="POSTapi-v1-organization-memberships"
               value="active"
               data-component="body">
    <br>
<p>Status of the membership. Example: <code>active</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>active</code></li> <li><code>inactive</code></li> <li><code>terminated</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>additional_roles</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="additional_roles"                data-endpoint="POSTapi-v1-organization-memberships"
               value=""
               data-component="body">
    <br>
<p>Array of additional role IDs (optional).</p>
        </div>
        </form>

                    <h2 id="endpoints-GETapi-v1-organization-memberships--id-">GET api/v1/organization-memberships/{id}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-organization-memberships--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organization-memberships/01k3ahgfe5595rkvqzvxrdrg5t" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-memberships/01k3ahgfe5595rkvqzvxrdrg5t"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organization-memberships--id-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: &quot;01k3ahgfe5595rkvqzvxrdrg5t&quot;,
    &quot;user_id&quot;: &quot;01k3ahgfaja8jn926d2bx33dcd&quot;,
    &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
    &quot;organization_unit_id&quot;: &quot;01k3ahgd6h94mgvsspwdksgzyv&quot;,
    &quot;organization_position_id&quot;: &quot;01k3ahgd8ea6hxx4dmkpecwrk1&quot;,
    &quot;membership_type&quot;: &quot;board_member&quot;,
    &quot;start_date&quot;: &quot;2020-01-15T00:00:00.000000Z&quot;,
    &quot;end_date&quot;: null,
    &quot;status&quot;: &quot;active&quot;,
    &quot;additional_roles&quot;: [
        &quot;strategic_planning&quot;,
        &quot;investor_relations&quot;
    ],
    &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
    &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
    &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
    &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
    &quot;user&quot;: {
        &quot;id&quot;: &quot;01k3ahgfaja8jn926d2bx33dcd&quot;,
        &quot;name&quot;: &quot;John Smith&quot;,
        &quot;email&quot;: &quot;john.smith@techcorp.com&quot;,
        &quot;email_verified_at&quot;: &quot;2025-08-23T04:03:10.000000Z&quot;,
        &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
    },
    &quot;organization&quot;: {
        &quot;id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
        &quot;organization_code&quot;: &quot;HC001&quot;,
        &quot;organization_type&quot;: &quot;holding_company&quot;,
        &quot;parent_organization_id&quot;: null,
        &quot;name&quot;: &quot;TechCorp Holdings&quot;,
        &quot;description&quot;: &quot;Main holding company for technology businesses&quot;,
        &quot;address&quot;: &quot;123 Tech Street, Innovation District&quot;,
        &quot;phone&quot;: &quot;+1-555-0100&quot;,
        &quot;email&quot;: &quot;info@techcorp.com&quot;,
        &quot;website&quot;: &quot;https://techcorp.com&quot;,
        &quot;is_active&quot;: true,
        &quot;registration_number&quot;: &quot;REG001&quot;,
        &quot;tax_number&quot;: &quot;TAX001&quot;,
        &quot;governance_structure&quot;: {
            &quot;board_size&quot;: 7,
            &quot;independent_directors&quot;: 4,
            &quot;committees&quot;: [
                &quot;audit&quot;,
                &quot;risk&quot;,
                &quot;nomination&quot;,
                &quot;remuneration&quot;
            ]
        },
        &quot;authorized_capital&quot;: &quot;10000000.00&quot;,
        &quot;paid_capital&quot;: &quot;8500000.00&quot;,
        &quot;establishment_date&quot;: &quot;2020-01-15T00:00:00.000000Z&quot;,
        &quot;legal_status&quot;: &quot;Public Limited Company&quot;,
        &quot;business_activities&quot;: &quot;Investment holding and management&quot;,
        &quot;contact_persons&quot;: {
            &quot;ceo&quot;: {
                &quot;name&quot;: &quot;John Smith&quot;,
                &quot;email&quot;: &quot;ceo@techcorp.com&quot;
            },
            &quot;cfo&quot;: {
                &quot;name&quot;: &quot;Jane Doe&quot;,
                &quot;email&quot;: &quot;cfo@techcorp.com&quot;
            }
        },
        &quot;level&quot;: 0,
        &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
        &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
    },
    &quot;organization_unit&quot;: {
        &quot;id&quot;: &quot;01k3ahgd6h94mgvsspwdksgzyv&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
        &quot;unit_code&quot;: &quot;BOD001&quot;,
        &quot;name&quot;: &quot;Board of Directors&quot;,
        &quot;unit_type&quot;: &quot;board_of_directors&quot;,
        &quot;description&quot;: &quot;Executive board responsible for day-to-day management&quot;,
        &quot;parent_unit_id&quot;: null,
        &quot;responsibilities&quot;: [
            &quot;Corporate management&quot;,
            &quot;Strategic execution&quot;,
            &quot;Financial performance&quot;,
            &quot;Operational oversight&quot;
        ],
        &quot;authorities&quot;: [
            &quot;Execute business strategy&quot;,
            &quot;Manage operations&quot;,
            &quot;Make operational decisions&quot;,
            &quot;Report to commissioners&quot;
        ],
        &quot;is_active&quot;: true,
        &quot;sort_order&quot;: 2,
        &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
    },
    &quot;organization_position&quot;: {
        &quot;id&quot;: &quot;01k3ahgd8ea6hxx4dmkpecwrk1&quot;,
        &quot;organization_unit_id&quot;: &quot;01k3ahgd6h94mgvsspwdksgzyv&quot;,
        &quot;position_code&quot;: &quot;POS003&quot;,
        &quot;organization_position_level_id&quot;: &quot;01k3ahgd0txgyfe2hhhgpgptmw&quot;,
        &quot;title&quot;: &quot;Chief Executive Officer&quot;,
        &quot;job_description&quot;: &quot;Lead the organization and execute strategic initiatives&quot;,
        &quot;qualifications&quot;: [
            &quot;MBA or equivalent advanced degree&quot;,
            &quot;Minimum 15 years of executive experience&quot;,
            &quot;Proven track record in technology industry&quot;,
            &quot;Strong leadership and communication skills&quot;
        ],
        &quot;responsibilities&quot;: [
            &quot;Develop and execute corporate strategy&quot;,
            &quot;Lead executive team&quot;,
            &quot;Represent company to stakeholders&quot;,
            &quot;Drive business growth and profitability&quot;
        ],
        &quot;min_salary&quot;: &quot;800000.00&quot;,
        &quot;max_salary&quot;: &quot;1500000.00&quot;,
        &quot;is_active&quot;: true,
        &quot;max_incumbents&quot;: 1,
        &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organization-memberships--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organization-memberships--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organization-memberships--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organization-memberships--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organization-memberships--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organization-memberships--id-" data-method="GET"
      data-path="api/v1/organization-memberships/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organization-memberships--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organization-memberships--id-"
                    onclick="tryItOut('GETapi-v1-organization-memberships--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organization-memberships--id-"
                    onclick="cancelTryOut('GETapi-v1-organization-memberships--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organization-memberships--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organization-memberships/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organization-memberships--id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organization-memberships--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organization-memberships--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-v1-organization-memberships--id-"
               value="01k3ahgfe5595rkvqzvxrdrg5t"
               data-component="url">
    <br>
<p>The ID of the organization membership. Example: <code>01k3ahgfe5595rkvqzvxrdrg5t</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-PUTapi-v1-organization-memberships--id-">PUT api/v1/organization-memberships/{id}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PUTapi-v1-organization-memberships--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:8000/api/v1/organization-memberships/01k3ahgfe5595rkvqzvxrdrg5t" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"organization_unit_id\": 1,
    \"organization_position_id\": 1,
    \"membership_type\": \"employee\",
    \"start_date\": \"2024-01-01\",
    \"end_date\": \"2024-12-31\",
    \"status\": \"active\",
    \"additional_roles\": [
        1,
        2
    ]
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-memberships/01k3ahgfe5595rkvqzvxrdrg5t"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "organization_unit_id": 1,
    "organization_position_id": 1,
    "membership_type": "employee",
    "start_date": "2024-01-01",
    "end_date": "2024-12-31",
    "status": "active",
    "additional_roles": [
        1,
        2
    ]
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-organization-memberships--id-">
</span>
<span id="execution-results-PUTapi-v1-organization-memberships--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-organization-memberships--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-organization-memberships--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-organization-memberships--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-organization-memberships--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-organization-memberships--id-" data-method="PUT"
      data-path="api/v1/organization-memberships/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-organization-memberships--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-v1-organization-memberships--id-"
                    onclick="tryItOut('PUTapi-v1-organization-memberships--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-v1-organization-memberships--id-"
                    onclick="cancelTryOut('PUTapi-v1-organization-memberships--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-v1-organization-memberships--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/organization-memberships/{id}</code></b>
        </p>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/v1/organization-memberships/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-v1-organization-memberships--id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-organization-memberships--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-organization-memberships--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PUTapi-v1-organization-memberships--id-"
               value="01k3ahgfe5595rkvqzvxrdrg5t"
               data-component="url">
    <br>
<p>The ID of the organization membership. Example: <code>01k3ahgfe5595rkvqzvxrdrg5t</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>organization_unit_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="organization_unit_id"                data-endpoint="PUTapi-v1-organization-memberships--id-"
               value="1"
               data-component="body">
    <br>
<p>ID of the organization unit (optional). The <code>id</code> of an existing record in the organization_units table. Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>organization_position_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="organization_position_id"                data-endpoint="PUTapi-v1-organization-memberships--id-"
               value="1"
               data-component="body">
    <br>
<p>ID of the organization position (optional). The <code>id</code> of an existing record in the organization_positions table. Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>membership_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="membership_type"                data-endpoint="PUTapi-v1-organization-memberships--id-"
               value="employee"
               data-component="body">
    <br>
<p>Type of membership. Example: <code>employee</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>employee</code></li> <li><code>board_member</code></li> <li><code>consultant</code></li> <li><code>contractor</code></li> <li><code>intern</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>start_date</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="start_date"                data-endpoint="PUTapi-v1-organization-memberships--id-"
               value="2024-01-01"
               data-component="body">
    <br>
<p>Start date of membership. Must be a valid date. Example: <code>2024-01-01</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>end_date</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="end_date"                data-endpoint="PUTapi-v1-organization-memberships--id-"
               value="2024-12-31"
               data-component="body">
    <br>
<p>End date of membership (optional). Must be a valid date. Must be a date after <code>start_date</code>. Example: <code>2024-12-31</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>status</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="status"                data-endpoint="PUTapi-v1-organization-memberships--id-"
               value="active"
               data-component="body">
    <br>
<p>Status of the membership. Example: <code>active</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>active</code></li> <li><code>inactive</code></li> <li><code>terminated</code></li></ul>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>additional_roles</code></b>&nbsp;&nbsp;
<small>object</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="additional_roles"                data-endpoint="PUTapi-v1-organization-memberships--id-"
               value=""
               data-component="body">
    <br>
<p>Array of additional role IDs (optional).</p>
        </div>
        </form>

                    <h2 id="endpoints-DELETEapi-v1-organization-memberships--id-">DELETE api/v1/organization-memberships/{id}</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-DELETEapi-v1-organization-memberships--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:8000/api/v1/organization-memberships/01k3ahgfe5595rkvqzvxrdrg5t" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-memberships/01k3ahgfe5595rkvqzvxrdrg5t"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-organization-memberships--id-">
</span>
<span id="execution-results-DELETEapi-v1-organization-memberships--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-organization-memberships--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-organization-memberships--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-organization-memberships--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-organization-memberships--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-organization-memberships--id-" data-method="DELETE"
      data-path="api/v1/organization-memberships/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-organization-memberships--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-v1-organization-memberships--id-"
                    onclick="tryItOut('DELETEapi-v1-organization-memberships--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-v1-organization-memberships--id-"
                    onclick="cancelTryOut('DELETEapi-v1-organization-memberships--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-v1-organization-memberships--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/organization-memberships/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-organization-memberships--id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-organization-memberships--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-organization-memberships--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="DELETEapi-v1-organization-memberships--id-"
               value="01k3ahgfe5595rkvqzvxrdrg5t"
               data-component="url">
    <br>
<p>The ID of the organization membership. Example: <code>01k3ahgfe5595rkvqzvxrdrg5t</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-POSTapi-v1-organization-memberships--organizationMembership_id--activate">POST api/v1/organization-memberships/{organizationMembership_id}/activate</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-organization-memberships--organizationMembership_id--activate">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/organization-memberships/01k3ahgfe5595rkvqzvxrdrg5t/activate" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-memberships/01k3ahgfe5595rkvqzvxrdrg5t/activate"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-organization-memberships--organizationMembership_id--activate">
</span>
<span id="execution-results-POSTapi-v1-organization-memberships--organizationMembership_id--activate" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-organization-memberships--organizationMembership_id--activate"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-organization-memberships--organizationMembership_id--activate"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-organization-memberships--organizationMembership_id--activate" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-organization-memberships--organizationMembership_id--activate">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-organization-memberships--organizationMembership_id--activate" data-method="POST"
      data-path="api/v1/organization-memberships/{organizationMembership_id}/activate"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-organization-memberships--organizationMembership_id--activate', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-organization-memberships--organizationMembership_id--activate"
                    onclick="tryItOut('POSTapi-v1-organization-memberships--organizationMembership_id--activate');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-organization-memberships--organizationMembership_id--activate"
                    onclick="cancelTryOut('POSTapi-v1-organization-memberships--organizationMembership_id--activate');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-organization-memberships--organizationMembership_id--activate"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/organization-memberships/{organizationMembership_id}/activate</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-organization-memberships--organizationMembership_id--activate"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-organization-memberships--organizationMembership_id--activate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-organization-memberships--organizationMembership_id--activate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>organizationMembership_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="organizationMembership_id"                data-endpoint="POSTapi-v1-organization-memberships--organizationMembership_id--activate"
               value="01k3ahgfe5595rkvqzvxrdrg5t"
               data-component="url">
    <br>
<p>The ID of the organizationMembership. Example: <code>01k3ahgfe5595rkvqzvxrdrg5t</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-POSTapi-v1-organization-memberships--organizationMembership_id--deactivate">POST api/v1/organization-memberships/{organizationMembership_id}/deactivate</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-organization-memberships--organizationMembership_id--deactivate">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/organization-memberships/01k3ahgfe5595rkvqzvxrdrg5t/deactivate" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-memberships/01k3ahgfe5595rkvqzvxrdrg5t/deactivate"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-organization-memberships--organizationMembership_id--deactivate">
</span>
<span id="execution-results-POSTapi-v1-organization-memberships--organizationMembership_id--deactivate" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-organization-memberships--organizationMembership_id--deactivate"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-organization-memberships--organizationMembership_id--deactivate"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-organization-memberships--organizationMembership_id--deactivate" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-organization-memberships--organizationMembership_id--deactivate">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-organization-memberships--organizationMembership_id--deactivate" data-method="POST"
      data-path="api/v1/organization-memberships/{organizationMembership_id}/deactivate"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-organization-memberships--organizationMembership_id--deactivate', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-organization-memberships--organizationMembership_id--deactivate"
                    onclick="tryItOut('POSTapi-v1-organization-memberships--organizationMembership_id--deactivate');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-organization-memberships--organizationMembership_id--deactivate"
                    onclick="cancelTryOut('POSTapi-v1-organization-memberships--organizationMembership_id--deactivate');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-organization-memberships--organizationMembership_id--deactivate"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/organization-memberships/{organizationMembership_id}/deactivate</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-organization-memberships--organizationMembership_id--deactivate"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-organization-memberships--organizationMembership_id--deactivate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-organization-memberships--organizationMembership_id--deactivate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>organizationMembership_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="organizationMembership_id"                data-endpoint="POSTapi-v1-organization-memberships--organizationMembership_id--deactivate"
               value="01k3ahgfe5595rkvqzvxrdrg5t"
               data-component="url">
    <br>
<p>The ID of the organizationMembership. Example: <code>01k3ahgfe5595rkvqzvxrdrg5t</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-POSTapi-v1-organization-memberships--organizationMembership_id--terminate">POST api/v1/organization-memberships/{organizationMembership_id}/terminate</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-organization-memberships--organizationMembership_id--terminate">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/organization-memberships/01k3ahgfe5595rkvqzvxrdrg5t/terminate" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"end_date\": \"2024-12-31\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organization-memberships/01k3ahgfe5595rkvqzvxrdrg5t/terminate"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "end_date": "2024-12-31"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-organization-memberships--organizationMembership_id--terminate">
</span>
<span id="execution-results-POSTapi-v1-organization-memberships--organizationMembership_id--terminate" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-organization-memberships--organizationMembership_id--terminate"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-organization-memberships--organizationMembership_id--terminate"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-organization-memberships--organizationMembership_id--terminate" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-organization-memberships--organizationMembership_id--terminate">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-organization-memberships--organizationMembership_id--terminate" data-method="POST"
      data-path="api/v1/organization-memberships/{organizationMembership_id}/terminate"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-organization-memberships--organizationMembership_id--terminate', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-organization-memberships--organizationMembership_id--terminate"
                    onclick="tryItOut('POSTapi-v1-organization-memberships--organizationMembership_id--terminate');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-organization-memberships--organizationMembership_id--terminate"
                    onclick="cancelTryOut('POSTapi-v1-organization-memberships--organizationMembership_id--terminate');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-organization-memberships--organizationMembership_id--terminate"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/organization-memberships/{organizationMembership_id}/terminate</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-organization-memberships--organizationMembership_id--terminate"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-organization-memberships--organizationMembership_id--terminate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-organization-memberships--organizationMembership_id--terminate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>organizationMembership_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="organizationMembership_id"                data-endpoint="POSTapi-v1-organization-memberships--organizationMembership_id--terminate"
               value="01k3ahgfe5595rkvqzvxrdrg5t"
               data-component="url">
    <br>
<p>The ID of the organizationMembership. Example: <code>01k3ahgfe5595rkvqzvxrdrg5t</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>end_date</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="end_date"                data-endpoint="POSTapi-v1-organization-memberships--organizationMembership_id--terminate"
               value="2024-12-31"
               data-component="body">
    <br>
<p>Termination date for the membership (optional, defaults to current date). Must be a valid date. Example: <code>2024-12-31</code></p>
        </div>
        </form>

                    <h2 id="endpoints-GETapi-v1-users--user_id--memberships">GET api/v1/users/{user_id}/memberships</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-users--user_id--memberships">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/users/01k3ahgcdg9dzsh45sdpbcff64/memberships" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/users/01k3ahgcdg9dzsh45sdpbcff64/memberships"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-users--user_id--memberships">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">[
    {
        &quot;id&quot;: &quot;01k3ahgffw63x0k6eg7rx124se&quot;,
        &quot;user_id&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
        &quot;organization_unit_id&quot;: &quot;01k3ahgd72vvytzzw7q4htmrd9&quot;,
        &quot;organization_position_id&quot;: null,
        &quot;membership_type&quot;: &quot;consultant&quot;,
        &quot;start_date&quot;: &quot;2024-01-01T00:00:00.000000Z&quot;,
        &quot;end_date&quot;: &quot;2024-12-31T00:00:00.000000Z&quot;,
        &quot;status&quot;: &quot;active&quot;,
        &quot;additional_roles&quot;: [
            &quot;technical_advisory&quot;,
            &quot;code_review&quot;
        ],
        &quot;created_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;updated_at&quot;: &quot;2025-08-22T21:03:12.000000Z&quot;,
        &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
        &quot;organization&quot;: {
            &quot;id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;organization_code&quot;: &quot;SUB001&quot;,
            &quot;organization_type&quot;: &quot;subsidiary&quot;,
            &quot;parent_organization_id&quot;: &quot;01k3ahgd2pxfkakcba63e337fv&quot;,
            &quot;name&quot;: &quot;TechCorp Software&quot;,
            &quot;description&quot;: &quot;Software development and consulting services&quot;,
            &quot;address&quot;: &quot;456 Software Ave, Tech City&quot;,
            &quot;phone&quot;: &quot;+1-555-0200&quot;,
            &quot;email&quot;: &quot;info@techcorpsoftware.com&quot;,
            &quot;website&quot;: &quot;https://techcorpsoftware.com&quot;,
            &quot;is_active&quot;: true,
            &quot;registration_number&quot;: &quot;REG002&quot;,
            &quot;tax_number&quot;: &quot;TAX002&quot;,
            &quot;governance_structure&quot;: {
                &quot;board_size&quot;: 5,
                &quot;independent_directors&quot;: 2,
                &quot;committees&quot;: [
                    &quot;audit&quot;,
                    &quot;risk&quot;
                ]
            },
            &quot;authorized_capital&quot;: &quot;5000000.00&quot;,
            &quot;paid_capital&quot;: &quot;4000000.00&quot;,
            &quot;establishment_date&quot;: &quot;2021-03-01T00:00:00.000000Z&quot;,
            &quot;legal_status&quot;: &quot;Private Limited Company&quot;,
            &quot;business_activities&quot;: &quot;Software development, web applications, mobile apps&quot;,
            &quot;contact_persons&quot;: {
                &quot;managing_director&quot;: {
                    &quot;name&quot;: &quot;Mike Johnson&quot;,
                    &quot;email&quot;: &quot;md@techcorpsoftware.com&quot;
                },
                &quot;cto&quot;: {
                    &quot;name&quot;: &quot;Sarah Wilson&quot;,
                    &quot;email&quot;: &quot;cto@techcorpsoftware.com&quot;
                }
            },
            &quot;level&quot;: 1,
            &quot;path&quot;: &quot;01k3ahgd2pxfkakcba63e337fv/01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_unit&quot;: {
            &quot;id&quot;: &quot;01k3ahgd72vvytzzw7q4htmrd9&quot;,
            &quot;organization_id&quot;: &quot;01k3ahgd38c38bxcxseden9ja2&quot;,
            &quot;unit_code&quot;: &quot;ENG001&quot;,
            &quot;name&quot;: &quot;Engineering Division&quot;,
            &quot;unit_type&quot;: &quot;division&quot;,
            &quot;description&quot;: &quot;Software engineering and development&quot;,
            &quot;parent_unit_id&quot;: null,
            &quot;responsibilities&quot;: [
                &quot;Software development&quot;,
                &quot;Technical architecture&quot;,
                &quot;Code quality assurance&quot;,
                &quot;Development methodology&quot;
            ],
            &quot;authorities&quot;: [
                &quot;Define technical standards&quot;,
                &quot;Approve technical designs&quot;,
                &quot;Manage development teams&quot;,
                &quot;Release software products&quot;
            ],
            &quot;is_active&quot;: true,
            &quot;sort_order&quot;: 2,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:10.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
        },
        &quot;organization_position&quot;: null
    }
]</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-users--user_id--memberships" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-users--user_id--memberships"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-users--user_id--memberships"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-users--user_id--memberships" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-users--user_id--memberships">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-users--user_id--memberships" data-method="GET"
      data-path="api/v1/users/{user_id}/memberships"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-users--user_id--memberships', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-users--user_id--memberships"
                    onclick="tryItOut('GETapi-v1-users--user_id--memberships');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-users--user_id--memberships"
                    onclick="cancelTryOut('GETapi-v1-users--user_id--memberships');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-users--user_id--memberships"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/users/{user_id}/memberships</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-users--user_id--memberships"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-users--user_id--memberships"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-users--user_id--memberships"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>user_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="user_id"                data-endpoint="GETapi-v1-users--user_id--memberships"
               value="01k3ahgcdg9dzsh45sdpbcff64"
               data-component="url">
    <br>
<p>The ID of the user. Example: <code>01k3ahgcdg9dzsh45sdpbcff64</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-GETapi-v1-organizations--organization_id--memberships">GET api/v1/organizations/{organization_id}/memberships</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-organizations--organization_id--memberships">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4/memberships" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4/memberships"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organizations--organization_id--memberships">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">[]</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organizations--organization_id--memberships" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organizations--organization_id--memberships"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organizations--organization_id--memberships"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organizations--organization_id--memberships" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organizations--organization_id--memberships">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organizations--organization_id--memberships" data-method="GET"
      data-path="api/v1/organizations/{organization_id}/memberships"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organizations--organization_id--memberships', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organizations--organization_id--memberships"
                    onclick="tryItOut('GETapi-v1-organizations--organization_id--memberships');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organizations--organization_id--memberships"
                    onclick="cancelTryOut('GETapi-v1-organizations--organization_id--memberships');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organizations--organization_id--memberships"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organizations/{organization_id}/memberships</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organizations--organization_id--memberships"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organizations--organization_id--memberships"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organizations--organization_id--memberships"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>organization_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="organization_id"                data-endpoint="GETapi-v1-organizations--organization_id--memberships"
               value="01k3ahgcgwbgdzwmy8an85afk4"
               data-component="url">
    <br>
<p>The ID of the organization. Example: <code>01k3ahgcgwbgdzwmy8an85afk4</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-GETapi-v1-board-members">GET api/v1/board-members</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-board-members">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/board-members" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/board-members"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-board-members">
            <blockquote>
            <p>Example response (500):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Server Error&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-board-members" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-board-members"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-board-members"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-board-members" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-board-members">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-board-members" data-method="GET"
      data-path="api/v1/board-members"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-board-members', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-board-members"
                    onclick="tryItOut('GETapi-v1-board-members');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-board-members"
                    onclick="cancelTryOut('GETapi-v1-board-members');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-board-members"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/board-members</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-board-members"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-board-members"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-board-members"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-GETapi-v1-executives">GET api/v1/executives</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-executives">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/executives" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/executives"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-executives">
            <blockquote>
            <p>Example response (500):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Server Error&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-executives" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-executives"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-executives"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-executives" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-executives">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-executives" data-method="GET"
      data-path="api/v1/executives"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-executives', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-executives"
                    onclick="tryItOut('GETapi-v1-executives');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-executives"
                    onclick="cancelTryOut('GETapi-v1-executives');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-executives"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/executives</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-executives"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-executives"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-executives"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-GETapi-v1-auth-webauthn-options">Get authentication options for WebAuthn login</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-auth-webauthn-options">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/auth/webauthn/options" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/webauthn/options"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-auth-webauthn-options">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;challenge&quot;: &quot;qQS6opzNjk+c5u2gk2J8b8L5e6TinvCdnfHxtwaCics=&quot;,
    &quot;timeout&quot;: 60000,
    &quot;rpId&quot;: &quot;localhost&quot;,
    &quot;allowCredentials&quot;: [],
    &quot;userVerification&quot;: &quot;preferred&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-auth-webauthn-options" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-auth-webauthn-options"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-auth-webauthn-options"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-auth-webauthn-options" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-auth-webauthn-options">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-auth-webauthn-options" data-method="GET"
      data-path="api/v1/auth/webauthn/options"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-auth-webauthn-options', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-auth-webauthn-options"
                    onclick="tryItOut('GETapi-v1-auth-webauthn-options');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-auth-webauthn-options"
                    onclick="cancelTryOut('GETapi-v1-auth-webauthn-options');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-auth-webauthn-options"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/auth/webauthn/options</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-auth-webauthn-options"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-auth-webauthn-options"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-auth-webauthn-options"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-POSTapi-v1-auth-webauthn-authenticate">Authenticate using WebAuthn (passwordless login)</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-auth-webauthn-authenticate">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/auth/webauthn/authenticate" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/webauthn/authenticate"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-auth-webauthn-authenticate">
</span>
<span id="execution-results-POSTapi-v1-auth-webauthn-authenticate" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-auth-webauthn-authenticate"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-auth-webauthn-authenticate"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-auth-webauthn-authenticate" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-auth-webauthn-authenticate">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-auth-webauthn-authenticate" data-method="POST"
      data-path="api/v1/auth/webauthn/authenticate"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-auth-webauthn-authenticate', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-auth-webauthn-authenticate"
                    onclick="tryItOut('POSTapi-v1-auth-webauthn-authenticate');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-auth-webauthn-authenticate"
                    onclick="cancelTryOut('POSTapi-v1-auth-webauthn-authenticate');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-auth-webauthn-authenticate"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/auth/webauthn/authenticate</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-auth-webauthn-authenticate"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-auth-webauthn-authenticate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-auth-webauthn-authenticate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-GETapi-v1-auth-webauthn-capabilities">Get WebAuthn capabilities and user agent info</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-auth-webauthn-capabilities">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/auth/webauthn/capabilities" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/webauthn/capabilities"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-auth-webauthn-capabilities">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;webauthn_supported&quot;: true,
    &quot;platform_authenticator&quot;: null,
    &quot;cross_platform_authenticator&quot;: null,
    &quot;user_agent&quot;: &quot;Symfony&quot;,
    &quot;server_capabilities&quot;: {
        &quot;resident_keys&quot;: true,
        &quot;user_verification&quot;: &quot;preferred&quot;,
        &quot;attestation&quot;: &quot;none&quot;,
        &quot;algorithms&quot;: [
            &quot;RS256&quot;,
            &quot;ES256&quot;
        ]
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-auth-webauthn-capabilities" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-auth-webauthn-capabilities"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-auth-webauthn-capabilities"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-auth-webauthn-capabilities" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-auth-webauthn-capabilities">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-auth-webauthn-capabilities" data-method="GET"
      data-path="api/v1/auth/webauthn/capabilities"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-auth-webauthn-capabilities', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-auth-webauthn-capabilities"
                    onclick="tryItOut('GETapi-v1-auth-webauthn-capabilities');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-auth-webauthn-capabilities"
                    onclick="cancelTryOut('GETapi-v1-auth-webauthn-capabilities');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-auth-webauthn-capabilities"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/auth/webauthn/capabilities</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-auth-webauthn-capabilities"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-auth-webauthn-capabilities"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-auth-webauthn-capabilities"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-GETapi-v1-auth-webauthn-health">Test WebAuthn connectivity and server readiness</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-auth-webauthn-health">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/auth/webauthn/health" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/auth/webauthn/health"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-auth-webauthn-health">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;status&quot;: &quot;healthy&quot;,
    &quot;webauthn_server&quot;: &quot;operational&quot;,
    &quot;timestamp&quot;: &quot;2025-08-23T08:49:03.336221Z&quot;,
    &quot;services&quot;: {
        &quot;registration&quot;: &quot;available&quot;,
        &quot;authentication&quot;: &quot;available&quot;,
        &quot;management&quot;: &quot;available&quot;
    },
    &quot;challenge_generation&quot;: true
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-auth-webauthn-health" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-auth-webauthn-health"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-auth-webauthn-health"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-auth-webauthn-health" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-auth-webauthn-health">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-auth-webauthn-health" data-method="GET"
      data-path="api/v1/auth/webauthn/health"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-auth-webauthn-health', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-auth-webauthn-health"
                    onclick="tryItOut('GETapi-v1-auth-webauthn-health');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-auth-webauthn-health"
                    onclick="cancelTryOut('GETapi-v1-auth-webauthn-health');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-auth-webauthn-health"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/auth/webauthn/health</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-auth-webauthn-health"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-auth-webauthn-health"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-auth-webauthn-health"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-GETapi-v1-webauthn">Get all passkeys for the authenticated user</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-webauthn">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/webauthn" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/webauthn"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-webauthn">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-webauthn" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-webauthn"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-webauthn"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-webauthn" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-webauthn">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-webauthn" data-method="GET"
      data-path="api/v1/webauthn"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-webauthn', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-webauthn"
                    onclick="tryItOut('GETapi-v1-webauthn');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-webauthn"
                    onclick="cancelTryOut('GETapi-v1-webauthn');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-webauthn"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/webauthn</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-webauthn"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-webauthn"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-webauthn"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-GETapi-v1-webauthn-register-options">Get registration options for creating a new passkey</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-webauthn-register-options">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/webauthn/register/options" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/webauthn/register/options"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-webauthn-register-options">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-webauthn-register-options" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-webauthn-register-options"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-webauthn-register-options"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-webauthn-register-options" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-webauthn-register-options">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-webauthn-register-options" data-method="GET"
      data-path="api/v1/webauthn/register/options"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-webauthn-register-options', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-webauthn-register-options"
                    onclick="tryItOut('GETapi-v1-webauthn-register-options');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-webauthn-register-options"
                    onclick="cancelTryOut('GETapi-v1-webauthn-register-options');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-webauthn-register-options"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/webauthn/register/options</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-webauthn-register-options"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-webauthn-register-options"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-webauthn-register-options"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-POSTapi-v1-webauthn-register">Register a new passkey</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-webauthn-register">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/webauthn/register" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/webauthn/register"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-webauthn-register">
</span>
<span id="execution-results-POSTapi-v1-webauthn-register" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-webauthn-register"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-webauthn-register"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-webauthn-register" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-webauthn-register">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-webauthn-register" data-method="POST"
      data-path="api/v1/webauthn/register"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-webauthn-register', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-webauthn-register"
                    onclick="tryItOut('POSTapi-v1-webauthn-register');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-webauthn-register"
                    onclick="cancelTryOut('POSTapi-v1-webauthn-register');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-webauthn-register"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/webauthn/register</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-webauthn-register"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-webauthn-register"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-webauthn-register"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-PUTapi-v1-webauthn--passkey_id-">Update passkey name</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PUTapi-v1-webauthn--passkey_id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:8000/api/v1/webauthn/16" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"My iPhone Touch ID\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/webauthn/16"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "My iPhone Touch ID"
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-webauthn--passkey_id-">
</span>
<span id="execution-results-PUTapi-v1-webauthn--passkey_id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-webauthn--passkey_id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-webauthn--passkey_id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-webauthn--passkey_id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-webauthn--passkey_id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-webauthn--passkey_id-" data-method="PUT"
      data-path="api/v1/webauthn/{passkey_id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-webauthn--passkey_id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-v1-webauthn--passkey_id-"
                    onclick="tryItOut('PUTapi-v1-webauthn--passkey_id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-v1-webauthn--passkey_id-"
                    onclick="cancelTryOut('PUTapi-v1-webauthn--passkey_id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-v1-webauthn--passkey_id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/webauthn/{passkey_id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-v1-webauthn--passkey_id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-webauthn--passkey_id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-webauthn--passkey_id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>passkey_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="passkey_id"                data-endpoint="PUTapi-v1-webauthn--passkey_id-"
               value="16"
               data-component="url">
    <br>
<p>The ID of the passkey. Example: <code>16</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="PUTapi-v1-webauthn--passkey_id-"
               value="My iPhone Touch ID"
               data-component="body">
    <br>
<p>Friendly name for the passkey/WebAuthn credential. Must not be greater than 255 characters. Example: <code>My iPhone Touch ID</code></p>
        </div>
        </form>

                    <h2 id="endpoints-DELETEapi-v1-webauthn--passkey_id-">Delete a passkey</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-DELETEapi-v1-webauthn--passkey_id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:8000/api/v1/webauthn/16" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/webauthn/16"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-webauthn--passkey_id-">
</span>
<span id="execution-results-DELETEapi-v1-webauthn--passkey_id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-webauthn--passkey_id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-webauthn--passkey_id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-webauthn--passkey_id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-webauthn--passkey_id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-webauthn--passkey_id-" data-method="DELETE"
      data-path="api/v1/webauthn/{passkey_id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-webauthn--passkey_id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-v1-webauthn--passkey_id-"
                    onclick="tryItOut('DELETEapi-v1-webauthn--passkey_id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-v1-webauthn--passkey_id-"
                    onclick="cancelTryOut('DELETEapi-v1-webauthn--passkey_id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-v1-webauthn--passkey_id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/webauthn/{passkey_id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-webauthn--passkey_id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-webauthn--passkey_id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-webauthn--passkey_id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>passkey_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
 &nbsp;
                <input type="number" style="display: none"
               step="any"               name="passkey_id"                data-endpoint="DELETEapi-v1-webauthn--passkey_id-"
               value="16"
               data-component="url">
    <br>
<p>The ID of the passkey. Example: <code>16</code></p>
            </div>
                    </form>

                    <h2 id="endpoints-GETapi-v1-webauthn-statistics">Get usage statistics for user&#039;s passkeys</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-webauthn-statistics">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/webauthn/statistics" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/webauthn/statistics"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-webauthn-statistics">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-webauthn-statistics" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-webauthn-statistics"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-webauthn-statistics"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-webauthn-statistics" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-webauthn-statistics">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-webauthn-statistics" data-method="GET"
      data-path="api/v1/webauthn/statistics"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-webauthn-statistics', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-webauthn-statistics"
                    onclick="tryItOut('GETapi-v1-webauthn-statistics');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-webauthn-statistics"
                    onclick="cancelTryOut('GETapi-v1-webauthn-statistics');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-webauthn-statistics"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/webauthn/statistics</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-webauthn-statistics"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-webauthn-statistics"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-webauthn-statistics"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="endpoints-GETapi-organization-position-levels">Get organization position levels for API/select options.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-organization-position-levels">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/organization-position-levels" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/organization-position-levels"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-organization-position-levels">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
set-cookie: XSRF-TOKEN=eyJpdiI6IkZhN1ZHK3JHbEdCL05zaVRVWE5uY3c9PSIsInZhbHVlIjoiU1RjLzFENlpCRUtvRndob3Vza1dtL2drd1A5WityUWdCazg4a2xTOVlxZ2Uyd3JCczZrSWl1a0g1eDNNc3htSGJmYVB1bFVyaFdZaXBpaUVoL2tNWFVLNDNuTUhaN0Q5bVBTbmF2dDNoMC9xZ1BTa3FrL1Z5TTlncGROVUpXRW4iLCJtYWMiOiI3ODlkMTRhZjg2MzBhMWY1MzIzMzg0ZmVkODA5NGU4MWEzNjY5NTIyMzY0OTU1NDlhMzNkNjQxMDlmMDNmZjgwIiwidGFnIjoiIn0%3D; expires=Sat, 23 Aug 2025 10:49:03 GMT; Max-Age=7200; path=/; samesite=lax; laravel_session=eyJpdiI6Imp4a1VRZWpXSkdJVW0vOVgyZWJ2bXc9PSIsInZhbHVlIjoiZFhLT3RtVUJTcG9SR0tFZ2huaVhOZTIwY3N3djRFWTY3R0pndlNlTlVIZzhydWxNdGpTY0FGV0UzZW1xbC96ZnQwNVN2QWxDVWt3R0JKdEZBNEJPZGdNbCtrZnVJc0RIV1NURURHMlBCQkxsN0RyUnFzUUxJYlV4M3V2d2ZvWlgiLCJtYWMiOiI1NDFkMTIwYzZjNTkzNTQ2OTc3ZDU1MzMxNmY4OTJhZGI0NWI3MTg2ZGFjNWY3YzVkMDFlMDczZTY4ZjY4MDYyIiwidGFnIjoiIn0%3D; expires=Sat, 23 Aug 2025 10:49:03 GMT; Max-Age=7200; path=/; httponly; samesite=lax
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-organization-position-levels" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-organization-position-levels"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-organization-position-levels"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-organization-position-levels" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-organization-position-levels">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-organization-position-levels" data-method="GET"
      data-path="api/organization-position-levels"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-organization-position-levels', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-organization-position-levels"
                    onclick="tryItOut('GETapi-organization-position-levels');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-organization-position-levels"
                    onclick="cancelTryOut('GETapi-organization-position-levels');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-organization-position-levels"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/organization-position-levels</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-organization-position-levels"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-organization-position-levels"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-organization-position-levels"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                <h1 id="multi-factor-authentication">Multi-Factor Authentication</h1>

    

                                <h2 id="multi-factor-authentication-GETapi-v1-mfa">Get MFA status</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retrieve the current MFA configuration status for the authenticated user</p>

<span id="example-requests-GETapi-v1-mfa">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/mfa" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/mfa"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-mfa">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;mfa_enabled&quot;: true,
    &quot;totp_enabled&quot;: true,
    &quot;backup_codes_count&quot;: 8,
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;,
    &quot;confirmed_at&quot;: &quot;2024-01-15T10:35:00Z&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-mfa" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-mfa"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-mfa"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-mfa" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-mfa">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-mfa" data-method="GET"
      data-path="api/v1/mfa"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-mfa', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-mfa"
                    onclick="tryItOut('GETapi-v1-mfa');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-mfa"
                    onclick="cancelTryOut('GETapi-v1-mfa');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-mfa"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/mfa</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-mfa"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-mfa"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-mfa"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="multi-factor-authentication-POSTapi-v1-mfa">Initialize MFA setup</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Start the MFA setup process by generating a TOTP secret and QR code</p>

<span id="example-requests-POSTapi-v1-mfa">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/mfa" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/mfa"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-mfa">
            <blockquote>
            <p>Example response (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;MFA setup initiated&quot;,
    &quot;secret&quot;: &quot;JBSWY3DPEHPK3PXP&quot;,
    &quot;qr_code_url&quot;: &quot;otpauth://totp/Example:user@example.com?secret=JBSWY3DPEHPK3PXP&amp;issuer=Example&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (400):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;error&quot;: &quot;MFA is already enabled&quot;,
    &quot;code&quot;: &quot;MFA_ALREADY_ENABLED&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-mfa" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-mfa"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-mfa"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-mfa" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-mfa">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-mfa" data-method="POST"
      data-path="api/v1/mfa"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-mfa', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-mfa"
                    onclick="tryItOut('POSTapi-v1-mfa');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-mfa"
                    onclick="cancelTryOut('POSTapi-v1-mfa');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-mfa"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/mfa</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-mfa"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-mfa"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-mfa"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="multi-factor-authentication-PUTapi-v1-mfa">Confirm and fully enable MFA</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PUTapi-v1-mfa">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:8000/api/v1/mfa" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"code\": \"123456\",
    \"password\": \"password123\",
    \"secret\": \"JBSWY3DPEHPK3PXP\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/mfa"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "code": "123456",
    "password": "password123",
    "secret": "JBSWY3DPEHPK3PXP"
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-mfa">
</span>
<span id="execution-results-PUTapi-v1-mfa" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-mfa"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-mfa"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-mfa" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-mfa">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-mfa" data-method="PUT"
      data-path="api/v1/mfa"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-mfa', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-v1-mfa"
                    onclick="tryItOut('PUTapi-v1-mfa');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-v1-mfa"
                    onclick="cancelTryOut('PUTapi-v1-mfa');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-v1-mfa"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/mfa</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-v1-mfa"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-mfa"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-mfa"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>code</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="code"                data-endpoint="PUTapi-v1-mfa"
               value="123456"
               data-component="body">
    <br>
<p>6-digit TOTP code from authenticator app. Must be 6 characters. Example: <code>123456</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="PUTapi-v1-mfa"
               value="password123"
               data-component="body">
    <br>
<p>User current password for verification. Example: <code>password123</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>secret</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="secret"                data-endpoint="PUTapi-v1-mfa"
               value="JBSWY3DPEHPK3PXP"
               data-component="body">
    <br>
<p>TOTP secret key (optional, used during setup). Example: <code>JBSWY3DPEHPK3PXP</code></p>
        </div>
        </form>

                    <h2 id="multi-factor-authentication-DELETEapi-v1-mfa">Disable MFA for the authenticated user</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-DELETEapi-v1-mfa">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:8000/api/v1/mfa" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"password\": \"password123\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/mfa"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "password": "password123"
};

fetch(url, {
    method: "DELETE",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-mfa">
</span>
<span id="execution-results-DELETEapi-v1-mfa" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-mfa"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-mfa"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-mfa" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-mfa">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-mfa" data-method="DELETE"
      data-path="api/v1/mfa"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-mfa', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-v1-mfa"
                    onclick="tryItOut('DELETEapi-v1-mfa');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-v1-mfa"
                    onclick="cancelTryOut('DELETEapi-v1-mfa');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-v1-mfa"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/mfa</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-mfa"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-mfa"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-mfa"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="DELETEapi-v1-mfa"
               value="password123"
               data-component="body">
    <br>
<p>User current password for verification. Example: <code>password123</code></p>
        </div>
        </form>

                    <h2 id="multi-factor-authentication-POSTapi-v1-mfa-verify">Verify MFA code</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-mfa-verify">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/mfa/verify" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"code\": \"123456\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/mfa/verify"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "code": "123456"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-mfa-verify">
</span>
<span id="execution-results-POSTapi-v1-mfa-verify" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-mfa-verify"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-mfa-verify"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-mfa-verify" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-mfa-verify">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-mfa-verify" data-method="POST"
      data-path="api/v1/mfa/verify"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-mfa-verify', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-mfa-verify"
                    onclick="tryItOut('POSTapi-v1-mfa-verify');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-mfa-verify"
                    onclick="cancelTryOut('POSTapi-v1-mfa-verify');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-mfa-verify"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/mfa/verify</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-mfa-verify"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-mfa-verify"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-mfa-verify"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>code</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="code"                data-endpoint="POSTapi-v1-mfa-verify"
               value="123456"
               data-component="body">
    <br>
<p>The 6-digit TOTP code or backup code for verification. Example: <code>123456</code></p>
        </div>
        </form>

                    <h2 id="multi-factor-authentication-GETapi-v1-mfa-backup-codes-status">Get remaining backup codes count</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-mfa-backup-codes-status">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/mfa/backup-codes/status" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/mfa/backup-codes/status"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-mfa-backup-codes-status">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-mfa-backup-codes-status" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-mfa-backup-codes-status"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-mfa-backup-codes-status"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-mfa-backup-codes-status" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-mfa-backup-codes-status">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-mfa-backup-codes-status" data-method="GET"
      data-path="api/v1/mfa/backup-codes/status"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-mfa-backup-codes-status', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-mfa-backup-codes-status"
                    onclick="tryItOut('GETapi-v1-mfa-backup-codes-status');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-mfa-backup-codes-status"
                    onclick="cancelTryOut('GETapi-v1-mfa-backup-codes-status');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-mfa-backup-codes-status"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/mfa/backup-codes/status</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-mfa-backup-codes-status"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-mfa-backup-codes-status"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-mfa-backup-codes-status"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="multi-factor-authentication-POSTapi-v1-mfa-backup-codes-regenerate">Regenerate backup codes</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-POSTapi-v1-mfa-backup-codes-regenerate">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/mfa/backup-codes/regenerate" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"password\": \"password123\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/mfa/backup-codes/regenerate"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "password": "password123"
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-mfa-backup-codes-regenerate">
</span>
<span id="execution-results-POSTapi-v1-mfa-backup-codes-regenerate" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-mfa-backup-codes-regenerate"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-mfa-backup-codes-regenerate"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-mfa-backup-codes-regenerate" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-mfa-backup-codes-regenerate">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-mfa-backup-codes-regenerate" data-method="POST"
      data-path="api/v1/mfa/backup-codes/regenerate"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-mfa-backup-codes-regenerate', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-mfa-backup-codes-regenerate"
                    onclick="tryItOut('POSTapi-v1-mfa-backup-codes-regenerate');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-mfa-backup-codes-regenerate"
                    onclick="cancelTryOut('POSTapi-v1-mfa-backup-codes-regenerate');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-mfa-backup-codes-regenerate"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/mfa/backup-codes/regenerate</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-mfa-backup-codes-regenerate"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-mfa-backup-codes-regenerate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-mfa-backup-codes-regenerate"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="POSTapi-v1-mfa-backup-codes-regenerate"
               value="password123"
               data-component="body">
    <br>
<p>User current password for verification. Example: <code>password123</code></p>
        </div>
        </form>

                <h1 id="organization-management">Organization Management</h1>

    

                                <h2 id="organization-management-GETapi-v1-organizations">Get organizations</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retrieve a paginated list of organizations with optional filtering and relationships</p>

<span id="example-requests-GETapi-v1-organizations">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organizations?organization_type=holding_company&amp;parent_organization_id=1&amp;hierarchy_root=1&amp;include=departments%2Cchildren&amp;per_page=15" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organizations"
);

const params = {
    "organization_type": "holding_company",
    "parent_organization_id": "1",
    "hierarchy_root": "1",
    "include": "departments,children",
    "per_page": "15",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organizations">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: 1,
        &quot;name&quot;: &quot;Acme Corp&quot;,
        &quot;organization_type&quot;: &quot;holding_company&quot;
    },
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organizations" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organizations"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organizations"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organizations" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organizations">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organizations" data-method="GET"
      data-path="api/v1/organizations"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organizations', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organizations"
                    onclick="tryItOut('GETapi-v1-organizations');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organizations"
                    onclick="cancelTryOut('GETapi-v1-organizations');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organizations"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organizations</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organizations"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organizations"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organizations"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>organization_type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="organization_type"                data-endpoint="GETapi-v1-organizations"
               value="holding_company"
               data-component="query">
    <br>
<p>Filter by organization type Example: <code>holding_company</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>parent_organization_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="parent_organization_id"                data-endpoint="GETapi-v1-organizations"
               value="1"
               data-component="query">
    <br>
<p>Filter by parent organization ID Example: <code>1</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>hierarchy_root</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
                <label data-endpoint="GETapi-v1-organizations" style="display: none">
            <input type="radio" name="hierarchy_root"
                   value="1"
                   data-endpoint="GETapi-v1-organizations"
                   data-component="query"             >
            <code>true</code>
        </label>
        <label data-endpoint="GETapi-v1-organizations" style="display: none">
            <input type="radio" name="hierarchy_root"
                   value="0"
                   data-endpoint="GETapi-v1-organizations"
                   data-component="query"             >
            <code>false</code>
        </label>
    <br>
<p>Show only root organizations (no parent) Example: <code>true</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>include</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="include"                data-endpoint="GETapi-v1-organizations"
               value="departments,children"
               data-component="query">
    <br>
<p>Include relationships (comma-separated: departments,parent,children,units) Example: <code>departments,children</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-v1-organizations"
               value="15"
               data-component="query">
    <br>
<p>Number of results per page Example: <code>15</code></p>
            </div>
                </form>

                    <h2 id="organization-management-POSTapi-v1-organizations">Create organization</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Create a new organization with hierarchical structure support</p>

<span id="example-requests-POSTapi-v1-organizations">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/organizations" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organizations"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-organizations">
            <blockquote>
            <p>Example response (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: 1,
    &quot;name&quot;: &quot;New Organization&quot;,
    &quot;organization_type&quot;: &quot;division&quot;,
    &quot;created_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (400):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Organization cannot be its own parent&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-organizations" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-organizations"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-organizations"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-organizations" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-organizations">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-organizations" data-method="POST"
      data-path="api/v1/organizations"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-organizations', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-organizations"
                    onclick="tryItOut('POSTapi-v1-organizations');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-organizations"
                    onclick="cancelTryOut('POSTapi-v1-organizations');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-organizations"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/organizations</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-organizations"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-organizations"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-organizations"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="organization-management-GETapi-v1-organizations--id-">Get organization details</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retrieve detailed information about a specific organization</p>

<span id="example-requests-GETapi-v1-organizations--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4?include=departments" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4"
);

const params = {
    "include": "departments",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organizations--id-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: 1,
    &quot;name&quot;: &quot;Acme Corp&quot;,
    &quot;organization_type&quot;: &quot;holding_company&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organizations--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organizations--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organizations--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organizations--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organizations--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organizations--id-" data-method="GET"
      data-path="api/v1/organizations/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organizations--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organizations--id-"
                    onclick="tryItOut('GETapi-v1-organizations--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organizations--id-"
                    onclick="cancelTryOut('GETapi-v1-organizations--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organizations--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organizations/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organizations--id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organizations--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organizations--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-v1-organizations--id-"
               value="01k3ahgcgwbgdzwmy8an85afk4"
               data-component="url">
    <br>
<p>The ID of the organization. Example: <code>01k3ahgcgwbgdzwmy8an85afk4</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>include</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="include"                data-endpoint="GETapi-v1-organizations--id-"
               value="departments"
               data-component="query">
    <br>
<p>Include relationships (comma-separated: departments) Example: <code>departments</code></p>
            </div>
                </form>

                    <h2 id="organization-management-PUTapi-v1-organizations--id-">Update organization</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Update an existing organization with validation to prevent circular hierarchies</p>

<span id="example-requests-PUTapi-v1-organizations--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "PUT",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-organizations--id-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: 1,
    &quot;name&quot;: &quot;Updated Organization&quot;,
    &quot;organization_type&quot;: &quot;division&quot;,
    &quot;updated_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (400):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Organization cannot be its own parent&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-PUTapi-v1-organizations--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-organizations--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-organizations--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-organizations--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-organizations--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-organizations--id-" data-method="PUT"
      data-path="api/v1/organizations/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-organizations--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-v1-organizations--id-"
                    onclick="tryItOut('PUTapi-v1-organizations--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-v1-organizations--id-"
                    onclick="cancelTryOut('PUTapi-v1-organizations--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-v1-organizations--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/organizations/{id}</code></b>
        </p>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/v1/organizations/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-v1-organizations--id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-organizations--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-organizations--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PUTapi-v1-organizations--id-"
               value="01k3ahgcgwbgdzwmy8an85afk4"
               data-component="url">
    <br>
<p>The ID of the organization. Example: <code>01k3ahgcgwbgdzwmy8an85afk4</code></p>
            </div>
                    </form>

                    <h2 id="organization-management-DELETEapi-v1-organizations--id-">Delete organization</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Delete an organization after validating no dependencies exist</p>

<span id="example-requests-DELETEapi-v1-organizations--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-organizations--id-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Organization deleted successfully&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (400):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Cannot delete organization with child organizations. Please reassign or delete child organizations first.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-v1-organizations--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-organizations--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-organizations--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-organizations--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-organizations--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-organizations--id-" data-method="DELETE"
      data-path="api/v1/organizations/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-organizations--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-v1-organizations--id-"
                    onclick="tryItOut('DELETEapi-v1-organizations--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-v1-organizations--id-"
                    onclick="cancelTryOut('DELETEapi-v1-organizations--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-v1-organizations--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/organizations/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-organizations--id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-organizations--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-organizations--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="DELETEapi-v1-organizations--id-"
               value="01k3ahgcgwbgdzwmy8an85afk4"
               data-component="url">
    <br>
<p>The ID of the organization. Example: <code>01k3ahgcgwbgdzwmy8an85afk4</code></p>
            </div>
                    </form>

                    <h2 id="organization-management-GETapi-v1-organizations-hierarchy-tree">Get organization hierarchy</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retrieve the complete organizational hierarchy tree starting from root organizations</p>

<span id="example-requests-GETapi-v1-organizations-hierarchy-tree">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organizations/hierarchy/tree" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organizations/hierarchy/tree"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organizations-hierarchy-tree">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">[
    {
        &quot;id&quot;: 1,
        &quot;name&quot;: &quot;Root Corp&quot;,
        &quot;children&quot;: [
            {
                &quot;id&quot;: 2,
                &quot;name&quot;: &quot;Division A&quot;,
                &quot;children&quot;: []
            }
        ]
    }
]</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organizations-hierarchy-tree" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organizations-hierarchy-tree"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organizations-hierarchy-tree"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organizations-hierarchy-tree" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organizations-hierarchy-tree">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organizations-hierarchy-tree" data-method="GET"
      data-path="api/v1/organizations/hierarchy/tree"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organizations-hierarchy-tree', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organizations-hierarchy-tree"
                    onclick="tryItOut('GETapi-v1-organizations-hierarchy-tree');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organizations-hierarchy-tree"
                    onclick="cancelTryOut('GETapi-v1-organizations-hierarchy-tree');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organizations-hierarchy-tree"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organizations/hierarchy/tree</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organizations-hierarchy-tree"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organizations-hierarchy-tree"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organizations-hierarchy-tree"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="organization-management-GETapi-v1-organizations-type--type-">Get organizations by type</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retrieve organizations filtered by their organizational type</p>

<span id="example-requests-GETapi-v1-organizations-type--type-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organizations/type/architecto?type=holding_company" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organizations/type/architecto"
);

const params = {
    "type": "holding_company",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organizations-type--type-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: 1,
            &quot;name&quot;: &quot;Acme Holdings&quot;,
            &quot;organization_type&quot;: &quot;holding_company&quot;
        }
    ]
}</code>
 </pre>
            <blockquote>
            <p>Example response (400):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Invalid organization type&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organizations-type--type-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organizations-type--type-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organizations-type--type-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organizations-type--type-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organizations-type--type-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organizations-type--type-" data-method="GET"
      data-path="api/v1/organizations/type/{type}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organizations-type--type-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organizations-type--type-"
                    onclick="tryItOut('GETapi-v1-organizations-type--type-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organizations-type--type-"
                    onclick="cancelTryOut('GETapi-v1-organizations-type--type-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organizations-type--type-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organizations/type/{type}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organizations-type--type-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organizations-type--type-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organizations-type--type-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="type"                data-endpoint="GETapi-v1-organizations-type--type-"
               value="architecto"
               data-component="url">
    <br>
<p>The type. Example: <code>architecto</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>type</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="type"                data-endpoint="GETapi-v1-organizations-type--type-"
               value="holding_company"
               data-component="query">
    <br>
<p>Organization type Example: <code>holding_company</code></p>
Must be one of:
<ul style="list-style-type: square;"><li><code>holding_company</code></li> <li><code>subsidiary</code></li> <li><code>division</code></li> <li><code>branch</code></li> <li><code>department</code></li> <li><code>unit</code></li></ul>
            </div>
                </form>

                    <h2 id="organization-management-GETapi-v1-organizations--organization_id--members">Get organization members</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retrieve a paginated list of members in an organization with their roles</p>

<span id="example-requests-GETapi-v1-organizations--organization_id--members">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4/members?status=active&amp;per_page=15" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4/members"
);

const params = {
    "status": "active",
    "per_page": "15",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organizations--organization_id--members">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: {
        &quot;id&quot;: 1,
        &quot;name&quot;: &quot;John Doe&quot;,
        &quot;email&quot;: &quot;john@example.com&quot;
    },
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organizations--organization_id--members" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organizations--organization_id--members"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organizations--organization_id--members"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organizations--organization_id--members" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organizations--organization_id--members">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organizations--organization_id--members" data-method="GET"
      data-path="api/v1/organizations/{organization_id}/members"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organizations--organization_id--members', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organizations--organization_id--members"
                    onclick="tryItOut('GETapi-v1-organizations--organization_id--members');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organizations--organization_id--members"
                    onclick="cancelTryOut('GETapi-v1-organizations--organization_id--members');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organizations--organization_id--members"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organizations/{organization_id}/members</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organizations--organization_id--members"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organizations--organization_id--members"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organizations--organization_id--members"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>organization_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="organization_id"                data-endpoint="GETapi-v1-organizations--organization_id--members"
               value="01k3ahgcgwbgdzwmy8an85afk4"
               data-component="url">
    <br>
<p>The ID of the organization. Example: <code>01k3ahgcgwbgdzwmy8an85afk4</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>status</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="status"                data-endpoint="GETapi-v1-organizations--organization_id--members"
               value="active"
               data-component="query">
    <br>
<p>Filter by membership status Example: <code>active</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-v1-organizations--organization_id--members"
               value="15"
               data-component="query">
    <br>
<p>Number of results per page Example: <code>15</code></p>
            </div>
                </form>

                    <h2 id="organization-management-POSTapi-v1-organizations--organization_id--members">Add member to organization</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Add a user as a member to an organization with optional role assignments</p>

<span id="example-requests-POSTapi-v1-organizations--organization_id--members">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4/members" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4/members"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-organizations--organization_id--members">
            <blockquote>
            <p>Example response (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Member added successfully&quot;,
    &quot;membership&quot;: {
        &quot;id&quot;: 1,
        &quot;user_id&quot;: 1,
        &quot;organization_id&quot;: 1
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (400):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;User is already an active member of this organization&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-organizations--organization_id--members" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-organizations--organization_id--members"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-organizations--organization_id--members"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-organizations--organization_id--members" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-organizations--organization_id--members">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-organizations--organization_id--members" data-method="POST"
      data-path="api/v1/organizations/{organization_id}/members"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-organizations--organization_id--members', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-organizations--organization_id--members"
                    onclick="tryItOut('POSTapi-v1-organizations--organization_id--members');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-organizations--organization_id--members"
                    onclick="cancelTryOut('POSTapi-v1-organizations--organization_id--members');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-organizations--organization_id--members"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/organizations/{organization_id}/members</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-organizations--organization_id--members"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-organizations--organization_id--members"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-organizations--organization_id--members"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>organization_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="organization_id"                data-endpoint="POSTapi-v1-organizations--organization_id--members"
               value="01k3ahgcgwbgdzwmy8an85afk4"
               data-component="url">
    <br>
<p>The ID of the organization. Example: <code>01k3ahgcgwbgdzwmy8an85afk4</code></p>
            </div>
                    </form>

                    <h2 id="organization-management-PUTapi-v1-organizations--organization_id--members--membership_id-">Update organization membership</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Update an existing organization membership including role assignments</p>

<span id="example-requests-PUTapi-v1-organizations--organization_id--members--membership_id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4/members/01k3ahgfe5595rkvqzvxrdrg5t" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4/members/01k3ahgfe5595rkvqzvxrdrg5t"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "PUT",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-organizations--organization_id--members--membership_id-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Membership updated successfully&quot;,
    &quot;membership&quot;: {
        &quot;id&quot;: 1,
        &quot;status&quot;: &quot;active&quot;
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (404):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Membership not found in this organization&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-PUTapi-v1-organizations--organization_id--members--membership_id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-organizations--organization_id--members--membership_id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-organizations--organization_id--members--membership_id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-organizations--organization_id--members--membership_id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-organizations--organization_id--members--membership_id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-organizations--organization_id--members--membership_id-" data-method="PUT"
      data-path="api/v1/organizations/{organization_id}/members/{membership_id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-organizations--organization_id--members--membership_id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-v1-organizations--organization_id--members--membership_id-"
                    onclick="tryItOut('PUTapi-v1-organizations--organization_id--members--membership_id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-v1-organizations--organization_id--members--membership_id-"
                    onclick="cancelTryOut('PUTapi-v1-organizations--organization_id--members--membership_id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-v1-organizations--organization_id--members--membership_id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/organizations/{organization_id}/members/{membership_id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-v1-organizations--organization_id--members--membership_id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-organizations--organization_id--members--membership_id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-organizations--organization_id--members--membership_id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>organization_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="organization_id"                data-endpoint="PUTapi-v1-organizations--organization_id--members--membership_id-"
               value="01k3ahgcgwbgdzwmy8an85afk4"
               data-component="url">
    <br>
<p>The ID of the organization. Example: <code>01k3ahgcgwbgdzwmy8an85afk4</code></p>
            </div>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>membership_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="membership_id"                data-endpoint="PUTapi-v1-organizations--organization_id--members--membership_id-"
               value="01k3ahgfe5595rkvqzvxrdrg5t"
               data-component="url">
    <br>
<p>The ID of the membership. Example: <code>01k3ahgfe5595rkvqzvxrdrg5t</code></p>
            </div>
                    </form>

                    <h2 id="organization-management-DELETEapi-v1-organizations--organization_id--members--membership_id-">Remove member from organization</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Remove a member from an organization and revoke all associated roles</p>

<span id="example-requests-DELETEapi-v1-organizations--organization_id--members--membership_id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4/members/01k3ahgfe5595rkvqzvxrdrg5t" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4/members/01k3ahgfe5595rkvqzvxrdrg5t"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-organizations--organization_id--members--membership_id-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Member removed successfully&quot;
}</code>
 </pre>
            <blockquote>
            <p>Example response (404):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Membership not found in this organization&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-DELETEapi-v1-organizations--organization_id--members--membership_id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-organizations--organization_id--members--membership_id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-organizations--organization_id--members--membership_id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-organizations--organization_id--members--membership_id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-organizations--organization_id--members--membership_id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-organizations--organization_id--members--membership_id-" data-method="DELETE"
      data-path="api/v1/organizations/{organization_id}/members/{membership_id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-organizations--organization_id--members--membership_id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-v1-organizations--organization_id--members--membership_id-"
                    onclick="tryItOut('DELETEapi-v1-organizations--organization_id--members--membership_id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-v1-organizations--organization_id--members--membership_id-"
                    onclick="cancelTryOut('DELETEapi-v1-organizations--organization_id--members--membership_id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-v1-organizations--organization_id--members--membership_id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/organizations/{organization_id}/members/{membership_id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-organizations--organization_id--members--membership_id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-organizations--organization_id--members--membership_id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-organizations--organization_id--members--membership_id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>organization_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="organization_id"                data-endpoint="DELETEapi-v1-organizations--organization_id--members--membership_id-"
               value="01k3ahgcgwbgdzwmy8an85afk4"
               data-component="url">
    <br>
<p>The ID of the organization. Example: <code>01k3ahgcgwbgdzwmy8an85afk4</code></p>
            </div>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>membership_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="membership_id"                data-endpoint="DELETEapi-v1-organizations--organization_id--members--membership_id-"
               value="01k3ahgfe5595rkvqzvxrdrg5t"
               data-component="url">
    <br>
<p>The ID of the membership. Example: <code>01k3ahgfe5595rkvqzvxrdrg5t</code></p>
            </div>
                    </form>

                    <h2 id="organization-management-GETapi-v1-organizations--organization_id--roles">Get organization roles</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retrieve all roles defined for a specific organization</p>

<span id="example-requests-GETapi-v1-organizations--organization_id--roles">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4/roles" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4/roles"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-organizations--organization_id--roles">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">[
    {
        &quot;id&quot;: 1,
        &quot;name&quot;: &quot;admin&quot;,
        &quot;permissions&quot;: [
            {
                &quot;name&quot;: &quot;organization:admin&quot;
            }
        ]
    },
    {
        &quot;id&quot;: 2,
        &quot;name&quot;: &quot;member&quot;,
        &quot;permissions&quot;: [
            {
                &quot;name&quot;: &quot;organization:read&quot;
            }
        ]
    }
]</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-organizations--organization_id--roles" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-organizations--organization_id--roles"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-organizations--organization_id--roles"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-organizations--organization_id--roles" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-organizations--organization_id--roles">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-organizations--organization_id--roles" data-method="GET"
      data-path="api/v1/organizations/{organization_id}/roles"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-organizations--organization_id--roles', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-organizations--organization_id--roles"
                    onclick="tryItOut('GETapi-v1-organizations--organization_id--roles');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-organizations--organization_id--roles"
                    onclick="cancelTryOut('GETapi-v1-organizations--organization_id--roles');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-organizations--organization_id--roles"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/organizations/{organization_id}/roles</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-organizations--organization_id--roles"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-organizations--organization_id--roles"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-organizations--organization_id--roles"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>organization_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="organization_id"                data-endpoint="GETapi-v1-organizations--organization_id--roles"
               value="01k3ahgcgwbgdzwmy8an85afk4"
               data-component="url">
    <br>
<p>The ID of the organization. Example: <code>01k3ahgcgwbgdzwmy8an85afk4</code></p>
            </div>
                    </form>

                    <h2 id="organization-management-POSTapi-v1-organizations--organization_id--roles">Create organization role</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Create a new role within an organization with specific permissions</p>

<span id="example-requests-POSTapi-v1-organizations--organization_id--roles">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4/roles" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/organizations/01k3ahgcgwbgdzwmy8an85afk4/roles"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "POST",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-organizations--organization_id--roles">
            <blockquote>
            <p>Example response (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Role created successfully&quot;,
    &quot;role&quot;: {
        &quot;id&quot;: 1,
        &quot;name&quot;: &quot;manager&quot;,
        &quot;permissions&quot;: []
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (400):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Role already exists in this organization&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-organizations--organization_id--roles" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-organizations--organization_id--roles"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-organizations--organization_id--roles"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-organizations--organization_id--roles" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-organizations--organization_id--roles">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-organizations--organization_id--roles" data-method="POST"
      data-path="api/v1/organizations/{organization_id}/roles"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-organizations--organization_id--roles', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-organizations--organization_id--roles"
                    onclick="tryItOut('POSTapi-v1-organizations--organization_id--roles');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-organizations--organization_id--roles"
                    onclick="cancelTryOut('POSTapi-v1-organizations--organization_id--roles');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-organizations--organization_id--roles"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/organizations/{organization_id}/roles</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-organizations--organization_id--roles"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-organizations--organization_id--roles"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-organizations--organization_id--roles"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>organization_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="organization_id"                data-endpoint="POSTapi-v1-organizations--organization_id--roles"
               value="01k3ahgcgwbgdzwmy8an85afk4"
               data-component="url">
    <br>
<p>The ID of the organization. Example: <code>01k3ahgcgwbgdzwmy8an85afk4</code></p>
            </div>
                    </form>

                <h1 id="role-permission-management">Role & Permission Management</h1>

    

                                <h2 id="role-permission-management-GETapi-v1-roles">Get roles</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Retrieve a paginated list of roles with their permissions, optionally filtered by organization</p>

<span id="example-requests-GETapi-v1-roles">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/roles?organization_id=1&amp;search=admin&amp;per_page=15" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/roles"
);

const params = {
    "organization_id": "1",
    "search": "admin",
    "per_page": "15",
};
Object.keys(params)
    .forEach(key =&gt; url.searchParams.append(key, params[key]));

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-roles">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;data&quot;: [
        {
            &quot;id&quot;: 1,
            &quot;name&quot;: &quot;admin&quot;,
            &quot;permissions&quot;: [
                {
                    &quot;name&quot;: &quot;organization:admin&quot;
                }
            ]
        },
        {
            &quot;id&quot;: 2,
            &quot;name&quot;: &quot;member&quot;,
            &quot;permissions&quot;: [
                {
                    &quot;name&quot;: &quot;organization:read&quot;
                }
            ]
        }
    ],
    &quot;meta&quot;: {
        &quot;current_page&quot;: 1,
        &quot;total&quot;: 2
    }
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-roles" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-roles"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-roles"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-roles" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-roles">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-roles" data-method="GET"
      data-path="api/v1/roles"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-roles', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-roles"
                    onclick="tryItOut('GETapi-v1-roles');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-roles"
                    onclick="cancelTryOut('GETapi-v1-roles');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-roles"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/roles</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-roles"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-roles"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-roles"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Query Parameters</b></h4>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>organization_id</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="organization_id"                data-endpoint="GETapi-v1-roles"
               value="1"
               data-component="query">
    <br>
<p>Filter by organization/team ID Example: <code>1</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>search</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="search"                data-endpoint="GETapi-v1-roles"
               value="admin"
               data-component="query">
    <br>
<p>Search roles by name Example: <code>admin</code></p>
            </div>
                                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>per_page</code></b>&nbsp;&nbsp;
<small>integer</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="number" style="display: none"
               step="any"               name="per_page"                data-endpoint="GETapi-v1-roles"
               value="15"
               data-component="query">
    <br>
<p>Number of results per page Example: <code>15</code></p>
            </div>
                </form>

                    <h2 id="role-permission-management-POSTapi-v1-roles">Create role</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Create a new role with specified permissions</p>

<span id="example-requests-POSTapi-v1-roles">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request POST \
    "http://localhost:8000/api/v1/roles" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"manager\",
    \"team_id\": 1,
    \"permissions\": [
        \"architecto\"
    ]
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/roles"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "manager",
    "team_id": 1,
    "permissions": [
        "architecto"
    ]
};

fetch(url, {
    method: "POST",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-POSTapi-v1-roles">
            <blockquote>
            <p>Example response (201):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Role created successfully&quot;,
    &quot;role&quot;: {
        &quot;id&quot;: 1,
        &quot;name&quot;: &quot;manager&quot;,
        &quot;permissions&quot;: []
    }
}</code>
 </pre>
            <blockquote>
            <p>Example response (400):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Role already exists for this team/guard combination&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-POSTapi-v1-roles" hidden>
    <blockquote>Received response<span
                id="execution-response-status-POSTapi-v1-roles"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-POSTapi-v1-roles"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-POSTapi-v1-roles" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-POSTapi-v1-roles">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-POSTapi-v1-roles" data-method="POST"
      data-path="api/v1/roles"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('POSTapi-v1-roles', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-POSTapi-v1-roles"
                    onclick="tryItOut('POSTapi-v1-roles');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-POSTapi-v1-roles"
                    onclick="cancelTryOut('POSTapi-v1-roles');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-POSTapi-v1-roles"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-black">POST</small>
            <b><code>api/v1/roles</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="POSTapi-v1-roles"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="POSTapi-v1-roles"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="POSTapi-v1-roles"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="POSTapi-v1-roles"
               value="manager"
               data-component="body">
    <br>
<p>The name of the role. Must not be greater than 255 characters. Example: <code>manager</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>team_id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="team_id"                data-endpoint="POSTapi-v1-roles"
               value="1"
               data-component="body">
    <br>
<p>The organization/team ID this role belongs to (optional). The <code>id</code> of an existing record in the organizations table. Example: <code>1</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>permissions</code></b>&nbsp;&nbsp;
<small>string[]</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="permissions[0]"                data-endpoint="POSTapi-v1-roles"
               data-component="body">
        <input type="text" style="display: none"
               name="permissions[1]"                data-endpoint="POSTapi-v1-roles"
               data-component="body">
    <br>
<p>The <code>name</code> of an existing record in the sys_permissions table.</p>
        </div>
        </form>

                    <h2 id="role-permission-management-GETapi-v1-roles--id-">Display the specified resource.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-roles--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/roles/01k3ahgcrh158fp8ze1zv7v9dm" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/roles/01k3ahgcrh158fp8ze1zv7v9dm"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-roles--id-">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
    &quot;team_id&quot;: &quot;01k3ahgcgwbgdzwmy8an85afk4&quot;,
    &quot;name&quot;: &quot;Super Admin&quot;,
    &quot;guard_name&quot;: &quot;web&quot;,
    &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
    &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
    &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
    &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
    &quot;permissions&quot;: [
        {
            &quot;id&quot;: &quot;01k3ahgch7wxbk8trvdjkxxy0n&quot;,
            &quot;name&quot;: &quot;view users&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgch7wxbk8trvdjkxxy0n&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgchrfcmxgmy2mjjzvxv4&quot;,
            &quot;name&quot;: &quot;create users&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgchrfcmxgmy2mjjzvxv4&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcj3dycqhbqc46f6c2r3&quot;,
            &quot;name&quot;: &quot;edit users&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcj3dycqhbqc46f6c2r3&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcjde0khrayg497ayfvg&quot;,
            &quot;name&quot;: &quot;delete users&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcjde0khrayg497ayfvg&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcjpdy8gs9q47yyysgpc&quot;,
            &quot;name&quot;: &quot;view organizations&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcjpdy8gs9q47yyysgpc&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcjz3g809h3v9jgccnxr&quot;,
            &quot;name&quot;: &quot;create organizations&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcjz3g809h3v9jgccnxr&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgck9tahg0z3nb9t11cwy&quot;,
            &quot;name&quot;: &quot;edit organizations&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgck9tahg0z3nb9t11cwy&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgckj32svw6gzzhyz6q43&quot;,
            &quot;name&quot;: &quot;delete organizations&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgckj32svw6gzzhyz6q43&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgckvs01gzba6nxk8ghh5&quot;,
            &quot;name&quot;: &quot;view organization units&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgckvs01gzba6nxk8ghh5&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcm3q6gh2yja4pcmt1wn&quot;,
            &quot;name&quot;: &quot;create organization units&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcm3q6gh2yja4pcmt1wn&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcmca4hhfswc5n0bwd54&quot;,
            &quot;name&quot;: &quot;edit organization units&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcmca4hhfswc5n0bwd54&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcmnq9zh4b6sbzpra8kd&quot;,
            &quot;name&quot;: &quot;delete organization units&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcmnq9zh4b6sbzpra8kd&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcmynqvax2z5jbapbqm5&quot;,
            &quot;name&quot;: &quot;view organization positions&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcmynqvax2z5jbapbqm5&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcn7dg8s2eeqjb41krdr&quot;,
            &quot;name&quot;: &quot;create organization positions&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcn7dg8s2eeqjb41krdr&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcnf9ga2jgnszt3ywwbe&quot;,
            &quot;name&quot;: &quot;edit organization positions&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcnf9ga2jgnszt3ywwbe&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcnrj2se0c0cmwj5wkbf&quot;,
            &quot;name&quot;: &quot;delete organization positions&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcnrj2se0c0cmwj5wkbf&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcp0cwgczct5f6983rk8&quot;,
            &quot;name&quot;: &quot;view organization memberships&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcp0cwgczct5f6983rk8&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcp99x5zsa9fb6w8em0n&quot;,
            &quot;name&quot;: &quot;create organization memberships&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcp99x5zsa9fb6w8em0n&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcpj0vxzncc76qd62enp&quot;,
            &quot;name&quot;: &quot;edit organization memberships&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcpj0vxzncc76qd62enp&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcpvvshkcemzfcxetpay&quot;,
            &quot;name&quot;: &quot;delete organization memberships&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcpvvshkcemzfcxetpay&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcq4a8yc6gs9ytw04rhz&quot;,
            &quot;name&quot;: &quot;view system settings&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcq4a8yc6gs9ytw04rhz&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcqd27c9kbhsxd3wbewg&quot;,
            &quot;name&quot;: &quot;edit system settings&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcqd27c9kbhsxd3wbewg&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcqntpr39b0wak3d5a3z&quot;,
            &quot;name&quot;: &quot;view audit logs&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcqntpr39b0wak3d5a3z&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcqywx6e2aq65va4hkfm&quot;,
            &quot;name&quot;: &quot;manage roles&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcqywx6e2aq65va4hkfm&quot;
            }
        },
        {
            &quot;id&quot;: &quot;01k3ahgcr6nxx4h59t6bc9xyx5&quot;,
            &quot;name&quot;: &quot;manage permissions&quot;,
            &quot;guard_name&quot;: &quot;web&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;updated_by&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;pivot&quot;: {
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;permission_id&quot;: &quot;01k3ahgcr6nxx4h59t6bc9xyx5&quot;
            }
        }
    ],
    &quot;users&quot;: [
        {
            &quot;id&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;,
            &quot;name&quot;: &quot;Test User&quot;,
            &quot;email&quot;: &quot;test@example.com&quot;,
            &quot;email_verified_at&quot;: &quot;2025-08-23T04:03:09.000000Z&quot;,
            &quot;created_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;updated_at&quot;: &quot;2025-08-22T21:03:09.000000Z&quot;,
            &quot;created_by&quot;: null,
            &quot;updated_by&quot;: null,
            &quot;pivot&quot;: {
                &quot;model_type&quot;: &quot;App\\Models\\User&quot;,
                &quot;role_id&quot;: &quot;01k3ahgcrh158fp8ze1zv7v9dm&quot;,
                &quot;model_id&quot;: &quot;01k3ahgcdg9dzsh45sdpbcff64&quot;
            }
        }
    ]
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-roles--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-roles--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-roles--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-roles--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-roles--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-roles--id-" data-method="GET"
      data-path="api/v1/roles/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-roles--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-roles--id-"
                    onclick="tryItOut('GETapi-v1-roles--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-roles--id-"
                    onclick="cancelTryOut('GETapi-v1-roles--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-roles--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/roles/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-roles--id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-roles--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-roles--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="GETapi-v1-roles--id-"
               value="01k3ahgcrh158fp8ze1zv7v9dm"
               data-component="url">
    <br>
<p>The ID of the role. Example: <code>01k3ahgcrh158fp8ze1zv7v9dm</code></p>
            </div>
                    </form>

                    <h2 id="role-permission-management-PUTapi-v1-roles--id-">Update the specified resource in storage.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PUTapi-v1-roles--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:8000/api/v1/roles/01k3ahgcrh158fp8ze1zv7v9dm" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"name\": \"manager\",
    \"permissions\": [
        \"architecto\"
    ]
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/roles/01k3ahgcrh158fp8ze1zv7v9dm"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "name": "manager",
    "permissions": [
        "architecto"
    ]
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-roles--id-">
</span>
<span id="execution-results-PUTapi-v1-roles--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-roles--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-roles--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-roles--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-roles--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-roles--id-" data-method="PUT"
      data-path="api/v1/roles/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-roles--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-v1-roles--id-"
                    onclick="tryItOut('PUTapi-v1-roles--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-v1-roles--id-"
                    onclick="cancelTryOut('PUTapi-v1-roles--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-v1-roles--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/roles/{id}</code></b>
        </p>
            <p>
            <small class="badge badge-purple">PATCH</small>
            <b><code>api/v1/roles/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-v1-roles--id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-roles--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-roles--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="PUTapi-v1-roles--id-"
               value="01k3ahgcrh158fp8ze1zv7v9dm"
               data-component="url">
    <br>
<p>The ID of the role. Example: <code>01k3ahgcrh158fp8ze1zv7v9dm</code></p>
            </div>
                            <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>name</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="name"                data-endpoint="PUTapi-v1-roles--id-"
               value="manager"
               data-component="body">
    <br>
<p>The name of the role. Must not be greater than 255 characters. Example: <code>manager</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>permissions</code></b>&nbsp;&nbsp;
<small>string[]</small>&nbsp;
<i>optional</i> &nbsp;
                <input type="text" style="display: none"
                              name="permissions[0]"                data-endpoint="PUTapi-v1-roles--id-"
               data-component="body">
        <input type="text" style="display: none"
               name="permissions[1]"                data-endpoint="PUTapi-v1-roles--id-"
               data-component="body">
    <br>
<p>The <code>name</code> of an existing record in the sys_permissions table.</p>
        </div>
        </form>

                    <h2 id="role-permission-management-DELETEapi-v1-roles--id-">Remove the specified resource from storage.</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-DELETEapi-v1-roles--id-">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:8000/api/v1/roles/01k3ahgcrh158fp8ze1zv7v9dm" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/roles/01k3ahgcrh158fp8ze1zv7v9dm"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "DELETE",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-roles--id-">
</span>
<span id="execution-results-DELETEapi-v1-roles--id-" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-roles--id-"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-roles--id-"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-roles--id-" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-roles--id-">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-roles--id-" data-method="DELETE"
      data-path="api/v1/roles/{id}"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-roles--id-', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-v1-roles--id-"
                    onclick="tryItOut('DELETEapi-v1-roles--id-');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-v1-roles--id-"
                    onclick="cancelTryOut('DELETEapi-v1-roles--id-');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-v1-roles--id-"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/roles/{id}</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-roles--id-"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-roles--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-roles--id-"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        <h4 class="fancy-heading-panel"><b>URL Parameters</b></h4>
                    <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>id</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="id"                data-endpoint="DELETEapi-v1-roles--id-"
               value="01k3ahgcrh158fp8ze1zv7v9dm"
               data-component="url">
    <br>
<p>The ID of the role. Example: <code>01k3ahgcrh158fp8ze1zv7v9dm</code></p>
            </div>
                    </form>

                    <h2 id="role-permission-management-GETapi-v1-permissions">Get all available permissions</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-permissions">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/permissions" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/permissions"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-permissions">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">[
    {
        &quot;id&quot;: &quot;01k3ahgcp99x5zsa9fb6w8em0n&quot;,
        &quot;name&quot;: &quot;create organization memberships&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgcn7dg8s2eeqjb41krdr&quot;,
        &quot;name&quot;: &quot;create organization positions&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgcjz3g809h3v9jgccnxr&quot;,
        &quot;name&quot;: &quot;create organizations&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgcm3q6gh2yja4pcmt1wn&quot;,
        &quot;name&quot;: &quot;create organization units&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgchrfcmxgmy2mjjzvxv4&quot;,
        &quot;name&quot;: &quot;create users&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgcpvvshkcemzfcxetpay&quot;,
        &quot;name&quot;: &quot;delete organization memberships&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgcnrj2se0c0cmwj5wkbf&quot;,
        &quot;name&quot;: &quot;delete organization positions&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgckj32svw6gzzhyz6q43&quot;,
        &quot;name&quot;: &quot;delete organizations&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgcmnq9zh4b6sbzpra8kd&quot;,
        &quot;name&quot;: &quot;delete organization units&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgcjde0khrayg497ayfvg&quot;,
        &quot;name&quot;: &quot;delete users&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgcpj0vxzncc76qd62enp&quot;,
        &quot;name&quot;: &quot;edit organization memberships&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgcnf9ga2jgnszt3ywwbe&quot;,
        &quot;name&quot;: &quot;edit organization positions&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgck9tahg0z3nb9t11cwy&quot;,
        &quot;name&quot;: &quot;edit organizations&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgcmca4hhfswc5n0bwd54&quot;,
        &quot;name&quot;: &quot;edit organization units&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgcqd27c9kbhsxd3wbewg&quot;,
        &quot;name&quot;: &quot;edit system settings&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgcj3dycqhbqc46f6c2r3&quot;,
        &quot;name&quot;: &quot;edit users&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgcr6nxx4h59t6bc9xyx5&quot;,
        &quot;name&quot;: &quot;manage permissions&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgcqywx6e2aq65va4hkfm&quot;,
        &quot;name&quot;: &quot;manage roles&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgcqntpr39b0wak3d5a3z&quot;,
        &quot;name&quot;: &quot;view audit logs&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgcp0cwgczct5f6983rk8&quot;,
        &quot;name&quot;: &quot;view organization memberships&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgcmynqvax2z5jbapbqm5&quot;,
        &quot;name&quot;: &quot;view organization positions&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgcjpdy8gs9q47yyysgpc&quot;,
        &quot;name&quot;: &quot;view organizations&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgckvs01gzba6nxk8ghh5&quot;,
        &quot;name&quot;: &quot;view organization units&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgcq4a8yc6gs9ytw04rhz&quot;,
        &quot;name&quot;: &quot;view system settings&quot;
    },
    {
        &quot;id&quot;: &quot;01k3ahgch7wxbk8trvdjkxxy0n&quot;,
        &quot;name&quot;: &quot;view users&quot;
    }
]</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-permissions" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-permissions"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-permissions"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-permissions" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-permissions">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-permissions" data-method="GET"
      data-path="api/v1/permissions"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-permissions', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-permissions"
                    onclick="tryItOut('GETapi-v1-permissions');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-permissions"
                    onclick="cancelTryOut('GETapi-v1-permissions');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-permissions"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/permissions</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-permissions"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-permissions"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-permissions"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                <h1 id="user-security">User Security</h1>

    

                                <h2 id="user-security-GETapi-v1-security">Get security profile</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Get a comprehensive security profile for the authenticated user including authentication methods, security score, and recommendations</p>

<span id="example-requests-GETapi-v1-security">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/security" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/security"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-security">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;user&quot;: {
        &quot;id&quot;: 1,
        &quot;name&quot;: &quot;John Doe&quot;,
        &quot;email&quot;: &quot;john@example.com&quot;
    },
    &quot;authentication_methods&quot;: {
        &quot;password&quot;: {
            &quot;enabled&quot;: true
        },
        &quot;mfa&quot;: {
            &quot;enabled&quot;: true,
            &quot;totp_enabled&quot;: true
        },
        &quot;webauthn&quot;: {
            &quot;enabled&quot;: true,
            &quot;passkey_count&quot;: 2
        }
    },
    &quot;security_score&quot;: 85,
    &quot;recommendations&quot;: []
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-security" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-security"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-security"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-security" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-security">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-security" data-method="GET"
      data-path="api/v1/security"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-security', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-security"
                    onclick="tryItOut('GETapi-v1-security');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-security"
                    onclick="cancelTryOut('GETapi-v1-security');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-security"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/security</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-security"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-security"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-security"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="user-security-GETapi-v1-security-activity">Get security activity and audit log</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-security-activity">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/security/activity" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/security/activity"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-security-activity">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-security-activity" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-security-activity"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-security-activity"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-security-activity" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-security-activity">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-security-activity" data-method="GET"
      data-path="api/v1/security/activity"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-security-activity', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-security-activity"
                    onclick="tryItOut('GETapi-v1-security-activity');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-security-activity"
                    onclick="cancelTryOut('GETapi-v1-security-activity');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-security-activity"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/security/activity</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-security-activity"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-security-activity"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-security-activity"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="user-security-GETapi-v1-security-recommendations">Get account security recommendations</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-security-recommendations">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/security/recommendations" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/security/recommendations"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-security-recommendations">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-security-recommendations" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-security-recommendations"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-security-recommendations"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-security-recommendations" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-security-recommendations">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-security-recommendations" data-method="GET"
      data-path="api/v1/security/recommendations"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-security-recommendations', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-security-recommendations"
                    onclick="tryItOut('GETapi-v1-security-recommendations');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-security-recommendations"
                    onclick="cancelTryOut('GETapi-v1-security-recommendations');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-security-recommendations"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/security/recommendations</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-security-recommendations"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-security-recommendations"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-security-recommendations"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="user-security-GETapi-v1-security-settings">Get security settings summary</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-security-settings">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/security/settings" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/security/settings"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-security-settings">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-security-settings" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-security-settings"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-security-settings"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-security-settings" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-security-settings">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-security-settings" data-method="GET"
      data-path="api/v1/security/settings"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-security-settings', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-security-settings"
                    onclick="tryItOut('GETapi-v1-security-settings');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-security-settings"
                    onclick="cancelTryOut('GETapi-v1-security-settings');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-security-settings"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/security/settings</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-security-settings"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-security-settings"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-security-settings"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="user-security-PUTapi-v1-security-settings">Update security settings</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-PUTapi-v1-security-settings">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:8000/api/v1/security/settings" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"mfa_required\": false,
    \"password\": \"password123\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/security/settings"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "mfa_required": false,
    "password": "password123"
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-security-settings">
</span>
<span id="execution-results-PUTapi-v1-security-settings" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-security-settings"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-security-settings"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-security-settings" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-security-settings">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-security-settings" data-method="PUT"
      data-path="api/v1/security/settings"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-security-settings', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-v1-security-settings"
                    onclick="tryItOut('PUTapi-v1-security-settings');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-v1-security-settings"
                    onclick="cancelTryOut('PUTapi-v1-security-settings');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-v1-security-settings"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/security/settings</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-v1-security-settings"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-security-settings"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-security-settings"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>mfa_required</code></b>&nbsp;&nbsp;
<small>boolean</small>&nbsp;
<i>optional</i> &nbsp;
                <label data-endpoint="PUTapi-v1-security-settings" style="display: none">
            <input type="radio" name="mfa_required"
                   value="true"
                   data-endpoint="PUTapi-v1-security-settings"
                   data-component="body"             >
            <code>true</code>
        </label>
        <label data-endpoint="PUTapi-v1-security-settings" style="display: none">
            <input type="radio" name="mfa_required"
                   value="false"
                   data-endpoint="PUTapi-v1-security-settings"
                   data-component="body"             >
            <code>false</code>
        </label>
    <br>
<p>Whether MFA should be required for this user. Example: <code>false</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="PUTapi-v1-security-settings"
               value="password123"
               data-component="body">
    <br>
<p>User current password for verification. Example: <code>password123</code></p>
        </div>
        </form>

                    <h2 id="user-security-PUTapi-v1-security-password">Update password</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>

<p>Update the user's password with current password verification</p>

<span id="example-requests-PUTapi-v1-security-password">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request PUT \
    "http://localhost:8000/api/v1/security/password" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"current_password\": \"current-password-123\",
    \"password\": \"new-secure-password-456\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/security/password"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "current_password": "current-password-123",
    "password": "new-secure-password-456"
};

fetch(url, {
    method: "PUT",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-PUTapi-v1-security-password">
            <blockquote>
            <p>Example response (200):</p>
        </blockquote>
                <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Password updated successfully&quot;,
    &quot;updated_at&quot;: &quot;2024-01-15T10:30:00Z&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-PUTapi-v1-security-password" hidden>
    <blockquote>Received response<span
                id="execution-response-status-PUTapi-v1-security-password"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-PUTapi-v1-security-password"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-PUTapi-v1-security-password" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-PUTapi-v1-security-password">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-PUTapi-v1-security-password" data-method="PUT"
      data-path="api/v1/security/password"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('PUTapi-v1-security-password', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-PUTapi-v1-security-password"
                    onclick="tryItOut('PUTapi-v1-security-password');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-PUTapi-v1-security-password"
                    onclick="cancelTryOut('PUTapi-v1-security-password');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-PUTapi-v1-security-password"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-darkblue">PUT</small>
            <b><code>api/v1/security/password</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="PUTapi-v1-security-password"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="PUTapi-v1-security-password"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="PUTapi-v1-security-password"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>current_password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="current_password"                data-endpoint="PUTapi-v1-security-password"
               value="current-password-123"
               data-component="body">
    <br>
<p>The user's current password for verification. Example: <code>current-password-123</code></p>
        </div>
                <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="PUTapi-v1-security-password"
               value="new-secure-password-456"
               data-component="body">
    <br>
<p>The new password (must meet security requirements). Example: <code>new-secure-password-456</code></p>
        </div>
        </form>

                    <h2 id="user-security-GETapi-v1-security-sessions">Get current active sessions</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-GETapi-v1-security-sessions">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request GET \
    --get "http://localhost:8000/api/v1/security/sessions" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json"</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/security/sessions"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers,
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-GETapi-v1-security-sessions">
            <blockquote>
            <p>Example response (401):</p>
        </blockquote>
                <details class="annotation">
            <summary style="cursor: pointer;">
                <small onclick="textContent = parentElement.parentElement.open ? 'Show headers' : 'Hide headers'">Show headers</small>
            </summary>
            <pre><code class="language-http">cache-control: no-cache, private
content-type: application/json
access-control-allow-origin: *
 </code></pre></details>         <pre>

<code class="language-json" style="max-height: 300px;">{
    &quot;message&quot;: &quot;Unauthenticated.&quot;
}</code>
 </pre>
    </span>
<span id="execution-results-GETapi-v1-security-sessions" hidden>
    <blockquote>Received response<span
                id="execution-response-status-GETapi-v1-security-sessions"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-GETapi-v1-security-sessions"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-GETapi-v1-security-sessions" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-GETapi-v1-security-sessions">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-GETapi-v1-security-sessions" data-method="GET"
      data-path="api/v1/security/sessions"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('GETapi-v1-security-sessions', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-GETapi-v1-security-sessions"
                    onclick="tryItOut('GETapi-v1-security-sessions');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-GETapi-v1-security-sessions"
                    onclick="cancelTryOut('GETapi-v1-security-sessions');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-GETapi-v1-security-sessions"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-green">GET</small>
            <b><code>api/v1/security/sessions</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="GETapi-v1-security-sessions"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="GETapi-v1-security-sessions"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="GETapi-v1-security-sessions"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                        </form>

                    <h2 id="user-security-DELETEapi-v1-security-sessions">Revoke all sessions except current</h2>

<p>
<small class="badge badge-darkred">requires authentication</small>
</p>



<span id="example-requests-DELETEapi-v1-security-sessions">
<blockquote>Example request:</blockquote>


<div class="bash-example">
    <pre><code class="language-bash">curl --request DELETE \
    "http://localhost:8000/api/v1/security/sessions" \
    --header "Authorization: Bearer {YOUR_AUTH_KEY}" \
    --header "Content-Type: application/json" \
    --header "Accept: application/json" \
    --data "{
    \"password\": \"password123\"
}"
</code></pre></div>


<div class="javascript-example">
    <pre><code class="language-javascript">const url = new URL(
    "http://localhost:8000/api/v1/security/sessions"
);

const headers = {
    "Authorization": "Bearer {YOUR_AUTH_KEY}",
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "password": "password123"
};

fetch(url, {
    method: "DELETE",
    headers,
    body: JSON.stringify(body),
}).then(response =&gt; response.json());</code></pre></div>

</span>

<span id="example-responses-DELETEapi-v1-security-sessions">
</span>
<span id="execution-results-DELETEapi-v1-security-sessions" hidden>
    <blockquote>Received response<span
                id="execution-response-status-DELETEapi-v1-security-sessions"></span>:
    </blockquote>
    <pre class="json"><code id="execution-response-content-DELETEapi-v1-security-sessions"
      data-empty-response-text="<Empty response>" style="max-height: 400px;"></code></pre>
</span>
<span id="execution-error-DELETEapi-v1-security-sessions" hidden>
    <blockquote>Request failed with error:</blockquote>
    <pre><code id="execution-error-message-DELETEapi-v1-security-sessions">

Tip: Check that you&#039;re properly connected to the network.
If you&#039;re a maintainer of ths API, verify that your API is running and you&#039;ve enabled CORS.
You can check the Dev Tools console for debugging information.</code></pre>
</span>
<form id="form-DELETEapi-v1-security-sessions" data-method="DELETE"
      data-path="api/v1/security/sessions"
      data-authed="1"
      data-hasfiles="0"
      data-isarraybody="0"
      autocomplete="off"
      onsubmit="event.preventDefault(); executeTryOut('DELETEapi-v1-security-sessions', this);">
    <h3>
        Request&nbsp;&nbsp;&nbsp;
                    <button type="button"
                    style="background-color: #8fbcd4; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-tryout-DELETEapi-v1-security-sessions"
                    onclick="tryItOut('DELETEapi-v1-security-sessions');">Try it out ⚡
            </button>
            <button type="button"
                    style="background-color: #c97a7e; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-canceltryout-DELETEapi-v1-security-sessions"
                    onclick="cancelTryOut('DELETEapi-v1-security-sessions');" hidden>Cancel 🛑
            </button>&nbsp;&nbsp;
            <button type="submit"
                    style="background-color: #6ac174; padding: 5px 10px; border-radius: 5px; border-width: thin;"
                    id="btn-executetryout-DELETEapi-v1-security-sessions"
                    data-initial-text="Send Request 💥"
                    data-loading-text="⏱ Sending..."
                    hidden>Send Request 💥
            </button>
            </h3>
            <p>
            <small class="badge badge-red">DELETE</small>
            <b><code>api/v1/security/sessions</code></b>
        </p>
                <h4 class="fancy-heading-panel"><b>Headers</b></h4>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Authorization</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Authorization" class="auth-value"               data-endpoint="DELETEapi-v1-security-sessions"
               value="Bearer {YOUR_AUTH_KEY}"
               data-component="header">
    <br>
<p>Example: <code>Bearer {YOUR_AUTH_KEY}</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Content-Type</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Content-Type"                data-endpoint="DELETEapi-v1-security-sessions"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <div style="padding-left: 28px; clear: unset;">
                <b style="line-height: 2;"><code>Accept</code></b>&nbsp;&nbsp;
&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="Accept"                data-endpoint="DELETEapi-v1-security-sessions"
               value="application/json"
               data-component="header">
    <br>
<p>Example: <code>application/json</code></p>
            </div>
                                <h4 class="fancy-heading-panel"><b>Body Parameters</b></h4>
        <div style=" padding-left: 28px;  clear: unset;">
            <b style="line-height: 2;"><code>password</code></b>&nbsp;&nbsp;
<small>string</small>&nbsp;
 &nbsp;
                <input type="text" style="display: none"
                              name="password"                data-endpoint="DELETEapi-v1-security-sessions"
               value="password123"
               data-component="body">
    <br>
<p>User current password for verification before revoking sessions. Example: <code>password123</code></p>
        </div>
        </form>

            

        
    </div>
    <div class="dark-box">
                    <div class="lang-selector">
                                                        <button type="button" class="lang-button" data-language-name="bash">bash</button>
                                                        <button type="button" class="lang-button" data-language-name="javascript">javascript</button>
                            </div>
            </div>
</div>
</body>
</html>
