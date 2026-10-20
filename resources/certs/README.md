The SMS service uses `cacert.pem` to verify Gennet's HTTPS certificate, including on PHP installations without a configured CA bundle.

Source: https://curl.se/ca/cacert.pem (Mozilla CA store, distributed by curl).
Downloaded: 2026-10-20.

Refresh this file periodically from the source above. Set `GENNET_SMS_CA_BUNDLE` to use a different trusted bundle. Do not disable certificate verification.
