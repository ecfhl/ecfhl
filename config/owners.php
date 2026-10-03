<?php
return [
 'google_client_id'=>env('GOOGLE_CLIENT_ID'),
 'google_client_secret'=>env('GOOGLE_CLIENT_SECRET'),
 'google_redirect_uri'=>env('GOOGLE_REDIRECT_URI','https://ecfhl.win/auth/google/callback'),
 'admin_invite_token'=>env('ECFHL_ADMIN_INVITE_TOKEN'),
 'admin_invite_expires'=>env('ECFHL_ADMIN_INVITE_EXPIRES'),
];
