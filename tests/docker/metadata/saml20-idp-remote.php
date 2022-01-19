<?php

$metadata['https://ratelimit.local.stack-dev.cirrusidentity.com/simplesaml/saml2/idp/metadata.php'] = [
    'metadata-set' => 'saml20-idp-hosted',
    'entityid' => 'https://ratelimit.local.stack-dev.cirrusidentity.com/simplesaml/saml2/idp/metadata.php',
    'SingleSignOnService' => [
        [
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            'Location' => 'https://ratelimit.local.stack-dev.cirrusidentity.com/simplesaml/saml2/idp/SSOService.php',
        ],
    ],
    'SingleLogoutService' => [
        [
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            'Location' => 'https://ratelimit.local.stack-dev.cirrusidentity.com/simplesaml/saml2/idp/SingleLogoutService.php',
        ],
    ],
    'NameIDFormat' => [
        'urn:oasis:names:tc:SAML:2.0:nameid-format:transient',
    ],
    'certData' => 'MIIFtTCCA52gAwIBAgIJANUBCWQYFVyIMA0GCSqGSIb3DQEBBQUAMEUxCzAJBgNVBAYTAkFVMRMwEQYDVQQIEwpTb21lLVN0YXRlMSEwHwYDVQQKExhJbnRlcm5ldCBXaWRnaXRzIFB0eSBMdGQwHhcNMTYwNTE4MTY1NzI5WhcNMjYwNTE4MTY1NzI5WjBFMQswCQYDVQQGEwJBVTETMBEGA1UECBMKU29tZS1TdGF0ZTEhMB8GA1UEChMYSW50ZXJuZXQgV2lkZ2l0cyBQdHkgTHRkMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAsU+HGLvIwnvpBO/zAqHSbBGa7BPhjAZ45vAeSeV0Yt5rTjvkV+3zP0LUIYRBQ/+iU7A5mqYI3k+/TYxgGgEiUCsS3G4RSmQzFsrs0nWPoaDnkT+yM7DBf7SqQQIONNPUK5e1VETRsAVXZ9X5dvGYFKFCRHXso+dWywlM0M/oY7a6ZxcC3ybXass4CEvLBCQMJr71Psa4S1xWBV/B3ia2QwOv6vC4v/FKXrgUwHeJrIpHWlUF1tanD8E3SLOD0WhenJQM6a37eSCcnscBGDSQu6UUg3qflcApdvjr11f9Cf+s+u/Ud4ZssRFm8e8/LN9W8pfKDie7W5hJwUj2D59uuTCweZKmTBXqRc/bx+7U05ed9/jzUsApOzjG3hgSIJMmBEbGQjR/MlEaMBAZNVrhu0xQycQTrcDmO6nflvJvGgQQwH9Domw3WNfngb0fZ7bmgSBRLXcCFoWVq8T6btbi9bjLujZsBlu5419hNYOMhgL2pvDbhVnrzMZy8O2fNNFXcAu9OY1zKe1F1fVeqGtGydf26PYvCGdvHNMSinV/RQmMdhJ86DP3XaLv6zp8bnCYAxpuTdFatVag5hyfmIqvfSpN9e6Al3wKG8GGg3/RvZR7CtGlVpYOYHiKnDFvgWN1HWktWUNFHewYhnv0ZNPDqCOvc9fzYdvQRB9hCIux8E0CAwEAAaOBpzCBpDAdBgNVHQ4EFgQURgq/i7NXzIadL1PL4zFhtThYVIAwdQYDVR0jBG4wbIAURgq/i7NXzIadL1PL4zFhtThYVIChSaRHMEUxCzAJBgNVBAYTAkFVMRMwEQYDVQQIEwpTb21lLVN0YXRlMSEwHwYDVQQKExhJbnRlcm5ldCBXaWRnaXRzIFB0eSBMdGSCCQDVAQlkGBVciDAMBgNVHRMEBTADAQH/MA0GCSqGSIb3DQEBBQUAA4ICAQAcaPyER2Wlux9Qg4RLb4CCVscS+G37UU5WAJ8FfKiPqGvjyZyEXbxk4WByVfuPAStDHe4TnohEB0l+b/cxa0S0eC0WfXZHt5H2uvRqrPW78+NHO+ze0JvVseBBxlHxKg7yKpw/Vv4LR2uab00RUf0/vlznc/v5EYKSbiKFjpXN7YXJ09VSV859vwqQJOJ0WXxtqxmCwMLZ/0vYRjgVKfCEJPBHJMZ9Z4zd4PVHjk6pcuqSL3F4lC80ztmEa9JocQQe52XsVgAJHGOnsaQ84pZTwrTd3XO9vNJzCJVUEm6Gj6xLKB/RsHNnPm6r48alOySrBGLcSc492zhJNbTjGtuTjhypXqlhoH2dABB5fQF9w8Kvj+yRyQt5sy0GBrsP6yH7EWNgJS9BFrIOzPD3zqvRQJMMWASEHiH0UYD2hIkK/pKdvsCAtmjeSyz5QB9FhO2PN3vDm3LlgK91Uf7wgOJPlJpps8BZj6fD4S1pBXUfLuD8YF0woyz2zzdjx5RtP3QMdJJr2zwFFrRd/j9iXJGwsEL+HdtlJhSi+2k0wSbwi/PJEtwi6NYaYfqN+5bbU2TG8Fota7c7j5ORJpxioO+ZJ4m+7+G1YJksKWRH3jrXBLUmShpGZCRj1dboDotZSELv0WX0VvRYHppyEY7f7CjMOzBOOFQT3HIM+9MKIdjYAg==',
];