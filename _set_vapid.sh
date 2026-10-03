#!/bin/bash
WEBROOT=/var/www/vhosts/ardcsantana.ateneya.com/httpdocs
sed -i '/^VAPID_PUBLIC_KEY=/d' $WEBROOT/.env
sed -i '/^VAPID_PRIVATE_KEY=/d' $WEBROOT/.env
echo 'VAPID_PUBLIC_KEY=BO4zPrOMInEogK_l0qXrYn6R10kxuc60hTFjLGYw8BE1UJ2Xe3tcWnkLz9mtFaMy6j4WVF5crLMFwUgzpDofYqU' >> $WEBROOT/.env
echo 'VAPID_PRIVATE_KEY=iiP51axaiWG2CyRTn8fECDiWUvo95_1H_6DYbOy1OfI' >> $WEBROOT/.env
/opt/plesk/php/8.3/bin/php $WEBROOT/artisan config:clear 2>/dev/null
echo VAPID_OK
