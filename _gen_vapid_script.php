<?php
// Script local — lê VAPID keys do .env e gera um shell script para o servidor
$env = file_get_contents(__DIR__ . '/.env');

preg_match('/^VAPID_PUBLIC_KEY=(.+)$/m', $env, $pub);
preg_match('/^VAPID_PRIVATE_KEY=(.+)$/m', $env, $priv);

if (empty($pub[1]) || empty($priv[1])) {
    fwrite(STDERR, "ERRO: VAPID keys nao encontradas no .env local.\nCorre primeiro setup-webpush.bat\n");
    exit(1);
}

$pubKey  = trim($pub[1]);
$privKey = trim($priv[1]);
$webroot = '/var/www/vhosts/ardcsantana.ateneya.com/httpdocs';
$php     = '/opt/plesk/php/8.3/bin/php';

$script  = "#!/bin/bash\n";
$script .= "WEBROOT=$webroot\n";
$script .= "sed -i '/^VAPID_PUBLIC_KEY=/d' \$WEBROOT/.env\n";
$script .= "sed -i '/^VAPID_PRIVATE_KEY=/d' \$WEBROOT/.env\n";
$script .= "echo 'VAPID_PUBLIC_KEY=$pubKey' >> \$WEBROOT/.env\n";
$script .= "echo 'VAPID_PRIVATE_KEY=$privKey' >> \$WEBROOT/.env\n";
$script .= "$php \$WEBROOT/artisan config:clear 2>/dev/null\n";
$script .= "echo VAPID_OK\n";

file_put_contents(__DIR__ . '/_set_vapid.sh', $script);
echo "Script shell gerado: _set_vapid.sh\n";
