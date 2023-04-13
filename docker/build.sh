#!/bin/bash
# Builds a docker image of ensemble-iot
#
#
# Building is split into two stages; the first creates a base image using the eiot install script
# The second uses that image and pulls the latest copy of the source code
#
#
#
SCRIPT=`realpath $0`
BASEPATH=`dirname $SCRIPT`
cd $BASEPATH/..
rm var/* #We don't want to build temporary state into the docker image!

INSTALLHASH=($(md5sum bin/install.sh))

# Check if the environment image needs to be rebuilt
if [[ "$(docker images -q ensemble-iot-env:$INSTALLHASH 2> /dev/null)" == "" ]]; then
  echo "Environment requires build $INSTALLHASH"
  docker build -f docker/DockerfileEnv -t ensemble-iot-env:$INSTALLHASH -t ensemble-iot-env:latest .
else
  echo "Environment is up to date $INSTALLHASH";
fi

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
