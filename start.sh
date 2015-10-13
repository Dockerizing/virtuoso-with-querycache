#!/bin/sh

# start the php5-fpm service
echo "starting php …"
service php5-fpm start

# start the nginx service
echo "starting nginx …"
service nginx start

echo "Virtuoso+QueryCache Container is ready to set sail!"
echo ""
echo "following log:"

VQCLOG="/var/www/logs/virtuoso-querycache.log"
touch $VQCLOG
chmod a+w $VQCLOG
tail -f $VQCLOG
