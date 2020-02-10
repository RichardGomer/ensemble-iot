#!/bin/bash
# Builds a docker image of ensemble-iot
SCRIPT=`realpath $0`
BASEPATH=`dirname $SCRIPT`
cd $BASEPATH/..
docker build -f docker/Dockerfile -t ensemble-iot:latest .

echo ""
echo "========================================================================="
echo ""
echo "Assuming there aren't any errors above, an ensemble-iot docker image "
echo "(ensemble-iot:latest) has been built."
echo ""
echo "The image requires two environment variables: ENDPOINTURL and CONFIGNAME"
echo "in order to work."
echo ""
echo "Try running it like so:"
echo "   $ docker run -d -p 80:8090 \\"
echo "     -e ENDPOINTURL=http://192.168.0.2:8090/ensemble-iot/1.0/ \\"
echo "     -e CONFIGNAME=mydevice ensemble-iot"
echo ""
echo "========================================================================="
echo ""
