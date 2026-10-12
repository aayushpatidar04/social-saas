<?php

/*
|--------------------------------------------------------------------------
| Integration providers
|--------------------------------------------------------------------------
|
| Describes every third-party app whose credentials are stored in the database
| (table `platform_credentials`) instead of .env. The super admin UI and the
| organization "Integrations" page are rendered from this schema, so adding a
| provider or a field is a config change, not a code change.
|
|   secret   => never sent back to the browser, blank on update = keep current
|   default  => used when the field is empty
|   groups   => ["any one of these field sets must be complete"]
|   required => every one of these fields must be filled
|
*/

return [

    'providers' => [

        'meta' => [
            'label' => 'Meta (Facebook & Instagram)',
            'description' => 'One Meta app serves both. Fill the Facebook pair to let users connect Pages (and the Instagram accounts linked to them). '
                . 'Fill the Instagram pair to let users connect an Instagram professional account directly, without a Facebook Page.',
            'platforms' => ['facebook', 'instagram'],
            'has_webhook' => true,
            'byo_allowed' => true,
            'groups' => [
                ['facebook_app_id', 'facebook_app_secret'],
                ['instagram_app_id', 'instagram_app_secret'],
            ],
            'fields' => [
                'facebook_app_id' => ['label' => 'Facebook App ID', 'secret' => false,
                    'help' => 'App Dashboard → App settings → Basic → App ID'],
                'facebook_app_secret' => ['label' => 'Facebook App Secret', 'secret' => true,
                    'help' => 'App Dashboard → App settings → Basic → App secret'],
                'instagram_app_id' => ['label' => 'Instagram App ID', 'secret' => false,
                    'help' => 'App Dashboard → Instagram → API setup with Instagram login → Business login settings. This is NOT the Facebook App ID.'],
                'instagram_app_secret' => ['label' => 'Instagram App Secret', 'secret' => true,
                    'help' => 'Same screen as the Instagram App ID.'],
                'webhook_verify_token' => ['label' => 'Webhook verify token', 'secret' => true, 'generate' => true,
                    'help' => 'Any string you choose. Paste the same value into the Meta webhook configuration. Left blank, one is generated.'],
                'graph_version' => ['label' => 'Graph API version', 'secret' => false, 'default' => 'v25.0'],
                'facebook_scopes' => ['label' => 'Facebook Login permissions', 'secret' => false,
                    'default' => 'email,public_profile,pages_show_list,pages_read_engagement,pages_read_user_content,pages_manage_engagement,pages_manage_metadata,instagram_basic,instagram_manage_comments,business_management',
                    'help' => 'Comma separated. Change only if your Meta app review approved a different set.'],
                'instagram_scopes' => ['label' => 'Instagram Login permissions', 'secret' => false,
                    'default' => 'instagram_business_basic,instagram_business_manage_comments',
                    'help' => 'Comma separated. Add instagram_business_manage_messages / instagram_business_content_publish only if approved.'],
            ],
        ],

        'youtube' => [
            'label' => 'YouTube (Google)',
            'description' => 'Google Cloud OAuth client with the YouTube Data API enabled.',
            'platforms' => ['youtube'],
            'has_webhook' => false,
            'byo_allowed' => true,
            'required' => ['client_id', 'client_secret'],
            'fields' => [
                'client_id' => ['label' => 'Client ID', 'secret' => false],
                'client_secret' => ['label' => 'Client secret', 'secret' => true],
                'redirect_uri' => ['label' => 'Redirect URI override', 'secret' => false,
                    'help' => 'Leave blank to use the URL shown below.'],
            ],
        ],

        'twitter' => [
            'label' => 'X (Twitter)',
            'description' => 'X developer portal OAuth 2.0 app (confidential client).',
            'platforms' => ['twitter'],
            'has_webhook' => false,
            'byo_allowed' => true,
            'required' => ['client_id', 'client_secret'],
            'fields' => [
                'client_id' => ['label' => 'Client ID', 'secret' => false],
                'client_secret' => ['label' => 'Client secret', 'secret' => true],
                'redirect_uri' => ['label' => 'Redirect URI override', 'secret' => false],
            ],
        ],

        'linkedin' => [
            'label' => 'LinkedIn',
            'description' => 'LinkedIn developer app.',
            'platforms' => ['linkedin'],
            'has_webhook' => false,
            'byo_allowed' => true,
            'required' => ['client_id', 'client_secret'],
            'fields' => [
                'client_id' => ['label' => 'Client ID', 'secret' => false],
                'client_secret' => ['label' => 'Client secret', 'secret' => true],
                'redirect_uri' => ['label' => 'Redirect URI override', 'secret' => false],
            ],
        ],

        'openai' => [
            'label' => 'OpenAI',
            'description' => 'Used for comment analysis and replies when an Ollama server is not configured.',
            'platforms' => [],
            'has_webhook' => false,
            'byo_allowed' => true,
            'required' => ['api_key'],
            'fields' => [
                'api_key' => ['label' => 'API key', 'secret' => true],
            ],
        ],

        'ollama' => [
            'label' => 'Ollama (self-hosted AI)',
            'description' => 'Server used for analysis, replies and knowledge-base embeddings.',
            'platforms' => [],
            'has_webhook' => false,
            'byo_allowed' => false, // infrastructure, not tenant-owned
            'required' => ['base_url'],
            'fields' => [
                'base_url' => ['label' => 'Server URL', 'secret' => false, 'default' => 'http://localhost:11434'],
                'model' => ['label' => 'Chat model', 'secret' => false, 'default' => 'gemma2:2b'],
                'timeout' => ['label' => 'Timeout (seconds)', 'secret' => false, 'default' => '120'],
                'embedding_model' => ['label' => 'Embedding model', 'secret' => false, 'default' => 'nomic-embed-text'],
            ],
        ],
    ],

    // social platform  => credential provider that serves it
    'platform_provider' => [
        'facebook' => 'meta',
        'instagram' => 'meta',
        'youtube' => 'youtube',
        'twitter' => 'twitter',
        'linkedin' => 'linkedin',
    ],

    // How long a Facebook page-selection step stays valid (minutes).
    'pending_selection_ttl' => 15,
];
